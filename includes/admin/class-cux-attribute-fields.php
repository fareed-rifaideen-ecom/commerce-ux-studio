<?php
/**
 * Global Product Attributes & Attribute Term Custom Fields.
 *
 * Adds:
 * 1. Attribute-level fields (Products > Attributes): Attribute Role & Swatch Type.
 * 2. Term-level fields (Configure Terms): Primary Hex, Secondary Hex (Dual-Color),
 *    Split Direction (Horizontal 50/50, 45-deg, Vertical), Surface Finish (Metallic/Gloss),
 *    Custom Swatch Image ID, Tooltip, and a live preview column in the terms list table.
 *
 * @package Commerce_UX_Studio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUX_Attribute_Fields {

	/**
	 * Initialize hooks for WooCommerce Attributes and Attribute Taxonomies.
	 */
	public static function init(): void {
		if ( ! is_admin() ) {
			return;
		}

		// 1. Attribute-level fields (Products > Attributes Add/Edit screen).
		add_action( 'woocommerce_after_add_attribute_fields', array( __CLASS__, 'render_add_attribute_fields' ) );
		add_action( 'woocommerce_after_edit_attribute_fields', array( __CLASS__, 'render_edit_attribute_fields' ) );
		add_action( 'woocommerce_attribute_added', array( __CLASS__, 'save_attribute_fields' ), 10, 2 );
		add_action( 'woocommerce_attribute_updated', array( __CLASS__, 'save_attribute_fields' ), 10, 3 );

		// 2. Term-level fields for all registered product attributes (pa_*).
		add_action( 'admin_init', array( __CLASS__, 'register_attribute_term_hooks' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_color_picker_and_media' ) );
	}

	/**
	 * Enqueue WP Color Picker and Media Uploader on attribute term screens.
	 */
	public static function enqueue_color_picker_and_media(): void {
		$screen = get_current_screen();
		if ( ! $screen || ( false === strpos( $screen->taxonomy ?? '', 'pa_' ) && 'product_page_product_attributes' !== $screen->id ) ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_media();

		wp_add_inline_script(
			'wp-color-picker',
			'jQuery(function($){ $(".cux-color-picker").wpColorPicker(); });'
		);
	}

	/**
	 * Render Attribute Role & Swatch Type on the "Add New Attribute" form.
	 */
	public static function render_add_attribute_fields(): void {
		?>
		<div class="form-field">
			<label for="cux_attr_role"><?php esc_html_e( 'Commerce UX Attribute Role', 'commerce-ux-studio' ); ?></label>
			<select name="cux_attr_role" id="cux_attr_role">
				<option value="visual_primary"><?php esc_html_e( 'Primary Visual (e.g., Colorway - triggers full gallery & catalog card swap)', 'commerce-ux-studio' ); ?></option>
				<option value="visual_secondary"><?php esc_html_e( 'Secondary Visual (e.g., Groupset / Wheelset - triggers spec gallery swap)', 'commerce-ux-studio' ); ?></option>
				<option value="dimensional"><?php esc_html_e( 'Dimensional Only (e.g., Frame Size - instant click without slider reload)', 'commerce-ux-studio' ); ?></option>
			</select>
			<p class="description"><?php esc_html_e( 'Controls how the Stock Engine and Flatsome Flickity slider treat this attribute.', 'commerce-ux-studio' ); ?></p>
		</div>

		<div class="form-field">
			<label for="cux_swatch_type"><?php esc_html_e( 'Frontend Swatch Style', 'commerce-ux-studio' ); ?></label>
			<select name="cux_swatch_type" id="cux_swatch_type">
				<option value="color_circle"><?php esc_html_e( 'Color Circle (Solid, Dual-Color 50/50 Split, or Metallic)', 'commerce-ux-studio' ); ?></option>
				<option value="size_box"><?php esc_html_e( 'Square / Pill Button Box (Ideal for Frame & Apparel Sizes)', 'commerce-ux-studio' ); ?></option>
				<option value="radio_card"><?php esc_html_e( 'Rich Spec Radio Card (Shows Stock, Backorder ETA & Price Delta)', 'commerce-ux-studio' ); ?></option>
				<option value="variation_image"><?php esc_html_e( 'Auto Variation Thumbnail Swatch', 'commerce-ux-studio' ); ?></option>
				<option value="dropdown"><?php esc_html_e( 'Standard Dropdown Select', 'commerce-ux-studio' ); ?></option>
			</select>
		</div>
		<?php
	}

	/**
	 * Render Attribute Role & Swatch Type on the "Edit Attribute" screen.
	 */
	public static function render_edit_attribute_fields(): void {
		$attr_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$role    = get_option( "cux_attr_role_{$attr_id}", 'visual_primary' );
		$type    = get_option( "cux_swatch_type_{$attr_id}", 'color_circle' );
		?>
		<tr class="form-field">
			<th scope="row" valign="top">
				<label for="cux_attr_role"><?php esc_html_e( 'Commerce UX Attribute Role', 'commerce-ux-studio' ); ?></label>
			</th>
			<td>
				<select name="cux_attr_role" id="cux_attr_role">
					<option value="visual_primary" <?php selected( $role, 'visual_primary' ); ?>><?php esc_html_e( 'Primary Visual (e.g., Colorway - triggers full gallery & catalog card swap)', 'commerce-ux-studio' ); ?></option>
					<option value="visual_secondary" <?php selected( $role, 'visual_secondary' ); ?>><?php esc_html_e( 'Secondary Visual (e.g., Groupset / Wheelset - triggers spec gallery swap)', 'commerce-ux-studio' ); ?></option>
					<option value="dimensional" <?php selected( $role, 'dimensional' ); ?>><?php esc_html_e( 'Dimensional Only (e.g., Frame Size - instant click without slider reload)', 'commerce-ux-studio' ); ?></option>
				</select>
			</td>
		</tr>

		<tr class="form-field">
			<th scope="row" valign="top">
				<label for="cux_swatch_type"><?php esc_html_e( 'Frontend Swatch Style', 'commerce-ux-studio' ); ?></label>
			</th>
			<td>
				<select name="cux_swatch_type" id="cux_swatch_type">
					<option value="color_circle" <?php selected( $type, 'color_circle' ); ?>><?php esc_html_e( 'Color Circle (Solid, Dual-Color 50/50 Split, or Metallic)', 'commerce-ux-studio' ); ?></option>
					<option value="size_box" <?php selected( $type, 'size_box' ); ?>><?php esc_html_e( 'Square / Pill Button Box (Ideal for Frame & Apparel Sizes)', 'commerce-ux-studio' ); ?></option>
					<option value="radio_card" <?php selected( $type, 'radio_card' ); ?>><?php esc_html_e( 'Rich Spec Radio Card (Shows Stock, Backorder ETA & Price Delta)', 'commerce-ux-studio' ); ?></option>
					<option value="variation_image" <?php selected( $type, 'variation_image' ); ?>><?php esc_html_e( 'Auto Variation Thumbnail Swatch', 'commerce-ux-studio' ); ?></option>
					<option value="dropdown" <?php selected( $type, 'dropdown' ); ?>><?php esc_html_e( 'Standard Dropdown Select', 'commerce-ux-studio' ); ?></option>
				</select>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save Attribute-level Role and Swatch Type.
	 *
	 * @param int   $attr_id   Attribute ID.
	 * @param array $data      Attribute data.
	 * @param mixed $old_slug  Optional old attribute slug.
	 */
	public static function save_attribute_fields( int $attr_id, array $data, $old_slug = '' ): void {
		if ( isset( $_POST['cux_attr_role'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_option( "cux_attr_role_{$attr_id}", sanitize_key( wp_unslash( $_POST['cux_attr_role'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		if ( isset( $_POST['cux_swatch_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_option( "cux_swatch_type_{$attr_id}", sanitize_key( wp_unslash( $_POST['cux_swatch_type'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
	}

	/**
	 * Hook into all WooCommerce attribute taxonomies (pa_*) to add Term Swatch fields.
	 */
	public static function register_attribute_term_hooks(): void {
		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			return;
		}

		$attribute_taxonomies = wc_get_attribute_taxonomies();
		if ( empty( $attribute_taxonomies ) ) {
			return;
		}

		foreach ( $attribute_taxonomies as $tax ) {
			$taxonomy = wc_attribute_taxonomy_name( $tax->attribute_name );

			add_action( "{$taxonomy}_add_form_fields", array( __CLASS__, 'render_add_term_fields' ) );
			add_action( "{$taxonomy}_edit_form_fields", array( __CLASS__, 'render_edit_term_fields' ), 10, 2 );
			add_action( "created_{$taxonomy}", array( __CLASS__, 'save_term_fields' ), 10, 2 );
			add_action( "edited_{$taxonomy}", array( __CLASS__, 'save_term_fields' ), 10, 2 );

			// Add live swatch preview column in WP Admin Terms table.
			add_filter( "manage_edit-{$taxonomy}_columns", array( __CLASS__, 'add_term_preview_column' ) );
			add_filter( "manage_{$taxonomy}_custom_column", array( __CLASS__, 'render_term_preview_column' ), 10, 3 );
		}
	}

	/**
	 * Render custom swatch fields when adding a new attribute term (e.g. a new Color or Groupset).
	 */
	public static function render_add_term_fields(): void {
		?>
		<div class="form-field">
			<label for="cux_hex_primary"><?php esc_html_e( '1. Primary Swatch Color', 'commerce-ux-studio' ); ?></label>
			<input type="text" name="cux_hex_primary" id="cux_hex_primary" class="cux-color-picker" value="" />
			<p class="description"><?php esc_html_e( 'Main color of the circle swatch.', 'commerce-ux-studio' ); ?></p>
		</div>

		<div class="form-field">
			<label for="cux_hex_secondary"><?php esc_html_e( '2. Secondary Color (For Dual-Color Split or Gradient)', 'commerce-ux-studio' ); ?></label>
			<input type="text" name="cux_hex_secondary" id="cux_hex_secondary" class="cux-color-picker" value="" />
			<p class="description"><?php esc_html_e( 'Pick a second color to create a 50/50 split or smooth gradient.', 'commerce-ux-studio' ); ?></p>
		</div>

		<div class="form-field">
			<label for="cux_split_style"><?php esc_html_e( '3. Swatch Finish / Gradient Style', 'commerce-ux-studio' ); ?></label>
			<select name="cux_split_style" id="cux_split_style">
				<option value="horizontal"><?php esc_html_e( 'Horizontal 50/50 Split (Top / Bottom - Specialized Style)', 'commerce-ux-studio' ); ?></option>
				<option value="angled"><?php esc_html_e( '45-Degree Angled Split', 'commerce-ux-studio' ); ?></option>
				<option value="vertical"><?php esc_html_e( 'Vertical 50/50 Split (Left / Right)', 'commerce-ux-studio' ); ?></option>
				<option value="smooth_gradient"><?php esc_html_e( 'Smooth Ombre Gradient (Primary -> Secondary)', 'commerce-ux-studio' ); ?></option>
				<option value="metallic"><?php esc_html_e( 'Metallic Liquid Chrome Sheen (Pinarello / S-Works Style)', 'commerce-ux-studio' ); ?></option>
				<option value="carbon_matte"><?php esc_html_e( 'Carbon / Satin Matte Finish', 'commerce-ux-studio' ); ?></option>
			</select>
		</div>

		<div class="form-field">
			<label for="cux_swatch_image_id"><?php esc_html_e( '4. Custom Swatch Texture / Image (Optional Override)', 'commerce-ux-studio' ); ?></label>
			<div class="cux-term-img-wrap" style="display:flex;align-items:center;gap:10px;">
				<input type="hidden" name="cux_swatch_image_id" id="cux_swatch_image_id" value="" />
				<div id="cux_swatch_img_preview" style="width:40px;height:40px;border-radius:50%;border:1px solid #cbd5e1;background:#f8fafc;background-size:cover;background-position:center;"></div>
				<button type="button" class="button" id="cux_upload_swatch_img_btn"><?php esc_html_e( 'Upload Texture / Photo', 'commerce-ux-studio' ); ?></button>
				<button type="button" class="button" id="cux_remove_swatch_img_btn" style="display:none;"><?php esc_html_e( 'Remove', 'commerce-ux-studio' ); ?></button>
			</div>
			<p class="description"><?php esc_html_e( 'Upload a close-up paint chip, carbon weave, or fabric photo. Overrides hex colors if set.', 'commerce-ux-studio' ); ?></p>
		</div>

		<div class="form-field">
			<label for="cux_term_tooltip"><?php esc_html_e( 'Swatch Tooltip / Spec Note', 'commerce-ux-studio' ); ?></label>
			<input type="text" name="cux_term_tooltip" id="cux_term_tooltip" value="" />
		</div>
		<?php
		self::render_term_media_inline_js();
	}

	/**
	 * Render custom swatch fields when editing an existing attribute term.
	 *
	 * @param WP_Term $term     Current term object.
	 * @param string  $taxonomy Current taxonomy slug.
	 */
	public static function render_edit_term_fields( WP_Term $term, string $taxonomy ): void {
		$hex_1   = (string) get_term_meta( $term->term_id, '_cux_hex_primary', true );
		$hex_2   = (string) get_term_meta( $term->term_id, '_cux_hex_secondary', true );
		$split   = (string) get_term_meta( $term->term_id, '_cux_split_style', true );
		$img_id  = (int) get_term_meta( $term->term_id, '_cux_swatch_image_id', true );
		$img_url = $img_id > 0 ? wp_get_attachment_image_url( $img_id, 'thumbnail' ) : '';
		$tooltip = (string) get_term_meta( $term->term_id, '_cux_term_tooltip', true );
		if ( empty( $split ) ) {
			$split = 'horizontal';
		}
		?>
		<tr class="form-field">
			<th scope="row" valign="top"><label for="cux_hex_primary"><?php esc_html_e( '1. Primary Swatch Color', 'commerce-ux-studio' ); ?></label></th>
			<td>
				<input type="text" name="cux_hex_primary" id="cux_hex_primary" class="cux-color-picker" value="<?php echo esc_attr( $hex_1 ); ?>" />
			</td>
		</tr>

		<tr class="form-field">
			<th scope="row" valign="top"><label for="cux_hex_secondary"><?php esc_html_e( '2. Secondary Color (Dual-Color or Gradient)', 'commerce-ux-studio' ); ?></label></th>
			<td>
				<input type="text" name="cux_hex_secondary" id="cux_hex_secondary" class="cux-color-picker" value="<?php echo esc_attr( $hex_2 ); ?>" />
				<p class="description"><?php esc_html_e( 'Leave blank for a single color, or pick a second color for a 50/50 split or gradient.', 'commerce-ux-studio' ); ?></p>
			</td>
		</tr>

		<tr class="form-field">
			<th scope="row" valign="top"><label for="cux_split_style"><?php esc_html_e( '3. Swatch Finish / Gradient Style', 'commerce-ux-studio' ); ?></label></th>
			<td>
				<select name="cux_split_style" id="cux_split_style">
					<option value="horizontal" <?php selected( $split, 'horizontal' ); ?>><?php esc_html_e( 'Horizontal 50/50 Split (Top / Bottom - Specialized Style)', 'commerce-ux-studio' ); ?></option>
					<option value="angled" <?php selected( $split, 'angled' ); ?>><?php esc_html_e( '45-Degree Angled Split', 'commerce-ux-studio' ); ?></option>
					<option value="vertical" <?php selected( $split, 'vertical' ); ?>><?php esc_html_e( 'Vertical 50/50 Split (Left / Right)', 'commerce-ux-studio' ); ?></option>
					<option value="smooth_gradient" <?php selected( $split, 'smooth_gradient' ); ?>><?php esc_html_e( 'Smooth Ombre Gradient (Primary -> Secondary)', 'commerce-ux-studio' ); ?></option>
					<option value="metallic" <?php selected( $split, 'metallic' ); ?>><?php esc_html_e( 'Metallic Liquid Chrome Sheen (Pinarello / S-Works Style)', 'commerce-ux-studio' ); ?></option>
					<option value="carbon_matte" <?php selected( $split, 'carbon_matte' ); ?>><?php esc_html_e( 'Carbon / Satin Matte Finish', 'commerce-ux-studio' ); ?></option>
				</select>
			</td>
		</tr>

		<tr class="form-field">
			<th scope="row" valign="top"><label for="cux_swatch_image_id"><?php esc_html_e( '4. Custom Swatch Texture / Image', 'commerce-ux-studio' ); ?></label></th>
			<td>
				<div class="cux-term-img-wrap" style="display:flex;align-items:center;gap:10px;">
					<input type="hidden" name="cux_swatch_image_id" id="cux_swatch_image_id" value="<?php echo esc_attr( $img_id > 0 ? (string) $img_id : '' ); ?>" />
					<div id="cux_swatch_img_preview" style="width:44px;height:44px;border-radius:50%;border:2px solid #cbd5e1;background:#f8fafc;background-size:cover;background-position:center;<?php echo $img_url ? 'background-image:url(' . esc_url( $img_url ) . ');' : ''; ?>"></div>
					<button type="button" class="button" id="cux_upload_swatch_img_btn"><?php esc_html_e( 'Upload Texture / Photo', 'commerce-ux-studio' ); ?></button>
					<button type="button" class="button" id="cux_remove_swatch_img_btn" style="<?php echo $img_id > 0 ? '' : 'display:none;'; ?>"><?php esc_html_e( 'Remove', 'commerce-ux-studio' ); ?></button>
				</div>
				<p class="description"><?php esc_html_e( 'Optional close-up paint chip or texture image for this swatch circle.', 'commerce-ux-studio' ); ?></p>
			</td>
		</tr>

		<tr class="form-field">
			<th scope="row" valign="top"><label for="cux_term_tooltip"><?php esc_html_e( 'Swatch Tooltip / Spec Subtitle', 'commerce-ux-studio' ); ?></label></th>
			<td>
				<input type="text" name="cux_term_tooltip" id="cux_term_tooltip" value="<?php echo esc_attr( $tooltip ); ?>" class="regular-text" />
			</td>
		</tr>
		<?php
		self::render_term_media_inline_js();
	}

	/**
	 * Inline JS for the Attribute Term Swatch Image Uploader.
	 */
	private static function render_term_media_inline_js(): void {
		?>
		<script>
		jQuery(function($){
			$('#cux_upload_swatch_img_btn').off('click').on('click', function(e){
				e.preventDefault();
				var frame = wp.media({
					title: 'Select Swatch Texture or Paint Chip Image',
					button: { text: 'Use as Swatch' },
					multiple: false
				});
				frame.on('select', function(){
					var att = frame.state().get('selection').first().toJSON();
					var url = (att.sizes && att.sizes.thumbnail) ? att.sizes.thumbnail.url : att.url;
					$('#cux_swatch_image_id').val(att.id);
					$('#cux_swatch_img_preview').css('background-image', 'url(' + url + ')');
					$('#cux_remove_swatch_img_btn').show();
				});
				frame.open();
			});
			$('#cux_remove_swatch_img_btn').off('click').on('click', function(e){
				e.preventDefault();
				$('#cux_swatch_image_id').val('');
				$('#cux_swatch_img_preview').css('background-image', 'none');
				$(this).hide();
			});
		});
		</script>
		<?php
	}

	/**
	 * Save custom term meta when an attribute term is created or updated.
	 *
	 * @param int $term_id Term ID.
	 */
	public static function save_term_fields( int $term_id ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( isset( $_POST['cux_hex_primary'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_term_meta( $term_id, '_cux_hex_primary', sanitize_hex_color( wp_unslash( $_POST['cux_hex_primary'] ) ) ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		if ( isset( $_POST['cux_hex_secondary'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_term_meta( $term_id, '_cux_hex_secondary', sanitize_hex_color( wp_unslash( $_POST['cux_hex_secondary'] ) ) ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		if ( isset( $_POST['cux_split_style'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_term_meta( $term_id, '_cux_split_style', sanitize_key( wp_unslash( $_POST['cux_split_style'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		if ( isset( $_POST['cux_swatch_image_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_term_meta( $term_id, '_cux_swatch_image_id', absint( $_POST['cux_swatch_image_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		if ( isset( $_POST['cux_term_tooltip'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_term_meta( $term_id, '_cux_term_tooltip', sanitize_text_field( wp_unslash( $_POST['cux_term_tooltip'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
	}

	/**
	 * Add a Swatch Preview column to the Attribute Terms table in WP Admin.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public static function add_term_preview_column( array $columns ): array {
		$new_columns = array();
		foreach ( $columns as $key => $label ) {
			if ( 'name' === $key ) {
				$new_columns['cux_swatch_preview'] = __( 'Swatch', 'commerce-ux-studio' );
			}
			$new_columns[ $key ] = $label;
		}
		return $new_columns;
	}

	/**
	 * Render the visual circle swatch inside the WP Admin Attribute Terms table.
	 *
	 * @param string $content     Column HTML.
	 * @param string $column_name Column key.
	 * @param int    $term_id     Term ID.
	 * @return string
	 */
	public static function render_term_preview_column( string $content, string $column_name, int $term_id ): string {
		if ( 'cux_swatch_preview' !== $column_name ) {
			return $content;
		}

		$img_id  = (int) get_term_meta( $term_id, '_cux_swatch_image_id', true );
		$img_url = $img_id > 0 ? wp_get_attachment_image_url( $img_id, 'thumbnail' ) : '';
		if ( $img_url ) {
			return sprintf(
				'<span style="display:inline-block;width:30px;height:30px;border-radius:50%%;background:url(%s) center/cover no-repeat;border:2px solid #fff;box-shadow:0 0 0 1px #cbd5e1;"></span>',
				esc_url( $img_url )
			);
		}

		$hex_1 = (string) get_term_meta( $term_id, '_cux_hex_primary', true );
		$hex_2 = (string) get_term_meta( $term->term_id ?? $term_id, '_cux_hex_secondary', true );
		$split = (string) get_term_meta( $term_id, '_cux_split_style', true );

		if ( empty( $hex_1 ) ) {
			return '<span style="color:#94a3b8;font-size:11px;">Auto</span>';
		}

		$bg_css = self::compile_swatch_background_css( $hex_1, $hex_2, $split, '' );

		return sprintf(
			'<span style="display:inline-block;width:30px;height:30px;border-radius:50%%;background:%s;border:2px solid #fff;box-shadow:0 0 0 1px #cbd5e1;"></span>',
			esc_attr( $bg_css )
		);
	}

	/**
	 * Shared helper to compile CSS background for solid, 50/50 split, gradient, metallic, carbon, or image swatches.
	 *
	 * @param string $hex_1   Primary hex color.
	 * @param string $hex_2   Secondary hex color.
	 * @param string $split   Split or finish style.
	 * @param string $img_url Optional texture image URL.
	 * @return string Valid CSS background property value.
	 */
	public static function compile_swatch_background_css( string $hex_1, string $hex_2, string $split, string $img_url = '' ): string {
		if ( ! empty( $img_url ) ) {
			return 'url(' . esc_url_raw( $img_url ) . ') center/cover no-repeat';
		}

		if ( empty( $hex_1 ) ) {
			$hex_1 = '#334155';
		}

		if ( ! empty( $hex_2 ) ) {
			if ( 'angled' === $split ) {
				return "linear-gradient(135deg, {$hex_1} 50%, {$hex_2} 50%)";
			}
			if ( 'vertical' === $split ) {
				return "linear-gradient(90deg, {$hex_1} 50%, {$hex_2} 50%)";
			}
			if ( 'smooth_gradient' === $split ) {
				return "linear-gradient(135deg, {$hex_1} 0%, {$hex_2} 100%)";
			}
			if ( 'metallic' === $split ) {
				return "radial-gradient(circle at 30% 30%, rgba(255,255,255,0.55), transparent 55%), linear-gradient(135deg, {$hex_1} 45%, {$hex_2} 55%)";
			}
			return "linear-gradient(180deg, {$hex_1} 50%, {$hex_2} 50%)";
		}

		if ( 'metallic' === $split ) {
			return "radial-gradient(circle at 30% 28%, rgba(255,255,255,0.6), {$hex_1} 68%)";
		}
		if ( 'carbon_matte' === $split ) {
			return "repeating-linear-gradient(45deg, rgba(255,255,255,0.07) 0px, rgba(255,255,255,0.07) 2px, transparent 2px, transparent 4px), {$hex_1}";
		}

		return $hex_1;
	}
}
