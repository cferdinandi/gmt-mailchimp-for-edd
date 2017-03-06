<?php

	/**
	 * Create the metabox
	 */
	function mailchimp_edd_create_metabox() {
		add_meta_box( 'mailchimp_edd_metabox', 'Easy Digital Downloads Integration', 'mailchimp_edd_render_metabox', 'gmt-mailchimp', 'normal', 'low' );
	}
	add_action( 'add_meta_boxes', 'mailchimp_edd_create_metabox' );



	/**
	 * Render the metabox
	 */
	function mailchimp_edd_render_metabox() {

		// Variables
		global $post;
		$saved_download = get_post_meta( $post->ID, 'mailchimp_edd_download', true );
		$saved_discount = get_post_meta( $post->ID, 'mailchimp_edd_discount', true );
		$saved_optin = get_post_meta( $post->ID, 'mailchimp_edd_double_opt_in', true );

		$downloads = get_posts(
			array(
				'posts_per_page' => -1,
				'post_type'      => 'download',
				'post_status'    => 'publish',
			)
		);

		$discounts = get_posts(
			array(
				'posts_per_page' => -1,
				'post_type'      => 'edd_discount',
				'post_status'    => 'active',
			)
		);

		?>

			<fieldset>
				<p><strong>Will add buyer to MailChimp list selected above if <em>any</em> of the options below are met.</strong></p>

				<label for="mailchimp_edd_download"><?php _e( 'Download:', 'mailchimp_edd' ); ?></label><br>
				<select id="mailchimp_edd_download" name="mailchimp_edd[download]">
					<option value="" <?php selected( '', $saved_download ); ?>></option>
					<?php foreach ( $downloads as $key => $download ) : ?>
						<option value="<?php echo esc_attr( $download->ID ); ?>" <?php selected( $download->ID, $saved_download ); ?>><?php echo esc_html( $download->post_title ); ?></option>
					<?php endforeach; ?>
				</select>
				<br><br>

				<label for="mailchimp_edd_discout"><?php _e( 'Discount:', 'mailchimp_edd' ); ?></label><br>
				<select id="mailchimp_edd_discout" name="mailchimp_edd[discount]">
					<option value="" <?php selected( '', $saved_discount ); ?>></option>
					<?php foreach ( $discounts as $key => $discount ) : ?>
						<?php $discount_code = get_post_meta( $discount->ID, '_edd_discount_code', true ); ?>
						<option value="<?php echo esc_attr( $discount_code ); ?>" <?php selected( $discount_code, $saved_discount ); ?>><?php echo esc_html( $discount_code ); ?></option>
					<?php endforeach; ?>
				</select>
				<br><br>

				<label>
					<input type="checkbox" name="mailchimp_edd[double_opt_in]" value="on" <?php checked( 'on', $saved_optin ); ?>>
					<?php _e( 'Disable double opt-in', 'mailchimp_edd' ); ?>
				</label>
				<br>
			</fieldset>

		<?php

		// Security field
		wp_nonce_field( 'mailchimp_edd_form_metabox_nonce', 'mailchimp_edd_form_metabox_process' );

	}



	/**
	 * Save the metabox
	 * @param  Number $post_id The post ID
	 * @param  Array  $post    The post data
	 */
	function mailchimp_edd_save_metabox( $post_id, $post ) {

		if ( !isset( $_POST['mailchimp_edd_form_metabox_process'] ) ) return;

		// Verify data came from edit screen
		if ( !wp_verify_nonce( $_POST['mailchimp_edd_form_metabox_process'], 'mailchimp_edd_form_metabox_nonce' ) ) {
			return $post->ID;
		}

		// Verify user has permission to edit post
		if ( !current_user_can( 'edit_post', $post->ID )) {
			return $post->ID;
		}

		// Check that events details are being passed along
		if ( !isset( $_POST['mailchimp_edd'] ) ) {
			return $post->ID;
		}

		// Update data in database
		update_post_meta( $post->ID, 'mailchimp_edd_download', wp_filter_post_kses( $_POST['mailchimp_edd']['download'] ) );
		update_post_meta( $post->ID, 'mailchimp_edd_discount', wp_filter_post_kses( $_POST['mailchimp_edd']['discount'] ) );
		if ( isset( $_POST['mailchimp_edd']['double_opt_in'] ) ) {
			update_post_meta( $post->ID, 'mailchimp_edd_double_opt_in', 'on' );
		} else {
			update_post_meta( $post->ID, 'mailchimp_edd_double_opt_in', 'off' );
		}

	}
	add_action( 'save_post', 'mailchimp_edd_save_metabox', 1, 2 );