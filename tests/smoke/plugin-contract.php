<?php
declare(strict_types=1);

$root = dirname( __DIR__, 2 );
$main = file_get_contents( $root . '/kevira-mail-gateway.php' );
$changelog = file_get_contents( $root . '/CHANGELOG.md' );
foreach ( array( 'Plugin Name:       Kevira Mail Gateway', 'Version:           0.2.1', 'Update URI:        https://github.com/kevira-wordpress/kevira-releases', 'register_activation_hook', 'plugins_loaded' ) as $needle ) {
	if ( ! str_contains( (string) $main, $needle ) ) {
		throw new RuntimeException( 'Missing plugin contract: ' . $needle );
	}
}
if ( ! str_contains( (string) $changelog, '## [0.2.1]' ) ) {
	throw new RuntimeException( 'Release changelog is missing.' );
}
$plugin = file_get_contents( $root . '/src/Plugin.php' );
$actions = file_get_contents( $root . '/src/Admin/Actions.php' );
foreach ( array( "'kevira-mail'", "'kevira-mail queue'" ) as $command ) {
	if ( ! str_contains( (string) $plugin, $command ) ) { throw new RuntimeException( 'Missing CLI contract: ' . $command ); }
}
if ( ! str_contains( (string) $actions, 'check_admin_referer' ) || ! str_contains( (string) $actions, 'manage_options' ) ) {
	throw new RuntimeException( 'Admin security contract is missing.' );
}
$queueCommands = file_get_contents( $root . '/src/Cli/QueueCommands.php' );
foreach ( array( 'WP_CLI::confirm', 'MAX_FAILED_RETRY_BATCH', 'purgeFailed' ) as $contract ) {
	if ( ! str_contains( (string) $queueCommands, $contract ) ) { throw new RuntimeException( 'Queue CLI safety contract is missing: ' . $contract ); }
}
$config = file_get_contents( $root . '/src/Config.php' );
foreach ( array( 'KEVIRA_MAIL_QUEUE_KEY_FILE', 'ABSPATH', 'WP_CONTENT_DIR', 'wp_upload_dir', 'KEVIRA_MAIL_GATEWAY_PATH', 'is_link', '0022' ) as $contract ) {
	if ( ! str_contains( (string) $config, $contract ) ) { throw new RuntimeException( 'Key-file hardening contract is missing: ' . $contract ); }
}
$menu = file_get_contents( $root . '/src/Admin/Menu.php' );
$page = file_get_contents( $root . '/src/Admin/Page.php' );
foreach ( array( "add_submenu_page(\n\t\t\tself::PARENT_SLUG", 'kevira-mail-gateway', 'menuIcon' ) as $contract ) {
	if ( ! str_contains( (string) $menu, $contract ) ) { throw new RuntimeException( 'Shared Kevira menu contract is missing: ' . $contract ); }
}
foreach ( array( 'kevira-admin__header', 'kevira-admin__products', 'productNavigation', 'درگاه ایمیل کویرا' ) as $contract ) {
	if ( ! str_contains( (string) $page, $contract ) ) { throw new RuntimeException( 'Shared Kevira design contract is missing: ' . $contract ); }
}
echo "Mail Gateway smoke checks passed.\n";
