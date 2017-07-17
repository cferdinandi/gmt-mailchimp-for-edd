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
				'status' => ( $details['disable_optin'] === 'on' ? 'subscribed' : 'pending' ),
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

		set_transient( 'edd_mc_test_3', 'It worked!', 1 * HOUR_IN_SECONDS );

	}
	add_action( 'wp_async_edd_complete_purchase', 'mailchimp_edd_on_complete_purchase', 10, 2 );


	/**
	 * Asynchronously run the MailChimp API functions
	 */
	class MailChimp_EDD_Async_Task extends WP_Async_Task {

		protected $action = 'edd_complete_purchase';

		/**
		 * Prepare data for the asynchronous request
		 *
		 * @throws Exception If for any reason the request should not happen
		 *
		 * @param array $data An array of data sent to the hook
		 *
		 * @return array
		 */
		protected function prepare_data( $data ) {
			return array( 'payment_id' => $data[0] );
		}

		/**
		 * Run the async task action
		 */
		protected function run_action() {
			do_action( "wp_async_$this->action", $_POST['payment_id'] );
		}

	}


	/**
	 * Initialize our extended class
	 */
	function init_mc_edd_async() {
	    new MailChimp_EDD_Async_Task();
	}
	add_action( 'plugins_loaded', 'init_mc_edd_async' );