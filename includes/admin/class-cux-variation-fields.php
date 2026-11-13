<?php
/**
 * Child Variation Custom Fields (Gallery Override, Showroom Pickup Status, Backorder ETA & Low Stock).
 *
 * Adds inside each WooCommerce Variation accordion:
 * - Variation Additional Gallery Images (`_wc_variation_gallery_ids`)
 * - In-Store Pickup Availability Mode (`_cux_pickup_status`: Ship to Store vs Get It Today)
 * - Backorder / Restock ETA Date (`_cux_restock_eta`, e.g., "24th Oct")
 * - Variation Low Stock Threshold (`_cux_low_stock_limit`)
 * - Spec Radio Card Subtitle / Delta Note (`_cux_var_spec_note`)
 *
 * @package Commerce_UX_Studio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUX_Variation_Fields {

	/**
	 * Initialize hooks for child variations.
	 */
	public static function init(): void {
		if ( ! is_admin() ) {
			return;
		}

		// Hook right after variation pricing so it appears near the top of the variation panel, as well as standard variable attributes.
		add_action( 'woocommerce_variation_options_pricing', array( __CLASS__, 'render_variation_fields' ), 20, 3 );
		add_action( 'woocommerce_save_product_variation', array( __CLASS__, 'save_variation_fields' ), 15, 2 );
		add_action( 'admin_footer', array( __CLASS__, 'render_variation_gallery_js' ) );
	}

	/**
	 * Render custom fields inside each variation row in WP Admin.
	 *
	 * @param int   $loop           Variation loop index.
	 * @param array $variation_data Variation data array.
	 * @param mixed $variation      Variation post object.
	 */
	public static function render_variation_fields( $loop, $variation_data, $variation ): void {
		$variation_id    = is_object( $variation ) && isset( $variation->ID ) ? (int) $variation->ID : 0;
		if ( ! $variation_id ) {
			return;
		}
		$gallery_ids_raw = (string) get_post_meta( $variation_id, '_wc_variation_gallery_ids', true );
		// Fallback to Flatsome's native variation gallery meta if present.
		if ( empty( $gallery_ids_raw ) ) {
			$gallery_ids_raw = (string) get_post_meta( $variation_id, '_wc_additional_variation_images', true );
		}
		$image_ids       = array_filter( array_map( 'absint', explode( ',', $gallery_ids_raw ) ) );
		$pickup_status   = (string) get_post_meta( $variation_id, '_cux_pickup_status', true );
		$restock_eta     = (string) get_post_meta( $variation_id, '_cux_restock_eta', true );
		$low_stock_limit = (string) get_post_meta( $variation_id, '_cux_low_stock_limit', true );
		$spec_note       = (string) get_post_meta( $variation_id, '_cux_var_spec_note', true );
		?>
		<div class="form-row form-row-full cux-variation-studio-box" style="clear: both; margin: 12px 0; padding: 12px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px;">
				</label>
				<input type="hidden" class="cux-var-gallery-input" name="cux_var_gallery_ids[<?php echo esc_attr( (string) $loop ); ?>]" value="<?php echo esc_attr( implode( ',', $image_ids ) ); ?>" />
				<div class="cux-var-gallery-thumbs" style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 8px;">
					<?php
					foreach ( $image_ids as $img_id ) :
						$thumb = wp_get_attachment_image_src( $img_id, 'thumbnail' );
						if ( $thumb ) :
							?>
							<div class="cux-var-thumb" data-id="<?php echo esc_attr( (string) $img_id ); ?>" style="position: relative; width: 56px; height: 56px; border: 1px solid #94a3b8; border-radius: 4px; background: #fff;">
								<img src="<?php echo esc_url( $thumb[0] ); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 3px;" />
								<span class="cux-var-remove-img" style="position: absolute; top: -6px; right: -6px; background: #dc2626; color: #fff; border-radius: 50%; width: 17px; height: 17px; line-height: 15px; text-align: center; cursor: pointer; font-size: 11px;">&times;</span>
							</div>
							<?php
						endif;
					endforeach;
					?>
				</div>
				<button type="button" class="button cux-var-add-gallery-btn">
					<?php esc_html_e( '+ Add Variation Gallery Angles', 'commerce-ux-studio' ); ?>
				</button>
			</div>

			<!-- 2. Showroom Pickup Availability & Restock ETA -->
			<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
				<p class="form-row form-row-first" style="margin: 0;">
					<label><?php esc_html_e( 'In-Store Pickup Status (Odoo Branch):', 'commerce-ux-studio' ); ?></label>
					<select name="cux_pickup_status[<?php echo esc_attr( (string) $loop ); ?>]">
						<option value="ship_to_store" <?php selected( $pickup_status, 'ship_to_store' ); ?>><?php esc_html_e( 'Ship to Store (Arrives in 6-10 days)', 'commerce-ux-studio' ); ?></option>
						<option value="ready_today" <?php selected( $pickup_status, 'ready_today' ); ?>><?php esc_html_e( 'Get It Today (In-Store Showroom Stock)', 'commerce-ux-studio' ); ?></option>
						<option value="unavailable" <?php selected( $pickup_status, 'unavailable' ); ?>><?php esc_html_e( 'Not Available for In-Store Pickup', 'commerce-ux-studio' ); ?></option>
					</select>
				</p>

				<p class="form-row form-row-last" style="margin: 0;">
					<label><?php esc_html_e( 'Backorder / Restock ETA Badge (Vilgain Style):', 'commerce-ux-studio' ); ?></label>
					<input type="text" name="cux_restock_eta[<?php echo esc_attr( (string) $loop ); ?>]" value="<?php echo esc_attr( $restock_eta ); ?>" placeholder="<?php esc_attr_e( 'e.g., 24th Oct', 'commerce-ux-studio' ); ?>" />
				</p>

				<p class="form-row form-row-first" style="margin: 0;">
					<label><?php esc_html_e( 'Low Stock Banner Threshold:', 'commerce-ux-studio' ); ?></label>
					<input type="number" min="0" max="99" name="cux_low_stock_limit[<?php echo esc_attr( (string) $loop ); ?>]" value="<?php echo esc_attr( $low_stock_limit ); ?>" placeholder="<?php esc_attr_e( 'Inherit default (2)', 'commerce-ux-studio' ); ?>" />
				</p>

				<p class="form-row form-row-last" style="margin: 0;">
					<label><?php esc_html_e( 'Spec Radio Card Highlight Note:', 'commerce-ux-studio' ); ?></label>
					<input type="text" name="cux_var_spec_note[<?php echo esc_attr( (string) $loop ); ?>]" value="<?php echo esc_attr( $spec_note ); ?>" placeholder="<?php esc_attr_e( 'e.g., Electronic Carbon Groupset', 'commerce-ux-studio' ); ?>" />
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Save child variation custom fields.
	 *
	 * @param int $variation_id Variation ID.
	 * @param int $i            Loop index.
	 */
	public static function save_variation_fields( $variation_id, $i ): void {
		$variation_id = (int) $variation_id;
		if ( isset( $_POST['cux_var_gallery_ids'][ $i ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$raw       = sanitize_text_field( wp_unslash( $_POST['cux_var_gallery_ids'][ $i ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$clean_ids = array_filter( array_map( 'absint', explode( ',', $raw ) ) );
			$csv_str   = implode( ',', $clean_ids );
			update_post_meta( $variation_id, '_wc_variation_gallery_ids', $csv_str );
			// Also sync with Flatsome's native variation gallery meta key so both boxes stay in sync!
			update_post_meta( $variation_id, '_wc_additional_variation_images', $csv_str );
		}

		if ( isset( $_POST['cux_pickup_status'][ $i ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_post_meta( $variation_id, '_cux_pickup_status', sanitize_key( wp_unslash( $_POST['cux_pickup_status'][ $i ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		if ( isset( $_POST['cux_restock_eta'][ $i ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_post_meta( $variation_id, '_cux_restock_eta', sanitize_text_field( wp_unslash( $_POST['cux_restock_eta'][ $i ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		if ( isset( $_POST['cux_low_stock_limit'][ $i ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$limit = sanitize_text_field( wp_unslash( $_POST['cux_low_stock_limit'][ $i ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_post_meta( $variation_id, '_cux_low_stock_limit', '' !== $limit ? absint( $limit ) : '' );
		}

		if ( isset( $_POST['cux_var_spec_note'][ $i ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_post_meta( $variation_id, '_cux_var_spec_note', sanitize_text_field( wp_unslash( $_POST['cux_var_spec_note'][ $i ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
	}

	/**
	 * Output JS for per-variation multi-image gallery uploader.
	 */
	public static function render_variation_gallery_js(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->id ) {
			return;
		}
		?>
		<script>
		jQuery(function($){
			$(document).on('click', '.cux-var-add-gallery-btn', function(e){
				e.preventDefault();
				var $wrapper = $(this).closest('.cux-var-gallery-wrapper');
				var $input   = $wrapper.find('.cux-var-gallery-input');
				var $thumbs  = $wrapper.find('.cux-var-gallery-thumbs');

				var frame = wp.media({
					title: 'Select Variation Gallery Images',
					button: { text: 'Add to Variation Gallery' },
					multiple: true
				});

				frame.on('select', function(){
					var currentIds = $input.val() ? $input.val().split(',').filter(Boolean) : [];
					var selection  = frame.state().get('selection');

					selection.map(function(attachment){
						attachment = attachment.toJSON();
						var idStr  = String(attachment.id);
						if (attachment.id && currentIds.indexOf(idStr) === -1) {
							currentIds.push(idStr);
							var thumbUrl = (attachment.sizes && attachment.sizes.thumbnail) ? attachment.sizes.thumbnail.url : attachment.url;
							$thumbs.append(
								'<div class="cux-var-thumb" data-id="'+idStr+'" style="position:relative;width:56px;height:56px;border:1px solid #94a3b8;border-radius:4px;background:#fff;">' +
								'<img src="'+thumbUrl+'" style="width:100%;height:100%;object-fit:cover;border-radius:3px;" />' +
								'<span class="cux-var-remove-img" style="position:absolute;top:-6px;right:-6px;background:#dc2626;color:#fff;border-radius:50%;width:17px;height:17px;line-height:15px;text-align:center;cursor:pointer;font-size:11px;">&times;</span>' +
								'</div>'
							);
						}
					});

					$input.val(currentIds.join(',')).trigger('change');
					$wrapper.closest('.woocommerce_variation').addClass('variation-needs-update');
					$('button.cancel-variation-changes, button.save-variation-changes').removeAttr('disabled');
				});

				frame.open();
			});

			$(document).on('click', '.cux-var-remove-img', function(){
				var $thumb   = $(this).closest('.cux-var-thumb');
				var $wrapper = $thumb.closest('.cux-var-gallery-wrapper');
				var $input   = $wrapper.find('.cux-var-gallery-input');
				var removeId = String($thumb.data('id'));

				$thumb.remove();
				var updated = ($input.val() ? $input.val().split(',') : []).filter(function(id){
					return id !== removeId && id !== '';
				});
				$input.val(updated.join(',')).trigger('change');
				$wrapper.closest('.woocommerce_variation').addClass('variation-needs-update');
				$('button.cancel-variation-changes, button.save-variation-changes').removeAttr('disabled');
			});
		});
		</script>
		<?php
	}
}
