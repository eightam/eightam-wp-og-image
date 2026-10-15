<?php
/**
 * Plugin Name:       8am OG Image
 * Plugin URI:        https://github.com/eightam/eightam-wp-og-image
 * Description:       A share image for every page, drawn from one template — the logo, the title, a bar of colour and the featured image — and drawn again when the title or the image changes, with the Open Graph and Twitter tags that point to it. A post can have an image of its own instead.
 * Version:           1.3.0
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            8am
 * Author URI:        https://8am.ch
 * License:           GPL-2.0-or-later
 * Text Domain:       eightam-og-image
 * Update URI:        https://github.com/eightam/eightam-wp-og-image
 *
 * A link shared on WhatsApp, Facebook, LinkedIn, Signal or X shows the image
 * a page names in its `og:image` tag. This plugin draws that image for every
 * page from one template (includes/template.php), 1200 by 630 pixels, with
 * GD: the site's logo, the title, a bar of colour and the featured image. How
 * it looks is chosen in Settings → Share images — one of four templates, in
 * the colours and typefaces read from the theme (Blocksy's Customizer or
 * theme.json) unless others are picked — and a site's code can change it
 * through `eightam_og_image_style`; what it says, through
 * `eightam_og_image_card`.
 *
 * A card is a file in uploads/eightam-og-image/, named after the page and a
 * hash of everything drawn on it. A new title, another featured image or a
 * change of style makes a new name, so the card is drawn again, and the old
 * file is deleted. A post is drawn in the background when it is saved; any
 * page whose card is missing is drawn when it is first viewed.
 *
 * In the editor's sidebar, "Share image" shows the card as it stands, and
 * lets an editor change the words on it, choose another photograph or none,
 * or use an image of their own instead of the card.
 *
 * @package EightamOgImage
 */

declare( strict_types=1 );

namespace Eightam\OgImage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VERSION = '1.3.0';

/*
 * The plugin's main file as WordPress addresses it: in its folder under the
 * plugins directory, not where __FILE__ resolves to. A deploy that makes that
 * folder a symlink to a release directory (`.deploy8/…`) leaves __FILE__
 * pointing into the release, so URLs built from it name the release and hooks
 * named after it never fire. Installed under another name (or with another
 * copy left in that folder), the plugin falls back to __FILE__.
 */
define( __NAMESPACE__ . '\\FILE', realpath( WP_PLUGIN_DIR . '/eightam-og-image/eightam-og-image.php' ) === realpath( __FILE__ ) ? WP_PLUGIN_DIR . '/eightam-og-image/eightam-og-image.php' : __FILE__ );

// The folder in uploads the cards are kept in.
const FOLDER = 'eightam-og-image';

// Post meta an editor sets in the Share image panel.
const META_IMAGE = '_eightam_og_image'; // An image of the post's own, used as it is.
const META_TEXT  = '_eightam_og_text';  // The words on the card, in place of the title.
const META_PHOTO = '_eightam_og_photo'; // The card's photograph: 0 the featured image, -1 none, or an attachment.

// Cron: draw one post's card.
const HOOK = 'eightam_og_image_draw';

require_once __DIR__ . '/includes/canvas.php';
require_once __DIR__ . '/includes/sfnt.php';
require_once __DIR__ . '/includes/template.php';
require_once __DIR__ . '/includes/brand.php';
require_once __DIR__ . '/includes/fonts.php';
require_once __DIR__ . '/includes/templates.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/card.php';
require_once __DIR__ . '/includes/store.php';
require_once __DIR__ . '/includes/post.php';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/seo.php';
require_once __DIR__ . '/includes/rest.php';
require_once __DIR__ . '/includes/updater.php';

if ( is_admin() ) {
	require_once __DIR__ . '/includes/admin.php';
	require_once __DIR__ . '/includes/settings-page.php';
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/includes/cli.php';
}

register_deactivation_hook( FILE, __NAMESPACE__ . '\\deactivate' );
/**
 * Stop the drawing that is waiting. The cards stay: they are files the pages
 * that shared them still point to.
 */
function deactivate(): void {
	wp_unschedule_hook( HOOK );
}
