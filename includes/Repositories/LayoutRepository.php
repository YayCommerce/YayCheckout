<?php
namespace YayCheckout\Repositories;

use YayCheckout\Enums\LayoutPostStatus;
use YayCheckout\Enums\LayoutStatus;
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
            $this->is_active( $id )
        );
    }

    /**
     * Whether the layout is in the active set the current plan allows.
     *
     * Cheap to call per row: the option comes from WordPress's alloptions
     * cache and the plan flag is a static read, so callers need not hoist it.
     */
    public function is_active( int $id ): bool {
        return in_array( $id, $this->active_ids(), true );
    }

    /**
     * The active layout, or the last one activated when Pro holds several:
     * set_active() appends under Pro, so the end of the set is the merchant's
     * most recent choice and the one they expect to be serving.
     */
    public function active(): ?Layout {
        $ids = $this->active_ids();
        $id  = empty( $ids ) ? 0 : (int) end( $ids );

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
                'orderby'                => 'date ID',
                'order'                  => 'DESC',
                'update_post_meta_cache' => true,
                'update_post_term_cache' => false,
            ]
        );

        remove_filter( 'posts_search', [ $this, 'match_search_to_title' ], 10 );

        $layouts = [];

        foreach ( $wp_query->posts as $post ) {
            if ( ! $post instanceof \WP_Post ) {
                continue;
            }

            $layouts[] = Layout::from_post(
                $post,
                $this->hydrate_meta( get_post_meta( $post->ID ) ),
                $this->is_active( (int) $post->ID )
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

            /*
             * Store-wide, unlike the per-row is_active the list returns: the
             * admin app needs to know a layout is the last active one while
             * looking at a single page of results.
             */
            'active'   => count( $this->active_ids() ),
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
     * Publishing a scheduled layout overrides the schedule: it goes live now
     * and its date moves to now, so nothing is left for cron to publish.
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

        if ( LayoutPostStatus::PUBLISHED !== $post->post_status ) {
            $update = [
                'ID'          => $id,
                'post_status' => LayoutPostStatus::PUBLISHED,
            ];

            /*
             * wp_insert_post() turns 'publish' back into 'future' while the
             * date is ahead, whatever the current status — a pending layout
             * can carry a future date too. Moving the date to now keeps the
             * status honest; core's own transition handling then clears the
             * publish_future_post event. A zero date parses as false and is
             * left for core to fill in.
             */
            if ( strtotime( $post->post_date_gmt . ' GMT' ) > time() ) {
                $now                     = current_time( 'mysql', true );
                $update['post_date_gmt'] = $now;
                $update['post_date']     = get_date_from_gmt( $now );
            }

            wp_update_post( $update );
        }

        return $this->find( $id );
    }

    /**
     * Publish the layout unless it already reads as published, then make it
     * active.
     *
     * Publishing first keeps the invariant that an active layout always has a
     * snapshot to serve: activating a draft would otherwise put a layout live
     * with nothing published behind it.
     *
     * @param  int $id
     * @return Layout
     * @throws \InvalidArgumentException When the layout is missing or its markup is unusable.
     */
    public function activate( int $id ): Layout {
        $layout = $this->find( $id );

        if ( null === $layout ) {
            throw new \InvalidArgumentException( sprintf( 'Layout %d not found.', $id ) );
        }

        /*
         * Publishing an archived layout would silently restore it from the
         * trash, and on Free push the layout count past FREE_LIMIT. Restoring
         * is the merchant's decision, so refuse rather than decide for them.
         */
        if ( LayoutPostStatus::ARCHIVED === $layout->post_status ) {
            throw new \InvalidArgumentException(
                __( 'Restore this layout from the archive before activating it.', 'yaycheckout' )
            );
        }

        /*
         * Same rule as the admin's Set active modal, which reads "Publish &
         * activate" for anything not reported as published: a layout with
         * no snapshot gets one, and a scheduled or pending layout — even one
         * published before — has its current draft published now, because
         * activating it is the merchant overriding the schedule or review.
         */
        if ( LayoutStatus::PUBLISHED !== $layout->status() ) {
            $this->publish( $id );
        }

        $this->set_active( $id );

        return $this->find( $id );
    }

    /**
     * Drop a layout from the active set.
     *
     * Deliberately does not touch post_status: deactivating says "stop serving
     * this to shoppers", not "unpublish". The layout keeps its snapshot and can
     * be activated again without republishing.
     *
     * @param  int $id
     * @return Layout
     * @throws \InvalidArgumentException When the layout is missing.
     */
    public function deactivate( int $id ): Layout {
        $post = get_post( $id );

        if ( ! $post instanceof \WP_Post || Layout::POST_TYPE !== $post->post_type ) {
            throw new \InvalidArgumentException( sprintf( 'Layout %d not found.', $id ) );
        }

        $this->store_ids( array_diff( $this->active_ids(), [ $id ] ) );

        return $this->find( $id );
    }

    /**
     * Free serves one layout, so activating replaces the set. Pro may hold
     * several: the id is moved to the end, so re-activating a layout that is
     * already active makes it the most recent choice active() serves.
     *
     * Plan policy lives here, read from Plan, the same way the layout cap
     * does — no hook for Pro to attach to, because there is no Pro code
     * involved, only a different rule.
     *
     * Writes nothing else.
     */
    public function set_active( int $id ): void {
        $ids = Plan::is_pro()
            ? array_merge( array_diff( $this->active_ids(), [ $id ] ), [ $id ] )
            : [ $id ];

        $this->store_ids( $ids );
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

    /**
     * The active layout ids the current plan allows, oldest first.
     *
     * A bare int is accepted: the option held a single id before Pro could
     * activate several, so a site that stored one keeps working.
     *
     * Free serves one layout at a time, but a site that ran Pro can still have
     * several ids stored — nothing prunes the option when the Pro build is
     * swapped out. Keep the last, which set_active() appends and active()
     * resolves to, so dropping back to Free leaves the storefront on the same
     * layout instead of reviving an older one. The next write persists the
     * trimmed set: on Free, deactivating the active layout must leave nothing
     * active, not bring an older Pro-era layout back.
     *
     * @return int[]
     */
    private function active_ids(): array {
        $stored = get_option( Layout::OPTION_ACTIVE, [] );
        $ids    = is_array( $stored ) ? $stored : [ $stored ];
        $ids    = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );

        return Plan::is_pro() ? $ids : array_slice( $ids, -1 );
    }

    /**
     * @param int[] $ids
     */
    private function store_ids( array $ids ): void {
        update_option( Layout::OPTION_ACTIVE, array_values( $ids ) );
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
