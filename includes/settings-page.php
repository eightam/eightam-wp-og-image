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
				'palette' => array_slice( array_keys( (array) Brand\get()['palette'] ), 0, 10 ),
				'choose'  => __( 'Choose an image', 'eightam-og-image' ),
				'use'     => __( 'Use this image', 'eightam-og-image' ),
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
		'bundled' => __( 'Inter, the plugin’s own', 'eightam-og-image' ),
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
				<div class="eightam-og__templates">
					<?php foreach ( Templates\all() as $slug => $template ) : ?>
						<label class="eightam-og__template">
							<input type="radio" name="<?php echo esc_attr( $name( 'template' ) ); ?>" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $s['template'], $slug ); ?> />
							<img src="" data-template="<?php echo esc_attr( $slug ); ?>" alt="" width="600" height="315" loading="lazy" />
							<strong><?php echo esc_html( $template['label'] ); ?></strong>
							<span><?php echo esc_html( $template['description'] ); ?></span>
						</label>
					<?php endforeach; ?>
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
					<?php foreach ( array_unique( array_merge( array_keys( (array) $brand['families'] ), array( 'Inter' ) ) ) as $family ) : ?>
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
								<input type="text" list="eightam-og-families" class="regular-text" id="eightam-og-<?php echo esc_attr( $role ); ?>-font" name="<?php echo esc_attr( $name( $role . '_font' ) ); ?>" value="<?php echo esc_attr( (string) $s[ $role . '_font' ] ); ?>" placeholder="<?php echo esc_attr( '' !== (string) $theme['family'] ? (string) $theme['family'] : 'Inter' ); ?>" />
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
					image_row( $name( 'logo' ), (int) $s['logo'], __( 'Logo', 'eightam-og-image' ), __( 'The site’s logo', 'eightam-og-image' ), __( 'None', 'eightam-og-image' ), (int) $brand['logo'], __( 'A PNG, JPEG or WebP, about twice as large as it shows; an SVG only where the server’s Imagick reads SVG. Without one, the site’s name is set in its place.', 'eightam-og-image' ) );
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
					image_row( $name( 'mark' ), (int) $s['mark'], __( 'Mark', 'eightam-og-image' ), __( 'The site icon', 'eightam-og-image' ), __( 'None', 'eightam-og-image' ), (int) $brand['icon'], __( 'Drawn in the top corner of a card without a photograph.', 'eightam-og-image' ) );
					?>
				</table>

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
 * A row choosing an image: the site's, none, or one from the media library.
 *
 * @param string $name     Field name.
 * @param int    $value    0 the site's, -1 none, or an attachment ID.
 * @param string $label    Label.
 * @param string $site     What 0 is called.
 * @param string $none     What -1 is called.
 * @param int    $site_id  The site's image, if it has one.
 * @param string $help     A line of help.
 */
function image_row( string $name, int $value, string $label, string $site, string $none, int $site_id, string $help ): void {
	$mode  = $value > 0 ? 'custom' : ( -1 === $value ? 'none' : 'site' );
	$shown = $value > 0 ? $value : ( 0 === $value ? $site_id : 0 );
	$id    = 'eightam-og-' . sanitize_key( $name );
	?>
	<tr class="eightam-og__image" data-site="<?php echo esc_attr( (string) $site_id ); ?>" data-site-url="<?php echo esc_url( $site_id ? (string) wp_get_attachment_image_url( $site_id, 'medium' ) : '' ); ?>">
		<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
		<td>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" />
			<select id="<?php echo esc_attr( $id ); ?>" class="eightam-og__image-mode">
				<option value="site" <?php selected( $mode, 'site' ); ?> <?php disabled( ! $site_id && 'site' !== $mode ); ?>><?php echo esc_html( $site . ( $site_id ? '' : ' ' . __( '(none set)', 'eightam-og-image' ) ) ); ?></option>
				<option value="none" <?php selected( $mode, 'none' ); ?>><?php echo esc_html( $none ); ?></option>
				<option value="custom" <?php selected( $mode, 'custom' ); ?>><?php esc_html_e( 'Another image…', 'eightam-og-image' ); ?></option>
			</select>
			<span class="eightam-og__thumb"><?php echo $shown ? wp_get_attachment_image( $shown, 'medium' ) : ''; ?></span>
			<p class="description"><?php echo esc_html( $help ); ?></p>
		</td>
	</tr>
	<?php
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
