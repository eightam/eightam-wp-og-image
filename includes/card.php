<?php
/**
 * What a card says, and the style it is drawn in.
 *
 * A card is an array: `title`, `kicker` (the small line over the title),
 * `subtitle` (the line under it), `meta` (the address at the foot) and
 * `photo`. This file makes one for a post and for any other screen, hands it
 * to the site through `eightam_og_image_card`, and then applies what the
 * post's editor chose in the Share image panel, which wins over the site.
 *
 * The photograph can be given as an attachment ID, as `array( 'id' => … )`,
 * as a file (`array( 'file' => … )`) or as an address in uploads
 * (`array( 'url' => … )`); it leaves this file as a file on disk and a focus.
 *
 * @package EightamOgImage
 */

declare( strict_types=1 );

namespace Eightam\OgImage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The kinds of post that have a card: every public type but attachments.
 *
 * @return string[]
 */
function post_types(): array {
	$types = array_values( array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment' ) ) );

	/**
	 * The post types that have a card.
	 *
	 * @param string[] $types Post types.
	 */
	return array_values( array_unique( array_map( 'strval', (array) apply_filters( 'eightam_og_image_post_types', $types ) ) ) );
}

/**
 * The kinds of post an editor can change the card of: those with a card whose
 * meta the REST API shows, which the editor's panel writes through.
 *
 * @return string[]
 */
function field_types(): array {
	$types = array_filter(
		post_types(),
		static function ( string $type ): bool {
			$type_object = get_post_type_object( $type );
			return $type_object && $type_object->show_in_rest && post_type_supports( $type, 'custom-fields' );
		}
	);

	/**
	 * The post types whose editors see the Share image panel.
	 *
	 * @param string[] $types Post types.
	 */
	return array_values( array_map( 'strval', (array) apply_filters( 'eightam_og_image_field_types', array_values( $types ) ) ) );
}

/**
 * What a post's card says.
 *
 * @param \WP_Post            $post    Post.
 * @param array<string,mixed> $unsaved What the editor has changed and not
 *                                     saved yet, for the preview: title,
 *                                     text, photo, featured, and first, the
 *                                     first picture in the text as it
 *                                     stands (an attachment ID, a file, or
 *                                     null for none).
 * @return array<string,mixed>
 */
function post_card( \WP_Post $post, array $unsaved = array() ): array {
	$title    = (string) ( $unsaved['title'] ?? $post->post_title );
	$featured = (int) ( $unsaved['featured'] ?? get_post_thumbnail_id( $post ) );
	$front    = 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === $post->ID;

	// Without a featured image, the first picture in the text large enough
	// for a card stands in.
	$photo = $featured > 0 ? $featured : null;
	if ( null === $photo ) {
		$photo = array_key_exists( 'first', $unsaved ) ? $unsaved['first'] : first_image( $post );
	}

	$card = array(
		// The front page is called Home, which says nothing on a card: it
		// says what the site says about itself.
		'title'    => words( $front ? site_line() : $title ),
		'kicker'   => '',
		'subtitle' => '',
		'meta'     => host( (string) get_permalink( $post ) ),
		'photo'    => $photo,
	);

	/**
	 * What a card says: title, kicker, subtitle, meta and photo.
	 *
	 * @param array                                $card    The card.
	 * @param \WP_Post|\WP_Post_Type|\WP_Term|null $subject What it is the card of.
	 */
	$card = (array) apply_filters( 'eightam_og_image_card', $card, $post );

	// What the editor chose wins over what the site says.
	$text  = trim( (string) ( $unsaved['text'] ?? get_post_meta( $post->ID, META_TEXT, true ) ) );
	$photo = (int) ( $unsaved['photo'] ?? get_post_meta( $post->ID, META_PHOTO, true ) );
	if ( '' !== $text ) {
		$card['title'] = words( $text, true );
	}
	if ( -1 === $photo ) {
		$card['photo'] = null;
	} elseif ( $photo > 0 ) {
		$card['photo'] = $photo;
	}

	return tidy( $card );
}

/**
 * The first picture in a post's text that is large enough for a card: what
 * a post without a featured image shows on its card.
 *
 * @param \WP_Post $post Post.
 * @return int|array{file:string}|null An attachment ID, a file, or null.
 */
function first_image( \WP_Post $post ) {
	// A protected post's pictures stay behind its password: the card is the
	// same for every reader, whoever has typed it.
	$found = '' !== $post->post_password ? null : content_image( $post->post_content );

	/**
	 * The picture a post without a featured image shows on its card: an
	 * attachment ID, array( 'file' => … ), or null for none.
	 *
	 * @param int|array|null $found The first picture in the text.
	 * @param \WP_Post       $post  The post.
	 */
	return apply_filters( 'eightam_og_image_first_image', $found, $post );
}

/**
 * The first picture in some content, in the order a reader meets it: a block
 * that keeps a picture by attachment ID — an image, a cover, media and text,
 * a gallery, a hero, a story's step, a synced pattern — or an <img> of this
 * site's, in a block's HTML or in classic content. A picture smaller than
 * 600 by 300 pixels is passed over: an icon or a thumbnail would be a blur on
 * a card.
 *
 * @param string $content Content.
 * @param int    $depth   How many synced patterns deep this is.
 * @return int|array{file:string,focus?:array}|null
 */
function content_image( string $content, int $depth = 0 ) {
	if ( ! str_contains( $content, '<img' ) && ! preg_match( '/"(id|mediaId|imageId|ids|imageIds|ref)"/', $content ) ) {
		return null;
	}
	foreach ( parse_blocks( $content ) as $block ) {
		$found = block_image( $block, $depth );
		if ( null !== $found ) {
			return $found;
		}
	}
	return null;
}

/**
 * The first picture in a block, its own before those of the blocks inside it.
 *
 * @param array<string,mixed> $block A parsed block.
 * @param int                 $depth How many synced patterns deep this is.
 * @return int|array{file:string,focus?:array}|null
 */
function block_image( array $block, int $depth ) {
	$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();

	if ( 'core/block' === ( $block['blockName'] ?? '' ) ) {
		$pattern = $depth < 2 && ! empty( $attrs['ref'] ) ? get_post( (int) $attrs['ref'] ) : null;
		return $pattern instanceof \WP_Post && 'publish' === $pattern->post_status ? content_image( $pattern->post_content, $depth + 1 ) : null;
	}

	$ids = array();
	foreach ( array( 'id', 'mediaId', 'imageId' ) as $key ) {
		$ids[] = $attrs[ $key ] ?? 0;
	}
	foreach ( array( 'ids', 'imageIds' ) as $key ) {
		$ids = array_merge( $ids, is_array( $attrs[ $key ] ?? null ) ? $attrs[ $key ] : array() );
	}
	foreach ( $ids as $id ) {
		if ( is_numeric( $id ) && large_enough( (int) $id ) ) {
			$focus = $attrs['focalPoint'] ?? null;
			return is_array( $focus ) && isset( $focus['x'], $focus['y'] ) ? array(
				'id'    => (int) $id,
				'focus' => array(
					'x' => (float) $focus['x'],
					'y' => (float) $focus['y'],
				),
			) : (int) $id;
		}
	}

	$found = html_image( (string) ( $block['innerHTML'] ?? '' ) );
	if ( null !== $found ) {
		return $found;
	}

	foreach ( (array) ( $block['innerBlocks'] ?? array() ) as $inner ) {
		$found = is_array( $inner ) ? block_image( $inner, $depth ) : null;
		if ( null !== $found ) {
			return $found;
		}
	}
	return null;
}

/**
 * The first picture of this site's in some HTML: by the attachment its
 * `wp-image-` class names, else by the file its address is.
 *
 * @param string $html HTML.
 * @return int|array{file:string}|null
 */
function html_image( string $html ) {
	if ( ! str_contains( $html, '<img' ) || ! preg_match_all( '/<img\b[^>]*>/i', $html, $tags ) ) {
		return null;
	}
	foreach ( $tags[0] as $tag ) {
		if ( preg_match( '/\bwp-image-(\d+)\b/', $tag, $match ) && large_enough( (int) $match[1] ) ) {
			return (int) $match[1];
		}
		if ( preg_match( '/\ssrc\s*=\s*(["\'])(.*?)\1/i', $tag, $match ) ) {
			$file = site_file( html_entity_decode( $match[2], ENT_QUOTES ) );
			if ( '' !== $file && large_enough( $file ) ) {
				return array( 'file' => $file );
			}
		}
	}
	return null;
}

/**
 * Whether a picture is large enough for a card: an image attachment, or an
 * image file, at least 600 by 300 pixels.
 *
 * @param int|string $image Attachment ID or file.
 */
function large_enough( $image ): bool {
	if ( is_int( $image ) ) {
		if ( $image < 1 || ! wp_attachment_is_image( $image ) ) {
			return false;
		}
		$meta = wp_get_attachment_metadata( $image );
		return is_array( $meta ) && (int) ( $meta['width'] ?? 0 ) >= 600 && (int) ( $meta['height'] ?? 0 ) >= 300;
	}
	$size = @getimagesize( (string) $image ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	return is_array( $size ) && $size[0] >= 600 && $size[1] >= 300;
}

/**
 * The file behind an address of this site's own pictures: in uploads, or in
 * the theme. Anything else — another site, a plugin's folder — is ''.
 *
 * @param string $url Address, absolute or from the site's root.
 */
function site_file( string $url ): string {
	$url  = \WP_Http::make_absolute_url( $url, home_url( '/' ) );
	$file = uploads_file( $url );
	if ( '' !== $file ) {
		return $file;
	}

	$url    = set_url_scheme( (string) strtok( $url, '?#' ), 'http' );
	$themes = array(
		get_stylesheet_directory_uri() => get_stylesheet_directory(),
		get_template_directory_uri()   => get_template_directory(),
	);
	foreach ( $themes as $base => $dir ) {
		$base = set_url_scheme( (string) $base, 'http' ) . '/';
		if ( ! str_starts_with( $url, $base ) ) {
			continue;
		}
		$relative = rawurldecode( substr( $url, strlen( $base ) ) );
		$file     = (string) $dir . '/' . $relative;
		return ! str_contains( $relative, '..' ) && is_readable( $file ) ? $file : '';
	}
	return '';
}

/**
 * What the card of the screen being viewed says, when it is not a post: an
 * archive, a term, or the site itself. Returns the card and its key, the name
 * its file starts with.
 *
 * @return array{0:array<string,mixed>,1:string,2:mixed}
 */
function screen_card(): array {
	$subject = get_queried_object();
	$locale  = sanitize_key( get_locale() );

	if ( $subject instanceof \WP_Post_Type ) {
		$title = (string) post_type_archive_title( '', false );
		$title = '' !== $title ? $title : (string) $subject->labels->name;
		$key   = 'type-' . $subject->name;
		$url   = (string) get_post_type_archive_link( $subject->name );
	} elseif ( $subject instanceof \WP_Term ) {
		$title = $subject->name;
		$key   = 'term-' . $subject->term_id;
		$link  = get_term_link( $subject );
		$url   = is_string( $link ) ? $link : home_url( '/' );
	} else {
		$subject = null;
		$title   = site_line();
		$key     = 'site';
		$url     = home_url( '/' );
	}

	$card = array(
		'title'    => words( $title ),
		'kicker'   => '',
		'subtitle' => '',
		'meta'     => host( $url ),
		'photo'    => null,
	);

	/** This filter is documented in includes/card.php */
	$card = (array) apply_filters( 'eightam_og_image_card', $card, $subject );

	// The same screen has a card in every language it is read in.
	return array( tidy( $card ), sanitize_file_name( $key . '-' . $locale ), $subject );
}

/**
 * A card as the template takes it: strings where it wants strings, and the
 * photograph as a file with its focus, or null.
 *
 * @param array<string,mixed> $card A card.
 * @return array<string,mixed>
 */
function tidy( array $card ): array {
	return array(
		'title'    => trim( (string) ( $card['title'] ?? '' ) ),
		'kicker'   => trim( (string) ( $card['kicker'] ?? '' ) ),
		'subtitle' => trim( (string) ( $card['subtitle'] ?? '' ) ),
		'meta'     => trim( (string) ( $card['meta'] ?? '' ) ),
		'photo'    => photo( $card['photo'] ?? null ),
	);
}

/**
 * A photograph as a file on disk and the point to crop it around.
 *
 * @param mixed $photo An attachment ID, or an array with an id, a file or an
 *                     address in uploads, and optionally a focus.
 * @return array{file:string,focus:array{x:float,y:float}}|null
 */
function photo( $photo ): ?array {
	if ( is_numeric( $photo ) ) {
		$photo = array( 'id' => (int) $photo );
	}
	if ( ! is_array( $photo ) ) {
		return null;
	}

	$id   = (int) ( $photo['id'] ?? 0 );
	$file = (string) ( $photo['file'] ?? '' );
	if ( '' === $file && $id > 0 ) {
		$file = attachment_file( $id );
	}
	if ( '' === $file && ! empty( $photo['url'] ) ) {
		$file = uploads_file( (string) $photo['url'] );
	}
	if ( '' === $file || ! is_readable( $file ) ) {
		return null;
	}

	$focus = $photo['focus'] ?? null;
	if ( ! is_array( $focus ) && $id > 0 ) {
		/**
		 * The point to crop an attachment around, as fractions from the top
		 * left: array( 'x' => 0.5, 'y' => 0.3 ). Null for the middle.
		 *
		 * @param array|null $focus         Focus.
		 * @param int        $attachment_id Attachment ID.
		 */
		$focus = apply_filters( 'eightam_og_image_focus', null, $id );
	}
	$focus = is_array( $focus ) ? $focus : array();

	return array(
		'file'  => $file,
		'focus' => array(
			'x' => max( 0.0, min( 1.0, (float) ( $focus['x'] ?? 0.5 ) ) ),
			'y' => max( 0.0, min( 1.0, (float) ( $focus['y'] ?? 0.5 ) ) ),
		),
	);
}

/**
 * The file of an attachment to draw a card from: the smallest of its sizes,
 * uncropped, that is still larger than the card needs, so a photograph is
 * scaled down and never up, and a 20-megapixel original is not read for a
 * 1200-pixel card.
 *
 * @param int $id Attachment ID.
 */
function attachment_file( int $id ): string {
	if ( ! wp_attachment_is_image( $id ) ) {
		return '';
	}
	$full = (string) get_attached_file( $id );
	$meta = wp_get_attachment_metadata( $id );
	if ( '' === $full || ! is_array( $meta ) || empty( $meta['width'] ) || empty( $meta['height'] ) ) {
		return is_readable( $full ) ? $full : '';
	}

	$ratio = (int) $meta['width'] / (int) $meta['height'];
	$best  = '';
	$area  = PHP_INT_MAX;
	foreach ( (array) ( $meta['sizes'] ?? array() ) as $size ) {
		$width  = (int) ( $size['width'] ?? 0 );
		$height = (int) ( $size['height'] ?? 0 );
		if ( $width < 1000 || $height < 630 || abs( $width / $height - $ratio ) > 0.02 ) {
			continue;
		}
		$path = dirname( $full ) . '/' . wp_basename( (string) ( $size['file'] ?? '' ) );
		if ( $width * $height < $area && is_readable( $path ) ) {
			$best = $path;
			$area = $width * $height;
		}
	}
	if ( '' !== $best ) {
		return $best;
	}
	return is_readable( $full ) ? $full : '';
}

/**
 * The file behind an address in this site's uploads, or '' for any other.
 *
 * @param string $url Address.
 */
function uploads_file( string $url ): string {
	$uploads = wp_get_upload_dir();
	$base    = set_url_scheme( (string) $uploads['baseurl'], 'http' ) . '/';
	$url     = set_url_scheme( (string) strtok( $url, '?#' ), 'http' );
	if ( ! str_starts_with( $url, $base ) ) {
		return '';
	}
	$relative = rawurldecode( substr( $url, strlen( $base ) ) );
	if ( str_contains( $relative, '..' ) ) {
		return '';
	}
	$file = (string) $uploads['basedir'] . '/' . $relative;
	return is_readable( $file ) ? $file : '';
}

/**
 * A title as it is printed, as plain text: curly quotes and dashes, tags and
 * entities gone. With `$lines`, line breaks an editor typed are kept, and the
 * card breaks there.
 *
 * @param string $text  Text.
 * @param bool   $lines Whether to keep line breaks.
 */
function words( string $text, bool $lines = false ): string {
	$parts = $lines ? preg_split( '/\R/u', $text ) : array( $text );
	$parts = array_map(
		static fn( string $part ): string => trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( wptexturize( $part ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ),
		(array) $parts
	);
	return implode( "\n", array_filter( $parts, 'strlen' ) );
}

/**
 * What the site says about itself: its tagline, or its name.
 */
function site_line(): string {
	$line = (string) get_bloginfo( 'description' );
	return '' !== trim( $line ) ? $line : (string) get_bloginfo( 'name' );
}

/**
 * The host of an address, without www: what the foot of a card shows.
 *
 * @param string $url Address.
 */
function host( string $url ): string {
	return (string) preg_replace( '/^www\./', '', (string) wp_parse_url( $url, PHP_URL_HOST ) );
}

/**
 * The style a card is drawn in, from the site, over the template's defaults.
 *
 * A font the style names that cannot be read is replaced by the title's, and
 * the title's by one of the server's; a logo, a mark or a photograph that
 * cannot be read is left out. So a card can always be drawn where GD can
 * draw type at all.
 *
 * @param array<string,mixed> $card    The card.
 * @param mixed               $subject What it is the card of.
 * @return array<string,mixed>
 */
function style( array $card, $subject ): array {
	/**
	 * The style of a card: colours, typefaces, the logo, the bar and the
	 * mark. See Eightam\OgImage\Template\defaults() for every key. It
	 * starts as the template chosen in Settings → Share images, drawn in the
	 * brand read from the theme.
	 *
	 * @param array                                $style   The style so far.
	 * @param array                                $card    The card.
	 * @param \WP_Post|\WP_Post_Type|\WP_Term|null $subject What it is the card of.
	 */
	$style = Template\style( (array) apply_filters( 'eightam_og_image_style', Templates\style( settings_for( $subject ), Brand\get(), $subject ), $card, $subject ) );

	$title = (string) $style['title']['font'];
	if ( ! is_readable( $title ) ) {
		$title = Fonts\bundled( 700 )['file'] ?: system_font();
	}
	foreach ( array( 'title', 'kicker', 'subtitle', 'meta' ) as $role ) {
		$font                   = (string) $style[ $role ]['font'];
		$style[ $role ]['font'] = is_readable( $font ) ? $font : $title;
	}
	if ( ! is_readable( (string) $style['logo']['font'] ) ) {
		$style['logo']['font'] = $title;
	}
	return $style;
}

/**
 * A TrueType font the server has, for a site that names none: the ones most
 * Linux servers carry, since GD's own packages bring them.
 */
function system_font(): string {
	$fonts = array(
		'/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
		'/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
		'/usr/share/fonts/TTF/DejaVuSans-Bold.ttf',
		'/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
		'/usr/share/fonts/liberation/LiberationSans-Bold.ttf',
		'/usr/share/fonts/truetype/freefont/FreeSansBold.ttf',
		'/usr/share/fonts/noto/NotoSans-Bold.ttf',
		'/usr/share/fonts/truetype/noto/NotoSans-Bold.ttf',
	);

	/**
	 * Fonts to try when a site's style names none that can be read.
	 *
	 * @param string[] $fonts Paths.
	 */
	foreach ( (array) apply_filters( 'eightam_og_image_system_fonts', $fonts ) as $font ) {
		if ( is_string( $font ) && is_readable( $font ) ) {
			return $font;
		}
	}
	return '';
}

/**
 * Whether cards can be drawn here: GD with FreeType, and a font.
 *
 * @param array<string,mixed>|null $style A style, or null for the site's.
 */
function can_draw( ?array $style = null ): bool {
	if ( ! Canvas\available() ) {
		return false;
	}
	$style = $style ?? style( tidy( array() ), null );
	return '' !== (string) $style['title']['font'] && is_readable( (string) $style['title']['font'] );
}

/**
 * The locale a post's card is drawn in: its language, where a plugin says
 * what that is. A card drawn in the background after a save speaks the
 * language of the post, not the site's.
 *
 * @param \WP_Post $post Post.
 */
function locale_of( \WP_Post $post ): string {
	/**
	 * The locale of a post, such as sw_TZ, or '' for the site's.
	 *
	 * @param string   $locale Locale.
	 * @param \WP_Post $post   Post.
	 */
	return (string) apply_filters( 'eightam_og_image_locale', '', $post );
}

/**
 * Run something in a locale, and come back.
 *
 * @param string   $locale   Locale, or '' for the one in force.
 * @param callable $callback What to run.
 * @return mixed What it returned.
 */
function in_locale( string $locale, callable $callback ) {
	$switched = '' !== $locale && determine_locale() !== $locale && switch_to_locale( $locale );
	try {
		return $callback();
	} finally {
		if ( $switched ) {
			restore_previous_locale();
		}
	}
}
