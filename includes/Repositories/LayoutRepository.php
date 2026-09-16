<?php
namespace YayCheckout\Repositories;

use YayCheckout\Enums\LayoutPostStatus;
use YayCheckout\Models\Layout;
use YayCheckout\Models\LayoutQuery;
use YayCheckout\Services\DerivedCacheBuilder;
use YayCheckout\Support\Plan;

defined( 'ABSPATH' ) || exit;

/**
 * All layout storage access. Every get_post/update_post_meta/get_option call
 * for layouts belongs here so the draft/snapshot rules have one owner.
 */
class LayoutRepository {

    /** Free edition may hold this many layouts. */
    public const FREE_LIMIT = 3;

    public function find( int $id ): ?Layout {
        $post = get_post( $id );

        if ( ! $post instanceof \WP_Post || Layout::POST_TYPE !== $post->post_type ) {
            return null;
        }

        return Layout::from_post(
            $post,
            $this->hydrate_meta( get_post_meta( $id ) ),
            $this->active_id() === $id
        );
    }

    public function active(): ?Layout {
        $id = $this->active_id();

        return $id > 0 ? $this->find( $id ) : null;
    }

    /**
     * The layout that should render for the current request.
     *
     * 1.x resolves to the active layout. Pro assignment rules
     * (Layout::META_RULES, ordered by menu_order) are evaluated here first and
     * fall back to this same active layout when none match.
     */
    public function resolve_for_request(): ?Layout {
        return $this->active();
    }

    /**
     * One page of matching layouts and the total across all pages, per
     * $query's search/page/per_page. One WP_Query: found_posts gives the
     * total without a second, unbounded query.
     *
     * Search() matches post_title only — LayoutRestController's `search` arg
     * is documented as searching by name, and post_content is Gutenberg block
     * markup, not user-facing text WP's default title+content 's' should
     * match against.
     *
     * @return array{items: Layout[], total: int}
     */
    public function query( LayoutQuery $query ): array {
        add_filter( 'posts_search', [ $this, 'match_search_to_title' ], 10, 2 );

        $wp_query = new \WP_Query(
            [
                'post_type'              => Layout::POST_TYPE,
                'post_status'            => $this->post_statuses_for( $query->statuses() ),
                's'                      => $query->search(),
                'posts_per_page'         => $query->per_page(),
                'paged'                  => $query->page(),
                'orderby'                => 'menu_order ID',
                'order'                  => 'ASC',
                'update_post_meta_cache' => true,
                'update_post_term_cache' => false,
            ]
        );

        remove_filter( 'posts_search', [ $this, 'match_search_to_title' ], 10 );

        $active_id = $this->active_id();
        $layouts   = [];

        foreach ( $wp_query->posts as $post ) {
            if ( ! $post instanceof \WP_Post ) {
                continue;
            }

            $layouts[] = Layout::from_post(
                $post,
                $this->hydrate_meta( get_post_meta( $post->ID ) ),
                $active_id === (int) $post->ID
            );
        }

        return [
            'items'       => $layouts,
            'total'       => (int) $wp_query->found_posts,
            'total_pages' => (int) $wp_query->max_num_pages,
        ];
    }

    /**
     * Narrows WP_Query's default title+excerpt+content search to post_title
     * only. Mirrors the AND-of-terms shape WP_Query::parse_search() itself
     * builds, so multi-word search behaves the same way merchants already
     * expect from WordPress.
     *
     * @param  string    $search
     * @param  \WP_Query $wp_query
     * @return string
     */
    public function match_search_to_title( string $search, \WP_Query $wp_query ): string {
        global $wpdb;

        $terms = $wp_query->query_vars['search_terms'] ?? [];

        if ( '' === $search || empty( $terms ) ) {
            return $search;
        }

        $clauses = [];

        foreach ( (array) $terms as $term ) {
            $clauses[] = $wpdb->prepare( "{$wpdb->posts}.post_title LIKE %s", '%' . $wpdb->esc_like( $term ) . '%' );
        }

        return ' AND (' . implode( ' AND ', $clauses ) . ') ';
    }

    /**
     * Layouts that consume a Free slot. Archived layouts do not count: the
     * merchant can archive all three and still add a new draft (YC-02.13).
     *
     * Called with no argument from LayoutCapGuard and duplicate(); pass a
     * LayoutQuery to count a filtered set.
     */
    public function count( ?LayoutQuery $query = null ): int {
        $query = $query ?? LayoutQuery::listed();

        $wp_query = new \WP_Query(
            [
                'post_type'      => Layout::POST_TYPE,
                'post_status'    => $this->post_statuses_for( $query->statuses() ),
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ]
        );

        return count( $wp_query->posts );
    }

    /**
     * Tab totals for the whole store, independent of the current status filter.
     *
     * @return array{listed: int, archived: int}
     */
    public function counts(): array {
        return [
            'listed'   => $this->count( LayoutQuery::listed() ),
            'archived' => $this->count( LayoutQuery::archived() ),
        ];
    }

    /**
     * Copy the working draft to the published snapshot.
     *
     * The write order matters. Derived caches are built first and in memory, so
     * unusable markup throws before anything is stored and the previously
     * published layout keeps serving. The published-at marker is written last,
     * so a failure part-way through leaves the old snapshot in place rather
     * than a half-updated one.
     *
     * @param  int $id
     * @return Layout
     * @throws \InvalidArgumentException When the layout is missing or its markup is unusable.
     */
    public function publish( int $id ): Layout {
        $post = get_post( $id );

        if ( ! $post instanceof \WP_Post || Layout::POST_TYPE !== $post->post_type ) {
            throw new \InvalidArgumentException( sprintf( 'Layout %d not found.', $id ) );
        }

        $markup = (string) $post->post_content;

        // Pure, and first: nothing is written if this throws.
        $derived = DerivedCacheBuilder::build( $markup );

        $revision_id = wp_save_post_revision( $id );

        update_post_meta( $id, Layout::META_DERIVED, $derived );
        update_post_meta( $id, Layout::META_PUBLISHED, $markup );

        /*
         * wp_save_post_revision() returns null when the content is unchanged
         * since the last revision, which is the normal case when republishing
         * an untouched draft. Keep the previous revision id rather than
         * clearing the pointer that orders rely on.
         */
        if ( $revision_id ) {
            update_post_meta( $id, Layout::META_PUBLISHED_REVISION, (int) $revision_id );
        }

        // Commit marker, written last.
        update_post_meta( $id, Layout::META_PUBLISHED_AT, current_time( 'mysql', true ) );

        /*
         * post_status records only that this layout has been published at least
         * once. See LayoutPostStatus::PUBLISHED for why it is never 'publish'.
         */
        if ( LayoutPostStatus::PUBLISHED !== $post->post_status ) {
            wp_update_post(
                [
                    'ID'          => $id,
                    'post_status' => LayoutPostStatus::PUBLISHED,
                ]
            );
        }

        return $this->find( $id );
    }

    /**
     * Exactly one layout is active, held as a single option, so the invariant
     * cannot be violated by construction. Writes nothing else.
     */
    public function set_active( int $id ): void {
        update_option( Layout::OPTION_ACTIVE, $id );
    }

    /**
     * Create a new blank layout draft.
     *
     * The content defaults to the locked root block rather than an empty
     * string: LayoutPostType's template_lock 'all' requires it, so a blank
     * default keeps a brand-new draft a working checkout immediately.
     *
     * @param  string $title
     * @return Layout
     * @throws \InvalidArgumentException When the Free/Pro layout limit is reached
     *                                    or the insert fails.
     */
    public function create( string $title ): Layout {
        if ( Plan::layout_limit_reached( $this->count() ) ) {
            throw new \InvalidArgumentException( Plan::layout_limit_message() );
        }

        $new_id = wp_insert_post(
            [
                'post_type'    => Layout::POST_TYPE,
                'post_status'  => LayoutPostStatus::DRAFT,
                'post_title'   => $title,
                'post_content' => '<!-- wp:yaycheckout/checkout /-->',
            ],
            true
        );

        if ( is_wp_error( $new_id ) ) {
            throw new \InvalidArgumentException( $new_id->get_error_message() );
        }

        return $this->find( (int) $new_id );
    }

    /**
     * Copy a layout's draft into a new layout.
     *
     * The snapshot and its derived caches are deliberately not copied: the copy
     * has never been published, and inheriting another layout's published
     * markup would put content live that nobody published.
     *
     * @param  int $id
     * @return Layout
     * @throws \InvalidArgumentException When the source layout does not exist.
     */
    public function duplicate( int $id ): Layout {
        $source = $this->find( $id );

        if ( null === $source ) {
            throw new \InvalidArgumentException( sprintf( 'Layout %d not found.', $id ) );
        }

        /*
         * The cap applies here too. LayoutCapGuard covers creation through
         * core's REST endpoints, but duplicating is our own code path and would
         * otherwise be the obvious way around the limit.
         */
        if ( Plan::layout_limit_reached( $this->count() ) ) {
            throw new \InvalidArgumentException( Plan::layout_limit_message() );
        }

        $new_id = wp_insert_post(
            [
                'post_type'    => Layout::POST_TYPE,
                'post_status'  => LayoutPostStatus::DRAFT,
                /* translators: %s: source layout title. */
                'post_title'   => sprintf( __( '%s (copy)', 'yaycheckout' ), $source->title ),
                'post_content' => $source->draft,
            ],
            true
        );

        if ( is_wp_error( $new_id ) ) {
            throw new \InvalidArgumentException( $new_id->get_error_message() );
        }

        return $this->find( (int) $new_id );
    }

    private function active_id(): int {
        return (int) get_option( Layout::OPTION_ACTIVE, 0 );
    }

    /**
     * Unserialize bulk post meta.
     *
     * With no key, get_post_meta( $id ) returns the raw meta cache: unlike the
     * single-key call it does not run maybe_unserialize, so array values such
     * as the derived caches arrive as serialized strings. Doing it here keeps
     * the model free of WordPress calls.
     *
     * @param  mixed $meta
     * @return array
     */
    private function hydrate_meta( $meta ): array {
        if ( ! is_array( $meta ) ) {
            return [];
        }

        foreach ( $meta as $key => $values ) {
            $meta[ $key ] = array_map( 'maybe_unserialize', (array) $values );
        }

        return $meta;
    }

    /**
     * @param  string[] $statuses
     * @return string[]
     */
    private function post_statuses_for( array $statuses ): array {
        $post_statuses = array_map( [ LayoutPostStatus::class, 'for_status' ], $statuses );

        return array_values( array_unique( array_filter( $post_statuses ) ) );
    }
}
