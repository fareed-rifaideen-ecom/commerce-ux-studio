<?php
/**
 * WordPress Quick Edit, Bulk Edit & WooCommerce CSV Importer Field Mapping.
 *
 * Allows fast catalog management of Spec Subtitles, Badges, Hero Lock, and
 * registers human-readable column names in the native WooCommerce CSV Importer.
 *
 * @package Commerce_UX_Studio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUX_Bulk_Quick_Edit {

	/**
	 * Initialize hooks for Quick Edit, Bulk Edit, and CSV Importer.
	 */
	public static function init(): void {
		if ( ! is_admin() ) {
			return;
		}

		// 1. Quick Edit & Bulk Edit UI + Save handlers.
		add_action( 'woocommerce_product_quick_edit_end', array( __CLASS__, 'render_quick_edit_fields' ) );
		add_action( 'woocommerce_product_quick_edit_save', array( __CLASS__, 'save_quick_edit_fields' ) );
		add_action( 'woocommerce_product_bulk_edit_end', array( __CLASS__, 'render_bulk_edit_fields' ) );
		add_action( 'woocommerce_product_bulk_edit_save', array( __CLASS__, 'save_bulk_edit_fields' ) );

		// Inject current values into the Product list column for Quick Edit JS population.
		add_action( 'manage_product_posts_custom_column', array( __CLASS__, 'render_hidden_quick_edit_data' ), 20, 2 );
		add_action( 'admin_footer-edit.php', array( __CLASS__, 'quick_edit_populate_js' ) );

		// 2. Native WooCommerce CSV Importer Column Mapping.
		add_filter( 'woocommerce_csv_product_import_mapping_options', array( __CLASS__, 'register_csv_mapping_options' ) );
		add_filter( 'woocommerce_csv_product_import_mapping_default_columns', array( __CLASS__, 'register_csv_default_columns' ) );
	}

	/**
	 * Render Commerce UX Studio fields inside the Product Quick Edit box.
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
				<span class="checkbox-title"><?php esc_html_e( 'Lock Parent Hero Image (Prevent Stock Sync Overwrite)', 'commerce-ux-studio' ); ?></span>
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
	 * Render Commerce UX Studio fields inside the Product Bulk Edit panel.
	 */
	public static function render_bulk_edit_fields(): void {
		?>
		<div class="inline-edit-group">
			<label>
				<span class="title"><?php esc_html_e( 'Top-Left Badge', 'commerce-ux-studio' ); ?></span>
				<span class="input-text-wrap">
					<input type="text" name="_cux_bulk_badge_tl" class="text" placeholder="<?php esc_attr_e( '— No Change —', 'commerce-ux-studio' ); ?>" value="" />
				</span>
			</label>
		</div>
		<div class="inline-edit-group">
			<label>
				<span class="title"><?php esc_html_e( 'Corner Spec (DISC)', 'commerce-ux-studio' ); ?></span>
				<span class="input-text-wrap">
					<input type="text" name="_cux_bulk_badge_tr" class="text" placeholder="<?php esc_attr_e( '— No Change —', 'commerce-ux-studio' ); ?>" value="" />
				</span>
			</label>
		</div>
		<div class="inline-edit-group">
			<label>
				<span class="title"><?php esc_html_e( 'Hero Image Lock', 'commerce-ux-studio' ); ?></span>
				<span class="input-text-wrap">
					<select name="_cux_bulk_hero_lock">
						<option value=""><?php esc_html_e( '— No Change —', 'commerce-ux-studio' ); ?></option>
						<option value="yes"><?php esc_html_e( 'Yes (Lock Hero Image)', 'commerce-ux-studio' ); ?></option>
						<option value="no"><?php esc_html_e( 'No (Auto-Sync with Highest Stock)', 'commerce-ux-studio' ); ?></option>
					</select>
				</span>
			</label>
		</div>
		<?php
	}

	/**
	 * Save Bulk Edit fields.
	 *
	 * @param WC_Product $product Product object.
	 */
	public static function save_bulk_edit_fields( $product ): void {
		$product_id = $product->get_id();

		if ( ! empty( $_REQUEST['_cux_bulk_badge_tl'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			update_post_meta( $product_id, '_cux_badge_tl', sanitize_text_field( wp_unslash( $_REQUEST['_cux_bulk_badge_tl'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( ! empty( $_REQUEST['_cux_bulk_badge_tr'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			update_post_meta( $product_id, '_cux_badge_tr', sanitize_text_field( wp_unslash( $_REQUEST['_cux_bulk_badge_tr'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( ! empty( $_REQUEST['_cux_bulk_hero_lock'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			update_post_meta( $product_id, '_cux_hero_lock', sanitize_key( wp_unslash( $_REQUEST['_cux_bulk_hero_lock'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
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
		$subtitle  = (string) get_post_meta( $post_id, '_cux_spec_subtitle', true );
		$badge_tl  = (string) get_post_meta( $post_id, '_cux_badge_tl', true );
		$badge_tr  = (string) get_post_meta( $post_id, '_cux_badge_tr', true );
		$hero_lock = (string) get_post_meta( $post_id, '_cux_hero_lock', true );
		?>
		<div class="hidden" id="cux_inline_<?php echo esc_attr( (string) $post_id ); ?>"
			data-subtitle="<?php echo esc_attr( $subtitle ); ?>"
			data-badge-tl="<?php echo esc_attr( $badge_tl ); ?>"
			data-badge-tr="<?php echo esc_attr( $badge_tr ); ?>"
			data-hero-lock="<?php echo esc_attr( $hero_lock ); ?>">
		</div>
		<?php
	}

	/**
	 * Populate Quick Edit fields when clicking "Quick Edit" on the Products list screen.
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
	 * Register Commerce UX Studio fields in the WooCommerce CSV Importer mapping dropdown.
	 *
	 * @param array $options Existing CSV mapping options.
	 * @return array
	 */
	public static function register_csv_mapping_options( array $options ): array {
		$options['cux_studio_group'] = array(
			'name'    => __( 'Commerce UX Studio & Variation Galleries', 'commerce-ux-studio' ),
			'options' => array(
				'meta:_wc_variation_gallery_ids' => __( 'CUX: Variation Extra Gallery Image IDs / URLs', 'commerce-ux-studio' ),
				'meta:_cux_spec_subtitle'        => __( 'CUX: Technical Spec Subtitle (e.g., Dura-Ace Di2)', 'commerce-ux-studio' ),
				'meta:_cux_tagline'              => __( 'CUX: Marketing Tagline', 'commerce-ux-studio' ),
				'meta:_cux_badge_tl'             => __( 'CUX: Top-Left Card Badge (Award Winning)', 'commerce-ux-studio' ),
				'meta:_cux_badge_tr'             => __( 'CUX: Top-Right Spec Label (DISC)', 'commerce-ux-studio' ),
				'meta:_cux_financing_text'       => __( 'CUX: Financing / Installment Text', 'commerce-ux-studio' ),
				'meta:_cux_hero_lock'            => __( 'CUX: Merchandising Hero Lock (yes/no)', 'commerce-ux-studio' ),
				'meta:_cux_pickup_status'        => __( 'CUX: In-Store Pickup Status (ship_to_store / ready_today)', 'commerce-ux-studio' ),
				'meta:_cux_restock_eta'          => __( 'CUX: Backorder Restock ETA Date', 'commerce-ux-studio' ),
				'meta:_cux_low_stock_limit'      => __( 'CUX: Low Stock Warning Threshold', 'commerce-ux-studio' ),
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
	public static function register_csv_default_columns( array $columns ): array {
		$columns['CUX Variation Gallery']      = 'meta:_wc_variation_gallery_ids';
		$columns['Variation Gallery IDs']      = 'meta:_wc_variation_gallery_ids';
		$columns['CUX Spec Subtitle']          = 'meta:_cux_spec_subtitle';
		$columns['CUX Tagline']                = 'meta:_cux_tagline';
		$columns['CUX Top Left Badge']         = 'meta:_cux_badge_tl';
		$columns['CUX Top Right Spec']         = 'meta:_cux_badge_tr';
		$columns['CUX Hero Lock']              = 'meta:_cux_hero_lock';
		$columns['CUX Pickup Status']          = 'meta:_cux_pickup_status';
		$columns['CUX Restock ETA']            = 'meta:_cux_restock_eta';
		$columns['CUX Low Stock Threshold']    = 'meta:_cux_low_stock_limit';
		return $columns;
	}
}
