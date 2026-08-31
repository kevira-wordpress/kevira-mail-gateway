<?php
declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'KEVIRA_MAIL_CLEANUP_ON_UNINSTALL' ) || true !== KEVIRA_MAIL_CLEANUP_ON_UNINSTALL ) {
	return;
}

$kevira_mail_gateway_cleanup = static function (): void {
	global $wpdb;
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'kevira_mail_outbox' );
	delete_option( 'kevira_mail_gateway_db_version' );
	delete_option( 'kevira_mail_gateway_worker_lock' );
	delete_option( 'kevira_mail_gateway_last_accepted' );
	delete_option( 'kevira_mail_gateway_last_failure' );
};

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $kevira_mail_gateway_site_id ) {
		switch_to_blog( (int) $kevira_mail_gateway_site_id );
		$kevira_mail_gateway_cleanup();
		restore_current_blog();
	}
} else {
	$kevira_mail_gateway_cleanup();
}
