<?php
namespace YayCheckout\Repositories;

use YayCheckout\Blocks\BlockRegistry;
use YayCheckout\Controllers\LayoutTemplateCatalogue;
use YayCheckout\Enums\LayoutStatus;
use YayCheckout\Helpers\ExceptionHelper;
use YayCheckout\Models\Layout;
use YayCheckout\Models\LayoutDesign;
use YayCheckout\Models\LayoutQuery;
use YayCheckout\Services\LayoutAssignment;
use YayCheckout\Services\LayoutMarkupReader;
use YayCheckout\Support\Plan;

defined( 'ABSPATH' ) || exit;

/** All layout storage access; publishing and the active set have one owner. */
class LayoutRepository {

    /** What bulk() runs; each is a method of this class taking a layout id. */
    public const BULK_ACTIONS = [ 'deactivate', 'archive', 'restore', 'delete' ];

    public function find( int $id ): ?Layout {
        $post = get_post( $id );

        return self::is_layout( $post ) ? $this->to_layout( $post, $this->is_active( $id ) ) : null;
    }

    /**
     * Whether $post is a layout.
     *
     * @param \WP_Post|int|string|null $post A post or its id.
     */
    public static function is_layout( $post ): bool {
        if ( $post instanceof \WP_Post ) {
            return Layout::POST_TYPE === $post->post_type;
        }

        return null !== $post && Layout::POST_TYPE === get_post_type( $post );
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

        $args = [
            'post_type'              => Layout::POST_TYPE,
            'post_status'            => $query->statuses(),
            's'                      => $query->search(),
            'posts_per_page'         => $query->per_page(),
            'paged'                  => $query->page(),
            'orderby'                => $query->orderby() . ' ID',
            'order'                  => $query->order(),
            'update_post_meta_cache' => true,
            'update_post_term_cache' => false,
        ];

        if ( $query->active_only() ) {
            // An empty post__in means no filter to WP_Query, so [ 0 ] keeps an empty active set empty.
            $active_ids       = $this->active_ids();
            $args['post__in'] = $active_ids === [] ? [ 0 ] : $active_ids;
        }

        $wp_query = new \WP_Query( $args );

        remove_filter( 'posts_search', [ $this, 'match_search_to_title' ], 10 );

        $layouts = [];

        foreach ( $wp_query->posts as $post ) {
            if ( ! $post instanceof \WP_Post ) {
                continue;
            }

            $layouts[] = $this->to_layout( $post, $this->is_active( (int) $post->ID ) );
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
            'active'   => $this->active_count(),
        ];
    }

    /** How many layouts are in the active set, without the listing queries counts() runs. */
    public function active_count(): int {
        return count( $this->active_ids() );
    }

    /**
     * Publishes a layout: a post_status change, as the editor's own button does.
     *
     * @throws \InvalidArgumentException When the layout is missing, archived, or fails LayoutMarkupReader::publish_error().
     */
    public function publish( int $id ): Layout {
        $post = $this->post_or_throw( $id );

        // Same refusal as activate(): the status write below would un-archive it.
        if ( LayoutStatus::ARCHIVED === $post->post_status ) {
            throw new \InvalidArgumentException(
                esc_html__( 'Restore this layout from the archive before publishing it.', 'yaycheckout' )
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
        $layout = $this->find_or_throw( $id );

        // Publishing would silently restore it from the trash.
        if ( LayoutStatus::ARCHIVED === $layout->post_status ) {
            throw new \InvalidArgumentException(
                esc_html__( 'Restore this layout from the archive before activating it.', 'yaycheckout' )
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
        $error = LayoutMarkupReader::publish_error( $markup, BlockRegistry::locked_blocks() );

        if ( '' !== $error ) {
            throw new \InvalidArgumentException( esc_html( $error ) );
        }
    }

    /**
     * Stops serving the layout and returns it to draft, so a layout deactivated
     * from now on is never left published. Pending and scheduled count too.
     * A missing layout throws \InvalidArgumentException, from post_or_throw().
     *
     * @throws \RuntimeException When WordPress fails to move it to draft.
     */
    public function deactivate( int $id ): Layout {
        $post = $this->post_or_throw( $id );

        $this->forget_active( $id );

        if ( in_array( $post->post_status, array_merge( LayoutStatus::live(), [ LayoutStatus::PENDING ] ), true ) ) {
            $updated = wp_update_post(
                [
                    'ID'          => $id,
                    'post_status' => LayoutStatus::DRAFT,
                ],
                true
            );

            if ( is_wp_error( $updated ) ) {
                throw new \RuntimeException( esc_html( sprintf( 'Layout %d could not be moved to draft.', $id ) ) );
            }

            $post = get_post( $id );
        }

        return $this->to_layout( $post, false );
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
        $post = $this->post_or_throw( $id );

        if ( LayoutStatus::ARCHIVED === $post->post_status ) {
            throw new \InvalidArgumentException( esc_html__( 'This layout is already archived.', 'yaycheckout' ) );
        }

        // LayoutActiveGuard refuses to trash the active layout; archiving is the merchant's explicit choice, so deactivate first.
        $this->deactivate( $id );

        $trashed = wp_trash_post( $id );

        if ( ! $trashed instanceof \WP_Post ) {
            throw new \RuntimeException( esc_html( sprintf( 'Layout %d could not be archived.', $id ) ) );
        }

        // wp_trash_post() hands back the pre-update object; only the status moved.
        $trashed->post_status = LayoutStatus::ARCHIVED;

        return $this->to_layout( $trashed, false );
    }

    /**
     * Moves an archived layout back to draft: wp_untrash_post() always restores to 'draft' (WordPress 5.6+).
     *
     * @throws \InvalidArgumentException When the layout is missing or not archived.
     * @throws \RuntimeException         When WordPress refuses or fails to untrash it.
     */
    public function restore( int $id ): Layout {
        $post = $this->post_or_throw( $id );

        if ( LayoutStatus::ARCHIVED !== $post->post_status ) {
            throw new \InvalidArgumentException( esc_html__( 'Layout is not archived.', 'yaycheckout' ) );
        }

        $restored = wp_untrash_post( $id );

        if ( ! $restored instanceof \WP_Post ) {
            throw new \RuntimeException( esc_html( sprintf( 'Layout %d could not be restored.', $id ) ) );
        }

        // wp_untrash_post() hands back the pre-update object; only the status moved.
        $restored->post_status = LayoutStatus::DRAFT;

        return $this->to_layout( $restored, false );
    }

    /**
     * Free replaces the set and drafts the layout it replaces, as a Deactivate would;
     * Pro moves the id to the end, the most recent choice.
     */
    public function set_active( int $id ): void {
        $others = array_diff( $this->active_ids(), [ $id ] );

        if ( Plan::is_pro() ) {
            $ids = array_merge( $others, [ $id ] );
        } else {
            foreach ( $others as $replaced ) {
                $this->deactivate( (int) $replaced );
            }

            $ids = [ $id ];
        }

        $this->store_ids( $ids );
    }

    /**
     * Creates a layout draft from a catalogue template: its markup, which is a complete checkout, so
     * activating it as-is lets a shopper place an order, its design, and its logged-out view. The post
     * type's template only applies to auto-drafts, not to this insert.
     *
     * @throws \InvalidArgumentException When the template is not available or the insert fails.
     */
    public function create( string $title, string $template_slug ): Layout {
        return $this->insert_draft(
            $title,
            LayoutTemplateCatalogue::markup( $template_slug ),
            [],
            LayoutTemplateCatalogue::design( $template_slug ),
            $template_slug,
            LayoutTemplateCatalogue::logged_out_markup( $template_slug )
        );
    }

    /**
     * Copies a layout's content, logged-out view, assignment rules, design and template into a new draft layout.
     *
     * @throws \InvalidArgumentException When the source is missing, the limit is reached, or the insert fails.
     */
    public function duplicate( int $id ): Layout {
        $source = $this->find_or_throw( $id );

        /* translators: %s: source layout title. */
        return $this->insert_draft( sprintf( __( 'Duplicate of %s', 'yaycheckout' ), $source->title ), $source->content, $source->rules, $source->design, $source->template, $source->logged_out_content );
    }

    /**
     * Creates a draft from an export file's title, markup, assignment rules, design and logged-out view, as the current user
     * may store them (kses-filtered without unfiltered_html).
     * Never overwrites: a same-titled layout is a second layout, and the merchant archives the one they do not want.
     *
     * @param array  $rules              Already sanitized (Layout::sanitize_rules()); [] for none.
     * @param string $logged_out_content The logged-out view's markup; '' when none.
     * @throws \InvalidArgumentException When the limit is reached or the insert fails.
     */
    public function import( string $title, string $content, array $rules, LayoutDesign $design, string $logged_out_content = '' ): Layout {
        return $this->insert_draft( $title, $content, $rules, $design, '', $logged_out_content );
    }

    /**
     * Puts a layout's blocks and design back to its template's, or to the default template's when it has
     * none available on this plan (a Pro template in Free falls back to the default one).
     * Title, status, rules, the template and the active flag stay. WordPress stores the old markup and
     * design as a revision, so the editor can undo it.
     *
     * @throws \InvalidArgumentException When the layout is missing or the update fails.
     */
    public function reset( int $id ): Layout {
        $layout = $this->find_or_throw( $id );

        $slug = LayoutTemplateCatalogue::is_available( $layout->template ) ? $layout->template : LayoutTemplateCatalogue::DEFAULT_SLUG;

        // Core revisions the post after the update, so a layout never saved in the editor has no revision
        // holding the old markup. No-op when the latest revision already matches.
        wp_save_post_revision( $id );

        // Slashed by update_post(): without it block attributes lose their \uXXXX escapes, and a template's
        // custom CSS its backslashes. The design goes in the same update, so the revision core saves after it
        // pairs the template's markup with the template's design.
        $this->update_post(
            [
                'ID'           => $id,
                'post_content' => LayoutTemplateCatalogue::markup( $slug ),
                'meta_input'   => [ Layout::META_DESIGN => LayoutTemplateCatalogue::design( $slug )->to_meta() ],
            ]
        );

        return $this->find( $id );
    }

    /**
     * Changes a layout's title. Status, content, rules, design and the active flag stay.
     *
     * @param string $title Already sanitized (sanitize_text_field()).
     * @throws \InvalidArgumentException When the layout is missing, the title is blank, or the update fails.
     */
    public function rename( int $id, string $title ): Layout {
        $this->find_or_throw( $id );

        $title = trim( $title );

        if ( '' === $title ) {
            throw new \InvalidArgumentException( esc_html__( 'Name is required.', 'yaycheckout' ) );
        }

        // Slashed by update_post(): a title may hold backslashes.
        $this->update_post(
            [
                'ID'         => $id,
                'post_title' => $title,
            ]
        );

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
        $this->find_or_throw( $id );

        $clean = Layout::sanitize_rules( $rules );

        if ( [] === $clean ) {
            delete_post_meta( $id, Layout::META_RULES );
        } else {
            update_post_meta( $id, Layout::META_RULES, $clean );
        }

        return $clean;
    }

    /**
     * The logged-out view as the current user may store it: kses-filtered unless they have unfiltered_html, as core's
     * content_save_pre filters post_content. Core never filters meta, so every writer of Layout::META_LOGGED_OUT calls this.
     *
     * @param string $markup Unslashed.
     */
    public static function filter_logged_out_content( string $markup ): string {
        // wp_filter_post_kses() is content_save_pre's filter and takes slashed data.
        return current_user_can( 'unfiltered_html' ) ? $markup : wp_unslash( wp_filter_post_kses( wp_slash( $markup ) ) );
    }

    /**
     * The one insert path for a new draft, so slashing cannot drift.
     *
     * @param array  $rules         Already sanitized; written with the post, like the design, so the draft never exists without them.
     * @param string $template_slug The catalogue template it comes from; '' when none (an import).
     * @param string $logged_out_content The logged-out view's markup; '' when none.
     * @throws \InvalidArgumentException When the limit is reached or the insert fails.
     */
    private function insert_draft( string $title, string $content, array $rules, LayoutDesign $design, string $template_slug, string $logged_out_content = '' ): Layout {
        $meta = [ Layout::META_DESIGN => $design->to_meta() ];

        if ( [] !== $rules ) {
            $meta[ Layout::META_RULES ] = $rules;
        }

        if ( '' !== $template_slug ) {
            $meta[ Layout::META_TEMPLATE ] = $template_slug;
        }

        if ( '' !== $logged_out_content ) {
            $meta[ Layout::META_LOGGED_OUT ] = self::filter_logged_out_content( $logged_out_content );
        }

        // wp_insert_post() unslashes; without wp_slash() block attributes lose their \uXXXX escapes.
        $new_id = wp_insert_post(
            wp_slash(
                [
                    'post_type'    => Layout::POST_TYPE,
                    'post_status'  => LayoutStatus::DRAFT,
                    'post_title'   => $title,
                    'post_content' => $content,
                    'meta_input'   => $meta,
                ]
            ),
            true
        );

        if ( is_wp_error( $new_id ) ) {
            throw new \InvalidArgumentException( esc_html( $new_id->get_error_message() ) );
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
        $post = $this->post_or_throw( $id );

        if ( LayoutStatus::ARCHIVED !== $post->post_status ) {
            throw new \InvalidArgumentException( esc_html__( 'Archive this layout before deleting it.', 'yaycheckout' ) );
        }

        if ( $this->is_active( $id ) ) {
            throw new \InvalidArgumentException( esc_html__( 'Deactivate this layout before deleting it.', 'yaycheckout' ) );
        }

        if ( ! wp_delete_post( $id, true ) ) {
            throw new \RuntimeException( esc_html( sprintf( 'Layout %d could not be deleted.', $id ) ) );
        }
    }

    /**
     * Runs one of BULK_ACTIONS on each id and reports which ran and which were refused.
     * Unlike archive(), a bulk archive refuses an active layout instead of deactivating it:
     * a selection must not stop serving a layout the merchant did not pick to deactivate.
     * A \RuntimeException from a failed WordPress write stops the loop; earlier ids stay changed.
     *
     * @param string $action One of BULK_ACTIONS.
     * @param int[]  $ids    Layout ids.
     * @return array{done: int[], failed: array<int, array{id: int, message: string}>}
     * @throws \InvalidArgumentException When $action is unknown.
     */
    public function bulk( string $action, array $ids ): array {
        if ( ! in_array( $action, self::BULK_ACTIONS, true ) ) {
            throw new \InvalidArgumentException( esc_html( sprintf( 'Unknown bulk action "%s".', $action ) ) );
        }

        $done   = [];
        $failed = [];

        foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
            try {
                if ( ! self::is_layout( $id ) ) {
                    throw new \InvalidArgumentException( esc_html__( 'Layout not found.', 'yaycheckout' ) );
                }

                if ( 'archive' === $action && $this->is_active( $id ) ) {
                    throw new \InvalidArgumentException( esc_html__( 'Deactivate this layout before archiving it.', 'yaycheckout' ) );
                }

                $this->{$action}( $id );
                $done[] = $id;
            } catch ( \InvalidArgumentException $ex ) {
                $failed[] = [
                    'id'      => $id,
                    'message' => ExceptionHelper::message( $ex ),
                ];
            }
        }

        return [
            'done'   => $done,
            'failed' => $failed,
        ];
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
     * @throws \InvalidArgumentException When $id is not a layout.
     */
    private function post_or_throw( int $id ): \WP_Post {
        $post = get_post( $id );

        if ( ! self::is_layout( $post ) ) {
            throw new \InvalidArgumentException( esc_html( sprintf( 'Layout %d not found.', $id ) ) );
        }

        return $post;
    }

    /**
     * @throws \InvalidArgumentException When $id is not a layout.
     */
    private function find_or_throw( int $id ): Layout {
        $layout = $this->find( $id );

        if ( null === $layout ) {
            throw new \InvalidArgumentException( esc_html( sprintf( 'Layout %d not found.', $id ) ) );
        }

        return $layout;
    }

    private function to_layout( \WP_Post $post, bool $is_active ): Layout {
        $layout = Layout::from_post( $post, $this->hydrate_meta( get_post_meta( $post->ID ) ), $is_active );

        // A draft not updated since insert keeps a zero post_modified_gmt; derive it from the local time, as core's REST API does.
        if ( '0000-00-00 00:00:00' === $post->post_modified_gmt ) {
            $layout->updated_at = get_gmt_from_date( $post->post_modified );
        }

        return $layout;
    }

    /**
     * Updates the post, slashed first because wp_update_post() unslashes its input.
     *
     * @throws \InvalidArgumentException With WordPress's message when the update fails.
     */
    private function update_post( array $fields ): void {
        // Without edit_date, core re-dates a never-published draft to now, which moves it to the top of the list.
        $fields['edit_date'] = true;

        $result = wp_update_post( wp_slash( $fields ), true );

        if ( is_wp_error( $result ) ) {
            throw new \InvalidArgumentException( esc_html( $result->get_error_message() ) );
        }
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
