<?php
namespace YayCheckout\Controllers;

defined( 'ABSPATH' ) || exit;

/**
 * Dump data for the Layouts screen, keyed by case. Bypasses LayoutRepository
 * entirely — every non-modal state in YC-02_Layouts_Home.dc.html is a literal
 * array here, so the screen can be built and reviewed before the real backend
 * (health metrics, compatibility engine, archive storage, safe-mode detection,
 * assignment rules) exists.
 *
 * Delete this file and repoint LayoutRestController::get_layouts() at
 * LayoutRepository once that backend lands.
 */
class LayoutsDumpData {

    public const DEFAULT_CASE = 'free-first-draft';

    public static function get( string $case_key ): array {
        $cases = self::all();

        return $cases[ $case_key ] ?? $cases[ self::DEFAULT_CASE ];
    }

    private static function all(): array {
        return [

            // yc02-1: nothing published yet, one draft layout.
            'free-first-draft'                => [
                'plan'            => 'free',
                'viewOnly'        => false,
                'archivedView'    => false,
                'listLocked'      => false,
                'counts'          => [
					'active'   => 1,
					'archived' => 0,
				],
                'planLimit'       => [
					'limit'   => 3,
					'used'    => 1,
					'reached' => false,
                ],
                'search'          => null,
                'pagination'      => null,
                'table'           => [
					'showAssignmentColumn' => false,
					'showArchivedColumns'  => false,
                ],
                'layouts'         => [
                    [
                        'id'              => 1,
                        'title'           => 'Default checkout',
                        'statusLabel'     => 'Draft',
                        'statusTone'      => 'default',
                        'isActive'        => false,
                        'lastPublishedAt' => null,
                        'actions'         => [
                            'edit'      => 'enabled',
							'preview'   => 'preview',
							'setActive' => 'disabled',
                            'publish'   => false,
							'moreMenu'  => true,
                        ],
                    ],
                ],
                'health'          => [
                    'rows' => [
                        [
							'label' => 'Orders through YayCheckout · 7 days',
							'value' => '—',
						],
                        [
							'label' => 'Checkout errors',
							'value' => '—',
                        ],
                        [
							'label' => 'Last error',
							'value' => '—',
                        ],
                    ],
                    'note' => 'No data until a layout is live.',
                    'link' => null,
                ],
                'compatibility'   => [
					'summary' => '0 warnings',
					'tone'    => 'default',
					'link'    => [ 'label' => 'Open Compatibility' ],
                ],
                'asideNote'       => null,
                'banner'          => [
                    'tone'    => 'info',
                    'message' => 'Nothing is live yet — publish and set active from the editor.',
                    'action'  => [ 'label' => 'Open editor' ],
                ],
                'planPromo'       => null,
                'emptyState'      => null,
                'tableFooterNote' => '1 layout · ⋯ menu: Duplicate, Archive',
            ],

            // yc02-2: one published+active layout, one draft.
            'free-one-active'                 => [
                'plan'            => 'free',
                'viewOnly'        => false,
                'archivedView'    => false,
                'listLocked'      => false,
                'counts'          => [
					'active'   => 2,
					'archived' => 0,
				],
                'planLimit'       => [
					'limit'   => 3,
					'used'    => 2,
					'reached' => false,
                ],
                'search'          => null,
                'pagination'      => null,
                'table'           => [
					'showAssignmentColumn' => false,
					'showArchivedColumns'  => false,
                ],
                'layouts'         => [
                    [
                        'id'              => 1,
						'title'           => 'Default checkout',
						'statusLabel'     => 'Published',
						'statusTone'      => 'default',
                        'isActive'        => true,
						'lastPublishedAt' => '4 Sep, 10:12',
                        'actions'         => [
							'edit'      => 'enabled',
							'preview'   => 'preview',
							'setActive' => 'hidden',
							'publish'   => false,
							'moreMenu'  => true,
						],
                    ],
                    [
                        'id'              => 2,
						'title'           => 'Spring redesign',
						'statusLabel'     => 'Draft',
						'statusTone'      => 'default',
                        'isActive'        => false,
						'lastPublishedAt' => null,
                        'actions'         => [
							'edit'      => 'enabled',
							'preview'   => 'preview',
							'setActive' => 'disabled',
							'publish'   => false,
							'moreMenu'  => true,
						],
                    ],
                ],
                'health'          => [
                    'rows' => [
                        [
							'label' => 'Orders through YayCheckout · 7 days',
							'value' => '128',
						],
                        [
							'label' => 'Checkout errors',
							'value' => '0',
                        ],
                        [
							'label' => 'Last error',
							'value' => 'Passed 2h ago',
                        ],
                    ],
                    'link' => [ 'label' => 'View logs' ],
                ],
                'compatibility'   => [
					'summary' => '0 warnings',
					'tone'    => 'default',
					'link'    => [ 'label' => 'Open Compatibility' ],
                ],
                'asideNote'       => null,
                'banner'          => null,
                'planPromo'       => null,
                'emptyState'      => null,
                'tableFooterNote' => '2 layouts · "Set active" on "Spring redesign" is disabled — publish first · ⋯ menu: Duplicate, Archive',
            ],

            // yc02-3: the active layout has a newer, unpublished draft.
            'free-active-unpublished-changes' => [
                'plan'            => 'free',
                'viewOnly'        => false,
                'archivedView'    => false,
                'listLocked'      => false,
                'counts'          => [
					'active'   => 2,
					'archived' => 0,
				],
                'planLimit'       => [
					'limit'   => 3,
					'used'    => 2,
					'reached' => false,
                ],
                'search'          => null,
                'pagination'      => null,
                'table'           => [
					'showAssignmentColumn' => false,
					'showArchivedColumns'  => false,
                ],
                'layouts'         => [
                    [
                        'id'              => 1,
						'title'           => 'Default checkout',
						'statusLabel'     => 'Published · unpublished changes',
						'statusTone'      => 'default',
                        'isActive'        => true,
						'lastPublishedAt' => '4 Sep, 10:12',
                        'actions'         => [
							'edit'      => 'enabled',
							'preview'   => 'previewDraft',
							'setActive' => 'hidden',
							'publish'   => true,
							'moreMenu'  => true,
						],
                    ],
                    [
                        'id'              => 2,
						'title'           => 'Spring redesign',
						'statusLabel'     => 'Draft',
						'statusTone'      => 'default',
                        'isActive'        => false,
						'lastPublishedAt' => null,
                        'actions'         => [
							'edit'      => 'enabled',
							'preview'   => 'preview',
							'setActive' => 'disabled',
							'publish'   => false,
							'moreMenu'  => true,
						],
                    ],
                ],
                'health'          => [
                    'rows' => [
                        [
							'label' => 'Orders through YayCheckout · 7 days',
							'value' => '128',
						],
                        [
							'label' => 'Checkout errors',
							'value' => '0',
                        ],
                        [
							'label' => 'Last error',
							'value' => 'Passed 2h ago',
                        ],
                    ],
                    'link' => [ 'label' => 'View logs' ],
                ],
                'compatibility'   => [
					'summary' => '0 warnings',
					'tone'    => 'default',
					'link'    => [ 'label' => 'Open Compatibility' ],
                ],
                'asideNote'       => null,
                'banner'          => null,
                'planPromo'       => null,
                'emptyState'      => null,
                'tableFooterNote' => '2 layouts · customers still see the last published version',
            ],

            // yc02-4: Lite's 3-layout cap reached.
            'free-limit-reached'              => [
                'plan'            => 'free',
                'viewOnly'        => false,
                'archivedView'    => false,
                'listLocked'      => false,
                'counts'          => [
					'active'   => 3,
					'archived' => 0,
				],
                'planLimit'       => [
					'limit'   => 3,
					'used'    => 3,
					'reached' => true,
                ],
                'search'          => null,
                'pagination'      => null,
                'table'           => [
					'showAssignmentColumn' => false,
					'showArchivedColumns'  => false,
                ],
                'layouts'         => [
                    [
                        'id'              => 1,
						'title'           => 'Default checkout',
						'statusLabel'     => 'Published',
						'statusTone'      => 'default',
                        'isActive'        => true,
						'lastPublishedAt' => '4 Sep, 10:12',
                        'actions'         => [
							'edit'      => 'enabled',
							'preview'   => 'preview',
							'setActive' => 'hidden',
							'publish'   => false,
							'moreMenu'  => true,
						],
                    ],
                    [
                        'id'              => 2,
						'title'           => 'Spring redesign',
						'statusLabel'     => 'Published',
						'statusTone'      => 'default',
                        'isActive'        => false,
						'lastPublishedAt' => '28 Aug, 16:40',
                        'actions'         => [
							'edit'      => 'enabled',
							'preview'   => 'preview',
							'setActive' => 'enabled',
							'publish'   => false,
							'moreMenu'  => true,
						],
                    ],
                    [
                        'id'              => 3,
						'title'           => 'Black Friday test',
						'statusLabel'     => 'Draft',
						'statusTone'      => 'default',
                        'isActive'        => false,
						'lastPublishedAt' => null,
                        'actions'         => [
							'edit'      => 'enabled',
							'preview'   => 'preview',
							'setActive' => 'disabled',
							'publish'   => false,
							'moreMenu'  => true,
						],
                    ],
                ],
                'health'          => [
                    'rows' => [
                        [
							'label' => 'Orders through YayCheckout · 7 days',
							'value' => '128',
						],
                        [
							'label' => 'Checkout errors',
							'value' => '0',
                        ],
                        [
							'label' => 'Last error',
							'value' => 'Passed 2h ago',
                        ],
                    ],
                    'link' => [ 'label' => 'View logs' ],
                ],
                'compatibility'   => [
					'summary' => '0 warnings',
					'tone'    => 'default',
					'link'    => [ 'label' => 'Open Compatibility' ],
                ],
                'asideNote'       => null,
                'banner'          => null,
                'planPromo'       => [ 'message' => 'Assignment rules — show different layouts by role, country, cart or campaign link' ],
                'emptyState'      => null,
                'tableFooterNote' => '3 of 3 layouts used on Lite',
            ],

            // yc02-5: safe mode engaged, list read-only.
            'safe-mode'                       => [
                'plan'            => 'free',
                'viewOnly'        => false,
                'archivedView'    => false,
                'listLocked'      => false,
                'counts'          => [
					'active'   => 2,
					'archived' => 0,
				],
                'planLimit'       => [
					'limit'   => 3,
					'used'    => 2,
					'reached' => false,
                ],
                'search'          => null,
                'pagination'      => null,
                'table'           => [
					'showAssignmentColumn' => false,
					'showArchivedColumns'  => false,
                ],
                'layouts'         => [
                    [
                        'id'              => 1,
						'title'           => 'Default checkout',
						'statusLabel'     => 'Published',
						'statusTone'      => 'default',
                        'isActive'        => true,
						'lastPublishedAt' => '4 Sep, 10:12',
						'dimmed'          => true,
                        'actions'         => [
							'edit'      => 'disabled',
							'preview'   => 'disabled',
							'setActive' => 'hidden',
							'publish'   => false,
							'moreMenu'  => false,
						],
                    ],
                    [
                        'id'              => 2,
						'title'           => 'Spring redesign',
						'statusLabel'     => 'Draft',
						'statusTone'      => 'default',
                        'isActive'        => false,
						'lastPublishedAt' => null,
						'dimmed'          => true,
                        'actions'         => [
							'edit'      => 'disabled',
							'preview'   => 'disabled',
							'setActive' => 'hidden',
							'publish'   => false,
							'moreMenu'  => false,
						],
                    ],
                ],
                'health'          => [
                    'rows' => [
                        [
							'label' => 'Orders through YayCheckout · 7 days',
							'value' => '128',
						],
                        [
							'label' => 'Checkout errors',
							'value' => '0',
                        ],
                        [
							'label' => 'Last error',
							'value' => 'Passed 2h ago',
                        ],
                    ],
                    'link' => [ 'label' => 'View logs' ],
                ],
                'compatibility'   => [
					'summary' => '1 warning',
					'tone'    => 'warning',
					'link'    => [ 'label' => 'Open Compatibility' ],
                ],
                'asideNote'       => null,
                'banner'          => [
                    'tone'    => 'warning',
                    'title'   => 'Safe mode is on — your native checkout is showing',
                    'message' => 'Turned on automatically after a fatal error was caught while rendering "Default checkout" on 8 Sep, 09:41.',
                    'action'  => [ 'label' => 'Turn safe mode off' ],
                ],
                'planPromo'       => null,
                'emptyState'      => null,
                'tableFooterNote' => 'List is read-only while safe mode is on',
            ],

            // yc02-6: Pro plan, Assignment column and priority order.
            'pro-with-rules'                  => [
                'plan'            => 'pro',
                'viewOnly'        => false,
                'archivedView'    => false,
                'listLocked'      => false,
                'counts'          => [
					'active'   => 4,
					'archived' => 0,
				],
                'planLimit'       => [
					'limit'   => null,
					'used'    => 4,
					'reached' => false,
                ],
                'search'          => null,
                'pagination'      => null,
                'table'           => [
					'showAssignmentColumn' => true,
					'showArchivedColumns'  => false,
                ],
                'layouts'         => [
                    [
                        'id'              => 1,
						'title'           => 'B2B checkout',
						'statusLabel'     => 'Published',
						'statusTone'      => 'default',
                        'isActive'        => false,
						'lastPublishedAt' => '1 Sep, 09:03',
						'assignment'      => 'Role is Wholesale',
                        'actions'         => [
							'edit'      => 'enabled',
							'preview'   => 'preview',
							'setActive' => 'enabled',
							'publish'   => false,
							'moreMenu'  => true,
						],
                    ],
                    [
                        'id'              => 2,
						'title'           => 'DACH checkout',
						'statusLabel'     => 'Published',
						'statusTone'      => 'default',
                        'isActive'        => false,
						'lastPublishedAt' => '30 Aug, 14:20',
						'assignment'      => 'Country is DE, AT, CH',
                        'actions'         => [
							'edit'      => 'enabled',
							'preview'   => 'preview',
							'setActive' => 'enabled',
							'publish'   => false,
							'moreMenu'  => true,
						],
                    ],
                    [
                        'id'              => 3,
						'title'           => 'Spring sale landing',
						'statusLabel'     => 'Draft',
						'statusTone'      => 'default',
                        'isActive'        => false,
						'lastPublishedAt' => null,
						'assignment'      => 'Campaign link: spring-sale',
                        'actions'         => [
							'edit'      => 'enabled',
							'preview'   => 'preview',
							'setActive' => 'disabled',
							'publish'   => false,
							'moreMenu'  => true,
						],
                    ],
                    [
                        'id'              => 4,
						'title'           => 'Default checkout',
						'statusLabel'     => 'Published',
						'statusTone'      => 'default',
                        'isActive'        => true,
						'lastPublishedAt' => '4 Sep, 10:12',
						'assignment'      => 'Default — everyone else',
                        'actions'         => [
							'edit'      => 'enabled',
							'preview'   => 'preview',
							'setActive' => 'hidden',
							'publish'   => false,
							'moreMenu'  => true,
						],
                    ],
                ],
                'health'          => [
                    'rows' => [
                        [
							'label' => 'Orders through YayCheckout · 7 days',
							'value' => '128',
						],
                        [
							'label' => 'Checkout errors',
							'value' => '0',
                        ],
                        [
							'label' => 'Last error',
							'value' => 'Passed 2h ago',
                        ],
                    ],
                    'link' => [ 'label' => 'View logs' ],
                ],
                'compatibility'   => [
					'summary' => '0 warnings',
					'tone'    => 'default',
					'link'    => [ 'label' => 'Open Compatibility' ],
                ],
                'asideNote'       => null,
                'banner'          => null,
                'planPromo'       => null,
                'emptyState'      => null,
                'tableFooterNote' => '4 layouts · drag ⠿ to change priority · first match from the top wins · the Default row cannot be reordered',
            ],

            // yc02-13: every layout archived, native checkout showing.
            'edge-no-layouts-left'            => [
                'plan'            => 'free',
                'viewOnly'        => false,
                'archivedView'    => false,
                'listLocked'      => false,
                'counts'          => [
					'active'   => 0,
					'archived' => 3,
				],
                'planLimit'       => [
					'limit'   => 3,
					'used'    => 0,
					'reached' => false,
                ],
                'search'          => null,
                'pagination'      => null,
                'table'           => [
					'showAssignmentColumn' => false,
					'showArchivedColumns'  => false,
                ],
                'layouts'         => [],
                'health'          => [
                    'rows' => [
                        [
							'label' => 'Orders through YayCheckout · 7 days',
							'value' => '12',
						],
                        [
							'label' => 'Checkout errors',
							'value' => '0',
                        ],
                        [
							'label' => 'Last self-test',
							'value' => 'n/a',
                        ],
                    ],
                    'note' => 'Orders counted before the last layout was archived.',
                    'link' => null,
                ],
                'compatibility'   => [
					'summary' => '1 warning — no active layout',
					'tone'    => 'warning',
					'link'    => [ 'label' => 'Open Compatibility' ],
                ],
                'asideNote'       => null,
                'banner'          => [
                    'tone'    => 'info',
                    'message' => 'No layout is active — your customers see the native WooCommerce checkout.',
                    'link'    => [ 'label' => 'View archived (3)' ],
                ],
                'planPromo'       => null,
                'emptyState'      => [
                    'title'   => 'Nothing here yet',
                    'message' => 'All three of your layouts are archived. Restore one to bring it back to this list, or start a new draft. Archiving never deletes anything.',
                ],
                'tableFooterNote' => null,
            ],

            // yc02-14: the Archive tab — restore or delete for good.
            'edge-archive-view'               => [
                'plan'            => 'free',
                'viewOnly'        => false,
                'archivedView'    => true,
                'listLocked'      => false,
                'counts'          => [
					'active'   => 2,
					'archived' => 3,
				],
                'planLimit'       => [
					'limit'   => 3,
					'used'    => 2,
					'reached' => false,
                ],
                'search'          => null,
                'pagination'      => null,
                'table'           => [
					'showAssignmentColumn' => false,
					'showArchivedColumns'  => true,
                ],
                'layouts'         => [
                    [
                        'id'              => 1,
						'title'           => 'Black Friday 2025',
						'statusLabel'     => 'Archived',
						'statusTone'      => 'default',
                        'isActive'        => false,
						'lastPublishedAt' => '28 Nov 2025',
						'archivedAt'      => '2 Dec 2025',
                        'actions'         => [
							'edit'          => 'hidden',
							'preview'       => 'preview',
							'setActive'     => 'hidden',
							'publish'       => false,
							'moreMenu'      => false,
							'restore'       => true,
							'deleteForGood' => true,
						],
                    ],
                    [
                        'id'              => 2,
						'title'           => 'Two-column test',
						'statusLabel'     => 'Archived',
						'statusTone'      => 'default',
                        'isActive'        => false,
						'lastPublishedAt' => null,
						'archivedAt'      => '14 Jan',
                        'actions'         => [
							'edit'          => 'hidden',
							'preview'       => 'preview',
							'setActive'     => 'hidden',
							'publish'       => false,
							'moreMenu'      => false,
							'restore'       => true,
							'deleteForGood' => true,
						],
                    ],
                    [
                        'id'              => 3,
						'title'           => 'Imported from FunnelKit',
						'statusLabel'     => 'Archived',
						'statusTone'      => 'default',
                        'isActive'        => false,
						'lastPublishedAt' => null,
						'archivedAt'      => '3 Sep',
                        'actions'         => [
							'edit'          => 'hidden',
							'preview'       => 'preview',
							'setActive'     => 'hidden',
							'publish'       => false,
							'moreMenu'      => false,
							'restore'       => true,
							'deleteForGood' => true,
						],
                    ],
                ],
                'health'          => [
                    'rows' => [
                        [
							'label' => 'Orders through YayCheckout · 7 days',
							'value' => '128',
						],
                        [
							'label' => 'Checkout errors',
							'value' => '0',
                        ],
                        [
							'label' => 'Last self-test',
							'value' => 'Passed 2h ago',
                        ],
                    ],
                    'link' => null,
                ],
                'compatibility'   => [
					'summary' => '0 warnings',
					'tone'    => 'default',
					'link'    => [ 'label' => 'Open Compatibility' ],
                ],
                'asideNote'       => null,
                'banner'          => null,
                'planPromo'       => null,
                'emptyState'      => null,
                'tableFooterNote' => 'Archived layouts never render. Restoring one puts it back as Published or Draft, exactly as it was — it does not become active.',
            ],

            // yc02-15: 24 layouts, search + pagination (static), long names truncate.
            'edge-many-long-names'            => [
                'plan'            => 'pro',
                'viewOnly'        => false,
                'archivedView'    => false,
                'listLocked'      => false,
                'counts'          => [
					'active'   => 24,
					'archived' => 0,
				],
                'planLimit'       => [
					'limit'   => null,
					'used'    => 24,
					'reached' => false,
                ],
                'search'          => [
					'term'       => '',
					'totalShown' => 6,
					'total'      => 24,
                ],
                'pagination'      => [
					'page'       => 1,
					'totalPages' => 4,
                ],
                'table'           => [
					'showAssignmentColumn' => true,
					'showArchivedColumns'  => false,
                ],
                'layouts'         => [
                    [
                        'id'              => 1,
						'title'           => 'Wholesale checkout — EU pallet orders with VAT validation and net-30 terms',
                        'statusLabel'     => 'Published',
						'statusTone'      => 'default',
						'isActive'        => false,
						'lastPublishedAt' => '1 Sep',
                        'assignment'      => 'Role is Wholesale + Country is DE, AT, CH',
                        'actions'         => [
							'edit'      => 'enabled',
							'preview'   => 'hidden',
							'setActive' => 'hidden',
							'publish'   => false,
							'moreMenu'  => true,
						],
                    ],
                    [
                        'id'              => 2,
						'title'           => 'Spring sale landing page checkout (campaign traffic only, do not set active)',
                        'statusLabel'     => 'Draft',
						'statusTone'      => 'default',
						'isActive'        => false,
						'lastPublishedAt' => null,
                        'assignment'      => 'Campaign link: spring-sale',
                        'actions'         => [
							'edit'      => 'enabled',
							'preview'   => 'hidden',
							'setActive' => 'hidden',
							'publish'   => false,
							'moreMenu'  => true,
						],
                    ],
                    [
                        'id'              => 3,
						'title'           => 'DACH checkout',
						'statusLabel'     => 'Published',
						'statusTone'      => 'default',
                        'isActive'        => false,
						'lastPublishedAt' => '30 Aug',
						'assignment'      => 'Country is DE, AT, CH',
                        'actions'         => [
							'edit'      => 'enabled',
							'preview'   => 'hidden',
							'setActive' => 'hidden',
							'publish'   => false,
							'moreMenu'  => true,
						],
                    ],
                    [
                        'id'              => 4,
						'title'           => 'Mobile express — Apple Pay first, single column, no order notes field',
                        'statusLabel'     => 'Published · edited',
						'statusTone'      => 'default',
						'isActive'        => false,
						'lastPublishedAt' => '22 Aug',
                        'assignment'      => 'Device is Mobile',
                        'actions'         => [
							'edit'      => 'enabled',
							'preview'   => 'hidden',
							'setActive' => 'hidden',
							'publish'   => false,
							'moreMenu'  => true,
						],
                    ],
                    [
                        'id'              => 5,
						'title'           => 'Digital goods only',
						'statusLabel'     => 'Published',
						'statusTone'      => 'default',
                        'isActive'        => false,
						'lastPublishedAt' => '11 Aug',
						'assignment'      => 'Cart has no shippable items',
                        'actions'         => [
							'edit'      => 'enabled',
							'preview'   => 'hidden',
							'setActive' => 'hidden',
							'publish'   => false,
							'moreMenu'  => true,
						],
                    ],
                    [
                        'id'              => 6,
						'title'           => 'Default checkout',
						'statusLabel'     => 'Published',
						'statusTone'      => 'default',
                        'isActive'        => true,
						'lastPublishedAt' => '4 Sep',
						'assignment'      => 'Default — everyone else',
                        'actions'         => [
							'edit'      => 'enabled',
							'preview'   => 'hidden',
							'setActive' => 'hidden',
							'publish'   => false,
							'moreMenu'  => true,
						],
                    ],
                ],
                'health'          => [
                    'rows' => [
                        [
							'label' => 'Orders through YayCheckout · 7 days',
							'value' => '1,284',
						],
                        [
							'label' => 'Checkout errors',
							'value' => '0',
                        ],
                        [
							'label' => 'Last self-test',
							'value' => 'Passed 2h ago',
                        ],
                    ],
                    'link' => null,
                ],
                'compatibility'   => [
					'summary' => '0 warnings',
					'tone'    => 'default',
					'link'    => [ 'label' => 'Open Compatibility' ],
                ],
                'asideNote'       => [
                    'title'   => 'Priority across pages',
                    'message' => 'Dragging only reorders within the page you are viewing. Use "Move to position…" in the ⋯ menu to jump a layout across pages.',
                ],
                'banner'          => null,
                'planPromo'       => null,
                'emptyState'      => null,
                'tableFooterNote' => 'The Default row is pinned to the bottom of every page.',
            ],

            // yc02-17: signed in, missing manage_woocommerce — view only.
            'edge-read-only-role'             => [
                'plan'            => 'free',
                'viewOnly'        => true,
                'archivedView'    => false,
                'listLocked'      => false,
                'counts'          => [
					'active'   => 2,
					'archived' => 0,
				],
                'planLimit'       => [
					'limit'   => 3,
					'used'    => 2,
					'reached' => false,
                ],
                'search'          => null,
                'pagination'      => null,
                'table'           => [
					'showAssignmentColumn' => false,
					'showArchivedColumns'  => false,
                ],
                'layouts'         => [
                    [
                        'id'              => 1,
						'title'           => 'Default checkout',
						'statusLabel'     => 'Published',
						'statusTone'      => 'default',
                        'isActive'        => true,
						'lastPublishedAt' => '4 Sep, 10:12',
                        'actions'         => [
							'edit'      => 'disabled',
							'preview'   => 'preview',
							'setActive' => 'hidden',
							'publish'   => false,
							'moreMenu'  => false,
						],
                    ],
                    [
                        'id'              => 2,
						'title'           => 'Spring redesign',
						'statusLabel'     => 'Draft',
						'statusTone'      => 'default',
                        'isActive'        => false,
						'lastPublishedAt' => null,
                        'actions'         => [
							'edit'      => 'disabled',
							'preview'   => 'preview',
							'setActive' => 'disabled',
							'publish'   => false,
							'moreMenu'  => false,
						],
                    ],
                ],
                'health'          => [
                    'rows' => [
                        [
							'label' => 'Orders through YayCheckout · 7 days',
							'value' => '128',
						],
                        [
							'label' => 'Checkout errors',
							'value' => '0',
                        ],
                        [
							'label' => 'Last self-test',
							'value' => 'Passed 2h ago',
                        ],
                    ],
                    'note' => 'Running the self-test needs edit rights.',
                    'link' => null,
                ],
                'compatibility'   => [
					'summary' => '0 warnings',
					'tone'    => 'default',
					'link'    => [ 'label' => 'Open Compatibility' ],
                ],
                'asideNote'       => null,
                'banner'          => [
                    'tone'    => 'info',
                    'message' => 'You can view layouts but not change them. Editing the checkout needs the "manage WooCommerce" capability.',
                    'link'    => [ 'label' => 'Ask an administrator' ],
                ],
                'planPromo'       => null,
                'emptyState'      => null,
                'tableFooterNote' => 'Preview and the Compatibility report stay available so support staff can diagnose without edit rights.',
            ],

            // not in the wireframe: showcases every WordPress post status a layout can carry.
            'edge-all-statuses'               => [
                'plan'            => 'pro',
                'viewOnly'        => false,
                'archivedView'    => false,
                'listLocked'      => false,
                'counts'          => [
                    'active'   => 6,
                    'archived' => 0,
                ],
                'planLimit'       => [
                    'limit'   => null,
                    'used'    => 6,
                    'reached' => false,
                ],
                'search'          => null,
                'pagination'      => null,
                'table'           => [
                    'showAssignmentColumn' => false,
                    'showArchivedColumns'  => false,
                ],
                'layouts'         => [
                    [
                        'id'              => 1,
                        'title'           => 'Draft checkout',
                        'statusLabel'     => 'Draft',
                        'statusTone'      => 'default',
                        'isActive'        => false,
                        'lastPublishedAt' => null,
                        'actions'         => [
                            'edit'      => 'enabled',
                            'preview'   => 'preview',
                            'setActive' => 'disabled',
                            'publish'   => false,
                            'moreMenu'  => true,
                        ],
                    ],
                    [
                        'id'              => 2,
                        'title'           => 'Review checkout',
                        'statusLabel'     => 'Pending review',
                        'statusTone'      => 'default',
                        'isActive'        => false,
                        'lastPublishedAt' => null,
                        'actions'         => [
                            'edit'      => 'enabled',
                            'preview'   => 'preview',
                            'setActive' => 'disabled',
                            'publish'   => false,
                            'moreMenu'  => true,
                        ],
                    ],
                    [
                        'id'              => 3,
                        'title'           => 'Internal test checkout',
                        'statusLabel'     => 'Private',
                        'statusTone'      => 'default',
                        'isActive'        => false,
                        'lastPublishedAt' => '5 Sep, 14:20',
                        'actions'         => [
                            'edit'      => 'enabled',
                            'preview'   => 'preview',
                            'setActive' => 'enabled',
                            'publish'   => false,
                            'moreMenu'  => true,
                        ],
                    ],
                    [
                        'id'              => 4,
                        'title'           => 'Fall sale checkout',
                        'statusLabel'     => 'Scheduled · 20 Sep, 2026',
                        'statusTone'      => 'default',
                        'isActive'        => false,
                        'lastPublishedAt' => null,
                        'actions'         => [
                            'edit'      => 'enabled',
                            'preview'   => 'preview',
                            'setActive' => 'disabled',
                            'publish'   => false,
                            'moreMenu'  => true,
                        ],
                    ],
                    [
                        'id'              => 5,
                        'title'           => 'Default checkout',
                        'statusLabel'     => 'Published',
                        'statusTone'      => 'default',
                        'isActive'        => false,
                        'lastPublishedAt' => '4 Sep, 10:12',
                        'actions'         => [
                            'edit'      => 'enabled',
                            'preview'   => 'preview',
                            'setActive' => 'enabled',
                            'publish'   => false,
                            'moreMenu'  => true,
                        ],
                    ],
                    [
                        'id'              => 6,
                        'title'           => 'Spring sale checkout',
                        'statusLabel'     => 'Published · unpublished changes',
                        'statusTone'      => 'default',
                        'isActive'        => false,
                        'lastPublishedAt' => '2 Sep, 09:15',
                        'actions'         => [
                            'edit'      => 'enabled',
                            'preview'   => 'previewDraft',
                            'setActive' => 'enabled',
                            'publish'   => true,
                            'moreMenu'  => true,
                        ],
                    ],
                ],
                'health'          => [
                    'rows' => [
                        [
                            'label' => 'Orders through YayCheckout · 7 days',
                            'value' => '128',
                        ],
                        [
                            'label' => 'Checkout errors',
                            'value' => '0',
                        ],
                        [
                            'label' => 'Last self-test',
                            'value' => 'Passed 2h ago',
                        ],
                    ],
                    'link' => [ 'label' => 'View logs' ],
                ],
                'compatibility'   => [
                    'summary' => '0 warnings',
                    'tone'    => 'default',
                    'link'    => [ 'label' => 'Open Compatibility' ],
                ],
                'asideNote'       => null,
                'banner'          => null,
                'planPromo'       => null,
                'emptyState'      => null,
                'tableFooterNote' => '6 layouts showing every publish status a layout can carry — Draft, Pending review, Private, Scheduled, Published, Published with unpublished changes.',
            ],

        ];
    }
}
