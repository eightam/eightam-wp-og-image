=== 8am OG Image ===
Contributors: eightam
Tags: open graph, og:image, social sharing, share image, twitter card
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.2.1
License: GPLv2 or later

A share image for every page, drawn from one template: the logo, the title, a bar of colour and the featured image, or the page's first picture without one. Drawn again when the title or the image changes, with the Open Graph and Twitter tags that point to it.

== Description ==

A link shared on WhatsApp, Signal, Facebook, LinkedIn, Slack or X shows the picture the page names in its `og:image` tag. Most pages name none, or a featured image cropped wherever the platform likes. This plugin draws a card for every page instead, 1200 by 630 pixels, and prints the tags.

* **One template.** The site's logo at the head, the title set as large as it fits at the foot, a kicker over it and a line under it, the address, a bar of colour along the bottom edge, and the featured image beside the type — fading in from the right, or with a clean edge. A page without a featured image shows its first picture large enough for a card; one without any picture has a mark of the site's own in the corner.
* **The site's brand, out of the box.** With Blocksy, the colours and typefaces of its Customizer; with a theme that has theme.json, those of its global styles, as the Site Editor leaves them; and the site's logo. Nothing to set up.
* **Four templates.** Spotlight, Paper, Full bleed and Brand colour, chosen in Settings → Share images, with a live preview, and any colour, typeface, logo or line of the card changed there.
* **The site's code still has the last word.** `eightam_og_image_style` and `eightam_og_image_card` see what the settings made; a site with two brands hands over two styles and picks one per page.
* **Up to date by itself.** A card is a file named after its page and a hash of everything drawn on it. A new title, another featured image, a new logo or a change of style makes a new name, so the card is drawn again and the old file deleted. A post's card is drawn in the background when it is saved; any page whose card is missing is drawn when first viewed. A post that stops being public, or is deleted, takes its cards with it.
* **Every page.** Posts and pages of every public type, archives, terms and the front page each have a card: the front page says the site's tagline, an archive its name.
* **The editor's say.** "Share image" in the editor's sidebar shows the card as it will be shared, drawn from what is in the editor, saved or not. An editor can change the words on it (a new line breaks the line on the card), choose another photograph or none, or use an image of their own in its place.
* **The tags.** `og:title`, `og:description`, `og:url`, `og:type`, `og:site_name`, `og:locale`, `og:image` with its width, height, type and alt text, the article's dates, and `twitter:card`. Where an SEO plugin prints its own, this prints none.
* **Nothing but PHP.** GD draws the cards, on the server, with no service to call and nothing to install. A card takes about a tenth of a second.

= Settings → Share images =

* **Template** — Spotlight (dark, the photograph fading in from the right), Paper (light, the photograph beside the title on a clean edge), Full bleed (the photograph across the card under a scrim, the title over it) or Brand colour (the accent as the background). Each thumbnail is drawn from the site's own brand and a real post.
* **Colours** — background, type and accent, each the theme's unless picked, with the theme's palette to pick from; the bar along the foot as the template has it, none, or colours of your own.
* **Typefaces** — the title's and the other words', each a family the theme has or any on Google Fonts, at any weight.
* **Logo** — the site's, none, or another image; on a tab of the theme's light colour on a dark card, always or never. Without a logo the site's name is set in its place. A mark — the site icon or another image — for a card without a photograph.
* **What a card says** — over the title the category or the kind of post; under it the date, the author, both or the excerpt; at the foot the address or the site's name. Whether a post without a featured image shows the first large picture in its text.
* **Advanced** — the kinds of post that have a card, whether the Open Graph tags are printed beside an SEO plugin, and whether Google Fonts may be asked.
* **Delete all share images** — every card is drawn again when its page is next needed; optionally the typefaces are fetched again too. Saving new settings already draws each card again when it is next needed: a card's file name is a hash of everything on it.

= Where the brand comes from =

* **Blocksy**: the eight palette colours of the Customizer (the first two its accents, the third its text, the fourth its headings, the sixth its light background) and the families and weights of Base Font and Heading 1.
* **A theme with theme.json** (Twenty Twenty-Five and every block theme): the body's background and text, the headings' colour, the link's or the button's colour as the accent, the palette, and the font families of the body and the headings with the files of their faces.
* **Any other theme**: the logo, and a neutral set of colours to change in the settings.

`eightam_og_image_brand` changes what was read.

= Typefaces =

GD draws type from one TrueType or OpenType file, at a variable font's default weight only, and kerns only from a legacy `kern` table. So a family is looked for, in order: in the theme's own files, if one is TrueType, OpenType or WOFF, static, and of the weight wanted; on Google Fonts, which serves a static cut of any weight of its families, fetched once by the server and kept in `uploads/eightam-og-image/fonts/` (readers' browsers never ask Google for anything); then Plus Jakarta Sans, which the plugin carries; then the server's own. Each file is given a `kern` table written from its GPOS kerning, in PHP, once.

= What a card says =

`eightam_og_image_card` gets the card and what it is the card of — a `WP_Post`, a `WP_Post_Type`, a `WP_Term`, or null for the site — and returns it:

    add_filter( 'eightam_og_image_card', function ( array $card, $object ): array {
        if ( $object instanceof WP_Post && 'event' === $object->post_type ) {
            $card['kicker']   = 'Event';
            $card['subtitle'] = get_post_meta( $object->ID, 'date', true ) . ' · ' . get_post_meta( $object->ID, 'venue', true );
        }
        return $card;
    }, 10, 2 );

* `title` — the post's title; the site's tagline on the front page; an archive's or a term's name.
* `kicker` — the small line over the title, set in capitals. Empty by default.
* `subtitle` — the line under it. Parts separated by " · " are left out from the end until it fits.
* `meta` — the address at the foot: the host the page is on.
* `photo` — the featured image by default. Without one, the first picture in the post's text that is at least 600 by 300 pixels, in the order a reader meets it: whatever a block keeps by attachment ID (`id`, `mediaId`, `imageId`, `ids`, `imageIds` — an image, a cover, media and text, a gallery, a synced pattern's picture), else an `<img>` of the site's own, from uploads or the theme; `eightam_og_image_first_image` changes that choice or turns it off. An attachment ID, or `array( 'id' => … )`, `array( 'file' => … )` or `array( 'url' => … )` for a file in uploads, with an optional `'focus' => array( 'x' => 0.5, 'y' => 0.3 )`, the point to crop around, as CSS `object-position` places it. `eightam_og_image_focus` gives an attachment its focus wherever it is used; a cover's or media and text's focal point is used as it is.

What an editor sets in the Share image panel wins over the filter.

= How a card looks =

`eightam_og_image_style` gets the style the settings made and returns it, merged over the plugin's defaults (see `Eightam\OgImage\Template\defaults()` for every key):

    add_filter( 'eightam_og_image_style', function ( array $style, array $card, $object ): array {
        return array_merge( $style, array(
            'background' => '#131310',
            'title'      => array( 'font' => get_theme_file_path( 'og/Serif-SemiBold.ttf' ), 'color' => '#fffdf6' ),
            'kicker'     => array( 'font' => get_theme_file_path( 'og/Sans-Bold.ttf' ), 'color' => '#ffd500' ),
            'bar'        => array( 'colors' => array( '#ffd500', '#009ee0', '#00a650' ) ),
            'logo'       => array( 'file' => get_theme_file_path( 'og/logo.png' ), 'height' => 70, 'tab' => '#f4f0e3' ),
            'mark'       => array( 'file' => get_theme_file_path( 'og/mark.png' ), 'size' => 560 ),
            'photo'      => array( 'layout' => 'fade' ),
        ) );
    }, 10, 3 );

GD reads neither SVG nor, reliably, WOFF2, so:

* **Fonts** are TrueType or OpenType files, and static: GD draws a variable font at its default instance only. It kerns only from a legacy `kern` table, not from GPOS; a font without one is set unkerned. Cut a static instance with a `kern` table from a variable font with fontTools (`fontTools.varLib.instancer`, then write the GPOS pairs into `kern`).
* **Logos and marks** are PNG files, about twice the size the card shows them.

Without a font it can read, the plugin uses Plus Jakarta Sans, and then the server's own (DejaVu Sans, Liberation Sans, FreeSans, Noto Sans; `eightam_og_image_system_fonts`). Without GD's FreeType, or without any font, no card is drawn, links show the featured image, and administrators are told on the dashboard.

= Other filters =

* `eightam_og_image_brand` — the colours, typefaces and logo read from the theme.

* `eightam_og_image_post_types` — the types that have a card. Default: every public type but attachments.
* `eightam_og_image_field_types` — the types whose editors see the Share image panel. Default: those with a card that show their meta in the REST API.
* `eightam_og_image_first_image` — the picture a post without a featured image shows: an attachment ID, `array( 'file' => … )`, or null for none.
* `eightam_og_image_draw_on_save` — whether a post's card is drawn when it is saved (default) or only when it is first viewed. Turn it off for a type imported in the thousands.
* `eightam_og_image_locale` — the locale a post's card is drawn in, for a multilingual site: a card drawn in the background speaks the post's language, not the site's. A translation plugin answers it.
* `eightam_og_image_description` — the text of `og:description`. Default: the excerpt, or the first 32 words of the post; a term's or a type's description; the tagline.
* `eightam_og_image_tags` — every tag printed, as `[attribute, name, content]`.
* `eightam_og_image_print` — whether to print the tags at all. Default: yes, unless Yoast SEO, Rank Math, SEOPress, All in One SEO or The SEO Framework is active. Jetpack's own Open Graph tags are turned off while this prints.

The words on the card an editor writes are named to 8am Translate (`eightam_translate_meta`), which translates them with the post and copies the two images.

= Where the cards are =

In `wp-content/uploads/eightam-og-image/`, as `post-12-3f9c0a1b2c4d.jpg`, `type-event-en_us-….png`, `term-40-sw-….png`: a JPEG for a card with a photograph, a PNG for one of flat colour. They are not in the media library. A card's name changes whenever what is on it changes, so a CDN may keep every file forever.

    wp eightam-og-image draw              # draw the cards that are missing or out of date
    wp eightam-og-image draw 12 40        # these posts
    wp eightam-og-image draw --type=event # a type that is drawn when first viewed
    wp eightam-og-image card 12 --file=/tmp/card.png   # try a style without storing anything
    wp eightam-og-image status            # GD, fonts, how many cards
    wp eightam-og-image flush             # delete them all; each is drawn again when needed

= Updates =

Each new version is published as a release of [github.com/eightam/eightam-wp-og-image](https://github.com/eightam/eightam-wp-og-image/releases), and WordPress offers it on the Plugins screen like an update from WordPress.org, at most six hours after it is published ("Check again" on Dashboard → Updates asks at once). Install the plugin from a release's `eightam-og-image.zip`.

== Frequently Asked Questions ==

= I changed the title and Facebook still shows the old card. =

The page names a new file, but Facebook keeps what it read the first time for about a month. Ask it to read the page again in the Sharing Debugger; LinkedIn has its Post Inspector. WhatsApp and Signal read the page again after a while by themselves.

= Why not Imagick, or a headless browser? =

GD is on every server WordPress runs on, and draws a card in a tenth of a second. A browser would draw CSS, but it is a service to keep running.

= Can a card say something other than the title? =

In the editor: "Words on the card" in the Share image panel. In code: `eightam_og_image_card`.

== Changelog ==

= 1.2.1 =
* The typeface the plugin carries, for a site whose own cannot be found, is Plus Jakarta Sans instead of Inter; the plugin is 0.56 MB lighter.

= 1.2.0 =
* Updates from GitHub: the Plugins screen offers each new release of github.com/eightam/eightam-wp-og-image, and automatic updates work when they are turned on. The header's `Update URI` keeps WordPress.org from offering another plugin of the same name.
* An update installs into the folder the plugin is in, whatever it is called.

= 1.1.0 =
* Settings → Share images: four templates (Spotlight, Paper, Full bleed, Brand colour) with thumbnails and a live preview, the colours, typefaces, logo and mark, what a card says beside its title, the kinds of post, the tags, and a button that deletes every card.
* The brand read from the theme: Blocksy's Customizer palette and typography, or theme.json's global styles; the site's logo, with its name set in its place where it has none.
* Typefaces by family name: the theme's own files, or a static cut from Google Fonts, fetched once by the server, with a `kern` table written from GPOS in PHP; Inter bundled as the fallback.
* A new photograph layout, `cover`: the photograph across the whole card under a scrim.
* A logo in SVG is drawn to a PNG with Imagick where the server can.

= 1.0.1 =
* Hooks and asset URLs are built from the plugin's folder under the plugins directory rather than from `__FILE__`, which a deploy that symlinks that folder to a release directory resolves into the release: its URLs named the release, and its activation hooks never fired.

= 1.0.0 =
* A card for every page from one template: logo, title, kicker, the line under it, the address, a bar of colour and the featured image, fading in or with a clean edge; without a featured image the first picture on the page, and a mark without any.
* Drawn when a post is saved and when a page without one is first viewed; drawn again when anything on it changes, with the old file deleted; deleted with the post or when it stops being public.
* The Open Graph and Twitter tags, unless an SEO plugin prints its own.
* The Share image panel: a live preview, the words on the card, its photograph, or an image of the post's own instead.
* `wp eightam-og-image draw|card|status|flush`.
