<?php

	/**
	 * Add subscriber to MailChimp
	 * @param  array  $details  The MailChimp details
	 * @param  string $subscriber The subscriber email address
	 */
	function mailchimp_edd_add_to_mailchimp( $details, $subscriber ) {

		// Create interest groups array
		$interests = new stdClass();
		if ( !empty( $details['interests'] ) ) {
			foreach ( $details['interests'] as $key => $group ) {
				$interests->$key = true;
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
		$settings = edd_get_option( 'gmt_mailchimp_discount_code_groups', false );
		$settings_arr = explode(',', $settings);
		$codes = array();

		// Create codes array
		foreach ($settings_arr as $value) {
			$code = explode(':', trim($value));
			$codes[strtolower($code[0])] = $code[1];
		}

		return $codes;

	}

	/**
	 * Get discount codes that should be added to tags
	 * @return array The discount codes and their matching tags
	 */
	function mailchimp_edd_get_discount_tags() {

		// Variables
		$settings = edd_get_option( 'gmt_mailchimp_discount_code_tags', false );
		$settings_arr = explode(',', $settings);
		$codes = array();

		// Create codes array
		foreach ($settings_arr as $value) {
			$code = explode(':', trim($value));
			$codes[strtolower($code[0])] = $code[1];
		}

		return $codes;

	}

	/**
	 * Get the interest groups that apply to a user based on their discount codes
	 * @param  array $user_codes The discount codes that were used
	 * @return array             The groups they should be added to
	 */
	function mailchimp_edd_get_discount_groups_for_user($user_codes) {

		// Variables
		$discounts = mailchimp_edd_get_discount_interest_groups();
		$groups = array();

		// Get each group for the user
		foreach ($user_codes as $value) {
			$val = trim(strtolower($value));
			if (array_key_exists($val, $discounts)) {
				$groups[$discounts[$val]] = 'on';
			}
		}

		return $groups;

	}

	/**
	 * Get the tags that apply to a user based on their discount codes
	 * @param  array $user_codes The discount codes that were used
	 * @return array             The tags they should be added to
	 */
	function mailchimp_edd_get_discount_tags_for_user($user_codes) {

		// Variables
		$discounts = mailchimp_edd_get_discount_tags();
		$tags = array();

		// Get each group for the user
		foreach ($user_codes as $value) {
			$val = trim(strtolower($value));
			if (array_key_exists($val, $discounts)) {
				$tags[$discounts[$val]] = 'on';
			}
		}

		return $tags;

	}

	/**
	 * Merge discount code interest groups and tags into product-specific ones
	 * @param  array $groups  Discount code interest groups
	 * @param  array $tags    Discount code tags
	 * @param  array $details Subscriber details
	 * @return array          Merged details
	 */
	function mailchimp_edd_merge_groups_and_tags($groups, $tags, $details) {

		// Merge interest groups
		if (empty($details['interests'])) {
			$details['interests'] = $groups;
		} else {
			$details['interests'] = array_merge($details['interests'], $groups);
		}

		// Merge tags
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
		$user_codes = explode(',', $purchase['user_info']['discount']);
		$discount_groups = mailchimp_edd_get_discount_groups_for_user($user_codes);
		$discount_tags = mailchimp_edd_get_discount_tags_for_user($user_codes);

		// For each download, add subscriber to the list
		foreach( $purchase['downloads'] as $key => $download ) {
			$details = get_post_meta( $download['id'], 'mailchimp_edd_details', true );
			if ( empty( $details['signup'] ) || $details['signup'] === 'off' || empty( $details['list_id'] ) || empty( $purchase['user_info']['email'] ) ) continue;
			$details['tags'] = $discount_tags;
			$details = mailchimp_edd_merge_groups_and_tags($discount_groups, $discount_tags, $details);
			$mailchimp = mailchimp_edd_add_to_mailchimp( $details, $purchase['user_info'] );
		}

	}
	add_action( 'edd_complete_purchase', 'mailchimp_edd_on_complete_purchase', 10, 2 );