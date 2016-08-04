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
						<option value="<?php echo esc_attr( $discount->ID ); ?>" <?php selected( $discount->ID, $saved_download ); ?>><?php echo esc_html( $discount->post_title ); ?></option>
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



	/**
	 * Save events data to revisions
	 * @param  Number $post_id The post ID
	 */
	function mailchimp_edd_save_revisions( $post_id ) {

		// Check if it's a revision
		$parent_id = wp_is_post_revision( $post_id );

		// If is revision
		if ( $parent_id ) {

			// Get the data
			$parent = get_post( $parent_id );
			$download = get_post_meta( $post->ID, 'mailchimp_edd_download', true );
			$discount = get_post_meta( $post->ID, 'mailchimp_edd_discount', true );
			$optin = get_post_meta( $post->ID, 'mailchimp_edd_double_opt_in', true );

			// If data exists, add to revision
			if ( !empty( $download ) ) {
				add_metadata( 'post', $post_id, 'mailchimp_edd_download', $download );
			}
			if ( !empty( $discount ) ) {
				add_metadata( 'post', $post_id, 'mailchimp_edd_discount', $discount );
			}
			if ( !empty( $optin ) ) {
				add_metadata( 'post', $post_id, 'mailchimp_edd_double_opt_in', $optin );
			}

		}

	}
	add_action( 'save_post', 'mailchimp_edd_save_revisions' );



	/**
	 * Restore events data with post revisions
	 * @param  Number $post_id     The post ID
	 * @param  Number $revision_id The revision ID
	 */
	function mailchimp_edd_restore_revisions( $post_id, $revision_id ) {

		// Variables
		$post = get_post( $post_id );
		$revision = get_post( $revision_id );
		$download = get_metadata( 'post', $revision->ID, 'mailchimp_edd_download', true );
		$discount = get_metadata( 'post', $revision->ID, 'mailchimp_edd_discount', true );
		$optin = get_metadata( 'post', $revision->ID, 'mailchimp_edd_double_opt_in', true );

		// Update content
		if ( isset( $download ) ) {
			update_post_meta( $post_id, 'mailchimp_edd_download', $event_details );
		}
		if ( isset( $discount ) ) {
			update_post_meta( $post_id, 'mailchimp_edd_discount', $event_details );
		}
		if ( isset( $optin ) ) {
			update_post_meta( $post_id, 'mailchimp_edd_double_opt_in', $event_details );
		}

	}
	add_action( 'wp_restore_post_revision', 'mailchimp_edd_restore_revisions', 10, 2 );



	/**
	 * Get the data to display on the revisions page
	 * @param  Array $fields The fields
	 * @return Array The fields
	 */
	function mailchimp_edd_get_revisions_fields( $fields ) {
		$fields['mailchimp_edd_download'] = 'Download';
		$fields['mailchimp_edd_discount'] = 'Discount';
		$fields['mailchimp_edd_double_opt_in'] = 'Double Opt-In';
		return $fields;
	}
	add_filter( '_wp_post_revision_fields', 'mailchimp_edd_get_revisions_fields' );



	/**
	 * Display the data on the revisions page
	 * @param  String|Array $value The field value
	 * @param  Array        $field The field
	 */
	function mailchimp_edd_display_revisions_fields( $value, $field ) {
		global $revision;
		return get_metadata( 'post', $revision->ID, $field, true );
	}
	add_filter( '_wp_post_revision_field_my_meta', 'mailchimp_edd_display_revisions_fields', 10, 2 );