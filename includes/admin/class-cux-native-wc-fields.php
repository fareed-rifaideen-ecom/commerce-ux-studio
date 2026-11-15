<?php
/**
 * Native WooCommerce 11.3+ Fields Integration (v2.3.2 Universal Configurable Edition).
 *
 * File Path: includes/admin/class-cux-native-wc-fields.php
 *
 * Hooks directly into standard WooCommerce Product Data tabs so store managers
 * never have to learn a separate interface:
 * 1. Product data > General:
 *    - Live Synced WooCommerce Brand badge (`product_brand`)
 *    - Per-Product Visual Attribute Display Override (Variation Image / Term Image / Colour Circle / Text Box)
 *    - Per-Product Gallery Isolation Override (Strict Selected Variation Only vs. Colorway Group)
 *    - Card Spec Subtitle, Top-Left Badge, Top-Right Spec Label, Custom Tagline & Flatsome UX Block Size Guide Picker
 * 2. Product data > Inventory:
 *    - Lock Main Product Image (`_cux_hero_lock`)
 * 3. Product data > Variations:
 *    - Autopilot helper banner + Showroom Pickup Status & Backorder Restock ETA
 * 4. WooCommerce Native CSV Importer Mapping
 *
 * @package Commerce_UX_Studio
 * @author  Fareed M. Rifaideen <https://fareed-rifaideen.netlify.app/>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUX_Native_WC_Fields {

	/**
	 * Register WooCommerce product data tab, variation, and CSV importer hooks.
	 */
	public static function init(): void {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'render_general_tab_fields' ) );
		add_action( 'woocommerce_product_options_inventory_product_data', array( __CLASS__, 'render_inventory_tab_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_parent_product_fields' ) );

		add_action( 'woocommerce_variation_options', array( __CLASS__, 'render_variation_autopilot_banner' ), 10, 3 );
		add_action( 'woocommerce_product_after_variable_attributes', array( __CLASS__, 'render_variation_fulfilment_fields' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( __CLASS__, 'save_variation_fulfilment_fields' ), 10, 2 );

		// Native WooCommerce CSV Importer Column Mapping.
		add_filter( 'woocommerce_csv_product_import_mapping_options', array( __CLASS__, 'register_csv_mapping_options' ) );
		add_filter( 'woocommerce_csv_product_import_mapping_default_columns', array( __CLASS__, 'register_csv_default_columns' ) );
	}

	/**
	 * Render custom Shop Card, Swatch Override, Brand Sync & Size Guide fields in Product data > General.
	 */
	public static function render_general_tab_fields(): void {
		global $post;
		$product_id = isset( $post->ID ) ? (int) $post->ID : 0;

		$brand_info = ( class_exists( 'CUX_Inheritance_Engine' ) && method_exists( 'CUX_Inheritance_Engine', 'resolve_product_brand' ) )
			? CUX_Inheritance_Engine::resolve_product_brand( $product_id )
			: array( 'name' => '' );

		$badge_left = (string) get_post_meta( $product_id, '_cux_badge_tl', true );
		if ( '' === $badge_left ) {
			$badge_left = (string) get_post_meta( $product_id, '_cux_badge_left', true );
		}

		$badge_right = (string) get_post_meta( $product_id, '_cux_badge_tr', true );
		if ( '' === $badge_right ) {
			$badge_right = (string) get_post_meta( $product_id, '_cux_badge_right', true );
		}

		$tagline = (string) get_post_meta( $product_id, '_cux_tagline', true );
		if ( '' === $tagline ) {
			$tagline = (string) get_post_meta( $product_id, '_cux_brand_tagline', true );
		}

		$saved_guide = (string) get_post_meta( $product_id, '_cux_size_guide_block', true );
		if ( '' === $saved_guide ) {
			$saved_guide = (string) get_post_meta( $product_id, '_cux_size_guide_id', true );
		}
		if ( '' === $saved_guide ) {
			$saved_guide = (string) get_post_meta( $product_id, '_cux_size_guide', true );
		}

		echo '<div class="options_group cux-general-fields-group" style="border-top: 1px solid #eee; padding-top: 10px;">';
		echo '<p style="margin: 0 12px 8px; font-weight: 700; color: #1d2327;">' . esc_html__( 'Commerce UX Studio — Shop Card, Swatch & Header Settings', 'commerce-ux-studio' ) . '</p>';

		if ( ! empty( $brand_info['name'] ) ) {
			echo '<p style="margin: 0 12px 10px; padding: 6px 10px; background: #ecfdf5; border-left: 3px solid #10b981; color: #065f46; font-size: 12px;">';
			echo '<strong>&#10003; ' . esc_html__( 'Synced WooCommerce Brand:', 'commerce-ux-studio' ) . '</strong> ' . esc_html( $brand_info['name'] );
			echo '</p>';
		}

		woocommerce_wp_select(
			array(
				'id'          => '_cux_product_swatch_mode',
				'label'       => __( 'Visual Attribute Display', 'commerce-ux-studio' ),
				'description' => __( 'Choose whether visual attributes (e.g., Colour) display as Variation Photos, Colour Circles, or Text Buttons on this product.', 'commerce-ux-studio' ),
				'desc_tip'    => true,
				'value'       => get_post_meta( $product_id, '_cux_product_swatch_mode', true ) ?: 'inherit',
				'options'     => array(
					'inherit'              => __( 'Follow Store Default (Commerce UX Studio > Look & Feel)', 'commerce-ux-studio' ),
					'auto_variation_image' => __( 'Variation Image — Auto-use Variation Primary Photo (52×52px)', 'commerce-ux-studio' ),
					'term_image'           => __( 'Custom Attribute Term Image (Fallback to Variation Photo)', 'commerce-ux-studio' ),
					'color_circle'         => __( 'Colour / Split / Gradient Circle (40×40px)', 'commerce-ux-studio' ),
					'text_box'             => __( 'Text Button / Pill Box', 'commerce-ux-studio' ),
				),
			)
		);

		woocommerce_wp_select(
			array(
				'id'          => '_cux_pdp_gallery_isolation',
				'label'       => __( 'Product Gallery Isolation', 'commerce-ux-studio' ),
				'description' => __( 'Strict Selected Variation Only displays ONLY the selected variation photo(s) in the gallery.', 'commerce-ux-studio' ),
				'desc_tip'    => true,
				'value'       => get_post_meta( $product_id, '_cux_pdp_gallery_isolation', true ) ?: 'inherit',
				'options'     => array(
					'inherit'          => __( 'Follow Store Default', 'commerce-ux-studio' ),
					'strict_variation' => __( 'Strict Selected Variation Only (Hide other variations)', 'commerce-ux-studio' ),
					'colorway_group'   => __( 'Colorway Group Gallery (Share angles across sizes of selected colour)', 'commerce-ux-studio' ),
				),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => '_cux_spec_subtitle',
				'label'       => __( 'Card Spec Subtitle', 'commerce-ux-studio' ),
				'placeholder' => __( 'e.g., Shimano Dura-Ace Di2 or Carbon Frame', 'commerce-ux-studio' ),
				'desc_tip'    => true,
				'description' => __( 'Shown below the product title on Shop Cards (and optionally above price on the Product Page).', 'commerce-ux-studio' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => '_cux_badge_tl',
				'value'       => $badge_left,
				'label'       => __( 'Top-Left Card Badge', 'commerce-ux-studio' ),
				'placeholder' => __( 'e.g., Award Winning or New Arrival', 'commerce-ux-studio' ),
				'desc_tip'    => true,
				'description' => __( 'Displayed in the top-left corner of the Shop Card image.', 'commerce-ux-studio' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => '_cux_badge_tr',
				'value'       => $badge_right,
				'label'       => __( 'Top-Right Spec Label', 'commerce-ux-studio' ),
				'placeholder' => __( 'e.g., DISC', 'commerce-ux-studio' ),
				'desc_tip'    => true,
				'description' => __( 'Displayed in the top-right corner of Minimalist Showroom cards.', 'commerce-ux-studio' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => '_cux_tagline',
				'value'       => $tagline,
				'label'       => __( 'Custom Brand / Tagline', 'commerce-ux-studio' ),
				'placeholder' => __( 'Leave blank to auto-use Synced WooCommerce Brand', 'commerce-ux-studio' ),
				'desc_tip'    => true,
				'description' => __( 'Optional override. If left blank, the assigned WooCommerce Brand is used automatically.', 'commerce-ux-studio' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => '_cux_financing_text',
				'label'       => __( 'Monthly Financing Text', 'commerce-ux-studio' ),
				'placeholder' => __( 'e.g., 0% APR or as low as $364/mo', 'commerce-ux-studio' ),
				'desc_tip'    => true,
				'description' => __( 'Optional financing note displayed below the catalog card price.', 'commerce-ux-studio' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => '_cux_size_guide_block',
				'value'       => $saved_guide,
				'label'       => __( 'Size Guide UX Block', 'commerce-ux-studio' ),
				'placeholder' => __( 'e.g., [block id="size-guide"] or block slug', 'commerce-ux-studio' ),
				'desc_tip'    => true,
				'description' => __( 'Paste a Flatsome UX Block shortcode [block id="..."] or slug to open inside a popup modal when shoppers click "? Size Guide".', 'commerce-ux-studio' ),
			)
		);

		// Quick-Picker Dropdown for published Flatsome `blocks` post type if available.
		if ( post_type_exists( 'blocks' ) ) {
			$ux_blocks = get_posts(
				array(
					'post_type'      => 'blocks',
					'posts_per_page' => 50,
					'post_status'    => 'publish',
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			);
			if ( ! empty( $ux_blocks ) ) {
				echo '<p class="form-field"><label for="cux_quick_pick_ux_block">' . esc_html__( 'Quick-Pick UX Block', 'commerce-ux-studio' ) . '</label>';
				echo '<select id="cux_quick_pick_ux_block" onchange="if(this.value){document.getElementById(\'_cux_size_guide_block\').value=\'[block id=&quot;\'+this.value+\'&quot;]\';}">';
				echo '<option value="">' . esc_html__( '— Select a Flatsome UX Block to fill above —', 'commerce-ux-studio' ) . '</option>';
				foreach ( $ux_blocks as $blk ) {
					echo '<option value="' . esc_attr( $blk->post_name ) . '">' . esc_html( $blk->post_title . ' (' . $blk->post_name . ')' ) . '</option>';
				}
				echo '</select></p>';
			}
		}

		echo '</div>';
	}

	/**
	 * Render Hero Image Lock checkbox inside Product data > Inventory.
	 */
	public static function render_inventory_tab_fields(): void {
		echo '<div class="options_group">';
		woocommerce_wp_checkbox(
			array(
				'id'          => '_cux_hero_lock',
				'label'       => __( 'Lock Main Product Image', 'commerce-ux-studio' ),
				'description' => __( 'Keep my manually uploaded Main Product Image even when variation stock changes.', 'commerce-ux-studio' ),
			)
		);
		echo '</div>';
	}

	/**
	 * Save parent product custom fields safely across both primary and legacy meta keys.
	 *
	 * @param WC_Product $product Product object.
	 */
	public static function save_parent_product_fields( $product ): void {
		if ( ! ( $product instanceof WC_Product ) ) {
			return;
		}

		$text_fields = array(
			'_cux_product_swatch_mode',
			'_cux_pdp_gallery_isolation',
			'_cux_spec_subtitle',
			'_cux_badge_tl',
			'_cux_badge_tr',
			'_cux_tagline',
			'_cux_financing_text',
			'_cux_size_guide_block',
		);

		foreach ( $text_fields as $meta_key ) {
			if ( isset( $_POST[ $meta_key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$val = sanitize_text_field( wp_unslash( (string) $_POST[ $meta_key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$product->update_meta_data( $meta_key, $val );

				// Keep alias meta keys synchronized so all card & PDP renderers read the exact same value.
				if ( '_cux_badge_tl' === $meta_key ) {
					$product->update_meta_data( '_cux_badge_left', $val );
				} elseif ( '_cux_badge_tr' === $meta_key ) {
					$product->update_meta_data( '_cux_badge_right', $val );
				} elseif ( '_cux_tagline' === $meta_key ) {
					$product->update_meta_data( '_cux_brand_tagline', $val );
				} elseif ( '_cux_size_guide_block' === $meta_key ) {
					$product->update_meta_data( '_cux_size_guide_id', $val );
					$product->update_meta_data( '_cux_size_guide', $val );
				}
			}
		}

		$hero_lock = isset( $_POST['_cux_hero_lock'] ) ? 'yes' : 'no'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$product->update_meta_data( '_cux_hero_lock', $hero_lock );
	}

	/**
	 * Render helpful autopilot tip banner on the first variation row.
	 *
	 * @param int     $loop           Variation loop index.
	 * @param array   $variation_data Variation data.
	 * @param WP_Post $variation      Variation post object.
	 */
	public static function render_variation_autopilot_banner( $loop, $variation_data, $variation ): void {
		if ( 0 !== (int) $loop ) {
			return;
		}
		?>
		<div style="margin: 8px 0 12px; padding: 10px 14px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 4px; font-size: 12.5px; color: #1d2327;">
			<strong><?php esc_html_e( 'Smart Gallery & Stock Autopilot is Active:', 'commerce-ux-studio' ); ?></strong>
			<?php esc_html_e( 'Upload a main photo and "Variation gallery" images to ONE size of each color. When you click "Save changes", Autopilot automatically copies those photos to the other sizes of that color and promotes the highest-stock color as the main product image.', 'commerce-ux-studio' ); ?>
		</div>
		<?php
	}

	/**
	 * Render optional per-variation In-Store Pickup status and Backorder Restock ETA fields.
	 *
	 * @param int     $loop           Variation loop index.
	 * @param array   $variation_data Variation data.
	 * @param WP_Post $variation      Variation post object.
	 */
	public static function render_variation_fulfilment_fields( $loop, $variation_data, $variation ): void {
		$var_id = isset( $variation->ID ) ? (int) $variation->ID : 0;
		if ( $var_id <= 0 ) {
			return;
		}

		$pickup_status = (string) get_post_meta( $var_id, '_cux_pickup_status', true );
		$restock_eta   = (string) get_post_meta( $var_id, '_cux_restock_eta', true );
		?>
		<div class="form-row form-row-first">
			<label><?php esc_html_e( 'Showroom In-Store Pickup Status:', 'commerce-ux-studio' ); ?></label>
			<select name="cux_var_pickup_status[<?php echo esc_attr( (string) $loop ); ?>]">
				<option value="inherit" <?php selected( $pickup_status, 'inherit' ); ?>><?php esc_html_e( 'Follow Store Default', 'commerce-ux-studio' ); ?></option>
				<option value="ready_today" <?php selected( $pickup_status, 'ready_today' ); ?>><?php esc_html_e( 'Ready Today — In Stock at Showroom', 'commerce-ux-studio' ); ?></option>
				<option value="unavailable" <?php selected( $pickup_status, 'unavailable' ); ?>><?php esc_html_e( 'Unavailable for In-Store Pickup', 'commerce-ux-studio' ); ?></option>
			</select>
		</div>
		<div class="form-row form-row-last">
			<label><?php esc_html_e( 'Backorder / Restock Date (Optional):', 'commerce-ux-studio' ); ?></label>
			<input type="text" name="cux_var_restock_eta[<?php echo esc_attr( (string) $loop ); ?>]" value="<?php echo esc_attr( $restock_eta ); ?>" placeholder="<?php esc_attr_e( 'e.g., 24th Nov', 'commerce-ux-studio' ); ?>" />
		</div>
		<?php
	}

	/**
	 * Save per-variation fulfilment fields.
	 *
	 * @param int $variation_id Variation ID.
	 * @param int $loop         Loop index.
	 */
	public static function save_variation_fulfilment_fields( $variation_id, $loop ): void {
		$variation_id = (int) $variation_id;
		$loop         = (int) $loop;

		if ( isset( $_POST['cux_var_pickup_status'][ $loop ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_post_meta(
				$variation_id,
				'_cux_pickup_status',
				sanitize_key( wp_unslash( (string) $_POST['cux_var_pickup_status'][ $loop ] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			);
		}

		if ( isset( $_POST['cux_var_restock_eta'][ $loop ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_post_meta(
				$variation_id,
				'_cux_restock_eta',
				sanitize_text_field( wp_unslash( (string) $_POST['cux_var_restock_eta'][ $loop ] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			);
		}
	}

	/**
	 * Register Commerce UX Studio fields in the native WooCommerce CSV Importer mapping dropdown.
	 *
	 * @param array $options Existing CSV mapping options.
	 * @return array
	 */
	public static function register_csv_mapping_options( $options ): array {
		if ( ! is_array( $options ) ) {
			return array();
		}
		$options['cux_studio_group'] = array(
			'name'    => __( 'Commerce UX Studio & Variation Galleries', 'commerce-ux-studio' ),
			'options' => array(
				'meta:_product_image_gallery' => __( 'CUX: Variation Extra Gallery Image IDs', 'commerce-ux-studio' ),
				'meta:_cux_spec_subtitle'     => __( 'CUX: Card Spec Subtitle', 'commerce-ux-studio' ),
				'meta:_cux_badge_tl'          => __( 'CUX: Top-Left Card Badge', 'commerce-ux-studio' ),
				'meta:_cux_badge_tr'          => __( 'CUX: Top-Right Spec Label', 'commerce-ux-studio' ),
				'meta:_cux_tagline'           => __( 'CUX: Custom Brand / Tagline', 'commerce-ux-studio' ),
				'meta:_cux_hero_lock'         => __( 'CUX: Lock Main Product Image (yes/no)', 'commerce-ux-studio' ),
				'meta:_cux_pickup_status'     => __( 'CUX: In-Store Pickup Status', 'commerce-ux-studio' ),
				'meta:_cux_restock_eta'       => __( 'CUX: Backorder Restock ETA Date', 'commerce-ux-studio' ),
			),
		);
		return $options;
	}

	/**
	 * Auto-map CSV column headers when importing products/variations.
	 *
	 * @param array $columns Default header-to-field map.
	 * @return array
	 */
	public static function register_csv_default_columns( $columns ): array {
		if ( ! is_array( $columns ) ) {
			return array();
		}
		$columns['CUX Variation Gallery'] = 'meta:_product_image_gallery';
		$columns['CUX Spec Subtitle']     = 'meta:_cux_spec_subtitle';
		$columns['CUX Top Left Badge']    = 'meta:_cux_badge_tl';
		$columns['CUX Top Right Spec']    = 'meta:_cux_badge_tr';
		$columns['CUX Tagline']           = 'meta:_cux_tagline';
		$columns['CUX Hero Lock']         = 'meta:_cux_hero_lock';
		$columns['CUX Pickup Status']     = 'meta:_cux_pickup_status';
		$columns['CUX Restock ETA']       = 'meta:_cux_restock_eta';
		return $columns;
	}
}
