<?php
/**
 * Runs when the user clicks "Delete" on the Plugins screen.
 * WordPress calls this file automatically - never loaded on normal requests.
 *
 * Only removes data if the user has explicitly opted in via the settings.
 *
 * @package LivQ_AccessFix
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Always clear the scanner's scheduled cron on uninstall, regardless of the
// data-deletion preference - a scheduled event must never outlive the plugin.
wp_clear_scheduled_hook( 'livqacea_daily_scan' );

$livqacea_options = get_option( 'livqacea_options', array() );

if ( ! empty( $livqacea_options['delete_on_uninstall'] ) ) {
	delete_option( 'livqacea_options' );
	delete_transient( 'livqacea_scan_results_v1' );

	// Remove post_meta written by the heading hierarchy checker and statement module.
	delete_post_meta_by_key( '_livqacea_a11y_issues' );
	delete_post_meta_by_key( '_livqacea_statement_page' );
}
