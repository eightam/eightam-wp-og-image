<?php
/**
 * Settings → Share images: the template, the colours, the typefaces, the
 * logo, what a card says, and a preview drawn from the form as it stands.
 * And the button that deletes every card, so each is drawn again.
 *
 * The preview and the template thumbnails are drawn by the REST route in
 * includes/rest.php (`/sample`), from the settings in the form, saved or
 * not; nothing is stored.
 *
 * @package EightamOgImage
 */

declare( strict_types=1 );

namespace Eightam\OgImage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PAGE = 'eightam-og-image';

add_action( 'admin_menu', __NAMESPACE__ . '\\menu' );
/**
 * The page, under Settings.
 */
function menu(): void {
	add_options_page(
		__( 'Share images', 'eightam-og-image' ),
		__( 'Share images', 'eightam-og-image' ),
		'manage_options',
		PAGE,
		__NAMESPACE__ . '\\page'
	);
}

add_filter( 'plugin_action_links_' . plugin_basename( FILE ), __NAMESPACE__ . '\\action_links' );
/**
 * A Settings link on the Plugins screen.
 *
 * @param string[] $links Links.
 * @return string[]
 */
function action_links( $links ): array {
	array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'options-general.php?page=' . PAGE ) ), esc_html__( 'Settings', 'eightam-og-image' ) ) );
	return (array) $links;
}

add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\page_assets' );
/**
 * The page's script and styles: the media library, the colour picker, and a
 * little plain JavaScript.
 *
 * @param string $hook The screen.
 */
function page_assets( $hook ): void {
	if ( 'settings_page_' . PAGE !== $hook ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_style( 'eightam-og-image-settings', plugins_url( 'assets/settings.css', FILE ), array(), VERSION );
	wp_enqueue_script( 'eightam-og-image-settings', plugins_url( 'assets/settings.js', FILE ), array( 'jquery', 'wp-color-picker' ), VERSION, true );
	wp_add_inline_script(
		'eightam-og-image-settings',
		'window.eightamOgImageSettings = ' . wp_json_encode(
			array(
				'sample'  => rest_url( ROUTE . '/sample' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'option'  => OPTION,
				'convert' => rest_url( ROUTE . '/convert' ),
				'woff2'   => plugins_url( 'assets/vendor/woff2/decompress.js', FILE ),
				'jobs'    => jobs(),
				'palette' => array_slice( array_keys( (array) Brand\get()['palette'] ), 0, 10 ),
				'choose'  => __( 'Choose an image', 'eightam-og-image' ),
				'use'     => __( 'Use this image', 'eightam-og-image' ),
				'working' => __( 'Preparing the logos and typefaces this site uses…', 'eightam-og-image' ),
			)
		) . ';',
		'before'
	);
}

/**
 * The page.
 */
function page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s       = settings();
	$brand   = Brand\get();
	$fonts   = Templates\fonts( $s, $brand );
	$name    = static fn( string $key ): string => OPTION . '[' . $key . ']';
	$samples = sample_posts();
	$flushed = isset( $_GET['flushed'] ) ? (int) $_GET['flushed'] : -1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a count to show.

	$source = match ( $brand['source'] ) {
		/* translators: %s: theme name. */
		'blocksy'    => __( 'Colours and typefaces come from Blocksy’s Customizer.', 'eightam-og-image' ),
		/* translators: %s: theme name. */
		'theme.json' => sprintf( __( 'Colours and typefaces come from the global styles of %s.', 'eightam-og-image' ), $brand['label'] ),
		/* translators: %s: theme name. */
		default      => sprintf( __( '%s keeps no colours or typefaces this plugin can read: pick them below.', 'eightam-og-image' ), $brand['label'] ),
	};
	$sources = array(
		'theme'   => __( 'from the theme', 'eightam-og-image' ),
		'google'  => __( 'from Google Fonts', 'eightam-og-image' ),
		'bundled' => __( 'Plus Jakarta Sans, the plugin’s own', 'eightam-og-image' ),
		''        => __( 'the server’s', 'eightam-og-image' ),
	);
	$weights = array(
		0   => __( 'The theme’s', 'eightam-og-image' ),
		300 => '300',
		400 => '400',
		500 => '500',
		600 => '600',
		700 => '700',
		800 => '800',
		900 => '900',
	);
	?>
	<div class="wrap eightam-og">
		<h1><?php esc_html_e( 'Share images', 'eightam-og-image' ); ?></h1>
		<p class="eightam-og__intro"><?php esc_html_e( 'The picture a link to this site shows on WhatsApp, Facebook, LinkedIn, Slack or X. Every page has one, drawn from the template below and drawn again when its title or image changes.', 'eightam-og-image' ); ?></p>

		<?php if ( $flushed >= 0 ) : ?>
			<div class="notice notice-success is-dismissible"><p>
				<?php
				/* translators: %d: number of images deleted. */
				echo esc_html( sprintf( _n( 'Deleted %d share image. Each is drawn again when its page is next shared or viewed.', 'Deleted %d share images. Each is drawn again when its page is next shared or viewed.', $flushed, 'eightam-og-image' ), $flushed ) );
				?>
			</p></div>
		<?php endif; ?>
		<?php if ( ! can_draw() ) : ?>
			<div class="notice notice-warning"><p><?php esc_html_e( 'This server cannot draw share images: PHP’s GD extension with FreeType is missing. Links show the featured image instead.', 'eightam-og-image' ); ?></p></div>
		<?php endif; ?>

		<div class="eightam-og__layout">
			<form method="post" action="options.php" class="eightam-og__form" id="eightam-og-form">
				<?php settings_fields( 'eightam_og_image' ); ?>

				<h2><?php esc_html_e( 'Template', 'eightam-og-image' ); ?></h2>
				<?php
				foreach (
					array(
						'classic' => __( 'Classic: the title over the whole card', 'eightam-og-image' ),
						'bento'   => __( 'Bento: the card in tiles', 'eightam-og-image' ),
					) as $kind => $heading
				) :
					?>
					<h3 class="eightam-og__kind"><?php echo esc_html( $heading ); ?></h3>
					<div class="eightam-og__templates">
						<?php foreach ( Templates\all() as $slug => $template ) : ?>
							<?php
							if ( $kind !== $template['kind'] ) {
								continue;
							}
							?>
							<label class="eightam-og__template">
								<input type="radio" name="<?php echo esc_attr( $name( 'template' ) ); ?>" value="<?php echo esc_attr( $slug ); ?>" data-kind="<?php echo esc_attr( $kind ); ?>" <?php checked( $s['template'], $slug ); ?> />
								<img src="" data-template="<?php echo esc_attr( $slug ); ?>" alt="" width="600" height="315" loading="lazy" />
								<strong><?php echo esc_html( $template['label'] ); ?></strong>
								<span><?php echo esc_html( $template['description'] ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				<?php endforeach; ?>

				<div class="eightam-og__tiles" id="eightam-og-tiles" <?php echo Templates\is_bento( (string) $s['template'] ) ? '' : 'hidden'; ?>>
					<h2><?php esc_html_e( 'Tiles', 'eightam-og-image' ); ?></h2>
					<p class="description"><?php esc_html_e( 'What the bento templates show on their tiles. A tile with nothing to show — a page without a photograph, or a fact a page lacks — shows the mark or a pattern of squares instead.', 'eightam-og-image' ); ?></p>
					<div class="eightam-og__tiles-layout">
						<div class="eightam-og__schemes" aria-hidden="true">
							<?php tile_schemes( (string) $s['template'], $s ); ?>
						</div>
						<table class="form-table" role="presentation">
							<?php
							choice_row(
								$name( 'tone' ),
								(string) $s['tone'],
								__( 'Tone', 'eightam-og-image' ),
								array(
									'dark'  => __( 'Dark', 'eightam-og-image' ),
									'light' => __( 'Light', 'eightam-og-image' ),
									'brand' => __( 'The accent colour', 'eightam-og-image' ),
								)
							);
							choice_row(
								$name( 'head_fill' ),
								(string) $s['head_fill'],
								__( 'Title tile', 'eightam-og-image' ),
								array(
									'gradient' => __( 'The accent, as a gradient', 'eightam-og-image' ),
									'accent'   => __( 'The accent, flat', 'eightam-og-image' ),
									'tile'     => __( 'Like the other tiles', 'eightam-og-image' ),
								)
							);
							foreach (
								array(
									'fact1' => __( 'Fact 1', 'eightam-og-image' ),
									'fact2' => __( 'Fact 2', 'eightam-og-image' ),
								) as $slot => $label
							) :
								$id = 'eightam-og-' . $slot;
								?>
								<tr class="eightam-og__fact-row" data-slot="<?php echo esc_attr( $slot ); ?>">
									<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
									<td class="eightam-og__fact">
										<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name( $slot ) ); ?>" data-slot="<?php echo esc_attr( $slot ); ?>">
											<?php foreach ( fact_kinds() as $kind => $text ) : ?>
												<option value="<?php echo esc_attr( $kind ); ?>" data-short="<?php echo esc_attr( fact_short_names()[ $kind ] ?? $text ); ?>" <?php selected( $s[ $slot ], $kind ); ?>><?php echo esc_html( $text ); ?></option>
											<?php endforeach; ?>
										</select>
										<span class="eightam-og__field" <?php echo 'field' === $s[ $slot ] ? '' : 'hidden'; ?>>
											<input type="text" class="code" name="<?php echo esc_attr( $name( $slot . '_field' ) ); ?>" value="<?php echo esc_attr( (string) $s[ $slot . '_field' ] ); ?>" placeholder="<?php esc_attr_e( 'field_name', 'eightam-og-image' ); ?>" aria-label="<?php esc_attr_e( 'Field name', 'eightam-og-image' ); ?>" />
										</span>
										<input type="text" name="<?php echo esc_attr( $name( $slot . '_label' ) ); ?>" value="<?php echo esc_attr( (string) $s[ $slot . '_label' ] ); ?>" placeholder="<?php esc_attr_e( 'Its own label', 'eightam-og-image' ); ?>" aria-label="<?php esc_attr_e( 'Label', 'eightam-og-image' ); ?>" />
									</td>
								</tr>
							<?php endforeach; ?>
							<?php
							foreach (
								array(
									'gap'    => array( __( 'Space between tiles', 'eightam-og-image' ), 40 ),
									'radius' => array( __( 'Corner radius', 'eightam-og-image' ), 48 ),
								) as $key => [ $label, $max ]
							) :
								?>
								<tr>
									<th scope="row"><label for="eightam-og-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
									<td>
										<input type="range" id="eightam-og-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $name( $key ) ); ?>" min="0" max="<?php echo esc_attr( (string) $max ); ?>" step="2" value="<?php echo esc_attr( (string) $s[ $key ] ); ?>" />
										<output for="eightam-og-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( (string) $s[ $key ] ); ?></output> px
									</td>
								</tr>
							<?php endforeach; ?>
						</table>
					</div>
					<p class="description"><?php esc_html_e( 'A custom field is read with ACF where ACF is active, so a select, a relationship or a date shows as ACF formats it; otherwise as the post’s meta. Its label is the field’s own unless you give one.', 'eightam-og-image' ); ?></p>
				</div>

				<h2><?php esc_html_e( 'Colours', 'eightam-og-image' ); ?></h2>
				<p class="description"><?php echo esc_html( $source . ' ' . __( 'Leave a colour empty to use the theme’s.', 'eightam-og-image' ) ); ?></p>
				<table class="form-table" role="presentation">
					<?php
					foreach (
						array(
							'background' => __( 'Background', 'eightam-og-image' ),
							'text'       => __( 'Type', 'eightam-og-image' ),
							'accent'     => __( 'Accent', 'eightam-og-image' ),
						) as $key => $label
					) :
						?>
						<tr>
							<th scope="row"><label for="eightam-og-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
							<td><input type="text" class="eightam-og__color" id="eightam-og-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $name( $key ) ); ?>" value="<?php echo esc_attr( (string) $s[ $key ] ); ?>" /></td>
						</tr>
					<?php endforeach; ?>
					<tr>
						<th scope="row"><label for="eightam-og-bar"><?php esc_html_e( 'Bar along the foot', 'eightam-og-image' ); ?></label></th>
						<td>
							<select id="eightam-og-bar" name="<?php echo esc_attr( $name( 'bar' ) ); ?>">
								<option value="auto" <?php selected( $s['bar'], 'auto' ); ?>><?php esc_html_e( 'As the template has it', 'eightam-og-image' ); ?></option>
								<option value="none" <?php selected( $s['bar'], 'none' ); ?>><?php esc_html_e( 'None', 'eightam-og-image' ); ?></option>
								<option value="custom" <?php selected( $s['bar'], 'custom' ); ?>><?php esc_html_e( 'These colours', 'eightam-og-image' ); ?></option>
							</select>
							<input type="text" class="regular-text code" name="<?php echo esc_attr( $name( 'bar_colors' ) ); ?>" value="<?php echo esc_attr( implode( ', ', (array) $s['bar_colors'] ) ); ?>" placeholder="#ffd500, #009ee0, #00a650" />
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Typefaces', 'eightam-og-image' ); ?></h2>
				<p class="description"><?php esc_html_e( 'A family the theme has, or any family on Google Fonts, fetched once by this server and kept in uploads. Leave it empty to use the theme’s.', 'eightam-og-image' ); ?></p>
				<datalist id="eightam-og-families">
					<?php foreach ( array_unique( array_merge( array_keys( (array) $brand['families'] ), array( 'Plus Jakarta Sans' ) ) ) as $family ) : ?>
						<option value="<?php echo esc_attr( (string) $family ); ?>"></option>
					<?php endforeach; ?>
				</datalist>
				<table class="form-table" role="presentation">
					<?php
					foreach (
						array(
							'title' => array( __( 'Title', 'eightam-og-image' ), (array) $brand['fonts']['heading'] ),
							'text'  => array( __( 'Other words', 'eightam-og-image' ), (array) $brand['fonts']['body'] ),
						) as $role => [ $label, $theme ]
					) :
						?>
						<tr>
							<th scope="row"><label for="eightam-og-<?php echo esc_attr( $role ); ?>-font"><?php echo esc_html( $label ); ?></label></th>
							<td>
								<input type="text" list="eightam-og-families" class="regular-text" id="eightam-og-<?php echo esc_attr( $role ); ?>-font" name="<?php echo esc_attr( $name( $role . '_font' ) ); ?>" value="<?php echo esc_attr( (string) $s[ $role . '_font' ] ); ?>" placeholder="<?php echo esc_attr( '' !== (string) $theme['family'] ? (string) $theme['family'] : 'Plus Jakarta Sans' ); ?>" />
								<select name="<?php echo esc_attr( $name( $role . '_weight' ) ); ?>" aria-label="<?php esc_attr_e( 'Weight', 'eightam-og-image' ); ?>">
									<?php foreach ( $weights as $weight => $weight_label ) : ?>
										<option value="<?php echo esc_attr( (string) $weight ); ?>" <?php selected( (int) $s[ $role . '_weight' ], $weight ); ?>><?php echo esc_html( $weight_label ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php
									/* translators: 1: font file name, 2: where it came from. */
									echo esc_html( sprintf( __( 'In use: %1$s, %2$s.', 'eightam-og-image' ), wp_basename( (string) $fonts[ 'title' === $role ? 'title' : 'subtitle' ] ), $sources[ $fonts['sources'][ $role ] ?? '' ] ?? '' ) );
									?>
								</p>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>

				<h2><?php esc_html_e( 'Logo', 'eightam-og-image' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					image_row(
						$name( 'logo' ),
						(int) $s['logo'],
						__( 'Logo', 'eightam-og-image' ),
						array(
							0  => Brand\is_blocksy() ? __( 'The page’s own (Blocksy’s header)', 'eightam-og-image' ) : __( 'The site’s logo', 'eightam-og-image' ),
							-1 => __( 'None', 'eightam-og-image' ),
						),
						(int) $brand['logo'],
						__( 'A PNG, JPEG, WebP or SVG. Without one, the site’s name is set in its place. With Blocksy Pro, a page with a header of its own shows that header’s logo.', 'eightam-og-image' )
					);
					choice_row(
						$name( 'logo_size' ),
						(string) $s['logo_size'],
						__( 'Logo size', 'eightam-og-image' ),
						array(
							'small'  => __( 'Small', 'eightam-og-image' ),
							'medium' => __( 'Medium', 'eightam-og-image' ),
							'large'  => __( 'Large', 'eightam-og-image' ),
						)
					);
					?>
					<tr>
						<th scope="row"><label for="eightam-og-logo-tab"><?php esc_html_e( 'On a tab', 'eightam-og-image' ); ?></label></th>
						<td>
							<select id="eightam-og-logo-tab" name="<?php echo esc_attr( $name( 'logo_tab' ) ); ?>">
								<option value="auto" <?php selected( $s['logo_tab'], 'auto' ); ?>><?php esc_html_e( 'On a dark card', 'eightam-og-image' ); ?></option>
								<option value="on" <?php selected( $s['logo_tab'], 'on' ); ?>><?php esc_html_e( 'Always', 'eightam-og-image' ); ?></option>
								<option value="off" <?php selected( $s['logo_tab'], 'off' ); ?>><?php esc_html_e( 'Never', 'eightam-og-image' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'A tab of the theme’s light colour hanging from the top edge, so a logo made for a light background reads on a dark card.', 'eightam-og-image' ); ?></p>
						</td>
					</tr>
					<?php
					image_row(
						$name( 'mark' ),
						(int) $s['mark'],
						__( 'Mark', 'eightam-og-image' ),
						array(
							-1 => __( 'None', 'eightam-og-image' ),
							0  => __( 'The site icon', 'eightam-og-image' ),
						),
						(int) $brand['icon'],
						__( 'Drawn in the top corner of a card without a photograph.', 'eightam-og-image' )
					);
					?>
				</table>

				<h2><?php esc_html_e( 'Variants', 'eightam-og-image' ); ?></h2>
				<p class="description"><?php esc_html_e( 'A part of the site drawn differently: a page and every page under it, or a kind of post. The first variant that fits a page is used; what a variant leaves empty is as above.', 'eightam-og-image' ); ?></p>
				<div class="eightam-og__variants" data-name="<?php echo esc_attr( $name( 'variants' ) ); ?>">
					<?php
					foreach ( (array) $s['variants'] as $index => $variant ) {
						variant_row( $name( 'variants' ) . '[' . $index . ']', (array) $variant );
					}
					?>
				</div>
				<template id="eightam-og-variant">
					<?php variant_row( $name( 'variants' ) . '[__i__]', array() ); ?>
				</template>
				<p><button type="button" class="button" id="eightam-og-add-variant"><?php esc_html_e( 'Add a variant', 'eightam-og-image' ); ?></button></p>

				<h2><?php esc_html_e( 'What a card says', 'eightam-og-image' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					choice_row(
						$name( 'kicker' ),
						(string) $s['kicker'],
						__( 'Over the title', 'eightam-og-image' ),
						array(
							'none' => __( 'Nothing', 'eightam-og-image' ),
							'term' => __( 'The category', 'eightam-og-image' ),
							'type' => __( 'The kind of post (“Event”, “Product”)', 'eightam-og-image' ),
						)
					);
					choice_row(
						$name( 'subtitle' ),
						(string) $s['subtitle'],
						__( 'Under the title', 'eightam-og-image' ),
						array(
							'none'        => __( 'Nothing', 'eightam-og-image' ),
							'date'        => __( 'The date', 'eightam-og-image' ),
							'author'      => __( 'The author', 'eightam-og-image' ),
							'author_date' => __( 'The author and the date', 'eightam-og-image' ),
							'excerpt'     => __( 'The excerpt', 'eightam-og-image' ),
						)
					);
					choice_row(
						$name( 'meta' ),
						(string) $s['meta'],
						__( 'At the foot', 'eightam-og-image' ),
						array(
							'host' => __( 'The address', 'eightam-og-image' ),
							'name' => __( 'The site’s name', 'eightam-og-image' ),
							'none' => __( 'Nothing', 'eightam-og-image' ),
						)
					);
					?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Photograph', 'eightam-og-image' ); ?></th>
						<td>
							<input type="hidden" name="<?php echo esc_attr( $name( 'first_image' ) ); ?>" value="0" />
							<label><input type="checkbox" name="<?php echo esc_attr( $name( 'first_image' ) ); ?>" value="1" <?php checked( $s['first_image'] ); ?> /> <?php esc_html_e( 'Without a featured image, use the first large picture in the text', 'eightam-og-image' ); ?></label>
						</td>
					</tr>
				</table>
				<p class="description"><?php esc_html_e( 'Editors can change the words and the photograph of one post in the editor’s sidebar, under Share image, or share an image of their own instead.', 'eightam-og-image' ); ?></p>

				<h2><?php esc_html_e( 'Advanced', 'eightam-og-image' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Kinds of post', 'eightam-og-image' ); ?></th>
						<td>
							<fieldset>
								<?php
								$picked = (array) $s['post_types'];
								foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) :
									if ( 'attachment' === $type->name ) {
										continue;
									}
									?>
									<label class="eightam-og__check"><input type="checkbox" name="<?php echo esc_attr( $name( 'post_types' ) ); ?>[]" value="<?php echo esc_attr( $type->name ); ?>" <?php checked( ! $picked || in_array( $type->name, $picked, true ) ); ?> /> <?php echo esc_html( (string) $type->labels->name ); ?></label>
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
					<?php
					choice_row(
						$name( 'print' ),
						(string) $s['print'],
						__( 'Open Graph tags', 'eightam-og-image' ),
						array(
							/* translators: SEO plugin names. */
							'auto'   => __( 'Print them, unless an SEO plugin does (Yoast, Rank Math, SEOPress, AIOSEO, The SEO Framework)', 'eightam-og-image' ),
							'always' => __( 'Always print them', 'eightam-og-image' ),
							'never'  => __( 'Never print them', 'eightam-og-image' ),
						)
					);
					?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Google Fonts', 'eightam-og-image' ); ?></th>
						<td>
							<input type="hidden" name="<?php echo esc_attr( $name( 'google_fonts' ) ); ?>" value="0" />
							<label><input type="checkbox" name="<?php echo esc_attr( $name( 'google_fonts' ) ); ?>" value="1" <?php checked( $s['google_fonts'] ); ?> /> <?php esc_html_e( 'Fetch a typeface the theme has no usable file for from Google Fonts', 'eightam-og-image' ); ?></label>
							<p class="description"><?php esc_html_e( 'Fetched once by this server and kept in uploads; readers’ browsers never ask Google for anything.', 'eightam-og-image' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<aside class="eightam-og__aside">
				<div class="eightam-og__sticky">
					<h2><?php esc_html_e( 'Preview', 'eightam-og-image' ); ?></h2>
					<div class="eightam-og__preview"><img id="eightam-og-preview" src="" alt="<?php esc_attr_e( 'The share image, as the settings in the form draw it', 'eightam-og-image' ); ?>" width="1200" height="630" /></div>
					<label for="eightam-og-sample"><?php esc_html_e( 'Drawn for', 'eightam-og-image' ); ?></label>
					<select id="eightam-og-sample">
						<option value="0"><?php esc_html_e( 'The front page', 'eightam-og-image' ); ?></option>
						<?php foreach ( $samples as $sample ) : ?>
							<option value="<?php echo esc_attr( (string) $sample->ID ); ?>" <?php selected( $sample->ID, $samples[0]->ID ?? 0 ); ?>><?php echo esc_html( wp_html_excerpt( (string) get_the_title( $sample ), 60, '…' ) . ( has_post_thumbnail( $sample ) ? '' : ' ' . __( '(no featured image)', 'eightam-og-image' ) ) ); ?></option>
						<?php endforeach; ?>
					</select>

					<h2><?php esc_html_e( 'The images', 'eightam-og-image' ); ?></h2>
					<?php $stats = stats(); ?>
					<p>
						<?php
						/* translators: 1: number of images, 2: their size, such as "4 MB". */
						echo esc_html( sprintf( _n( '%1$d image drawn, %2$s, in uploads/eightam-og-image/.', '%1$d images drawn, %2$s, in uploads/eightam-og-image/.', $stats['count'], 'eightam-og-image' ), $stats['count'], size_format( $stats['bytes'] ) ?: '0 B' ) );
						?>
					</p>
					<p class="description"><?php esc_html_e( 'Saving new settings draws each page’s image again the next time it is needed. Delete them all to start afresh, or after changing a logo file in place.', 'eightam-og-image' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="eightam_og_image_flush" />
						<?php wp_nonce_field( 'eightam_og_image_flush' ); ?>
						<label class="eightam-og__check"><input type="checkbox" name="fonts" value="1" /> <?php esc_html_e( 'Fetch the typefaces again too', 'eightam-og-image' ); ?></label>
						<?php submit_button( __( 'Delete all share images', 'eightam-og-image' ), 'secondary', 'submit', false ); ?>
					</form>
				</div>
			</aside>
		</div>
	</div>
	<?php
}

/**
 * A plan of each bento template's grid, its tiles named: the one chosen
 * shown, the rest hidden until chosen.
 *
 * @param string              $chosen   The template chosen.
 * @param array<string,mixed> $settings Settings.
 */
function tile_schemes( string $chosen, array $settings ): void {
	$short = fact_short_names();
	$fact  = static fn( string $slot ): string => 'field' === $settings[ $slot ] && '' !== (string) $settings[ $slot . '_field' ] ? (string) $settings[ $slot . '_field' ] : ( $short[ (string) $settings[ $slot ] ] ?? $slot );
	$names = array(
		'head'  => __( 'Title', 'eightam-og-image' ),
		'photo' => __( 'Photograph', 'eightam-og-image' ),
		'logo'  => __( 'Logo', 'eightam-og-image' ),
		'fact1' => $fact( 'fact1' ),
		'fact2' => $fact( 'fact2' ),
	);
	$track = static fn( array $weights, int $count ): string => implode( ' ', array_map( static fn( $w ): string => (float) $w . 'fr', array_pad( $weights, $count, 1 ) ) );
	foreach ( Templates\layouts() as $slug => $layout ) {
		$rows  = array_map( static fn( string $row ): array => preg_split( '/\s+/', trim( $row ) ), $layout['areas'] );
		$style = sprintf(
			'grid-template-areas:%s;grid-template-columns:%s;grid-template-rows:%s',
			implode( ' ', array_map( static fn( string $row ): string => '"' . $row . '"', $layout['areas'] ) ),
			$track( $layout['columns'], count( $rows[0] ) ),
			$track( $layout['rows'], count( $rows ) )
		);
		?>
		<div class="eightam-og__scheme" data-layout="<?php echo esc_attr( $slug ); ?>" style="<?php echo esc_attr( $style ); ?>" <?php echo $chosen === $slug ? '' : 'hidden'; ?>>
			<?php foreach ( array_unique( array_merge( ...$rows ) ) as $area ) : ?>
				<span class="eightam-og__cell is-<?php echo esc_attr( $area ); ?>" style="grid-area:<?php echo esc_attr( $area ); ?>" data-area="<?php echo esc_attr( $area ); ?>"><?php echo esc_html( $names[ $area ] ?? $area ); ?></span>
			<?php endforeach; ?>
		</div>
		<?php
	}
}

/**
 * The facts' names as short as the plan of a grid has room for.
 *
 * @return array<string,string>
 */
function fact_short_names(): array {
	return array(
		'date'    => __( 'Date', 'eightam-og-image' ),
		'author'  => __( 'Author', 'eightam-og-image' ),
		'term'    => __( 'Category', 'eightam-og-image' ),
		'type'    => __( 'Kind', 'eightam-og-image' ),
		'reading' => __( 'Reading time', 'eightam-og-image' ),
		'site'    => __( 'Site name', 'eightam-og-image' ),
		'host'    => __( 'Address', 'eightam-og-image' ),
		'field'   => __( 'Field', 'eightam-og-image' ),
	);
}

/**
 * A row choosing an image.
 *
 * @param string            $name    Field name.
 * @param int               $value   The value stored.
 * @param string            $label   Label.
 * @param array<int,string> $choices Values other than an image of the media
 *                                   library, such as 0 for the site's, -1
 *                                   for none, => what each is called.
 * @param int               $site_id The image 0 stands for, to show.
 * @param string            $help    A line of help.
 */
function image_row( string $name, int $value, string $label, array $choices, int $site_id, string $help ): void {
	$id = 'eightam-og-' . sanitize_key( $name );
	?>
	<tr>
		<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
		<td>
			<?php image_field( $name, $value, $choices, $site_id, $id ); ?>
			<p class="description"><?php echo esc_html( $help ); ?></p>
		</td>
	</tr>
	<?php
}

/**
 * A field choosing an image: one of some values, or an image of the media
 * library, with a thumbnail.
 *
 * @param string            $name    Field name.
 * @param int               $value   The value stored.
 * @param array<int,string> $choices Values other than an image => names.
 * @param int               $site_id The image 0 stands for, to show.
 * @param string            $id      The select's ID.
 */
function image_field( string $name, int $value, array $choices, int $site_id, string $id = '' ): void {
	$custom = $value > 0;
	$shown  = $custom ? $value : ( 0 === $value ? $site_id : 0 );
	?>
	<span class="eightam-og__image" data-site-url="<?php echo esc_url( $site_id ? (string) wp_get_attachment_image_url( $site_id, 'medium' ) : '' ); ?>">
		<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" />
		<select <?php echo '' !== $id ? 'id="' . esc_attr( $id ) . '"' : ''; ?> class="eightam-og__image-mode">
			<?php foreach ( $choices as $choice => $text ) : ?>
				<option value="<?php echo esc_attr( (string) $choice ); ?>" <?php selected( ! $custom && $value === $choice ); ?>><?php echo esc_html( $text ); ?></option>
			<?php endforeach; ?>
			<option value="custom" <?php selected( $custom ); ?>><?php esc_html_e( 'Another image…', 'eightam-og-image' ); ?></option>
		</select>
		<span class="eightam-og__thumb"><?php echo $shown ? wp_get_attachment_image( $shown, 'medium' ) : ''; ?></span>
	</span>
	<?php
}

/**
 * One variant's fields.
 *
 * @param string              $name    Its field names' stem.
 * @param array<string,mixed> $variant The variant, or nothing for a new one.
 */
function variant_row( string $name, array $variant ): void {
	$target = (string) ( $variant['target'] ?? '' );
	?>
	<fieldset class="eightam-og__variant">
		<label>
			<span><?php esc_html_e( 'For', 'eightam-og-image' ); ?></span>
			<select name="<?php echo esc_attr( $name . '[target]' ); ?>">
				<optgroup label="<?php esc_attr_e( 'A page and the pages under it', 'eightam-og-image' ); ?>">
					<?php foreach ( get_pages( array( 'sort_column' => 'menu_order,post_title' ) ) as $page ) : ?>
						<option value="<?php echo esc_attr( 'page:' . $page->ID ); ?>" <?php selected( $target, 'page:' . $page->ID ); ?>><?php echo esc_html( str_repeat( '— ', count( get_post_ancestors( $page ) ) ) . ( '' !== $page->post_title ? $page->post_title : '#' . $page->ID ) ); ?></option>
					<?php endforeach; ?>
				</optgroup>
				<optgroup label="<?php esc_attr_e( 'A kind of post', 'eightam-og-image' ); ?>">
					<?php foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) : ?>
						<?php
						if ( 'attachment' === $type->name || 'page' === $type->name ) {
							continue;
						}
						?>
						<option value="<?php echo esc_attr( 'type:' . $type->name ); ?>" <?php selected( $target, 'type:' . $type->name ); ?>><?php echo esc_html( (string) $type->labels->name ); ?></option>
					<?php endforeach; ?>
				</optgroup>
			</select>
		</label>
		<label>
			<span><?php esc_html_e( 'Template', 'eightam-og-image' ); ?></span>
			<select name="<?php echo esc_attr( $name . '[template]' ); ?>">
				<option value=""><?php esc_html_e( 'As above', 'eightam-og-image' ); ?></option>
				<?php foreach ( Templates\all() as $slug => $template ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( (string) ( $variant['template'] ?? '' ), $slug ); ?>><?php echo esc_html( $template['label'] ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label>
			<span><?php esc_html_e( 'Logo', 'eightam-og-image' ); ?></span>
			<?php
			image_field(
				$name . '[logo]',
				(int) ( $variant['logo'] ?? -2 ),
				array(
					-2 => __( 'As above', 'eightam-og-image' ),
					0  => __( 'The page’s own', 'eightam-og-image' ),
					-1 => __( 'None', 'eightam-og-image' ),
				),
				0
			);
			?>
		</label>
		<label>
			<span><?php esc_html_e( 'Background', 'eightam-og-image' ); ?></span>
			<input type="text" class="eightam-og__color" name="<?php echo esc_attr( $name . '[background]' ); ?>" value="<?php echo esc_attr( (string) ( $variant['background'] ?? '' ) ); ?>" />
		</label>
		<label>
			<span><?php esc_html_e( 'Accent', 'eightam-og-image' ); ?></span>
			<input type="text" class="eightam-og__color" name="<?php echo esc_attr( $name . '[accent]' ); ?>" value="<?php echo esc_attr( (string) ( $variant['accent'] ?? '' ) ); ?>" />
		</label>
		<button type="button" class="button-link button-link-delete eightam-og__remove"><?php esc_html_e( 'Remove', 'eightam-og-image' ); ?></button>
	</fieldset>
	<?php
}

/**
 * What the settings page's browser is asked to prepare: the SVG logos the
 * site may show, drawn to PNG, and the WOFF2 files of the typefaces in use,
 * unpacked. Neither can the server do with PHP alone.
 *
 * @return array<int,array<string,mixed>>
 */
function jobs(): array {
	$s     = settings();
	$brand = Brand\get();
	$ids   = array_merge( Brand\all_logos(), array( (int) $s['logo'], (int) $s['mark'], (int) $brand['icon'] ) );
	foreach ( (array) $s['variants'] as $variant ) {
		$ids[] = (int) ( $variant['logo'] ?? 0 );
	}
	$jobs = array();
	foreach ( Templates\svgs_to_draw( array_filter( $ids, static fn( int $id ): bool => $id > 0 ) ) as $svg ) {
		$jobs[] = array( 'kind' => 'logo' ) + $svg;
	}
	$families = array(
		'' !== (string) $s['title_font'] ? (string) $s['title_font'] : (string) $brand['fonts']['heading']['family'],
		'' !== (string) $s['text_font'] ? (string) $s['text_font'] : (string) $brand['fonts']['body']['family'],
	);
	foreach ( Fonts\to_unpack( (array) $brand['families'], $families ) as $url ) {
		$jobs[] = array(
			'kind' => 'font',
			'url'  => $url,
		);
	}
	return $jobs;
}

/**
 * A row choosing one of some values.
 *
 * @param string               $name    Field name.
 * @param string               $value   The value chosen.
 * @param string               $label   Label.
 * @param array<string,string> $choices Value => label.
 */
function choice_row( string $name, string $value, string $label, array $choices ): void {
	$id = 'eightam-og-' . sanitize_key( $name );
	?>
	<tr>
		<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
		<td>
			<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
				<?php foreach ( $choices as $choice => $text ) : ?>
					<option value="<?php echo esc_attr( $choice ); ?>" <?php selected( $value, $choice ); ?>><?php echo esc_html( $text ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
	</tr>
	<?php
}

/**
 * Posts to draw the preview for: the newest of each kind with a card, those
 * with a featured image first.
 *
 * @return \WP_Post[]
 */
function sample_posts(): array {
	$posts = get_posts(
		array(
			'post_type'      => post_types(),
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
	usort( $posts, static fn( \WP_Post $a, \WP_Post $b ): int => (int) has_post_thumbnail( $b ) <=> (int) has_post_thumbnail( $a ) );
	return $posts;
}

/**
 * How many cards there are and how much room they take.
 *
 * @return array{count:int,bytes:int}
 */
function stats(): array {
	$dir   = folder()['dir'];
	$count = 0;
	$bytes = 0;
	foreach ( is_dir( $dir ) ? (array) scandir( $dir ) : array() as $file ) {
		if ( is_string( $file ) && preg_match( '/-[0-9a-f]{12}\.(jpg|png)$/', $file ) ) {
			++$count;
			$bytes += (int) filesize( $dir . '/' . $file );
		}
	}
	return array(
		'count' => $count,
		'bytes' => $bytes,
	);
}

add_action( 'admin_post_eightam_og_image_flush', __NAMESPACE__ . '\\flush_action' );
/**
 * Delete every card, and the typefaces if asked, and come back.
 */
function flush_action(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You may not do that.', 'eightam-og-image' ), 403 );
	}
	check_admin_referer( 'eightam_og_image_flush' );
	$count = flush();
	if ( ! empty( $_POST['fonts'] ) ) {
		Fonts\forget();
	}
	wp_safe_redirect( add_query_arg( 'flushed', $count, admin_url( 'options-general.php?page=' . PAGE ) ) );
	exit;
}
