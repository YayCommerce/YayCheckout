<?php
namespace YayCheckout\Repositories;

use YayCheckout\Enums\LayoutStatus;
use YayCheckout\Models\Layout;
use YayCheckout\Models\LayoutQuery;
use YayCheckout\Support\Plan;

defined( 'ABSPATH' ) || exit;

/**
 * All layout storage access; publishing and the active set have one owner.
 */
class LayoutRepository {

    /** Layouts the Free edition may hold. */
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

    /** Whether the layout is in the active set. Cheap per row: the option is in alloptions cache. */
    public function is_active( int $id ): bool {
        return in_array( $id, $this->active_ids(), true );
    }

    /** The active layout; the last activated when Pro holds several. set_active() appends, so the end is the most recent choice. */
    public function active(): ?Layout {
        $ids = $this->active_ids();
        $id  = empty( $ids ) ? 0 : (int) end( $ids );

        return $id > 0 ? $this->find( $id ) : null;
    }

    /** The layout that should render for the current request. Pro assignment rules (Layout::META_RULES) will be evaluated here before falling back to active(). */
    public function resolve_for_request(): ?Layout {
        return $this->active();
    }

    /**
     * One page of matching layouts and the total across all pages.
     * Search matches post_title only: post_content is block markup, not text.
     *
     * @return array{items: Layout[], total: int, total_pages: int}
     */
    public function query( LayoutQuery $query ): array {
        add_filter( 'posts_search', [ $this, 'match_search_to_title' ], 10, 2 );

        $wp_query = new \WP_Query(
            [
                'post_type'              => Layout::POST_TYPE,
                'post_status'            => $query->statuses(),
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

    /** Same AND-of-terms shape as WP_Query::parse_search(), restricted to post_title. */
    public function match_search_to_title( string $search, \WP_Query $wp_query ): string {
        global $wpdb;

        $terms = $wp_query->query_vars['search_terms'] ?? [];

        if ( '' === $search || empty( $terms ) || Layout::POST_TYPE !== $wp_query->get( 'post_type' ) ) {
            return $search;
        }

        $clauses = [];

        foreach ( (array) $terms as $term ) {
            $clauses[] = $wpdb->prepare( "{$wpdb->posts}.post_title LIKE %s", '%' . $wpdb->esc_like( $term ) . '%' );
        }

        return ' AND (' . implode( ' AND ', $clauses ) . ') ';
    }

    /** Layouts that consume a Free slot. Archived layouts do not. */
    public function count( ?LayoutQuery $query = null ): int {
        $query = $query ?? LayoutQuery::listed();

        $wp_query = new \WP_Query(
            [
                'post_type'      => Layout::POST_TYPE,
                'post_status'    => $query->statuses(),
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ]
        );

        return count( $wp_query->posts );
    }

    /**
     * Store-wide totals, independent of the current status filter.
     *
     * @return array{listed: int, archived: int, active: int}
     */
    public function counts(): array {
        return [
            'listed'   => $this->count( LayoutQuery::listed() ),
            'archived' => $this->count( LayoutQuery::archived() ),
            'active'   => count( $this->active_ids() ),
        ];
    }

    /**
     * Publishes a layout: a post_status change, as the editor's own button does.
     *
     * @throws \InvalidArgumentException When the layout is missing or archived.
     */
    public function publish( int $id ): Layout {
        $post = get_post( $id );

        if ( ! $post instanceof \WP_Post || Layout::POST_TYPE !== $post->post_type ) {
            throw new \InvalidArgumentException( sprintf( 'Layout %d not found.', $id ) );
        }

        // Same refusal as activate(): the status write below would un-archive it.
        if ( LayoutStatus::ARCHIVED === $post->post_status ) {
            throw new \InvalidArgumentException(
                __( 'Restore this layout from the archive before publishing it.', 'yaycheckout' )
            );
        }

        if ( LayoutStatus::PUBLISHED !== $post->post_status ) {
            $update = [
                'ID'          => $id,
                'post_status' => LayoutStatus::PUBLISHED,
            ];

            // wp_insert_post() flips 'publish' back to 'future' while the date is ahead;
            // moving it to now also lets core clear the publish_future_post event.
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
     * Publishes the layout unless it already is, then makes it active, so an
     * active layout is always one shoppers may see.
     *
     * @throws \InvalidArgumentException When the layout is missing or archived.
     */
    public function activate( int $id ): Layout {
        $layout = $this->find( $id );

        if ( null === $layout ) {
            throw new \InvalidArgumentException( sprintf( 'Layout %d not found.', $id ) );
        }

        // Publishing would silently restore it from the trash and could exceed FREE_LIMIT.
        if ( LayoutStatus::ARCHIVED === $layout->post_status ) {
            throw new \InvalidArgumentException(
                __( 'Restore this layout from the archive before activating it.', 'yaycheckout' )
            );
        }

        // Scheduled and pending count too: activating overrides the schedule or review.
        if ( ! $layout->is_published() ) {
            $this->publish( $id );
        }

        $this->set_active( $id );

        return $this->find( $id );
    }

    /**
     * Drops a layout from the active set. Leaves post_status alone: deactivate
     * means stop serving, not unpublish.
     *
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
     * Move a layout to the trash and drop it from the active set. Blocks,
     * meta and revisions stay; the layout can be restored later.
     *
     * @param  int $id
     * @return Layout
     * @throws \InvalidArgumentException When the layout is missing or already archived.
     * @throws \RuntimeException         When WordPress refuses or fails to trash it.
     */
    public function archive( int $id ): Layout {
        $post = get_post( $id );

        if ( ! $post instanceof \WP_Post || Layout::POST_TYPE !== $post->post_type ) {
            throw new \InvalidArgumentException( sprintf( 'Layout %d not found.', $id ) );
        }

        if ( LayoutStatus::ARCHIVED === $post->post_status ) {
            throw new \InvalidArgumentException( __( 'This layout is already archived.', 'yaycheckout' ) );
        }

        $trashed = wp_trash_post( $id );

        if ( ! $trashed instanceof \WP_Post ) {
            throw new \RuntimeException( sprintf( 'Layout %d could not be archived.', $id ) );
        }

        // wp_trash_post() hands back the pre-update object; only the status moved.
        $trashed->post_status = LayoutStatus::ARCHIVED;

        return Layout::from_post( $trashed, $this->hydrate_meta( get_post_meta( $id ) ), false );
    }

    /** Free replaces the set; Pro moves the id to the end so active() serves it. */
    public function set_active( int $id ): void {
        $ids = Plan::is_pro()
            ? array_merge( array_diff( $this->active_ids(), [ $id ] ), [ $id ] )
            : [ $id ];

        $this->store_ids( $ids );
    }

    /**
     * Creates a blank layout draft. Content starts as the root block, not '':
     * empty markup cannot be published.
     *
     * @throws \InvalidArgumentException When the layout limit is reached or the insert fails.
     */
    public function create( string $title ): Layout {
        if ( Plan::layout_limit_reached( $this->count() ) ) {
            throw new \InvalidArgumentException( Plan::layout_limit_message() );
        }

        $new_id = wp_insert_post(
            [
                'post_type'    => Layout::POST_TYPE,
                'post_status'  => LayoutStatus::DRAFT,
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
     * Copies a layout's content into a new draft layout.
     *
     * @throws \InvalidArgumentException When the source is missing, the limit is reached, or the insert fails.
     */
    public function duplicate( int $id ): Layout {
        $source = $this->find( $id );

        if ( null === $source ) {
            throw new \InvalidArgumentException( sprintf( 'Layout %d not found.', $id ) );
        }

        // LayoutCapGuard only sees wp/v2 inserts; this path must enforce the cap itself.
        if ( Plan::layout_limit_reached( $this->count() ) ) {
            throw new \InvalidArgumentException( Plan::layout_limit_message() );
        }

        // wp_insert_post() unslashes; without wp_slash() block attributes lose their \uXXXX escapes.
        $new_id = wp_insert_post(
            wp_slash(
                [
                    'post_type'    => Layout::POST_TYPE,
                    'post_status'  => LayoutStatus::DRAFT,
                    /* translators: %s: source layout title. */
                    'post_title'   => sprintf( __( 'Duplicate of %s', 'yaycheckout' ), $source->title ),
                    'post_content' => $source->content,
                ]
            ),
            true
        );

        if ( is_wp_error( $new_id ) ) {
            throw new \InvalidArgumentException( $new_id->get_error_message() );
        }

        return $this->find( (int) $new_id );
    }

    /**
     * The active ids the current plan allows, oldest first.
     * Accepts a bare int (pre-Pro storage). On Free, keeps only the last id so
     * a site dropping back from Pro stays on the same layout and deactivating
     * it does not revive an older one.
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
     * Unserializes bulk post meta. Keyless get_post_meta() skips
     * maybe_unserialize, so array meta arrives as strings.
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
}
