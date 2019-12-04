<?php

	/**
	 * Create the metabox
	 */
	function mailchimp_edd_create_metabox() {
		add_meta_box( 'mailchimp_edd_metabox', 'MailChimp', 'mailchimp_edd_render_metabox', 'download', 'normal', 'low' );
	}
	add_action( 'add_meta_boxes', 'mailchimp_edd_create_metabox' );



	/**
	 * Create the metabox default values
	 */
	function mailchimp_edd_metabox_defaults() {
		$list_id = edd_get_option( 'gmt_mailchimp_edd_list_id', false );
		return array(
			'signup' => 'off',
			'list_id' => $list_id,
			'double_optin' => 'off',
			'interests' => array(),
			'tags' => array(),
		);
	}


	/**
	 * Get tag data from the MailChimp API
	 * @param  string $list_id The list ID
	 * @return array           The tag data
	 */
	function mailchimp_edd_metabox_get_mailchimp_tags( $list_id ) {

		// Get the API key
		$api_key = edd_get_option( 'gmt_mailchimp_edd_api_key', false );

		if ( empty( $api_key ) || empty( $list_id ) ) return;

		// Create API call
		$shards = explode( '-', $api_key );
		$url = 'https://' . $shards[1] . '.api.mailchimp.com/3.0/lists/' . $list_id . '/segments?type=static&count=1000';
		$params = array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( 'mailchimp' . ':' . $api_key )
			),
		);

		// Get data from  MailChimp
		$request = wp_remote_get( $url, $params );
		$response = wp_remote_retrieve_body( $request );
		$data = json_decode( $response, true );

		// If request fails, bail
		if ( !is_array($data) || !array_key_exists( 'segments', $data ) || empty($data['segments']) ) return array();

		return $data;

	}



	/**
	 * Get interest group data from the MailChimp API
	 * @param  string $group The group ID
	 * @return array         Data from the MailChimp API
	 */
	function mailchimp_edd_metabox_get_mailchimp_groups( $list_id, $group = null ) {

		// Get the API key
		$api_key = edd_get_option( 'gmt_mailchimp_edd_api_key', false );

		if ( empty( $api_key ) || empty( $list_id ) ) return;

		// Create API call
		$shards = explode( '-', $api_key );
		$url = 'https://' . $shards[1] . '.api.mailchimp.com/3.0/lists/' . $list_id . '/interest-categories' . ( empty( $group ) ? '' : '/' . $group . '/interests?count=99' );
		$params = array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( 'mailchimp' . ':' . $api_key )
			),
		);

		// Get data from  MailChimp
		$request = wp_remote_get( $url, $params );
		$response = wp_remote_retrieve_body( $request );
		$data = json_decode( $response, true );

		// If request fails, bail
		if ( empty( $group ) ) {
			if ( !array_key_exists( 'categories', $data ) || !is_array( $data['categories'] ) || empty( $data['categories'] ) ) return array();
		} else {
			if ( !array_key_exists( 'interests', $data ) || !is_array( $data['interests'] ) || empty( $data['interests'] ) ) return array();
		}

		return $data;

	}



	/**
	 * Render interest groups
	 * @param  array $details  Saved data
	 */
	function mailchimp_edd_metabox_render_interest_groups( $details ) {

		// Variables
		$categories = mailchimp_edd_metabox_get_mailchimp_groups( $details['list_id'] );
		$html = '';

		foreach ( $categories['categories'] as $category ) {
			$html .= '<h4>' . esc_html( $category['title'] ) . '</h4>';
			$groups = mailchimp_edd_metabox_get_mailchimp_groups( $details['list_id'], $category['id'] );

			foreach ( $groups['interests'] as $group ) {
				$html .=
					'<label>' .
						'<input type="checkbox" name="mailchimp_edd[interest_groups][' . esc_attr( $group['id'] ) . ']" value="' . esc_attr( $group['id'] ) . '" ' . ( array_key_exists( $group['id'], $details['interests'] ) ? 'checked="checked"' : '' ) . '>' .
						esc_html( $group['name'] ) .
					'</label>' .
					'<br>';
			}

		}

		echo $html;
	}



	/**
	 * Render tags
	 * @param  array $details  Saved data
	 */
	function mailchimp_edd_metabox_render_tags( $details ) {

		// Variables
		$tags = mailchimp_edd_metabox_get_mailchimp_tags( $details['list_id'] );
		$html = '';

		foreach ($tags['segments'] as $tag) {
			$html .=
				'<label>' .
					'<input type="checkbox" name="mailchimp_edd[tags][' . esc_attr( $tag['id'] ) . ']" value="' . esc_attr( $tag['id'] ) . '" ' . ( array_key_exists( $tag['id'], $details['tags'] ) ? 'checked="checked"' : '' ) . '>' .
					esc_html( $tag['name'] ) .
				'</label>' .
				'<br>';
		}

		echo $html;

	}



	/**
	 * Render the metabox
	 */
	function mailchimp_edd_render_metabox() {

		// Variables
		global $post;
		$saved = get_post_meta( $post->ID, 'mailchimp_edd_details', true );
		$defaults = mailchimp_edd_metabox_defaults();
		$details = wp_parse_args( $saved, $defaults );

		?>

			<fieldset>
				<p><?php _e( 'Add anyone who buys this download to your list with the following details.', 'mailchimp_edd' ); ?></p>

				<label>
					<input type="checkbox" name="mailchimp_edd[signup]" value="on" <?php checked( 'on', $details['signup'] ); ?>>
					<?php _e( 'Subscribe buyers to your list', 'mailchimp_edd' ); ?>
				</label>
				<br><br>

				<div>
					<label for="mailchimp_list_id"><?php _e( 'List ID', 'mailchimp' ); ?></label>
					<input type="text" class="large-text" id="mailchimp_list_id" name="mailchimp_edd[list_id]" value="<?php echo esc_attr( $details['list_id'] ); ?>">
				</div>
				<br>

				<label for="mailchimp_double_optin">
					<input type="checkbox" id="mailchimp_double_optin" name="mailchimp_edd[double_optin]" value="on" <?php checked( 'on', $details['double_optin'] ); ?>>
					<?php _e( 'Enable double opt-in', 'mailchimp_edd' ); ?>
				</label>
				<br><br>

				<h3><?php _e( 'Interest Groups', 'mailchimp' ); ?></h3>

				<?php mailchimp_edd_metabox_render_interest_groups( $details ); ?>

				<br><br>

				<h3><?php _e( 'Tags', 'mailchimp' ); ?></h3>

				<?php mailchimp_edd_metabox_render_tags( $details ); ?>

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

		// Sanitize all data
		$sanitized = array();
		$interests = array();
		$tags = array();
		foreach ( $_POST['mailchimp_edd'] as $key => $detail ) {
			if ( $key === 'interest_groups' ) {
				foreach ($detail as $group) {
					$interests[$group] = 'on';
				}
				continue;
			}
			if ( $key === 'tags' ) {
				foreach ($detail as $tag) {
					$tags[$tag] = 'on';
				}
			}
			if ( $key === 'double_optin' ) {
				$sanitized[$key] = 'on';
				continue;
			}
			$sanitized[$key] = wp_filter_post_kses( $detail );
		}
		$sanitized['interests'] = $interests;
		$sanitized['tags'] = $tags;

		// Update data in database
		update_post_meta( $post->ID, 'mailchimp_edd_details', $sanitized );

	}
	add_action( 'save_post', 'mailchimp_edd_save_metabox', 1, 2 );