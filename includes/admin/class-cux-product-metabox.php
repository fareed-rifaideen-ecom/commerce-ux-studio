<?php
/**
 * Parent Product Data Tab ("UX Studio & Galleries") & Visual Gallery Matrix.
 *
 * Adds a dedicated tab inside the WooCommerce Product Data metabox with:
 * 1. Visual Gallery Matrix: Upload multi-angle galleries once per Colorway (and Color + Spec pair)
 *    instead of repeating uploads across every frame size variation.
 * 2. Catalog Card & Header Info: Technical Spec Subtitle (e.g. Shimano Dura-Ace Di2),
 *    Marketing Tagline, Top-Left Badge (Award Winning), Top-Right Spec (DISC), and Financing Text.
 * 3. Stock Winner & Merchandising Hero Lock: Lock flagship hero image or override calculation mode.
 * 4. Omnichannel Fulfilment & Size Guide Overrides: Ship to Home / In-Store Pickup lead times & Size Guide block.
 * 5. Complete Your Ride Selector: AJAX multi-product search to curate helmets, shoes & accessories.
 *
 * @package Commerce_UX_Studio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUX_Product_Metabox {

	/**
	 * Initialize hooks for the WooCommerce Product Data metabox.
	 */
	public static function init(): void {
		if ( ! is_admin() ) {
			return;
		}

		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'add_product_data_tab' ), 35 );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'render_product_data_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_product_data_panel' ), 20, 1 );
		add_action( 'admin_footer', array( __CLASS__, 'render_matrix_media_js' ) );
	}

	/**
	 * Register the "UX Studio & Galleries" tab in WooCommerce Product Data.
	 *
	 * @param array $tabs Existing product data tabs.
	 * @return array
	 */
	public static function add_product_data_tab( array $tabs ): array {
		$tabs['cux_ux_studio'] = array(
			'label'    => __( 'UX Studio & Galleries', 'commerce-ux-studio' ),
			'target'   => 'cux_ux_studio_data_panel',
			'class'    => array( 'show_if_simple', 'show_if_variable' ),
			'priority' => 65,
		);
		return $tabs;
	}

	/**
	 * Render the "UX Studio & Galleries" panel inside the Product Editor.
	 */
	public static function render_product_data_panel(): void {
		global $post;
		$product_id = $post ? (int) $post->ID : 0;
		$product    = wc_get_product( $product_id );

		$spec_subtitle  = (string) get_post_meta( $product_id, '_cux_spec_subtitle', true );
		$tagline        = (string) get_post_meta( $product_id, '_cux_tagline', true );
		$badge_tl       = (string) get_post_meta( $product_id, '_cux_badge_tl', true );
		$badge_tr       = (string) get_post_meta( $product_id, '_cux_badge_tr', true );
		$financing_text = (string) get_post_meta( $product_id, '_cux_financing_text', true );
		$hero_lock      = (string) get_post_meta( $product_id, '_cux_hero_lock', true );
		$size_guide_id  = (string) get_post_meta( $product_id, '_cux_size_guide_id', true );
		$cross_sell_ids = get_post_meta( $product_id, '_cux_cross_sell_ids', true );
		if ( ! is_array( $cross_sell_ids ) ) {
			$cross_sell_ids = array();
		}

		$overrides = get_post_meta( $product_id, '_cux_product_overrides', true );
		if ( ! is_array( $overrides ) ) {
			$overrides = array();
		}

		$visual_matrix = get_post_meta( $product_id, '_cux_visual_matrix', true );
		if ( ! is_array( $visual_matrix ) ) {
			$visual_matrix = array();
		}

		$visual_rows = self::get_product_visual_combinations( $product );
		?>
		<div id="cux_ux_studio_data_panel" class="panel woocommerce_options_panel hidden">
			<?php wp_nonce_field( 'cux_save_product_meta_action', 'cux_product_meta_nonce' ); ?>

			<!-- SECTION 1: VISUAL GALLERY MATRIX (SHARED BY COLOR & SPEC) -->
			<div class="options_group show_if_variable" style="padding: 15px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
				<h3 style="margin: 0 0 6px; font-size: 14px; color: #1e293b;">
					<?php esc_html_e( '1. Visual Gallery Matrix (Shared Colorway & Spec Galleries)', 'commerce-ux-studio' ); ?>
				</h3>
				<p class="description" style="margin: 0 0 12px;">
					<?php esc_html_e( 'Upload multi-angle images once per Colorway (or Color + Groupset combination). All frame/apparel sizes of that color will automatically inherit these images, and the first image becomes the main variation image.', 'commerce-ux-studio' ); ?>
				</p>

				<?php if ( empty( $visual_rows ) ) : ?>
					<p style="padding: 10px 12px; background: #fff; border-left: 4px solid #2271b1; margin: 0;">
						<?php esc_html_e( 'Add and save product attributes (e.g., Color, Groupset) under the "Attributes" tab first to populate the Visual Gallery Matrix.', 'commerce-ux-studio' ); ?>
					</p>
				<?php else : ?>
					<div class="cux-visual-matrix-list" style="display: flex; flex-direction: column; gap: 12px;">
						<?php
						foreach ( $visual_rows as $hash_key => $row_label ) :
							$saved_csv = isset( $visual_matrix[ $hash_key ] ) ? (string) $visual_matrix[ $hash_key ] : '';
							$img_ids   = array_filter( array_map( 'absint', explode( ',', $saved_csv ) ) );
							?>
							<div class="cux-matrix-row" style="background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 12px;">
								<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
									<strong style="font-size: 13px; color: #0f172a;">
										<?php echo esc_html( $row_label ); ?>
										<code style="font-size: 11px; color: #64748b; margin-left: 6px;"><?php echo esc_html( $hash_key ); ?></code>
									</strong>
									<button type="button" class="button cux-matrix-upload-btn">
										<?php esc_html_e( '+ Add / Manage Angles', 'commerce-ux-studio' ); ?>
									</button>
								</div>
								<input type="hidden" class="cux-matrix-input" name="cux_visual_matrix[<?php echo esc_attr( $hash_key ); ?>]" value="<?php echo esc_attr( implode( ',', $img_ids ) ); ?>" />
								<div class="cux-matrix-thumbs" style="display: flex; flex-wrap: wrap; gap: 8px; min-height: 36px;">
									<?php
									foreach ( $img_ids as $idx => $att_id ) :
										$thumb = wp_get_attachment_image_src( $att_id, 'thumbnail' );
										if ( $thumb ) :
											?>
											<div class="cux-matrix-thumb" data-id="<?php echo esc_attr( (string) $att_id ); ?>" style="position: relative; width: 64px; height: 64px; border: 2px solid <?php echo 0 === $idx ? '#2271b1' : '#cbd5e1'; ?>; border-radius: 4px; background: #fff;">
												<img src="<?php echo esc_url( $thumb[0] ); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 2px;" />
												<?php if ( 0 === $idx ) : ?>
													<span style="position: absolute; bottom: 0; left: 0; right: 0; background: rgba(34,113,177,0.9); color: #fff; font-size: 9px; text-align: center; line-height: 14px;">MAIN</span>
												<?php endif; ?>
												<span class="cux-matrix-remove" style="position: absolute; top: -6px; right: -6px; background: #dc2626; color: #fff; width: 18px; height: 18px; border-radius: 50%; text-align: center; line-height: 16px; font-size: 12px; cursor: pointer;">&times;</span>
											</div>
											<?php
										endif;
									endforeach;
									?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<!-- SECTION 2: CATALOG CARD & HEADER METADATA -->
			<div class="options_group">
				<h3 style="padding: 10px 12px 0; margin: 0; font-size: 14px;"><?php esc_html_e( '2. Catalog Card & Product Header Metadata (Specialized & Pinarello UI)', 'commerce-ux-studio' ); ?></h3>
				<?php
				woocommerce_wp_text_input(
					array(
						'id'          => '_cux_spec_subtitle',
						'label'       => __( 'Technical Spec Subtitle', 'commerce-ux-studio' ),
						'placeholder' => __( 'e.g., Shimano Dura-Ace Di2 or SRAM RED AXS', 'commerce-ux-studio' ),
						'desc_tip'    => true,
						'description' => __( 'Displayed directly below the product title on Specialized-style catalog cards and product headers.', 'commerce-ux-studio' ),
						'value'       => $spec_subtitle,
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'          => '_cux_tagline',
						'label'       => __( 'Marketing Tagline', 'commerce-ux-studio' ),
						'placeholder' => __( 'e.g., AMPLIFY EVERY EMOTION', 'commerce-ux-studio' ),
						'desc_tip'    => true,
						'description' => __( 'Uppercase brand tagline displayed on Pinarello-style showroom cards.', 'commerce-ux-studio' ),
						'value'       => $tagline,
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'          => '_cux_badge_tl',
						'label'       => __( 'Top-Left Card Badge', 'commerce-ux-studio' ),
						'placeholder' => __( 'e.g., Award Winning', 'commerce-ux-studio' ),
						'desc_tip'    => true,
						'description' => __( 'Pill badge rendered in the top-left corner of the product card.', 'commerce-ux-studio' ),
						'value'       => $badge_tl,
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'          => '_cux_badge_tr',
						'label'       => __( 'Top-Right Spec Indicator', 'commerce-ux-studio' ),
						'placeholder' => __( 'e.g., DISC', 'commerce-ux-studio' ),
						'desc_tip'    => true,
						'description' => __( 'Minimalist technical indicator in the top-right corner of the card.', 'commerce-ux-studio' ),
						'value'       => $badge_tr,
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'          => '_cux_financing_text',
						'label'       => __( 'Financing / Installment Text', 'commerce-ux-studio' ),
						'placeholder' => __( 'e.g., 0% APR or as low as $364/mo', 'commerce-ux-studio' ),
						'desc_tip'    => true,
						'description' => __( 'Optional monthly financing text shown below the catalog price.', 'commerce-ux-studio' ),
						'value'       => $financing_text,
					)
				);
				?>
			</div>

			<!-- SECTION 3: STOCK WINNER & LAYOUT OVERRIDES (TIER 1 PRIORITY) -->
			<div class="options_group">
				<h3 style="padding: 10px 12px 0; margin: 0; font-size: 14px;"><?php esc_html_e( '3. Stock Winner & Layout Preset Overrides (Tier 1 Priority)', 'commerce-ux-studio' ); ?></h3>
				<?php
				woocommerce_wp_checkbox(
					array(
						'id'          => '_cux_hero_lock',
						'label'       => __( 'Merchandising Hero Lock', 'commerce-ux-studio' ),
						'description' => __( 'Lock current Parent Main Image & Gallery so automated stock syncs never overwrite flagship campaign visuals (default variation attributes will still auto-sync to highest stock).', 'commerce-ux-studio' ),
						'value'       => 'yes' === $hero_lock ? 'yes' : 'no',
					)
				);

				woocommerce_wp_select(
					array(
						'id'      => 'cux_override_card_preset',
						'name'    => 'cux_product_overrides[catalog_card_preset]',
						'label'   => __( 'Card & PDP Preset Override', 'commerce-ux-studio' ),
						'value'   => $overrides['catalog_card_preset'] ?? 'inherit',
						'options' => array(
							'inherit'            => __( 'Inherit Category / Global Default', 'commerce-ux-studio' ),
							'specialized_spec'   => __( 'Specialized Technical Spec Mode', 'commerce-ux-studio' ),
							'pinarello_showroom' => __( 'Pinarello Minimalist Showroom Mode', 'commerce-ux-studio' ),
							'zara_quick_add'     => __( 'Zara Apparel Quick-Add Mode', 'commerce-ux-studio' ),
						),
					)
				);

				woocommerce_wp_select(
					array(
						'id'      => 'cux_override_stock_mode',
						'name'    => 'cux_product_overrides[stock_calc_mode]',
						'label'   => __( 'Stock Winner Mode Override', 'commerce-ux-studio' ),
						'value'   => $overrides['stock_calc_mode'] ?? 'inherit',
						'options' => array(
							'inherit'       => __( 'Inherit Global Store Default', 'commerce-ux-studio' ),
							'grouped_color' => __( 'Grouped by Colorway (Sum all sizes per color)', 'commerce-ux-studio' ),
							'single_sku'    => __( 'Single Highest Stock SKU Winner', 'commerce-ux-studio' ),
						),
					)
				);
				?>
			</div>

			<!-- SECTION 4: OMNICHANNEL FULFILMENT & SIZE GUIDE OVERRIDES -->
			<div class="options_group">
				<h3 style="padding: 10px 12px 0; margin: 0; font-size: 14px;"><?php esc_html_e( '4. Omnichannel Fulfilment & Size Guide', 'commerce-ux-studio' ); ?></h3>
				<?php
				woocommerce_wp_text_input(
					array(
						'id'          => '_cux_size_guide_id',
						'label'       => __( 'Size Guide Target (UX Block)', 'commerce-ux-studio' ),
						'placeholder' => __( 'e.g., tarmac-sl9-geometry', 'commerce-ux-studio' ),
						'desc_tip'    => true,
						'description' => __( 'Flatsome UX Block slug or ID opened when clicking "Size Guide" next to the Size label.', 'commerce-ux-studio' ),
						'value'       => $size_guide_id,
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'          => 'cux_override_home_lead',
						'name'        => 'cux_product_overrides[default_home_lead]',
						'label'       => __( 'Ship to Home Lead Time', 'commerce-ux-studio' ),
						'placeholder' => __( 'Leave blank to inherit (e.g., 4-7 days)', 'commerce-ux-studio' ),
						'value'       => $overrides['default_home_lead'] ?? '',
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'          => 'cux_override_home_cost',
						'name'        => 'cux_product_overrides[default_home_cost]',
						'label'       => __( 'Estimated Shipping Cost', 'commerce-ux-studio' ),
						'placeholder' => __( 'Leave blank to inherit (e.g., $75.00)', 'commerce-ux-studio' ),
						'value'       => $overrides['default_home_cost'] ?? '',
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'          => 'cux_override_pickup_lead',
						'name'        => 'cux_product_overrides[default_pickup_lead]',
						'label'       => __( 'In-Store Pickup Lead Time', 'commerce-ux-studio' ),
						'placeholder' => __( 'Leave blank to inherit (e.g., 6-10 days)', 'commerce-ux-studio' ),
						'value'       => $overrides['default_pickup_lead'] ?? '',
					)
				);
				?>
			</div>

			<!-- SECTION 5: COMPLETE YOUR RIDE ACCESSORY CURATOR -->
			<div class="options_group">
				<h3 style="padding: 10px 12px 0; margin: 0; font-size: 14px;"><?php esc_html_e( '5. "Complete Your Ride" In-Page Cross-Sell Products', 'commerce-ux-studio' ); ?></h3>
				<p class="form-field">
					<label for="cux_cross_sell_ids"><?php esc_html_e( 'Curated Accessories', 'commerce-ux-studio' ); ?></label>
					<select class="wc-product-search" multiple="multiple" style="width: 50%;" id="cux_cross_sell_ids" name="cux_cross_sell_ids[]" data-placeholder="<?php esc_attr_e( 'Search for helmets, shoes, bottle cages&hellip;', 'commerce-ux-studio' ); ?>" data-action="woocommerce_json_search_products" data-exclude="<?php echo esc_attr( (string) $product_id ); ?>">
						<?php
						foreach ( $cross_sell_ids as $cs_id ) {
							$cs_product = wc_get_product( $cs_id );
							if ( is_object( $cs_product ) ) {
								echo '<option value="' . esc_attr( (string) $cs_id ) . '"' . selected( true, true, false ) . '>' . wp_kses_post( $cs_product->get_formatted_name() ) . '</option>';
							}
						}
						?>
					</select>
					<?php echo wc_help_tip( __( 'Select accessories to display inside the interactive "Complete Your Ride" configurator below the Add to Cart button.', 'commerce-ux-studio' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Build the list of visual attribute combinations (Colorways & Color + Secondary Spec pairs) for the Matrix UI.
	 *
	 * @param WC_Product|false|null $product Product object.
	 * @return array Associative array of [ visual_hash => Human Label ].
	 */
	public static function get_product_visual_combinations( $product ): array {
		if ( ! $product || ! is_object( $product ) ) {
			return array();
		}

		$settings      = CUX_Inheritance_Engine::get_global_settings();
		$primary_attr  = $settings['primary_visual_attr'] ?? 'pa_color';
		$sec_attrs     = (array) ( $settings['secondary_visual_attrs'] ?? array() );
		$attributes    = $product->get_attributes();
		$primary_terms = array();
		$sec_terms_map = array();

		foreach ( $attributes as $attr_name => $attr_obj ) {
			if ( ! is_object( $attr_obj ) ) {
				continue;
			}
			$is_primary   = ( $attr_name === $primary_attr || 'pa_' . $attr_name === $primary_attr );
			$is_secondary = in_array( $attr_name, $sec_attrs, true ) || in_array( 'pa_' . $attr_name, $sec_attrs, true );

			if ( ! $is_primary && ! $is_secondary ) {
				continue;
			}

			$terms_list = array();
			if ( $attr_obj->is_taxonomy() ) {
				$terms = $attr_obj->get_terms();
				if ( is_array( $terms ) ) {
					foreach ( $terms as $t ) {
						$terms_list[ $t->slug ] = $t->name;
					}
				}
			} else {
				foreach ( $attr_obj->get_options() as $opt ) {
					$slug                = sanitize_title( $opt );
					$terms_list[ $slug ] = $opt;
				}
			}

			if ( $is_primary ) {
				$primary_terms = $terms_list;
			} elseif ( $is_secondary && ! empty( $terms_list ) ) {
				$sec_terms_map[ $attr_name ] = $terms_list;
			}
		}

		$rows = array();

		// 1. Add base Colorway rows.
		foreach ( $primary_terms as $c_slug => $c_name ) {
			$rows[ $c_slug ] = sprintf( __( 'Colorway: %s (All Sizes / Base)', 'commerce-ux-studio' ), $c_name );

			// 2. If the product also has a Secondary Visual Attribute (e.g., Groupset), add Color + Spec override rows.
			foreach ( $sec_terms_map as $sec_attr_name => $sec_terms ) {
				foreach ( $sec_terms as $s_slug => $s_name ) {
					$combo_hash          = $c_slug . '__' . $s_slug;
					$rows[ $combo_hash ] = sprintf( '%s + %s (Spec Override)', $c_name, $s_name );
				}
			}
		}

		// If product has Secondary Visual Attributes but no Color attribute.
		if ( empty( $primary_terms ) && ! empty( $sec_terms_map ) ) {
			foreach ( $sec_terms_map as $sec_terms ) {
				foreach ( $sec_terms as $s_slug => $s_name ) {
					$rows[ $s_slug ] = sprintf( __( 'Spec: %s', 'commerce-ux-studio' ), $s_name );
				}
			}
		}

		return $rows;
	}

	/**
	 * Save all custom fields from the "UX Studio & Galleries" Product Data panel.
	 *
	 * @param int $product_id Product ID.
	 */
	public static function save_product_data_panel( int $product_id ): void {
		if ( ! isset( $_POST['cux_product_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cux_product_meta_nonce'] ) ), 'cux_save_product_meta_action' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $product_id ) ) {
			return;
		}

		// 1. Save Visual Gallery Matrix.
		if ( isset( $_POST['cux_visual_matrix'] ) && is_array( $_POST['cux_visual_matrix'] ) ) {
			$raw_matrix   = wp_unslash( $_POST['cux_visual_matrix'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$clean_matrix = array();
			foreach ( $raw_matrix as $hash_key => $csv_ids ) {
				$clean_key = sanitize_text_field( (string) $hash_key );
				$ids       = array_filter( array_map( 'absint', explode( ',', (string) $csv_ids ) ) );
				if ( ! empty( $ids ) ) {
					$clean_matrix[ $clean_key ] = implode( ',', $ids );
				}
			}
			update_post_meta( $product_id, '_cux_visual_matrix', $clean_matrix );
		}

		// 2. Save Card & Header Metadata.
		$text_fields = array(
			'_cux_spec_subtitle',
			'_cux_tagline',
			'_cux_badge_tl',
			'_cux_badge_tr',
			'_cux_financing_text',
			'_cux_size_guide_id',
		);

		foreach ( $text_fields as $meta_key ) {
			if ( isset( $_POST[ $meta_key ] ) ) {
				update_post_meta( $product_id, $meta_key, sanitize_text_field( wp_unslash( $_POST[ $meta_key ] ) ) );
			}
		}

		// 3. Save Merchandising Hero Lock.
		$hero_lock = isset( $_POST['_cux_hero_lock'] ) ? 'yes' : 'no';
		update_post_meta( $product_id, '_cux_hero_lock', $hero_lock );

		// 4. Save Tier 1 Product Overrides.
		if ( isset( $_POST['cux_product_overrides'] ) && is_array( $_POST['cux_product_overrides'] ) ) {
			$raw_overrides   = wp_unslash( $_POST['cux_product_overrides'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$clean_overrides = array_map( 'sanitize_text_field', $raw_overrides );
			update_post_meta( $product_id, '_cux_product_overrides', $clean_overrides );
		}

		// 5. Save Curated Complete Your Ride IDs.
		if ( isset( $_POST['cux_cross_sell_ids'] ) && is_array( $_POST['cux_cross_sell_ids'] ) ) {
			$cs_ids = array_values( array_filter( array_map( 'absint', wp_unslash( $_POST['cux_cross_sell_ids'] ) ) ) );
			update_post_meta( $product_id, '_cux_cross_sell_ids', $cs_ids );
		} else {
			delete_post_meta( $product_id, '_cux_cross_sell_ids' );
		}
	}

	/**
	 * Output JavaScript for the Visual Gallery Matrix & Variation Media Uploaders on the Product Edit screen.
	 */
	public static function render_matrix_media_js(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->id ) {
			return;
		}
		?>
		<script>
		jQuery(function($){
			// Visual Gallery Matrix Multi-Image Picker
			$(document).on('click', '.cux-matrix-upload-btn', function(e){
				e.preventDefault();
				var $row    = $(this).closest('.cux-matrix-row');
				var $input  = $row.find('.cux-matrix-input');
				var $thumbs = $row.find('.cux-matrix-thumbs');

				var frame = wp.media({
					title: 'Select Colorway / Spec Gallery Angles (First image = Main Image)',
					button: { text: 'Use Selected Angles' },
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
							var isMain   = (currentIds.length === 1);
							var border   = isMain ? '#2271b1' : '#cbd5e1';
							var badge    = isMain ? '<span style="position:absolute;bottom:0;left:0;right:0;background:rgba(34,113,177,0.9);color:#fff;font-size:9px;text-align:center;line-height:14px;">MAIN</span>' : '';

							$thumbs.append(
								'<div class="cux-matrix-thumb" data-id="'+idStr+'" style="position:relative;width:64px;height:64px;border:2px solid '+border+';border-radius:4px;background:#fff;">' +
								'<img src="'+thumbUrl+'" style="width:100%;height:100%;object-fit:cover;border-radius:2px;" />' +
								badge +
								'<span class="cux-matrix-remove" style="position:absolute;top:-6px;right:-6px;background:#dc2626;color:#fff;width:18px;height:18px;border-radius:50%;text-align:center;line-height:16px;font-size:12px;cursor:pointer;">&times;</span>' +
								'</div>'
							);
						}
					});

					$input.val(currentIds.join(',')).trigger('change');
				});

				frame.open();
			});

			// Remove image from Visual Gallery Matrix
			$(document).on('click', '.cux-matrix-remove', function(){
				var $thumb  = $(this).closest('.cux-matrix-thumb');
				var $row    = $thumb.closest('.cux-matrix-row');
				var $input  = $row.find('.cux-matrix-input');
				var remId   = String($thumb.data('id'));

				$thumb.remove();
				var updated = ($input.val() ? $input.val().split(',') : []).filter(function(id){
					return id !== '' && id !== remId;
				});
				$input.val(updated.join(',')).trigger('change');
			});
		});
		</script>
		<?php
	}
}
