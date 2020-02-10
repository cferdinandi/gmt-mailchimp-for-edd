<?php

/**
 * Plugin Name: GMT MailChimp for EDD
 * Plugin URI: https://github.com/cferdinandi/gmt-mailchimp-for-edd/
 * GitHub Plugin URI: https://github.com/cferdinandi/gmt-mailchimp-for-edd/
 * Description: Adds deep MailChimp integration to Easy Digital Downloads.
 * Version: 3.4.1
 * Author: Chris Ferdinandi
 * Author URI: http://gomakethings.com
 * License: MIT
 */


// Define constants
define( 'GMT_MAILCHIMP_FOR_EDD_VERSION', '3.2.0' );


// Includes
require_once( plugin_dir_path( __FILE__ ) . 'includes/settings.php' );
require_once( plugin_dir_path( __FILE__ ) . 'includes/metabox.php' );
require_once( plugin_dir_path( __FILE__ ) . 'includes/mailchimp.php' );


/**
 * Check the plugin version and make updates if needed
 */
function gmt_mailchimp_for_edd_check_version() {

	// Get plugin data
	$old_version = get_site_option( 'gmt_mailchimp_for_edd_version' );

	// Update plugin to current version number
	if ( empty( $old_version ) || version_compare( $old_version, GMT_MAILCHIMP_FOR_EDD_VERSION, '<' ) ) {
		update_site_option( 'gmt_mailchimp_version', GMT_MAILCHIMP_FOR_EDD_VERSION );
	}

}
add_action( 'plugins_loaded', 'gmt_mailchimp_for_edd_check_version' );