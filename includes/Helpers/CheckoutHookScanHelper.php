<?php
namespace YayCheckout\Helpers;

use YayCheckout\Support\CompatibilityCatalogue;

defined( 'ABSPATH' ) || exit;

/**
 * "Plugins touching checkout": resolves every callback on a fixed list of
 * checkout hooks to its file, maps the file to a plugin, mu-plugin or theme,
 * and reports what each one touches. Detection is real; the catalogue only
 * adds status and notes. The scan runs inside a REST request, so hooks a
 * plugin registers only on `wp` or `template_redirect` are invisible.
 */
class CheckoutHookScanHelper {

    /** Bucket => hooks. Buckets are the SPA's `touches` vocabulary. Generic hooks like template_include are left out on purpose: page builders and themes filter them without touching checkout. */
    public const HOOKS = [
        'fields'       => [
            'woocommerce_checkout_fields',
            'woocommerce_billing_fields',
            'woocommerce_shipping_fields',
            'woocommerce_default_address_fields',
            'woocommerce_form_field',
            'woocommerce_form_field_args',
            'woocommerce_checkout_get_value',
        ],
        'templates'    => [
            'woocommerce_locate_template',
            'wc_get_template',
            'woocommerce_before_checkout_form',
            'woocommerce_after_checkout_form',
            'woocommerce_checkout_before_customer_details',
            'woocommerce_checkout_after_customer_details',
        ],
        'gateways'     => [
            'woocommerce_payment_gateways',
            'woocommerce_available_payment_gateways',
        ],
        'order_review' => [
            'woocommerce_checkout_before_order_review',
            'woocommerce_checkout_order_review',
            'woocommerce_checkout_after_order_review',
            'woocommerce_review_order_before_submit',
            'woocommerce_review_order_after_submit',
            'woocommerce_update_order_review_fragments',
        ],
        'submission'   => [
            'woocommerce_checkout_process',
            'woocommerce_checkout_posted_data',
            'woocommerce_checkout_update_order_review',
        ],
    ];

    /** Plugin slugs never reported; the plugin's own slug is added at runtime. */
    public const EXCLUDED_SLUGS = [ 'woocommerce' ];

    /** @var array<string, string> kind => realpath of its root. */
    private array $roots;

    /** Roots are injectable so a test can scan fixture directories instead of the live install. */
    public function __construct( ?string $plugin_root = null, ?string $mu_root = null, ?string $theme_root = null ) {
        $this->roots = array_filter(
            [
                'plugin'    => realpath( $plugin_root ?? WP_PLUGIN_DIR ),
                'mu-plugin' => realpath( $mu_root ?? WPMU_PLUGIN_DIR ),
                'theme'     => realpath( $theme_root ?? get_theme_root() ),
            ]
        );
    }

    /**
     * @param  array $catalogue CompatibilityCatalogueRepository::get() output; only 'plugins' is read.
     * @return array<int, array{slug: string, name: string, version: string, kind: string, touches: string[], status: string, notes: ?string, docs_url: ?string}> Sorted by name.
     */
    public function scan( array $catalogue ): array {
        global $wp_filter;

        $owners = [];
        foreach ( self::HOOKS as $bucket => $hooks ) {
            foreach ( $hooks as $hook ) {
                if ( ! isset( $wp_filter[ $hook ] ) ) {
                    continue;
                }

                foreach ( $wp_filter[ $hook ]->callbacks as $callbacks ) {
                    foreach ( $callbacks as $callback ) {
                        $file  = self::callback_file( $callback['function'] );
                        $owner = null === $file ? null : $this->owner( $file );

                        if ( null === $owner ) {
                            continue;
                        }

                        $key                         = $owner['kind'] . ':' . $owner['slug'];
                        $owners[ $key ]              = $owners[ $key ] ?? $owner + [ 'touches' => [] ];
                        $owners[ $key ]['touches'][] = $bucket;
                    }
                }
            }
        }

        $rows = [];
        foreach ( $owners as $owner ) {
            $rows[] = $this->row( $owner, $catalogue['plugins'][ $owner['slug'] ] ?? [] );
        }

        usort( $rows, static fn( array $a, array $b ) => strcasecmp( $a['name'], $b['name'] ) );

        return $rows;
    }

    /**
     * The file that defines a hook callback, or null when reflection can't place it.
     *
     * @param mixed $handler Anything add_filter() accepted.
     */
    public static function callback_file( $handler ): ?string {
        try {
            if ( $handler instanceof \Closure ) {
                $reflection = new \ReflectionFunction( $handler );
            } elseif ( is_array( $handler ) && 2 === count( $handler ) ) {
                $reflection = new \ReflectionMethod( $handler[0], $handler[1] );
            } elseif ( is_string( $handler ) && false !== strpos( $handler, '::' ) ) {
                $reflection = new \ReflectionMethod( ...explode( '::', $handler, 2 ) );
            } elseif ( is_string( $handler ) ) {
                $reflection = new \ReflectionFunction( $handler );
            } elseif ( is_object( $handler ) ) {
                $reflection = new \ReflectionMethod( $handler, '__invoke' );
            } else {
                return null;
            }
        } catch ( \Throwable $ex ) {
            return null;
        }

        $file = $reflection->getFileName();

        return false === $file ? null : $file;
    }

    /**
     * @return array{kind: string, slug: string, path: string}|null Null when the file is outside every root or belongs to an excluded plugin.
     */
    private function owner( string $file ): ?array {
        global $wp_plugin_paths;

        $real = realpath( $file );
        if ( false === $real ) {
            return null;
        }

        // A plugin symlinked into the plugins directory resolves outside it; core records realpath => symlink for those, so map back the way plugin_basename() does.
        foreach ( (array) $wp_plugin_paths as $dir => $realdir ) {
            if ( 0 === strpos( $real, $realdir . DIRECTORY_SEPARATOR ) ) {
                $real = $dir . substr( $real, strlen( $realdir ) );
            }
        }

        foreach ( $this->roots as $kind => $root ) {
            if ( 0 !== strpos( $real, $root . DIRECTORY_SEPARATOR ) ) {
                continue;
            }

            // First path segment under the root: a directory for most plugins and every theme, the file itself for mu-plugins and single-file plugins.
            $first = strtok( substr( $real, strlen( $root ) + 1 ), DIRECTORY_SEPARATOR );
            // Lowercased so a directory like YayBoost still matches its catalogue slug.
            $slug = strtolower( basename( $first, '.php' ) );

            if ( in_array( $slug, array_merge( self::EXCLUDED_SLUGS, [ strtolower( dirname( YAYCHECKOUT_BASE_NAME ) ) ] ), true ) ) {
                return null;
            }

            return [
                'kind' => $kind,
                'slug' => $slug,
                'path' => $root . DIRECTORY_SEPARATOR . $first,
            ];
        }

        return null;
    }

    private function row( array $owner, array $entry ): array {
        $described = PluginHeaderHelper::describe( $owner['kind'], $owner['slug'], $owner['path'] );
        $touches   = array_values( array_unique( $owner['touches'] ) );

        return [
            'slug'     => $owner['slug'],
            'name'     => $described['name'],
            'version'  => $described['version'],
            'kind'     => $owner['kind'],
            'touches'  => $touches,
            'status'   => $entry['status'] ?? CompatibilityCatalogue::STATUS_UNVERIFIED,
            'notes'    => $entry['notes'] ?? $this->default_note( $touches ),
            'docs_url' => $entry['docs_url'] ?? null,
        ];
    }

    /** @param string[] $touches */
    private function default_note( array $touches ): string {
        $labels = [
            'fields'       => __( 'fields', 'yaycheckout' ),
            'templates'    => __( 'templates', 'yaycheckout' ),
            'gateways'     => __( 'gateways', 'yaycheckout' ),
            'order_review' => __( 'order review', 'yaycheckout' ),
            'submission'   => __( 'submission', 'yaycheckout' ),
        ];

        return sprintf(
            /* translators: %s: comma-separated list of checkout areas, e.g. "fields, gateways" */
            __( 'Hooks into checkout %s. Not on our tested list.', 'yaycheckout' ),
            implode( ', ', array_map( static fn( string $bucket ) => $labels[ $bucket ] ?? $bucket, $touches ) )
        );
    }
}
