<?php

	/**
	 * Add subscriber to MailChimp
	 * @param  array  $details  The MailChimp details
	 * @param  string $subscriber The subscriber email address
	 */
	function mailchimp_edd_add_to_mailchimp( $details, $subscriber ) {

		// Create interest groups array
		if ( empty( $details['interests'] ) ) {
			$interests = new stdClass();
		} else {
			$interests = array();
			foreach ( $details['interests'] as $key => $group ) {
				$interests[$key] = true;
			}
		}

		// Create API call
		$api_key = edd_get_option( 'gmt_mailchimp_edd_api_key', false );
		$shards = explode( '-', $api_key );
		$url = 'https://' . $shards[1] . '.api.mailchimp.com/3.0/lists/' . $details['list_id'] . '/members';
		$params = array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( 'mailchimp' . ':' . $api_key )
			),
			'body' => json_encode(array(
				'status' => ( array_key_exists('double_optin', $details) && $details['double_optin'] === 'on' ? 'pending' : 'subscribed' ),
				'email_address' => $subscriber['email'],
				'merge_fields' => array(
					'FNAME' => $subscriber['first_name'],
					'LNAME' => $subscriber['last_name'],
				),
				'interests' => $interests,
			)),
		);

		// Add subscriber
		$request = wp_remote_post( $url, $params );
		$response = wp_remote_retrieve_body( $request );
		$data = json_decode( $response, true );

		// If subscriber already exists, update profile
		if ( array_key_exists( 'status', $data ) && $data['status'] === 400 && $data['title'] === 'Member Exists' ) {

			$url .= '/' . md5( $subscriber['email'] );
			$params = array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( 'mailchimp' . ':' . $api_key )
				),
				'method' => 'PUT',
				'body' => json_encode(array(
					'interests' => $interests,
				)),
			);
			$request = wp_remote_request( $url, $params );
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
	function mailchimp_edd_on_complete_purchase( $payment_id ) {

		// Get purchase data
		$purchase = edd_get_payment_meta( $payment_id );

		// For each download, add subscriber to the list
		foreach( $purchase['downloads'] as $key => $download ) {
			$details = get_post_meta( $download['id'], 'mailchimp_edd_details', true );
			if ( $details['signup'] === 'off' || empty( $details['list_id'] ) || empty( $purchase['user_info']['email'] ) ) continue;
			$mailchimp = mailchimp_edd_add_to_mailchimp( $details, $purchase['user_info'] );
		}

	}
	add_action( 'edd_complete_purchase', 'mailchimp_edd_on_complete_purchase', 10, 2 );



	/**
	 * Update/remove a subscriber from MailChimp
	 * @param  array  $details    The MailChimp details
	 * @param  string $email      The subscriber email address
	 */
	function mailchimp_edd_remove_from_mailchimp( $details, $email ) {

		// If there are no updates to make, just bail
		if ( empty( $details['interests'] ) && empty( $details['on_cancel'] ) ) return;
		if ( $details['interests'] === $details['on_cancel'] ) return;

		// Create interest groups array
		$interests = array();
		foreach ( $details['interests'] as $key => $group ) {
			$interests[$key] = false;
		}
		foreach ( $details['on_cancel'] as $key => $group ) {
			$interests[$key] = true;
		}

		// Create API call
		$api_key = edd_get_option( 'gmt_mailchimp_edd_api_key', false );
		$shards = explode( '-', $api_key );
		$url = 'https://' . $shards[1] . '.api.mailchimp.com/3.0/lists/' . $details['list_id'] . '/members/' . md5( $email );
		$params = array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( 'mailchimp' . ':' . $api_key )
			),
			'method' => 'PUT',
			'body' => json_encode(array(
				'interests' => $interests,
			)),
		);

		// Update subscriber
		$request = wp_remote_post( $url, $params );
		$response = wp_remote_retrieve_body( $request );
		$data = json_decode( $response, true );

		// If something went wrong, throw an error
		if ( array_key_exists( 'status', $data ) && $data['status'] === 404 ) return 'error';

		return 'updated';

	}



	/**
	 * Update subscriber in MailChimp after canceled subscription
	 * @param  Integer $payment_id ID for the purchase
	 */
	function mailchimp_edd_on_cancel_subscription( $subscription_id = 0 ) {

		// Get the subscription
		$subscription = new EDD_Subscription( $subscription_id );

		// Get Mailchimp details
		$details = get_post_meta( $subscription->product_id, 'mailchimp_edd_details', true );
		if ( $details['signup'] === 'off' || empty( $details['list_id'] ) || empty( $purchase['user_info']['email'] ) ) return;

		// Update subscription
		$mailchimp = mailchimp_edd_remove_from_mailchimp( $details, $subscription->customer->email );

	}
	add_action( 'edd_subscription_cancelled', 'mailchimp_edd_on_cancel_subscription', 10, 2 );



	/**
	 * Update subscriber in MailChimp after refund
	 * @param  Integer $payment_id ID for the purchase
	 */
	function mailchimp_edd_on_refund( $payment_id, $new_status, $old_status ) {

		global $edd_options;

		// Make sure update should happen
		if ( empty( $_POST['edd_refund_in_stripe'] ) ) return;
		$should_process_refund = 'publish' != $old_status && 'revoked' != $old_status ? false : true;
		$should_process_refund = apply_filters( 'edds_should_process_refund', $should_process_refund, $payment_id, $new_status, $old_status );
		if ( false === $should_process_refund ) return;
		if ( 'refunded' != $new_status ) return;

		// Get purchase data
		$purchase = edd_get_payment_meta( $payment_id );

		// For each download, update subscriber
		foreach( $purchase['downloads'] as $key => $download ) {
			$details = get_post_meta( $download['id'], 'mailchimp_edd_details', true );
			if ( $details['signup'] === 'off' || empty( $details['list_id'] ) || empty( $purchase['user_info']['email'] ) ) continue;
			$mailchimp = mailchimp_edd_remove_from_mailchimp( $details, $purchase['user_info']['email'] );
		}

	}
	add_action( 'edd_update_payment_status', 'mailchimp_edd_on_refund', 200, 3 );