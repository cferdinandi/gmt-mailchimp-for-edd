<?php

/**
 * Plugin Name: GMT MailChimp for EDD
 * Plugin URI: https://github.com/cferdinandi/gmt-mailchimp-for-edd/
 * GitHub Plugin URI: https://github.com/cferdinandi/gmt-mailchimp-for-edd/
 * Description: Extends MailChimp integration to Easy Digital Downloads
 * Version: 1.4.3
 * Author: Chris Ferdinandi
 * Author URI: http://gomakethings.com
 * License: MIT
 */


// Add new metabox
require_once( plugin_dir_path( __FILE__ ) . 'includes/metabox.php' );

// MailChimp integration
require_once( plugin_dir_path( __FILE__ ) . 'includes/mailchimp.php' );


// Check that GMT MailChimp plugin is installed
function mailchimp_edd_admin_notice() {
	if ( function_exists( 'mailchimp_add_custom_post_type' ) ) return;
	printf( '<div class="%1$s"><p>%2$s</p></div>', 'notice notice-error', __( 'GMT MailChimp for EDD requires the <a href="https://github.com/cferdinandi/gmt-mailchimp">GMT MailChimp plugin</a>. Please install it immediately.', 'sample-text-domain' ) );
}
add_action( 'admin_notices', 'mailchimp_edd_admin_notice' );