<?php
namespace YayCheckout\Engine\Register;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use YayCheckout\Utils\SingletonTrait;
use YayCheckout\Engine\Register\ScriptName;

/**
 * Register Facade.
 *
 * Picks RegisterDev (Vite dev server + HMR) or RegisterProd (built assets)
 * based on YAYCHECKOUT_IS_DEVELOPMENT. yaycheckout.php defaults it to false;
 * define it true in wp-config.php to use the dev server. The release ZIP
 * strips RegisterDev.php, hence the class_exists() guards.
 *
 * @method static RegisterFacade get_instance()
 */
class RegisterFacade {
    use SingletonTrait;

    /** Hooks Initialization */
    protected function __construct() {
        add_filter( 'script_loader_tag', [ $this, 'add_entry_as_module' ], 10, 3 );
        add_action( 'init', [ $this, 'register_all_assets' ] );
        add_filter( 'pre_load_script_translations', [ $this, 'use_mo_file_for_script_translations' ], 10, 4 );

        $is_prod = ! defined( 'YAYCHECKOUT_IS_DEVELOPMENT' ) || YAYCHECKOUT_IS_DEVELOPMENT !== true;
        if ( $is_prod && class_exists( '\YayCheckout\Engine\Register\RegisterProd' ) ) {
            \YayCheckout\Engine\Register\RegisterProd::get_instance();
        } elseif ( ! $is_prod && class_exists( '\YayCheckout\Engine\Register\RegisterDev' ) ) {
            \YayCheckout\Engine\Register\RegisterDev::get_instance();
        }
    }

    public function add_entry_as_module( $tag, $handle ) {
        if ( strpos( $handle, ScriptName::MODULE_PREFIX ) !== false ) {
            if ( strpos( $tag, 'type="' ) !== false ) {
                return preg_replace( '/\stype="\S+\s/', ' type="module" ', $tag, 1 );
            } else {
                return str_replace( ' src=', ' type="module" src=', $tag );
            }
        }
        return $tag;
    }

    public function register_all_assets() {
        wp_register_style(
            ScriptName::STYLE_SETTINGS,
            YAYCHECKOUT_PLUGIN_URL . 'assets/dist/admin/style.css',
            [
                'woocommerce_admin_styles',
                'wp-components',
            ],
            YAYCHECKOUT_VERSION
        );
    }

    /**
     * YayCheckout scripts translations are all in MO file.
     *
     * @param string $json_translations
     * @param string $file
     * @param string $handle
     * @param string $domain
     */
    public function use_mo_file_for_script_translations( $json_translations, $file, $handle, $domain ) {
        $all_handles = [
            ScriptName::PAGE_SETTINGS,
        ];

        if ( 'yaycheckout' !== $domain || ! in_array( $handle, $all_handles, true ) ) {
            return $json_translations;
        }

        $translations = get_translations_for_domain( 'yaycheckout' );
        $messages     = [
            '' => [
                'domain' => 'messages',
            ],
        ];
        $entries      = $translations->entries;
        foreach ( $entries as $key => $entry ) {
            $messages[ $entry->singular ] = $entry->translations;
        }

        return wp_json_encode(
            [
                'domain'      => 'messages',
                'locale_data' => [
                    'messages' => $messages,
                ],
            ]
        );
    }
}
