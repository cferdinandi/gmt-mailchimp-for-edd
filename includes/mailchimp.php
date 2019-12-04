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
		$url = 'https://' . $shards[1] . '.api.mailchimp.com/3.0/lists/' . $details['list_id'] . '/members/' . md5( $subscriber['email'] );
		$params = array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( 'mailchimp' . ':' . $api_key )
			),
			'method' => 'PUT',
			'body' => json_encode(array(
				'status' => ( array_key_exists('double_optin', $details) && $details['double_optin'] === 'on' ? 'pending' : 'subscribed' ),
				'status_if_new' => ( array_key_exists('double_optin', $details) && $details['double_optin'] === 'on' ? 'pending' : 'subscribed' ),
				'email_address' => $subscriber['email'],
				'merge_fields' => array(
					'FNAME' => $subscriber['first_name'],
					'LNAME' => $subscriber['last_name'],
				),
				'interests' => $interests,
			)),
		);

		// Add subscriber
		$request = wp_remote_request( $url, $params );
		$response = wp_remote_retrieve_body( $request );
		$data = json_decode( $response, true );

		// If something went wrong, throw an error
		if ( array_key_exists( 'status', $data ) && $data['status'] >= 404 ) return false;

		// Add tags to the user
		if (!empty($details['tags'])) {
			$params['method'] = 'POST';
			$params['body'] = json_encode(array('email_address' => $subscriber['email']));
			foreach ($details['tags'] as $tag => $val) {
				$request = wp_remote_request( 'https://' . $shards[1] . '.api.mailchimp.com/3.0/lists/' . $details['list_id'] . '/segments/' . $tag . '/members', $params );
			}
		}

		return true;

	}

	/**
	 * Get discount codes that should be added to interest groups
	 * @return array The discount codes and their matching interest groups
	 */
	function mailchimp_edd_get_discount_interest_groups() {

		// Variables
		$settings = edd_get_option( 'gmt_mailchimp_discount_codes', false );
		$settings_arr = explode(',', $settings);
		$codes = array();

		// Create codes array
		foreach ($settings_arr as $value) {
			$code = explode(':', trim($value));
			$codes[strtolower($code[0])] = $code[1];
		}

		return $codes;

	}

	function mailchimp_edd_get_discount_tags_for_user($user_codes) {

		// Variables
		$discounts = mailchimp_edd_get_discount_interest_groups();
		$user_codes_arr = explode(',', $user_codes);
		$tags = array();

		// Get each group for the user
		foreach ($user_codes_arr as $value) {
			$val = trim(strtolower($value));
			if (array_key_exists($val, $discounts)) {
				$tags[$discounts[$val]] = 'on';
			}
		}

		return $tags;

	}


	function mailchimp_edd_merge_tags($tags, $details) {
		if (empty($details['tags'])) {
			$details['tags'] = $tags;
		} else {
			$details['tags'] = array_merge($details['tags'], $tags);
		}
		return $details;
	}


	/**
	 * Add buyer to MailChimp after purchase is complete
	 * @param  Integer $payment_id ID for the purchase
	 */
	function mailchimp_edd_on_complete_purchase( $payment_id ) {

		// Get purchase data
		$purchase = edd_get_payment_meta( $payment_id );

		// Discount interest groups
		$discount_tags = mailchimp_edd_get_discount_tags_for_user($purchase['user_info']['discount']);

		// For each download, add subscriber to the list
		foreach( $purchase['downloads'] as $key => $download ) {
			$details = get_post_meta( $download['id'], 'mailchimp_edd_details', true );
			if ( $details['signup'] === 'off' || empty( $details['list_id'] ) || empty( $purchase['user_info']['email'] ) ) continue;
			$details['tags'] = $discount_tags;
			$details = mailchimp_edd_merge_tags($discount_tags, $details);
			$mailchimp = mailchimp_edd_add_to_mailchimp( $details, $purchase['user_info'] );
		}

	}
	add_action( 'edd_complete_purchase', 'mailchimp_edd_on_complete_purchase', 10, 2 );