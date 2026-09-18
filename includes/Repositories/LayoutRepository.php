<?php
namespace YayCheckout\Repositories;

use YayCheckout\Enums\LayoutStatus;
use YayCheckout\Models\Layout;
use YayCheckout\Models\LayoutQuery;
use YayCheckout\Services\DerivedCacheBuilder;
use YayCheckout\Support\Plan;

defined( 'ABSPATH' ) || exit;

/**
 * All layout storage access; the draft/snapshot rules have one owner.
 */
class LayoutRepository {

    /** Layouts the Free edition may hold. */
    public const FREE_LIMIT = 3;

    private static bool $publishing = false;

    /** True while publish() runs: its own status flip fires the transition LayoutPublishSync listens to. */
    public static function is_publishing(): bool {
        return self::$publishing;
    }

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

        if ( '' === $search || empty( $terms ) ) {
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
     * Copy the working draft to the published snapshot.
     *
     * Flagged so LayoutPublishSync ignores the status flip this itself makes;
     * finally, so a throw cannot leave later transitions silently unsnapshotted.
     *
     * @throws \InvalidArgumentException When the layout is missing or its markup is unusable.
     */
    public function publish( int $id ): Layout {
        self::$publishing = true;

        try {
            return $this->write_snapshot( $id );
        } finally {
            self::$publishing = false;
        }
    }

    /**
     * Write order is the safety net: derived caches build in memory first so
     * bad markup throws before anything is stored, and the published-at
     * marker is written last so a partial failure keeps the old snapshot live.
     *
     * @throws \InvalidArgumentException When the layout is missing or its markup is unusable.
     */
    private function write_snapshot( int $id ): Layout {
        $post = get_post( $id );

        if ( ! $post instanceof \WP_Post || Layout::POST_TYPE !== $post->post_type ) {
            throw new \InvalidArgumentException( sprintf( 'Layout %d not found.', $id ) );
        }

        $markup = (string) $post->post_content;

        $derived = DerivedCacheBuilder::build( $markup );

        $revision_id = wp_save_post_revision( $id );

        update_post_meta( $id, Layout::META_DERIVED, $derived );
        update_post_meta( $id, Layout::META_PUBLISHED, $markup );

        // Null when content is unchanged since the last revision; keep the pointer orders rely on.
        if ( $revision_id ) {
            update_post_meta( $id, Layout::META_PUBLISHED_REVISION, (int) $revision_id );
        }

        // Commit marker; must stay the last meta write.
        update_post_meta( $id, Layout::META_PUBLISHED_AT, current_time( 'mysql', true ) );

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
     * Publishes the layout unless it already reads as published, then makes it
     * active. Publish first so an active layout always has a snapshot to serve.
     *
     * @throws \InvalidArgumentException When the layout is missing, archived, or its markup is unusable.
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
        // A publish row with no snapshot (non-REST publish of unusable markup) must not go live empty.
        if ( ! $layout->is_published() || '' === $layout->snapshot ) {
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
     * Copies a layout's draft into a new layout. The draft only: inheriting a
     * snapshot would put unpublished content live.
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
                    'post_content' => $source->draft,
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
     * Permanently removes an archived layout with its revisions and meta.
     * Archived only: the archive is the reversible step, this one is not.
     *
     * @throws \InvalidArgumentException When the layout is missing, not archived, or still active.
     * @throws \RuntimeException         When WordPress refuses the delete.
     */
    public function delete( int $id ): void {
        $post = get_post( $id );

        if ( ! $post instanceof \WP_Post || Layout::POST_TYPE !== $post->post_type ) {
            throw new \InvalidArgumentException( sprintf( 'Layout %d not found.', $id ) );
        }

        if ( LayoutStatus::ARCHIVED !== $post->post_status ) {
            throw new \InvalidArgumentException( __( 'Archive this layout before deleting it.', 'yaycheckout' ) );
        }

        if ( $this->is_active( $id ) ) {
            throw new \InvalidArgumentException( __( 'Deactivate this layout before deleting it.', 'yaycheckout' ) );
        }

        if ( ! wp_delete_post( $id, true ) ) {
            throw new \RuntimeException( sprintf( 'Layout %d could not be deleted.', $id ) );
        }
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
