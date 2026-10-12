# A design font is uploaded into WordPress's Font Library when the layout is saved, and printed only on the checkout

**Purpose:** why a font picked under the layout's Design → Typography is downloaded into the site's Font Library instead of being bundled or loaded from Google, why it isn't activated site-wide, why it uploads when the layout is saved, and why the editor previews it from Google until then.

**Update when:** never edit it. Supersede it with a new record if fonts are bundled in the zip, loaded from a CDN on the checkout, activated in the site's global styles, uploaded at another moment, or previewed another way.

## Status

Proposed, 2026-10-11, by Trace Bui.

## Context

- `LayoutDesign::FONT_FAMILIES` offers twelve Google fonts, and the design only named the picked one in CSS. A pick showed only when the theme or the Font Library already loaded that family: on Twenty Twenty-Five only Manrope did, on Twenty Twenty-Four only Inter, and on classic themes such as Storefront none did. Every other pick fell back to the system sans stack, serif fonts included.
- WordPress 6.5 added the Font Library: `wp_font_family` and `wp_font_face` posts, files in `wp_get_font_dir()`, and core's Google Fonts collection, which lists all twelve fonts under the same slugs. The Fonts screen exists for classic themes only from WordPress 7.0; the plugin supports 6.5.
- Options turned down:
  - Bundling the font files in the zip: it adds about 1–2 MB that most sites never use. The merchant asked for the Font Library instead.
  - A Google Fonts stylesheet on the checkout: every shopper's browser would call Google, which is a privacy problem in the EU.
  - Activating the installed font in the site's global styles, as the Site Editor does: it changes the merchant's whole site to serve one checkout, and classic themes before WordPress 7.0 can't activate fonts at all.
  - Uploading as soon as a font is picked in the sidebar: it downloads every font the merchant only tries.
  - Uploading all twelve fonts in the setup wizard: about 45 files, most of them never used, for one checkout.
  - Uploading only when a layout is created from a template: every template ships with no font, so a font picked in the sidebar would never upload.
  - Uploading when the storefront renders: a page view would wait on a download.
  - Showing the system font in the editor until the save: the merchant can't judge a font they can't see.

## Decision

- `Repositories\FontRepository::ensure_installed()` installs a font the way the Site Editor installs one from Google Fonts. It reads the font from core's `google-fonts` collection, downloads the normal style at weights 400–700 into `wp_get_font_dir()`, and stores a `wp_font_family` post with a `wp_font_face` post per file (`_wp_font_face_file`). It does nothing when the site's global settings already load the family, or when it is installed.
- The font is not activated. `Renderer\DesignFonts` prints its `@font-face` rules on the checkout and in the editor canvas only, through `CheckoutBaseStyles`. It prints nothing when the site loads the family itself.
- A font uploads only when a layout's design is saved. `Engine\DesignFontInstall` calls `ensure_installed()` when the design meta is added or changed, which covers every writer: the editor's Save, and `LayoutRepository` creating a layout from a template, importing, duplicating or resetting one. It also checks after every editor Save (`rest_after_insert_` for the layout post type), so a font deleted from the Font Library uploads again without a design change. Picking a font, opening the editor, the setup wizard and the storefront never upload.
- Until the save uploads it, the editor canvas previews the font from Google's own files (`GET yaycheckout/v1/fonts/{slug}`, which returns the uploaded faces once there are some). Only the merchant's browser makes that request; the storefront prints uploaded faces only.
- A failed upload never fails the save. The page falls back to a system stack that ends in the font's generic family (`LayoutDesign::SERIF_FONTS` get a serif stack), the reason is logged to WooCommerce → Status → Logs, and the sidebar says the font shows only where the theme provides it.
- Uninstalling YayCheckout leaves installed fonts in place: they belong to the site's Font Library, under Appearance → Fonts on WordPress 7.0+ and in the Site Editor on block themes.

## Consequences

- Every listed font renders on any theme and on WordPress 6.5+, served from the site itself, with no request to Google on the checkout.
- Saving a layout with a new font needs the server to reach WordPress.org (the collection, cached a day by core) and fonts.gstatic.com once, and to write to `wp-content/uploads/fonts`. A host that blocks either gets the fallback stack. That first save takes a few seconds longer.
- The editor's preview needs the merchant's browser to reach fonts.gstatic.com; the server needs the collection to know the preview's files.
- Uploaded fonts show in the merchant's Font Library. Deleting one there removes its files, and the checkout falls back until the layout is saved again.
- A font the merchant installed themselves under the same slug is reused, with whatever weights it has.
- A new font added to `FONT_FAMILIES` must exist in core's Google Fonts collection under the same slug.
