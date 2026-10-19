<?php
namespace YayCheckout\Engine\Register;

class ScriptName {

    public const STYLE_SETTINGS = 'yaycheckout/style/settings';

    public const STYLE_CHECKOUT = 'yaycheckout/style/checkout';

    public const STYLE_CHECKOUT_BASE = 'yaycheckout/style/checkout-base';

    public const STYLE_CHECKOUT_FONTS = 'yaycheckout/style/checkout-fonts';

    public const STYLE_DESIGN_PRESETS = 'yaycheckout/style/design-presets';

    public const STYLE_YAYBOOST = 'yaycheckout/style/yayboost';

    public const MODULE_PREFIX = 'yaycheckout/module/';

    public const PAGE_SETTINGS = 'yaycheckout/module/page-settings';

    /** What the admin app needs loaded first, in dev and built alike. */
    public const PAGE_SETTINGS_DEPS = [ 'react', 'react-dom', 'wp-element', 'wp-components', 'wp-hooks', 'wp-i18n', 'wp-date' ];
}
