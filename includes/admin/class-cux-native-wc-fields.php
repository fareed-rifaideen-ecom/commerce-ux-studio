<?php
/**
 * Native WooCommerce 11.3+ Fields Integration (Zero-Learning-Curve Client Edition).
 *
 * File Path: includes/admin/class-cux-native-wc-fields.php
 *
 * Places simple, beginner-friendly inputs directly inside standard WooCommerce tabs:
 * 1. General Tab: Card Spec Subtitle, Tagline, Corner Badges (Award Winning / DISC), Financing & Size Guide.
 * 2. Inventory Tab: "Lock Main Image" checkbox (prevents stock autopilot from changing launch hero image).
 * 3. Variations Tab: Friendly Autopilot banner + simple In-Store Pickup Status & Backorder Date per variation.
 * 4. Quick Edit & Native WooCommerce CSV Importer mapping.
 *
 * @package Commerce_UX_Studio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUX_Native_WC_Fields {

	/**
	 * Initialize hooks into standard WooCommerce Product Data tabs, Variations, Quick Edit & CSV Importer.
	 */
	public static function init(): void {
		if ( ! is_admin() ) {
			return;
		}

		// 1. Standard "General" Tab Fields.
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'render_general_tab_fields' ) );

		// 2. Standard "Inventory" Tab Field (Lock Main Image Checkbox).
		add_action( 'woocommerce_product_options_stock_status', array( __CLASS__, 'render_inventory_tab_fields' ) );

		// Save Parent Product Fields.
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_parent_product_fields' ), 20, 1 );

		// 3. Standard "Variations" Tab Helpful Tip Banner & Simple Variation Fields.
		add_action( 'woocommerce_variable_product_before_variations', array( __CLASS__, 'render_variations_autopilot_banner' ) );
		add_action( 'woocommerce_variation_options_pricing', array( __CLASS__, 'render_variation_fields' ), 20, 3 );
		add_action( 'woocommerce_save_product_variation', array( __CLASS__, 'save_variation_fields' ), 15, 2 );

		// 4. Quick Edit & Native WooCommerce CSV Importer Mapping.
		add_action( 'woocommerce_product_quick_edit_end', array( __CLASS__, 'render_quick_edit_fields' ) );
		add_action( 'woocommerce_product_quick_edit_save', array( __CLASS__, 'save_quick_edit_fields' ) );
		add_action( 'manage_product_posts_custom_column', array( __CLASS__, 'render_hidden_quick_edit_data' ), 20, 2 );
		add_action( 'admin_footer-edit.php', array( __CLASS__, 'quick_edit_populate_js' ) );

		add_filter( 'woocommerce_csv_product_import_mapping_options', array( __CLASS__, 'register_csv_mapping_options' ) );
		add_filter( 'woocommerce_csv_product_import_mapping_default_columns', array( __CLASS__, 'register_csv_default_columns' ) );
	}

	/**
	 * 1. Render simple Card & Header fields inside WooCommerce's native "General" tab.
	 */
	public static function render_general_tab_fields(): void {
		global $post;
		$product_id = $post ? (int) $post->ID : 0;
		?>
		<div class="options_group" style="background: #f8fafc; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
			<p style="padding: 8px 12px 0; margin: 0; font-weight: 600; color: #1e293b;">
				<?php esc_html_e( 'Commerce UX Studio — Shop Card & Product Header Details', 'commerce-ux-studio' ); ?>
			</p>
			<?php
			wp_nonce_field( 'cux_native_wc_meta_action', 'cux_native_wc_meta_nonce' );

			woocommerce_wp_text_input(
				array(
					'id'          => '_cux_spec_subtitle',
					'label'       => __( 'Card Spec Subtitle', 'commerce-ux-studio' ),
					'placeholder' => __( 'e.g., Shimano Dura-Ace Di2 or SRAM RED AXS', 'commerce-ux-studio' ),
					'desc_tip'    => true,
					'description' => __( 'Shown directly below the product title on shop cards and single product pages.', 'commerce-ux-studio' ),
					'value'       => (string) get_post_meta( $product_id, '_cux_spec_subtitle', true ),
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'          => '_cux_badge_tl',
					'label'       => __( 'Top-Left Card Badge', 'commerce-ux-studio' ),
					'placeholder' => __( 'e.g., Award Winning or New Arrival', 'commerce-ux-studio' ),
					'desc_tip'    => true,
					'description' => __( 'Optional badge pill displayed in the top-left corner of the product card.', 'commerce-ux-studio' ),
					'value'       => (string) get_post_meta( $product_id, '_cux_badge_tl', true ),
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'          => '_cux_badge_tr',
					'label'       => __( 'Top-Right Spec Label', 'commerce-ux-studio' ),
					'placeholder' => __( 'e.g., DISC', 'commerce-ux-studio' ),
					'desc_tip'    => true,
					'description' => __( 'Optional short technical label in the top-right corner of the product card.', 'commerce-ux-studio' ),
					'value'       => (string) get_post_meta( $product_id, '_cux_badge_tr', true ),
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'          => '_cux_tagline',
					'label'       => __( 'Brand / Model Tagline', 'commerce-ux-studio' ),
					'placeholder' => __( 'e.g., AMPLIFY EVERY EMOTION', 'commerce-ux-studio' ),
					'desc_tip'    => true,
					'description' => __( 'Optional uppercase tagline displayed on Minimalist Showroom cards.', 'commerce-ux-studio' ),
					'value'       => (string) get_post_meta( $product_id, '_cux_tagline', true ),
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'          => '_cux_financing_text',
					'label'       => __( 'Monthly Financing Text', 'commerce-ux-studio' ),
					'placeholder' => __( 'e.g., 0% APR or as low as $364/mo', 'commerce-ux-studio' ),
					'desc_tip'    => true,
					'description' => __( 'Optional installment note shown beneath the price on shop cards.', 'commerce-ux-studio' ),
					'value'       => (string) get_post_meta( $product_id, '_cux_financing_text', true ),
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'          => '_cux_size_guide_id',
					'label'       => __( 'Size Guide UX Block', 'commerce-ux-studio' ),
					'placeholder' => __( 'Leave blank to use Category Default (e.g., bike-size-guide)', 'commerce-ux-studio' ),
					'desc_tip'    => true,
					'description' => __( 'Flatsome UX Block slug or ID opened when clicking "Size Guide" next to the Size selector.', 'commerce-ux-studio' ),
					'value'       => (string) get_post_meta( $product_id, '_cux_size_guide_id', true ),
				)
			);
			?>
		</div>
		<?php
	}

	/**
	 * 2. Render the "Lock Main Image" checkbox inside WooCommerce's native "Inventory" tab.
	 */
	public static function render_inventory_tab_fields(): void {
		global $post;
		$product_id = $post ? (int) $post->ID : 0;
		$hero_lock  = (string) get_post_meta( $product_id, '_cux_hero_lock', true );

		woocommerce_wp_checkbox(
			array(
				'id'          => '_cux_hero_lock',
				'label'       => __( 'Lock Main Image', 'commerce-ux-studio' ),
				'description' => __( 'Keep my manually uploaded Product Image even when variation stock changes (Default variation selection will still auto-update to highest stock).', 'commerce-ux-studio' ),
				'value'       => 'yes' === $hero_lock ? 'yes' : 'no',
			)
		);
	}

	/**
	 * Save parent product fields from the General and Inventory tabs.
	 *
	 * @param int $product_id Parent Product ID.
	 */
	public static function save_parent_product_fields( int $product_id ): void {
		if ( ! isset( $_POST['cux_native_wc_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cux_native_wc_meta_nonce'] ) ), 'cux_native_wc_meta_action' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $product_id ) ) {
			return;
		}

		$text_fields = array(
			'_cux_spec_subtitle',
			'_cux_badge_tl',
			'_cux_badge_tr',
			'_cux_tagline',
			'_cux_financing_text',
			'_cux_size_guide_id',
		);

		foreach ( $text_fields as $meta_key ) {
			if ( isset( $_POST[ $meta_key ] ) ) {
				update_post_meta( $product_id, $meta_key, sanitize_text_field( wp_unslash( $_POST[ $meta_key ] ) ) );
			}
		}

		$hero_lock = isset( $_POST['_cux_hero_lock'] ) ? 'yes' : 'no';
		update_post_meta( $product_id, '_cux_hero_lock', $hero_lock );
	}

	/**
	 * 3A. Render a friendly guide banner at the top of the Variations tab so beginners know how Autopilot works.
	 */
	public static function render_variations_autopilot_banner(): void {
		?>
		<div style="margin: 12px 16px; padding: 12px 16px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 4px; font-size: 13px; color: #1d2327;">
			<strong><?php esc_html_e( 'Smart Gallery & Stock Autopilot is Active:', 'commerce-ux-studio' ); ?></strong>
			<?php esc_html_e( 'You only need to upload a main photo and "Variation gallery" images to ONE size of each color below. When you click "Save changes", Autopilot will automatically copy those photos to the other sizes of that color and set the highest-stock color as the main product image!', 'commerce-ux-studio' ); ?>
		</div>
		<?php
	}

	/**
	 * 3B. Render simple In-Store Pickup & Backorder Date inputs inside each variation (right below pricing).
	 *
	 * @param int   $loop           Variation loop index.
	 * @param array $variation_data Variation data array.
	 * @param mixed $variation      Variation post object.
	 */
	public static function render_variation_fields( $loop, $variation_data, $variation ): void {
		$variation_id = is_object( $variation ) && isset( $variation->ID ) ? (int) $variation->ID : 0;
		if ( ! $variation_id ) {
			return;
		}

		$pickup_status = (string) get_post_meta( $variation_id, '_cux_pickup_status', true );
		$restock_eta   = (string) get_post_meta( $variation_id, '_cux_restock_eta', true );
		?>
		<div class="form-row form-row-full" style="clear: both; margin: 10px 0; padding: 12px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px;">
			<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
				<p class="form-row form-row-first" style="margin: 0;">
					<label><?php esc_html_e( 'Showroom In-Store Pickup Status:', 'commerce-ux-studio' ); ?></label>
					<select name="cux_pickup_status[<?php echo esc_attr( (string) $loop ); ?>]">
						<option value="ship_to_store" <?php selected( $pickup_status, 'ship_to_store' ); ?>><?php esc_html_e( 'Ship to Store (Default Lead Time)', 'commerce-ux-studio' ); ?></option>
						<option value="ready_today" <?php selected( $pickup_status, 'ready_today' ); ?>><?php esc_html_e( 'Get It Today (In Stock at Showroom)', 'commerce-ux-studio' ); ?></option>
						<option value="unavailable" <?php selected( $pickup_status, 'unavailable' ); ?>><?php esc_html_e( 'Not Available for In-Store Pickup', 'commerce-ux-studio' ); ?></option>
					</select>
				</p>

				<p class="form-row form-row-last" style="margin: 0;">
					<label><?php esc_html_e( 'Backorder / Restock Date (Optional):', 'commerce-ux-studio' ); ?></label>
					<input type="text" name="cux_restock_eta[<?php echo esc_attr( (string) $loop ); ?>]" value="<?php echo esc_attr( $restock_eta ); ?>" placeholder="<?php esc_attr_e( 'e.g., 24th Oct', 'commerce-ux-studio' ); ?>" />
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Save variation In-Store Pickup Status, Restock Date, and bridge WooCommerce 11.1+ / Flatsome variation galleries.
	 *
	 * @param int $variation_id Variation ID.
	 * @param int $i            Variation loop index.
	 */
	public static function save_variation_fields( $variation_id, $i ): void {
		$variation_id = (int) $variation_id;
		if ( ! current_user_can( 'edit_post', $variation_id ) ) {
			return;
		}

		if ( isset( $_POST['cux_pickup_status'][ $i ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_post_meta( $variation_id, '_cux_pickup_status', sanitize_key( wp_unslash( $_POST['cux_pickup_status'][ $i ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		if ( isset( $_POST['cux_restock_eta'][ $i ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_post_meta( $variation_id, '_cux_restock_eta', sanitize_text_field( wp_unslash( $_POST['cux_restock_eta'][ $i ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		// Sync WooCommerce 11.1+ native `_product_image_gallery`, Flatsome `_wc_additional_variation_images`, and `_wc_variation_gallery_ids`.
		$wc_native_gallery = (string) get_post_meta( $variation_id, '_product_image_gallery', true );
		$flatsome_gallery  = (string) get_post_meta( $variation_id, '_wc_additional_variation_images', true );
		$cux_gallery       = (string) get_post_meta( $variation_id, '_wc_variation_gallery_ids', true );

		$active_gallery = ! empty( $wc_native_gallery ) ? $wc_native_gallery : ( ! empty( $flatsome_gallery ) ? $flatsome_gallery : $cux_gallery );
		if ( ! empty( $active_gallery ) ) {
			update_post_meta( $variation_id, '_product_image_gallery', $active_gallery );
			update_post_meta( $variation_id, '_wc_additional_variation_images', $active_gallery );
			update_post_meta( $variation_id, '_wc_variation_gallery_ids', $active_gallery );
		}
	}

	/**
	 * 4A. Render Quick Edit fields on the Products list screen.
	 */
	public static function render_quick_edit_fields(): void {
		?>
		<br class="clear" />
		<div class="inline-edit-group">
			<label class="alignleft">
				<span class="title"><?php esc_html_e( 'Spec Subtitle', 'commerce-ux-studio' ); ?></span>
				<span class="input-text-wrap">
					<input type="text" name="_cux_spec_subtitle" class="text cux_spec_subtitle" placeholder="e.g., SRAM RED AXS" value="" />
				</span>
			</label>
		</div>
		<div class="inline-edit-group">
			<label class="alignleft">
				<span class="title"><?php esc_html_e( 'Card Badge', 'commerce-ux-studio' ); ?></span>
				<span class="input-text-wrap">
					<input type="text" name="_cux_badge_tl" class="text cux_badge_tl" placeholder="e.g., Award Winning" value="" />
				</span>
			</label>
			<label class="alignleft" style="margin-left: 16px;">
				<span class="title"><?php esc_html_e( 'Corner Spec', 'commerce-ux-studio' ); ?></span>
				<span class="input-text-wrap">
					<input type="text" name="_cux_badge_tr" class="text cux_badge_tr" placeholder="e.g., DISC" value="" />
				</span>
			</label>
		</div>
		<div class="inline-edit-group">
			<label class="alignleft">
				<input type="checkbox" name="_cux_hero_lock" class="cux_hero_lock" value="yes" />
				<span class="checkbox-title"><?php esc_html_e( 'Lock Main Image (Keep manual image when stock changes)', 'commerce-ux-studio' ); ?></span>
			</label>
		</div>
		<?php
	}

	/**
	 * Save Quick Edit fields.
	 *
	 * @param WC_Product $product Product object.
	 */
	public static function save_quick_edit_fields( $product ): void {
		$product_id = $product->get_id();
		if ( ! current_user_can( 'edit_post', $product_id ) ) {
			return;
		}

		if ( isset( $_REQUEST['_cux_spec_subtitle'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			update_post_meta( $product_id, '_cux_spec_subtitle', sanitize_text_field( wp_unslash( $_REQUEST['_cux_spec_subtitle'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( isset( $_REQUEST['_cux_badge_tl'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			update_post_meta( $product_id, '_cux_badge_tl', sanitize_text_field( wp_unslash( $_REQUEST['_cux_badge_tl'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( isset( $_REQUEST['_cux_badge_tr'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			update_post_meta( $product_id, '_cux_badge_tr', sanitize_text_field( wp_unslash( $_REQUEST['_cux_badge_tr'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$hero_lock = isset( $_REQUEST['_cux_hero_lock'] ) ? 'yes' : 'no'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		update_post_meta( $product_id, '_cux_hero_lock', $hero_lock );
	}

	/**
	 * Output hidden data attributes inside the Product Name column for Quick Edit population.
	 *
	 * @param string $column  Column slug.
	 * @param int    $post_id Product ID.
	 */
	public static function render_hidden_quick_edit_data( string $column, int $post_id ): void {
		if ( 'name' !== $column ) {
			return;
		}
		?>
		<div class="hidden" id="cux_inline_<?php echo esc_attr( (string) $post_id ); ?>"
			data-subtitle="<?php echo esc_attr( (string) get_post_meta( $post_id, '_cux_spec_subtitle', true ) ); ?>"
			data-badge-tl="<?php echo esc_attr( (string) get_post_meta( $post_id, '_cux_badge_tl', true ) ); ?>"
			data-badge-tr="<?php echo esc_attr( (string) get_post_meta( $post_id, '_cux_badge_tr', true ) ); ?>"
			data-hero-lock="<?php echo esc_attr( (string) get_post_meta( $post_id, '_cux_hero_lock', true ) ); ?>">
		</div>
		<?php
	}

	/**
	 * Populate Quick Edit inputs when clicking "Quick Edit" on the Products list screen.
	 */
	public static function quick_edit_populate_js(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'edit-product' !== $screen->id ) {
			return;
		}
		?>
		<script>
		jQuery(function($){
			$('#the-list').on('click', '.editinline', function(){
				var postId = $(this).closest('tr').attr('id');
				postId = postId ? postId.replace('post-', '') : '';
				if (!postId) return;

				var $data = $('#cux_inline_' + postId);
				var $editRow = $('#edit-' + postId);

				$editRow.find('.cux_spec_subtitle').val($data.data('subtitle') || '');
				$editRow.find('.cux_badge_tl').val($data.data('badge-tl') || '');
				$editRow.find('.cux_badge_tr').val($data.data('badge-tr') || '');
				$editRow.find('.cux_hero_lock').prop('checked', $data.data('hero-lock') === 'yes');
			});
		});
		</script>
		<?php
	}

	/**
	 * 4B. Register Commerce UX Studio fields in the native WooCommerce CSV Importer mapping dropdown.
	 *
	 * @param array $options Existing CSV mapping options.
	 * @return array
	 */
	public static function register_csv_mapping_options( array $options ): array {
		$options['cux_studio_group'] = array(
			'name'    => __( 'Commerce UX Studio', 'commerce-ux-studio' ),
			'options' => array(
				'meta:_product_image_gallery' => __( 'CUX: Variation Gallery Image IDs', 'commerce-ux-studio' ),
				'meta:_cux_spec_subtitle'     => __( 'CUX: Card Spec Subtitle (e.g., Dura-Ace Di2)', 'commerce-ux-studio' ),
				'meta:_cux_badge_tl'          => __( 'CUX: Top-Left Card Badge (Award Winning)', 'commerce-ux-studio' ),
				'meta:_cux_badge_tr'          => __( 'CUX: Top-Right Spec Label (DISC)', 'commerce-ux-studio' ),
				'meta:_cux_tagline'           => __( 'CUX: Brand / Model Tagline', 'commerce-ux-studio' ),
				'meta:_cux_financing_text'    => __( 'CUX: Monthly Financing Text', 'commerce-ux-studio' ),
				'meta:_cux_hero_lock'         => __( 'CUX: Lock Main Image (yes/no)', 'commerce-ux-studio' ),
				'meta:_cux_pickup_status'     => __( 'CUX: Showroom Pickup Status (ship_to_store / ready_today)', 'commerce-ux-studio' ),
				'meta:_cux_restock_eta'       => __( 'CUX: Backorder Restock Date', 'commerce-ux-studio' ),
			),
		);
		return $options;
	}

	/**
	 * Auto-map CSV column headers during product/variation CSV import.
	 *
	 * @param array $columns Default header-to-field map.
	 * @return array
	 */
	public static function register_csv_default_columns( array $columns ): array {
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
