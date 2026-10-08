<?php
namespace YayCheckout\Repositories;

use YayCheckout\Enums\LayoutStatus;
use YayCheckout\Models\Layout;
use YayCheckout\Models\LayoutQuery;
use YayCheckout\Services\LayoutAssignment;
use YayCheckout\Services\LayoutMarkupReader;
use YayCheckout\Support\Plan;

defined( 'ABSPATH' ) || exit;

/** All layout storage access; publishing and the active set have one owner. */
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

    /**
     * The active layout for this shopper: the most recently activated whose assignment rules match $context.
     * Free has no assignment, so its one active layout serves everyone.
     *
     * @param array $context Shopper facts; see LayoutAssignment::matches().
     */
    public function active( array $context ): ?Layout {
        $layouts = array_values( array_filter( array_map( [ $this, 'find' ], array_reverse( $this->active_ids() ) ) ) );

        return Plan::is_pro() ? LayoutAssignment::pick( $layouts, $context ) : ( $layouts[0] ?? null );
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
     * @throws \InvalidArgumentException When the layout is missing, archived, or fails LayoutMarkupReader::publish_error().
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

        self::refuse_unpublishable( $post->post_content );

        wp_publish_post( $id );

        return $this->find( $id );
    }

    /**
     * Publishes the layout unless it already is, then makes it active, so an
     * active layout is always one shoppers may see. On Free, the layout it
     * replaces goes back to draft.
     *
     * @throws \InvalidArgumentException When the layout is missing, archived or not publishable.
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

        // Also when already published: Quick Edit and layouts saved before these rules skip the check.
        self::refuse_unpublishable( $layout->content );

        // Scheduled and pending count too: activating overrides the schedule or review.
        if ( ! $layout->is_published() ) {
            $this->publish( $id );
        }

        $this->set_active( $id );

        return $this->find( $id );
    }

    /**
     * @throws \InvalidArgumentException With LayoutMarkupReader::publish_error() when the markup may not go live.
     */
    private static function refuse_unpublishable( string $markup ): void {
        $error = LayoutMarkupReader::publish_error( $markup );

        if ( '' !== $error ) {
            throw new \InvalidArgumentException( $error );
        }
    }

    /**
     * Stops serving the layout and returns it to draft, so a layout deactivated
     * from now on is never left published. Pending and scheduled count too.
     *
     * @throws \InvalidArgumentException When the layout is missing.
     * @throws \RuntimeException         When WordPress fails to move it to draft.
     */
    public function deactivate( int $id ): Layout {
        $post = get_post( $id );

        if ( ! $post instanceof \WP_Post || Layout::POST_TYPE !== $post->post_type ) {
            throw new \InvalidArgumentException( sprintf( 'Layout %d not found.', $id ) );
        }

        $this->forget_active( $id );

        if ( in_array( $post->post_status, [ LayoutStatus::PUBLISHED, LayoutStatus::SCHEDULED, LayoutStatus::PENDING ], true ) ) {
            $updated = wp_update_post(
                [
                    'ID'          => $id,
                    'post_status' => LayoutStatus::DRAFT,
                ],
                true
            );

            if ( is_wp_error( $updated ) ) {
                throw new \RuntimeException( sprintf( 'Layout %d could not be moved to draft.', $id ) );
            }

            $post = get_post( $id );
        }

        return Layout::from_post( $post, $this->hydrate_meta( get_post_meta( $id ) ), false );
    }

    /**
     * Drops a layout from the active set and leaves post_status alone. For hooks
     * that react to a status change: deactivate() would fire the transition again.
     */
    public function forget_active( int $id ): void {
        $this->store_ids( array_diff( $this->active_ids(), [ $id ] ) );
    }

    /**
     * Moves a layout to the trash and out of the active set; blocks, meta and revisions stay for a restore.
     *
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

        // LayoutActiveGuard refuses to trash the active layout; archiving is the merchant's explicit choice, so deactivate first.
        $this->deactivate( $id );

        $trashed = wp_trash_post( $id );

        if ( ! $trashed instanceof \WP_Post ) {
            throw new \RuntimeException( sprintf( 'Layout %d could not be archived.', $id ) );
        }

        // wp_trash_post() hands back the pre-update object; only the status moved.
        $trashed->post_status = LayoutStatus::ARCHIVED;

        return Layout::from_post( $trashed, $this->hydrate_meta( get_post_meta( $id ) ), false );
    }

    /**
     * Moves an archived layout back to draft: wp_untrash_post() always restores to 'draft' (WordPress 5.6+).
     *
     * @throws \InvalidArgumentException When the layout is missing or not archived.
     * @throws \RuntimeException         When WordPress refuses or fails to untrash it.
     */
    public function restore( int $id ): Layout {
        $post = get_post( $id );

        if ( ! $post instanceof \WP_Post || Layout::POST_TYPE !== $post->post_type ) {
            throw new \InvalidArgumentException( sprintf( 'Layout %d not found.', $id ) );
        }

        if ( LayoutStatus::ARCHIVED !== $post->post_status ) {
            throw new \InvalidArgumentException( __( 'Layout is not archived.', 'yaycheckout' ) );
        }

        $restored = wp_untrash_post( $id );

        if ( ! $restored instanceof \WP_Post ) {
            throw new \RuntimeException( sprintf( 'Layout %d could not be restored.', $id ) );
        }

        // wp_untrash_post() hands back the pre-update object; only the status moved.
        $restored->post_status = LayoutStatus::DRAFT;

        // No Plan::layout_limit_reached() check: a restore may exceed the Free cap, unlike a new layout.
        return Layout::from_post( $restored, $this->hydrate_meta( get_post_meta( $id ) ), false );
    }

    /**
     * Free replaces the set and drafts the layout it replaces, as a Deactivate would;
     * Pro moves the id to the end, the most recent choice.
     */
    public function set_active( int $id ): void {
        if ( ! Plan::is_pro() ) {
            foreach ( array_diff( $this->active_ids(), [ $id ] ) as $replaced ) {
                $this->deactivate( (int) $replaced );
            }
        }

        $ids = Plan::is_pro()
            ? array_merge( array_diff( $this->active_ids(), [ $id ] ), [ $id ] )
            : [ $id ];

        $this->store_ids( $ids );
    }

    /**
     * Creates a layout draft seeded with a complete checkout, so activating it
     * as-is lets a shopper place an order. The post type's template only
     * applies to auto-drafts, not to this insert.
     *
     * @throws \InvalidArgumentException When the layout limit is reached or the insert fails.
     */
    public function create( string $title ): Layout {
        return $this->insert_draft( $title, self::default_markup() );
    }

    /**
     * Copies a layout's content and assignment rules into a new draft layout.
     *
     * @throws \InvalidArgumentException When the source is missing, the limit is reached, or the insert fails.
     */
    public function duplicate( int $id ): Layout {
        $source = $this->find( $id );

        if ( null === $source ) {
            throw new \InvalidArgumentException( sprintf( 'Layout %d not found.', $id ) );
        }

        /* translators: %s: source layout title. */
        return $this->insert_draft( sprintf( __( 'Duplicate of %s', 'yaycheckout' ), $source->title ), $source->content, $source->rules );
    }

    /**
     * Creates a draft from an export file's title, markup and assignment rules, verbatim.
     * Never overwrites: a same-titled layout is a second layout, and the merchant archives the one they do not want.
     *
     * @param array $rules Already sanitized (Layout::sanitize_rules()); [] for none.
     * @throws \InvalidArgumentException When the limit is reached or the insert fails.
     */
    public function import( string $title, string $content, array $rules = [] ): Layout {
        return $this->insert_draft( $title, $content, $rules );
    }

    /**
     * Puts a layout's blocks back to the default layout. Title, status, meta and the
     * active flag stay; WordPress stores the old markup as a revision, so the editor can undo it.
     *
     * @throws \InvalidArgumentException When the layout is missing or the update fails.
     */
    public function reset( int $id ): Layout {
        if ( null === $this->find( $id ) ) {
            throw new \InvalidArgumentException( sprintf( 'Layout %d not found.', $id ) );
        }

        // Core revisions the post after the update, so a layout never saved in the editor has no revision
        // holding the old markup. No-op when the latest revision already matches.
        wp_save_post_revision( $id );

        // wp_update_post() unslashes; without wp_slash() block attributes lose their \uXXXX escapes.
        $result = wp_update_post(
            wp_slash(
                [
                    'ID'           => $id,
                    'post_content' => self::default_markup(),
                ]
            ),
            true
        );

        if ( is_wp_error( $result ) ) {
            throw new \InvalidArgumentException( $result->get_error_message() );
        }

        return $this->find( $id );
    }

    /**
     * Replaces a layout's assignment rules; [] clears them. Writes meta only, so the layout's
     * content, status and modified date stay as they are: rules save separately from the design.
     *
     * @param mixed $rules Raw rules; see Layout::sanitize_rules().
     * @return array The rules as stored.
     * @throws \InvalidArgumentException When the layout is missing or the rules are invalid.
     */
    public function set_rules( int $id, $rules ): array {
        if ( null === $this->find( $id ) ) {
            throw new \InvalidArgumentException( sprintf( 'Layout %d not found.', $id ) );
        }

        $clean = Layout::sanitize_rules( $rules );

        if ( [] === $clean ) {
            delete_post_meta( $id, Layout::META_RULES );
        } else {
            update_post_meta( $id, Layout::META_RULES, $clean );
        }

        return $clean;
    }

    /**
     * The one insert path for a new draft, so the cap and slashing cannot drift.
     *
     * @param array $rules Already sanitized; written with the post, so the draft never exists without them.
     * @throws \InvalidArgumentException When the limit is reached or the insert fails.
     */
    private function insert_draft( string $title, string $content, array $rules = [] ): Layout {
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
                    'post_title'   => $title,
                    'post_content' => $content,
                    'meta_input'   => [] === $rules ? [] : [ Layout::META_RULES => $rules ],
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
     * Block markup for a new layout, from templates/default-layout.php: a header, the checkout in two
     * columns (form; order summary with coupon), a footer.
     */
    public static function default_markup(): string {
        ob_start();
        include YAYCHECKOUT_PLUGIN_DIR . 'templates/default-layout.php';

        return (string) ob_get_clean();
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
