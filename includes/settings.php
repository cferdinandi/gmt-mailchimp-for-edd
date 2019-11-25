<?php

	/**
	 * Add settings section
	 * @param array $sections The current sections
	 */
	function gmt_mailchimp_edd_settings_section( $sections ) {
		$sections['gmt_mailchimp_edd'] = __( 'MailChimp', 'mailchimp_edd' );
		return $sections;
	}
	add_filter( 'edd_settings_sections_extensions', 'gmt_mailchimp_edd_settings_section' );



	/**
	 * Add settings
	 * @param  array $settings The existing settings
	 */
	function gmt_mailchimp_edd_settings( $settings ) {

		$mailchimp_settings = array(
			array(
				'id'      => 'gmt_mailchimp_edd_api_key',
				'name'    => __( 'API Key', 'mailchimp_edd' ),
				'desc'    => __( 'Your MailChimp API key', 'mailchimp_edd' ),
				'type'    => 'text',
				'size'    => 'regular',
			),
			array(
				'id'      => 'gmt_mailchimp_edd_list_id',
				'name'    => __( 'Default List ID', 'mailchimp_edd' ),
				'desc'    => __( 'The default list ID to use', 'mailchimp_edd' ),
				'type'    => 'text',
				'size'    => 'regular',
			),
			array(
				'id'      => 'gmt_mailchimp_discount_codes',
				'name'    => __( 'Discount Codes', 'mailchimp_edd' ),
				'desc'    => __( 'Group to add a customer to if they use a specific discount code. Use a <code>CODE:GROUP_ID</code> format. Use a comma for multiple items.', 'mailchimp_edd' ),
				'type'    => 'textarea',
				'size'    => 'regular',
			)
		);
		if ( version_compare( EDD_VERSION, 2.5, '>=' ) ) {
			$mailchimp_settings = array( 'gmt_mailchimp_edd' => $mailchimp_settings );
		}
		return array_merge( $settings, $mailchimp_settings );
	}
	add_filter( 'edd_settings_extensions', 'gmt_mailchimp_edd_settings', 999, 1 );