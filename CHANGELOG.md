# Changelog

## 1.4.1

* A password-protected post's excerpt is left off its card for every visitor. Before, a visitor who had entered the password could have the card drawn during their visit with the excerpt under the title, in a public file.

## 1.4.0

* Four bento templates — Bento, Mosaic, Magazine and Banner: the card divided into tiles with rounded corners, showing the title, the photograph, the logo and one or two facts.
* Facts on the tiles: the date, the author, the category, the kind of post, the reading time, the site's name, the address, or a custom field read with ACF, each with a label of its own if wanted. A fact a page lacks is replaced by the address.
* A Tiles section in Settings → Share images, shown for a bento template, with a plan of its grid: a dark, light or brand-colour tone, the title's tile as a gradient, flat or like the others, and sliders for the space between the tiles and their corner radius.
* A tile with nothing to show has the mark, or a pattern of squares picked from the title, in its place.
* The template picker groups the classic and the bento templates. The classic templates draw exactly as before.

## 1.3.0

* Variants: a page and the pages under it, or a kind of post, drawn with a template, logo, background or accent of its own.
* With Blocksy Pro, a page with a header of its own shows that header's logo on its card.
* SVG logos are drawn by the administrator's browser on the settings page, exactly as the site shows them, instead of by Imagick; logos are larger and come in three sizes.
* Fonts uploaded to Blocksy Pro's Custom Fonts are used, WOFF2 included: the browser unpacks a WOFF2 file once with Google's decoder, and the server kerns it for GD.
* SEOPress: its og:image and twitter:image show the card, with the large Twitter card.
* Blocksy palette colours with transparency are laid over white, so a light template is light again.

## 1.2.1

* The typeface the plugin carries, for a site whose own cannot be found, is Plus Jakarta Sans instead of Inter; the plugin is 0.56 MB lighter.

## 1.2.0

* Updates from GitHub: the Plugins screen offers each new release of github.com/eightam/eightam-wp-og-image, and automatic updates work when they are turned on. The header's `Update URI` keeps WordPress.org from offering another plugin of the same name.
* An update installs into the folder the plugin is in, whatever it is called.

## 1.1.0

* Settings → Share images: four templates (Spotlight, Paper, Full bleed, Brand colour) with thumbnails and a live preview, the colours, typefaces, logo and mark, what a card says beside its title, the kinds of post, the tags, and a button that deletes every card.
* The brand read from the theme: Blocksy's Customizer palette and typography, or theme.json's global styles; the site's logo, with its name set in its place where it has none.
* Typefaces by family name: the theme's own files, or a static cut from Google Fonts, fetched once by the server, with a `kern` table written from GPOS in PHP; Inter bundled as the fallback.
* A new photograph layout, `cover`: the photograph across the whole card under a scrim.
* A logo in SVG is drawn to a PNG with Imagick where the server can.

## 1.0.1

* Hooks and asset URLs are built from the plugin's folder under the plugins directory rather than from `__FILE__`, which a deploy that symlinks that folder to a release directory resolves into the release: its URLs named the release, and its activation hooks never fired.

## 1.0.0

* A card for every page from one template: logo, title, kicker, the line under it, the address, a bar of colour and the featured image, fading in or with a clean edge; without a featured image the first picture on the page, and a mark without any.
* Drawn when a post is saved and when a page without one is first viewed; drawn again when anything on it changes, with the old file deleted; deleted with the post or when it stops being public.
* The Open Graph and Twitter tags, unless an SEO plugin prints its own.
* The Share image panel: a live preview, the words on the card, its photograph, or an image of the post's own instead.
* `wp eightam-og-image draw|card|status|flush`.
