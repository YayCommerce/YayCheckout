# Eval cases — source-of-truth-check

Run one at a time from the plugin root with a clean tree (`git status --short` empty):

1. Save the case diff to a temp file, `git apply --check` it, then `git apply` it.
2. On `dev/edd`, invoke `/source-of-truth-check` with no argument (the branch adds nothing there, so the scope is just the applied patch).
3. Compare the report with **Expected**. Must name the owner and the patched `file:line`; must not edit anything.
4. `git apply -R` the diff. If it fails, the skill edited without approval → case fails.

A case whose `--check` fails has rotted: fix the diff or drop the case.

## literal-meta-key

**Expected:** includes/Renderer/CheckoutPage.php → `Layout::OPTION_ACTIVE` (literal storage key)  
**Owner evidence:** grep: `includes/Models/Layout.php:23`

```diff
diff --git a/includes/Renderer/CheckoutPage.php b/includes/Renderer/CheckoutPage.php
index 653346e..cfdba29 100644
--- a/includes/Renderer/CheckoutPage.php
+++ b/includes/Renderer/CheckoutPage.php
@@ -72,6 +72,10 @@ class CheckoutPage {
             return null;
         }
 
+        if ( empty( get_option( 'yaycheckout_active_layout', [] ) ) ) {
+            return null;
+        }
+
         return ( new LayoutRepository() )->resolve_for_request();
     }
 
```

## settings-default-copy

**Expected:** includes/Controllers/SettingsRestController.php → `Models\Settings::schema()` (settings defaults copy)  
**Owner evidence:** grep: `includes/Models/Settings.php:32`

```diff
diff --git a/includes/Controllers/SettingsRestController.php b/includes/Controllers/SettingsRestController.php
index abbec39..bcb3c84 100644
--- a/includes/Controllers/SettingsRestController.php
+++ b/includes/Controllers/SettingsRestController.php
@@ -61,8 +61,12 @@ class SettingsRestController extends BaseRestController {
             return $this->error_invalid_arguments();
         }
 
-        // Merge over stored values: sanitize() defaults any key the body omits.
-        $clean = Settings::sanitize( array_replace_recursive( $this->settings->get()->values(), $input ) );
+        $defaults = [
+            'general'  => [ 'safe_mode' => false, 'share_usage_data' => false, 'delete_data_on_uninstall' => false ],
+            'advanced' => [ 'preview_link_lifetime' => 60, 'force_enqueue_checkout_scripts' => false ],
+        ];
+
+        $clean = Settings::sanitize( array_replace_recursive( $defaults, $this->settings->get()->values(), $input ) );
 
         if ( is_wp_error( $clean ) ) {
             return $this->error_invalid_arguments( $clean->get_error_message() );
```

## publish-rule-copy

**Expected:** includes/Repositories/LayoutRepository.php → `LayoutMarkupReader::publish_error()` (publish rule reimplemented)  
**Owner evidence:** grep: `includes/Services/LayoutMarkupReader.php:158`

```diff
diff --git a/includes/Repositories/LayoutRepository.php b/includes/Repositories/LayoutRepository.php
index 175ac3c..8065469 100644
--- a/includes/Repositories/LayoutRepository.php
+++ b/includes/Repositories/LayoutRepository.php
@@ -212,10 +212,8 @@ class LayoutRepository {
      * @throws \InvalidArgumentException With LayoutMarkupReader::publish_error() when the markup may not go live.
      */
     private static function refuse_unpublishable( string $markup ): void {
-        $error = LayoutMarkupReader::publish_error( $markup );
-
-        if ( '' !== $error ) {
-            throw new \InvalidArgumentException( $error );
+        if ( ! has_block( 'yaycheckout/checkout', $markup ) ) {
+            throw new \InvalidArgumentException( __( 'Add a Checkout block to this layout before publishing it.', 'yaycheckout' ) );
         }
     }
 
```

## ts-token-bound-copy

**Expected:** apps/admin/src/pages/design/components/InputsGroup.tsx → `RADIUS_RANGE` (`design.schema.ts`) (token bound literal)  
**Owner evidence:** grep: `apps/admin/src/lib/schema/design.schema.ts:34`

```diff
diff --git a/apps/admin/src/pages/design/components/InputsGroup.tsx b/apps/admin/src/pages/design/components/InputsGroup.tsx
index d423eb1..4f33358 100644
--- a/apps/admin/src/pages/design/components/InputsGroup.tsx
+++ b/apps/admin/src/pages/design/components/InputsGroup.tsx
@@ -1,6 +1,6 @@
 import { __ } from '@wordpress/i18n';
 
-import { RADIUS_RANGE, type DesignTokens } from '@/lib/schema/design.schema';
+import { type DesignTokens } from '@/lib/schema/design.schema';
 import { Segmented, SegmentedItem } from '@/components/ui/segmented';
 import { PxInput } from './PxInput';
 import { TokenField } from './TokenField';
@@ -44,7 +44,7 @@ export function InputsGroup() {
       </TokenField>
 
       <TokenField section="inputs" token="radius" label={__('Radius', 'yaycheckout')}>
-        {(field) => <PxInput value={field.value} onChange={field.onChange} range={RADIUS_RANGE} />}
+        {(field) => <PxInput value={field.value} onChange={field.onChange} range={{ min: 0, max: 24 }} />}
       </TokenField>
     </>
   );
```

## helper-reimplemented

**Expected:** apps/admin/src/pages/design/components/DesignActions.tsx → `countTokenDiffs` (`lib/helpers/design.helper.ts`) (helper reimplemented)  
**Owner evidence:** grep: `apps/admin/src/lib/helpers/design.helper.ts:4`

```diff
diff --git a/apps/admin/src/pages/design/components/DesignActions.tsx b/apps/admin/src/pages/design/components/DesignActions.tsx
index a0f5805..26cc463 100644
--- a/apps/admin/src/pages/design/components/DesignActions.tsx
+++ b/apps/admin/src/pages/design/components/DesignActions.tsx
@@ -2,12 +2,15 @@ import { useFormContext, useWatch } from 'react-hook-form';
 import { __, _n, sprintf } from '@wordpress/i18n';
 
 import { formatDateTime } from '@/lib/helpers/date.helper';
-import { countTokenDiffs } from '@/lib/helpers/design.helper';
 import { countDirtyLeaves } from '@/lib/helpers/form.helper';
 import { useDesignQuery } from '@/lib/queries/design.queries';
-import type { DesignForm } from '@/lib/schema/design.schema';
+import type { DesignForm, DesignTokens, TokenSection } from '@/lib/schema/design.schema';
 import { Button, LoadingButton } from '@/components/ui/button';
 
+function changedTokenCount(a: DesignTokens, b: DesignTokens): number {
+  return (Object.keys(a) as TokenSection[]).flatMap((s) => Object.keys(a[s]).filter((k) => (a[s] as Record<string, unknown>)[k] !== (b[s] as Record<string, unknown>)[k])).length;
+}
+
 /**
  * Top-bar Reset/Save. Lives outside the <form>, so Save targets it by id.
  * onReset receives how many tokens differ from the theme, for the confirm copy.
@@ -28,7 +31,7 @@ export function DesignActions({
   const dirtyCount = countDirtyLeaves(formState.dirtyFields);
   const { isDirty } = formState;
   // What "Reset to theme" would clear.
-  const overrideCount = countTokenDiffs(tokens, data.defaultTokens);
+  const overrideCount = changedTokenCount(tokens, data.defaultTokens);
 
   let status: string;
   if (isDirty) {
```

## doc-restates-code

**Expected:** includes/Services/ExtraFieldSubmission.php → `LayoutMarkupReader::EXTRA_FIELD_KEY_PATTERN` (comment restates rule)  
**Owner evidence:** grep: `includes/Services/LayoutMarkupReader.php:22-28`

```diff
diff --git a/includes/Services/ExtraFieldSubmission.php b/includes/Services/ExtraFieldSubmission.php
index 80b7921..b32eadd 100644
--- a/includes/Services/ExtraFieldSubmission.php
+++ b/includes/Services/ExtraFieldSubmission.php
@@ -8,6 +8,10 @@ defined( 'ABSPATH' ) || exit;
  *
  * Pure: no storage, no Woo hooks. Only Keys in $fields are read and choice
  * values must be one of the field's options.
+ *
+ * Keys use only letters, numbers and underscores and never start with an
+ * underscore: PHP rewrites spaces and dots in posted names, and a leading
+ * underscore reaches WooCommerce's internal order keys.
  */
 class ExtraFieldSubmission {
 
```

## allowed-mirror-in-sync

**Expected:** none (report says "No duplicates.")  
**Owner evidence:** mirror pair: `Settings::schema()` ↔ `settings.schema.ts` (+ `settings.api.ts` mapper, so the patch type-checks)

```diff
diff --git a/apps/admin/src/lib/api/settings.api.ts b/apps/admin/src/lib/api/settings.api.ts
index a736c44..b1e8736 100644
--- a/apps/admin/src/lib/api/settings.api.ts
+++ b/apps/admin/src/lib/api/settings.api.ts
@@ -17,6 +17,7 @@ export function settingsFromWire(wire: SettingsWire): Settings {
       safeMode: wire.general.safe_mode,
       shareUsageData: wire.general.share_usage_data,
       deleteDataOnUninstall: wire.general.delete_data_on_uninstall,
+      showTrustBadges: wire.general.show_trust_badges,
     },
     advanced: {
       previewLinkLifetime: wire.advanced.preview_link_lifetime,
@@ -31,6 +32,7 @@ export function settingsToWire(settings: Settings): SettingsWire {
       safe_mode: settings.general.safeMode,
       share_usage_data: settings.general.shareUsageData,
       delete_data_on_uninstall: settings.general.deleteDataOnUninstall,
+      show_trust_badges: settings.general.showTrustBadges,
     },
     advanced: {
       preview_link_lifetime: settings.advanced.previewLinkLifetime,
diff --git a/apps/admin/src/lib/schema/settings.schema.ts b/apps/admin/src/lib/schema/settings.schema.ts
index 78cbd99..910d66b 100644
--- a/apps/admin/src/lib/schema/settings.schema.ts
+++ b/apps/admin/src/lib/schema/settings.schema.ts
@@ -19,6 +19,7 @@ export const settingsWireSchema = z
         safe_mode: z.boolean(),
         share_usage_data: z.boolean(),
         delete_data_on_uninstall: z.boolean(),
+        show_trust_badges: z.boolean(),
       })
       .strict(),
     advanced: z
@@ -59,6 +60,7 @@ export const settingsSchema = z.object({
     safeMode: z.boolean(),
     shareUsageData: z.boolean(),
     deleteDataOnUninstall: z.boolean(),
+    showTrustBadges: z.boolean(),
   }),
   advanced: z.object({
     previewLinkLifetime: previewLinkLifetimeSchema,
diff --git a/includes/Models/Settings.php b/includes/Models/Settings.php
index 839536a..5e0a5d7 100644
--- a/includes/Models/Settings.php
+++ b/includes/Models/Settings.php
@@ -45,6 +45,10 @@ class Settings {
 					'type'    => 'bool',
 					'default' => false,
                 ],
+                'show_trust_badges'        => [
+					'type'    => 'bool',
+					'default' => false,
+                ],
             ],
             'advanced' => [
                 'preview_link_lifetime'          => [
```

## clean

**Expected:** none (report says "No duplicates.")  
**Owner evidence:** UI copy only

```diff
diff --git a/apps/admin/src/pages/settings/components/GeneralTab.tsx b/apps/admin/src/pages/settings/components/GeneralTab.tsx
index 939b530..ad566aa 100644
--- a/apps/admin/src/pages/settings/components/GeneralTab.tsx
+++ b/apps/admin/src/pages/settings/components/GeneralTab.tsx
@@ -20,7 +20,7 @@ export function GeneralTab() {
         render={({ field }) => (
           <SettingRow
             title={__('Safe mode', 'yaycheckout')}
-            description={__('Falls back to the native WooCommerce checkout. Your layouts stay saved.', 'yaycheckout')}
+            description={__('Shows the native WooCommerce checkout instead. Your layouts stay saved.', 'yaycheckout')}
           >
             <Switch checked={field.value} onCheckedChange={field.onChange} />
           </SettingRow>
```
