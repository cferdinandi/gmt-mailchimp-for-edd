<?php

	/**
	 * Add subscriber to MailChimp
	 * @param  array  $mailchimp  The MailChimp integration data
	 * @param  string $subscriber The subscriber email address
	 */
	function mailchimp_edd_add_to_mailchimp( $mailchimp, $subscriber, $no_opt_in ) {

		// Create API call
		$options = mailchimp_get_theme_options();
		$shards = explode( '-', $options['mailchimp_api_key'] );
		$url = 'https://' . $shards[1] . '.api.mailchimp.com/3.0/lists/' . $mailchimp['list_id'] . '/members';
		$params = array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( 'mailchimp' . ':' . $options['mailchimp_api_key'] )
			),
			'body' => json_encode(array(
				'status' => ( $no_opt_in === 'on' ? 'subscribed' : 'pending' ),
				'email_address' => $subscriber,
				'interests' => ( !array_key_exists( 'group', $mailchimp ) || empty( $mailchimp['group'] ) ? new stdClass() : array( $mailchimp['group'] => true ) ),
			)),
		);

		// Add subscriber
		$request = wp_remote_post( $url, $params );
		$response = wp_remote_retrieve_body( $request );
		$data = json_decode( $response, true );

		// If subscriber already exists, update profile
		if ( array_key_exists( 'status', $data ) && $data['status'] === 400 && $data['title'] === 'Member Exists' ) {

			$url .= '/' . md5( $subscriber );
			$params = array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( 'mailchimp' . ':' . $options['mailchimp_api_key'] )
				),
				'method' => 'PUT',
				'body' => json_encode(array(
					'interests' => ( !array_key_exists( 'group', $mailchimp ) || empty( $mailchimp['group'] ) ? new stdClass() : array( $mailchimp['group'] => true ) ),
				)),
			);
			$request = wp_remote_post( $url, $params );
			$response = wp_remote_retrieve_body( $request );

			// If still pending, return "new" status again
			if ( array_key_exists( 'status', $data ) && $data['status'] === 'pending' ) return 'new';

			return 'updated';

		}

		// If something went wrong, throw an error
		if ( array_key_exists( 'status', $data ) && $data['status'] === 404 ) return 'error';

		return 'new';

	}


	/**
	 * Add buyer to MailChimp after purchase is complete
	 * @param  Integer $payment_id ID for the purchase
	 */
	function mailchimp_edd_on_complete_purchase(  $payment_id  ) {

		// Get purchase data
		$purchase = edd_get_payment_meta( $payment_id );

		// Get downloads and discounts
		$downloads = get_posts(
			array(
				'posts_per_page'   => -1,
				'post_type'        => 'gmt-mailchimp',
				'post_status'      => array( 'publish', 'active' ),
				'meta_query' => array(
					'relation' => 'OR',
					array(
						'key' => 'mailchimp_edd_download',
						'value' => $purchase['downloads'][0]['id'],
						'compare' => '='
					),
					array(
						'key' => 'mailchimp_edd_discount',
						'value' => $purchase['cart_details'][0]['discount'],
						'compare' => '='
					)
				)
			)
		);

		// For each integration, add to MailChimp
		foreach ( $downloads as $key => $download ) {
			$details = get_post_meta( $download->ID, 'mailchimp_details', true );
			$no_opt_in = get_post_meta( $download->ID, 'mailchimp_edd_double_opt_in', true );
			if ( empty( $details['list_id'] ) || empty( $purchase['user_info']['email'] ) ) continue;
			mailchimp_edd_add_to_mailchimp( $details, $purchase['user_info']['email'], $no_opt_in );
		}

	}
	add_action( 'edd_complete_purchase', 'mailchimp_edd_on_complete_purchase', 10, 2 );