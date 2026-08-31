<?php
declare(strict_types=1);

namespace Kevira\MailGateway;

use Kevira\MailGateway\Scheduling\WordPressScheduler;

final class Installer {
	public static function activate( bool $networkWide = false ): void {
		if ( is_multisite() && $networkWide ) {
			foreach ( get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			) as $siteId ) {
				switch_to_blog( (int) $siteId );
				self::installTable();
				restore_current_blog();
			}
			return;
		}
		self::installTable();
	}

	public static function deactivate(): void {
		( new WordPressScheduler() )->clear();
	}

	public static function installTable(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = $wpdb->prefix . 'kevira_mail_outbox';
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			idempotency_key varchar(64) NOT NULL,
			payload_encrypted longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			attempts smallint(5) unsigned NOT NULL DEFAULT 0,
			available_at datetime NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			last_error varchar(500) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			UNIQUE KEY idempotency_key (idempotency_key),
			KEY ready (status,available_at),
			KEY cleanup (status,updated_at)
		) {$charset};";
		dbDelta( $sql );
		update_option( 'kevira_mail_gateway_db_version', KEVIRA_MAIL_GATEWAY_VERSION, false );
	}
}
