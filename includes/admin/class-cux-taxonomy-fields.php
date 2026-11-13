<?php
/**
 * Product Category (`product_cat`) & Product Tag (`product_tag`) Custom Fields.
 *
 * Powers Tier 3 of the 4-Tier Inheritance Engine:
 * - Category Card Layout Preset (Specialized, Pinarello, Zara Quick-Add, or Inherit)
 * - Category Gallery Hover Image Preference (Angle #2 vs Second In-Stock Color)
 * - Category Default Size Guide UX Block / Page ID
 * - Category Omnichannel Fulfilment Defaults (Ship to Home / Pickup Lead Times & Shipping Cost)
 * - Product Tag Auto-Badge Text, Pre-Order Sync Toggle & Campaign Atmosphere Overrides
 *
 * @package Commerce_UX_Studio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUX_Taxonomy_Fields {

	/**
	 * Register hooks for `product_cat` and `product_tag`.
	 */
	public static function init(): void {
		if ( ! is_admin() ) {
			return;
		}

		// Product Category hooks.
		add_action( 'product_cat_add_form_fields', array( __CLASS__, 'render_add_category_fields' ), 20 );
		add_action( 'product_cat_edit_form_fields', array( __CLASS__, 'render_edit_category_fields' ), 20, 2 );
		add_action( 'created_product_cat', array( __CLASS__, 'save_category_fields' ), 10, 2 );
		add_action( 'edited_product_cat', array( __CLASS__, 'save_category_fields' ), 10, 2 );

		// Product Tag hooks.
		add_action( 'product_tag_add_form_fields', array( __CLASS__, 'render_add_tag_fields' ), 20 );
		add_action( 'product_tag_edit_form_fields', array( __CLASS__, 'render_edit_tag_fields' ), 20, 2 );
		add_action( 'created_product_tag', array( __CLASS__, 'save_tag_fields' ), 10, 2 );
		add_action( 'edited_product_tag', array( __CLASS__, 'save_tag_fields' ), 10, 2 );
	}

	/**
	 * Render Category UX Studio fields on the Add Category screen.
	 */
	public static function render_add_category_fields(): void {
		?>
		<div class="form-field">
			<label for="cux_cat_card_preset"><?php esc_html_e( 'Commerce UX: Category Card Preset', 'commerce-ux-studio' ); ?></label>
			<select name="cux_cat_card_preset" id="cux_cat_card_preset">
				<option value="inherit"><?php esc_html_e( 'Inherit Global Store Default', 'commerce-ux-studio' ); ?></option>
				<option value="specialized_spec"><?php esc_html_e( 'Specialized Technical Spec Card (Dual-color swatches, Compare box, Spec subtitle)', 'commerce-ux-studio' ); ?></option>
				<option value="pinarello_showroom"><?php esc_html_e( 'Pinarello Minimalist Showroom Card (Overlaid metallic swatches, DISC corner text)', 'commerce-ux-studio' ); ?></option>
				<option value="zara_quick_add"><?php esc_html_e( 'Zara Apparel Quick-Add Card (Hover size selector)', 'commerce-ux-studio' ); ?></option>
			</select>
		</div>

		<div class="form-field">
			<label for="cux_cat_hover_mode"><?php esc_html_e( 'Commerce UX: Category Hover Image Rule', 'commerce-ux-studio' ); ?></label>
			<select name="cux_cat_hover_mode" id="cux_cat_hover_mode">
				<option value="inherit"><?php esc_html_e( 'Inherit Global Store Default', 'commerce-ux-studio' ); ?></option>
				<option value="angle_2"><?php esc_html_e( 'Angle #2 of Winning Variation', 'commerce-ux-studio' ); ?></option>
				<option value="second_color"><?php esc_html_e( 'Main Image #1 of Second In-Stock Color', 'commerce-ux-studio' ); ?></option>
			</select>
		</div>

		<div class="form-field">
			<label for="cux_cat_size_guide_id"><?php esc_html_e( 'Commerce UX: Default Size Guide (UX Block Slug or ID)', 'commerce-ux-studio' ); ?></label>
			<input type="text" name="cux_cat_size_guide_id" id="cux_cat_size_guide_id" placeholder="e.g., bike-size-guide" />
		</div>
		<?php
	}

	/**
	 * Render Category UX Studio fields on the Edit Category screen.
	 *
	 * @param WP_Term $term Current category term.
	 */
	public static function render_edit_category_fields( WP_Term $term ): void {
		$preset     = (string) get_term_meta( $term->term_id, '_cux_cat_card_preset', true );
		$hover_mode = (string) get_term_meta( $term->term_id, '_cux_cat_hover_mode', true );
		$size_guide = (string) get_term_meta( $term->term_id, '_cux_cat_size_guide_id', true );
		$fulfilment = get_term_meta( $term->term_id, '_cux_cat_fulfilment', true );
		if ( ! is_array( $fulfilment ) ) {
			$fulfilment = array();
		}
		?>
		<tr class="form-field">
			<th scope="row" valign="top"><label for="cux_cat_card_preset"><?php esc_html_e( 'Commerce UX: Category Card Preset', 'commerce-ux-studio' ); ?></label></th>
			<td>
				<select name="cux_cat_card_preset" id="cux_cat_card_preset">
					<option value="inherit" <?php selected( $preset, 'inherit' ); ?>><?php esc_html_e( 'Inherit Global Store Default', 'commerce-ux-studio' ); ?></option>
					<option value="specialized_spec" <?php selected( $preset, 'specialized_spec' ); ?>><?php esc_html_e( 'Specialized Technical Spec Card (Dual-color swatches, Compare box, Spec subtitle)', 'commerce-ux-studio' ); ?></option>
					<option value="pinarello_showroom" <?php selected( $preset, 'pinarello_showroom' ); ?>><?php esc_html_e( 'Pinarello Minimalist Showroom Card (Overlaid metallic swatches, DISC corner text)', 'commerce-ux-studio' ); ?></option>
					<option value="zara_quick_add" <?php selected( $preset, 'zara_quick_add' ); ?>><?php esc_html_e( 'Zara Apparel Quick-Add Card (Hover size selector)', 'commerce-ux-studio' ); ?></option>
				</select>
			</td>
		</tr>

		<tr class="form-field">
			<th scope="row" valign="top"><label for="cux_cat_hover_mode"><?php esc_html_e( 'Commerce UX: Category Hover Image Rule', 'commerce-ux-studio' ); ?></label></th>
			<td>
				<select name="cux_cat_hover_mode" id="cux_cat_hover_mode">
					<option value="inherit" <?php selected( $hover_mode, 'inherit' ); ?>><?php esc_html_e( 'Inherit Global Store Default', 'commerce-ux-studio' ); ?></option>
					<option value="angle_2" <?php selected( $hover_mode, 'angle_2' ); ?>><?php esc_html_e( 'Angle #2 of Winning Variation', 'commerce-ux-studio' ); ?></option>
					<option value="second_color" <?php selected( $hover_mode, 'second_color' ); ?>><?php esc_html_e( 'Main Image #1 of Second In-Stock Color', 'commerce-ux-studio' ); ?></option>
				</select>
			</td>
		</tr>

		<tr class="form-field">
			<th scope="row" valign="top"><label for="cux_cat_size_guide_id"><?php esc_html_e( 'Commerce UX: Default Size Guide (UX Block Slug or ID)', 'commerce-ux-studio' ); ?></label></th>
			<td>
				<input type="text" name="cux_cat_size_guide_id" id="cux_cat_size_guide_id" value="<?php echo esc_attr( $size_guide ); ?>" class="regular-text" placeholder="e.g., road-bike-geometry" />
				<p class="description"><?php esc_html_e( 'Flatsome UX Block slug or ID opened when clicking "Size Guide" on products in this category.', 'commerce-ux-studio' ); ?></p>
			</td>
		</tr>

		<tr class="form-field">
			<th scope="row" valign="top"><label><?php esc_html_e( 'Commerce UX: Category Fulfilment Lead Time Overrides', 'commerce-ux-studio' ); ?></label></th>
			<td>
				<p>
					<label><?php esc_html_e( 'Ship to Home Lead Time:', 'commerce-ux-studio' ); ?>
						<input type="text" name="cux_cat_fulfilment[default_home_lead]" value="<?php echo esc_attr( $fulfilment['default_home_lead'] ?? '' ); ?>" placeholder="e.g., 4-7 days" />
					</label>
				</p>
				<p>
					<label><?php esc_html_e( 'Estimated Shipping Cost:', 'commerce-ux-studio' ); ?>
						<input type="text" name="cux_cat_fulfilment[default_home_cost]" value="<?php echo esc_attr( $fulfilment['default_home_cost'] ?? '' ); ?>" placeholder="e.g., $75.00" />
					</label>
				</p>
				<p>
					<label><?php esc_html_e( 'In-Store Pickup Lead Time:', 'commerce-ux-studio' ); ?>
						<input type="text" name="cux_cat_fulfilment[default_pickup_lead]" value="<?php echo esc_attr( $fulfilment['default_pickup_lead'] ?? '' ); ?>" placeholder="e.g., 6-10 days" />
					</label>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save Product Category UX Studio fields.
	 *
	 * @param int $term_id Category Term ID.
	 */
	public static function save_category_fields( int $term_id ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( isset( $_POST['cux_cat_card_preset'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_term_meta( $term_id, '_cux_cat_card_preset', sanitize_key( wp_unslash( $_POST['cux_cat_card_preset'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		if ( isset( $_POST['cux_cat_hover_mode'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_term_meta( $term_id, '_cux_cat_hover_mode', sanitize_key( wp_unslash( $_POST['cux_cat_hover_mode'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		if ( isset( $_POST['cux_cat_size_guide_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_term_meta( $term_id, '_cux_cat_size_guide_id', sanitize_text_field( wp_unslash( $_POST['cux_cat_size_guide_id'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		if ( isset( $_POST['cux_cat_fulfilment'] ) && is_array( $_POST['cux_cat_fulfilment'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$raw_fulfilment = wp_unslash( $_POST['cux_cat_fulfilment'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$clean          = array_map( 'sanitize_text_field', $raw_fulfilment );
			update_term_meta( $term_id, '_cux_cat_fulfilment', $clean );
		}
	}

	/**
	 * Render Product Tag UX Studio fields on the Add Tag screen.
	 */
	public static function render_add_tag_fields(): void {
		?>
		<div class="form-field">
			<label for="cux_tag_badge_tl"><?php esc_html_e( 'Commerce UX: Auto Top-Left Card Badge', 'commerce-ux-studio' ); ?></label>
			<input type="text" name="cux_tag_badge_tl" id="cux_tag_badge_tl" placeholder="e.g., Award Winning" />
			<p class="description"><?php esc_html_e( 'Any product with this tag automatically displays this badge pill on its catalog card.', 'commerce-ux-studio' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render Product Tag UX Studio fields on the Edit Tag screen.
	 *
	 * @param WP_Term $term Current tag term.
	 */
	public static function render_edit_tag_fields( WP_Term $term ): void {
		$badge      = (string) get_term_meta( $term->term_id, '_cux_tag_badge_tl', true );
		$atmosphere = get_term_meta( $term->term_id, '_cux_tag_atmosphere', true );
		if ( ! is_array( $atmosphere ) ) {
			$atmosphere = array();
		}
		?>
		<tr class="form-field">
			<th scope="row" valign="top"><label for="cux_tag_badge_tl"><?php esc_html_e( 'Commerce UX: Auto Top-Left Card Badge', 'commerce-ux-studio' ); ?></label></th>
			<td>
				<input type="text" name="cux_tag_badge_tl" id="cux_tag_badge_tl" value="<?php echo esc_attr( $badge ); ?>" class="regular-text" placeholder="e.g., Award Winning" />
			</td>
		</tr>

		<tr class="form-field">
			<th scope="row" valign="top"><label><?php esc_html_e( 'Commerce UX: Tag Campaign & Pre-Order Rules', 'commerce-ux-studio' ); ?></label></th>
			<td>
				<p>
					<label>
						<input type="checkbox" name="cux_tag_atmosphere[include_backorders]" value="yes" <?php checked( $atmosphere['include_backorders'] ?? '', 'yes' ); ?> />
						<?php esc_html_e( 'Treat 0-stock backorder variations as active gallery candidates (Pre-Order Launch Mode)', 'commerce-ux-studio' ); ?>
					</label>
				</p>
				<p>
					<label><?php esc_html_e( 'Campaign Top SVG / Logo Image URL:', 'commerce-ux-studio' ); ?><br/>
						<input type="url" name="cux_tag_atmosphere[campaign_logo_url]" value="<?php echo esc_attr( $atmosphere['campaign_logo_url'] ?? '' ); ?>" class="regular-text" />
					</label>
				</p>
				<p>
					<label><?php esc_html_e( 'Mandatory Verification / Fulfilment Notice:', 'commerce-ux-studio' ); ?><br/>
						<textarea name="cux_tag_atmosphere[verification_notice]" rows="3" class="large-text"><?php echo esc_textarea( $atmosphere['verification_notice'] ?? '' ); ?></textarea>
					</label>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save Product Tag UX Studio fields.
	 *
	 * @param int $term_id Tag Term ID.
	 */
	public static function save_tag_fields( int $term_id ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( isset( $_POST['cux_tag_badge_tl'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_term_meta( $term_id, '_cux_tag_badge_tl', sanitize_text_field( wp_unslash( $_POST['cux_tag_badge_tl'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		if ( isset( $_POST['cux_tag_atmosphere'] ) && is_array( $_POST['cux_tag_atmosphere'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$raw   = wp_unslash( $_POST['cux_tag_atmosphere'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$clean = array(
				'include_backorders'  => isset( $raw['include_backorders'] ) ? 'yes' : 'no',
				'campaign_logo_url'   => esc_url_raw( $raw['campaign_logo_url'] ?? '' ),
				'verification_notice' => sanitize_textarea_field( $raw['verification_notice'] ?? '' ),
			);
			update_term_meta( $term_id, '_cux_tag_atmosphere', $clean );
		}
	}
}
