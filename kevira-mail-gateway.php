<?php
/**
 * Plugin Name:       Kevira Mail Gateway
 * Plugin URI:        https://k1mirani.ir/plugins/kevira-mail-gateway
 * Description:       Securely routes WordPress transactional email through the separate Kevira Mail Gateway service.
 * Version:           0.2.1
 * Requires at least: 6.9
 * Tested up to:      7.1
 * Requires PHP:      8.1
 * Author:            k1mirani
 * Author URI:        https://k1mirani.ir/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       kevira-mail-gateway
 * Domain Path:       /languages
 * Update URI:        https://github.com/kevira-wordpress/kevira-releases
 *
 * @package Kevira_Mail_Gateway
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

define( 'KEVIRA_MAIL_GATEWAY_VERSION', '0.2.1' );
define( 'KEVIRA_MAIL_GATEWAY_FILE', __FILE__ );
define( 'KEVIRA_MAIL_GATEWAY_PATH', plugin_dir_path( __FILE__ ) );
define( 'KEVIRA_MAIL_GATEWAY_ASSET_URL', plugin_dir_url( __FILE__ ) );

$kevira_mail_gateway_autoloader = KEVIRA_MAIL_GATEWAY_PATH . 'vendor/autoload.php';
if ( ! is_readable( $kevira_mail_gateway_autoloader ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Kevira Mail Gateway could not start because its production autoloader is missing.', 'kevira-mail-gateway' ) . '</p></div>';
			}
		}
	);
	return;
}

require_once $kevira_mail_gateway_autoloader;

register_activation_hook( __FILE__, array( \Kevira\MailGateway\Installer::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \Kevira\MailGateway\Installer::class, 'deactivate' ) );
add_action(
	'plugins_loaded',
	static function (): void {
		( new \Kevira\MailGateway\Plugin() )->register();
	},
	20
);
