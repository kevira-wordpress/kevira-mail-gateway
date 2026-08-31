<?php
declare(strict_types=1);

$root = dirname( __DIR__, 2 );
$main = file_get_contents( $root . '/kevira-mail-gateway.php' );
$changelog = file_get_contents( $root . '/CHANGELOG.md' );
foreach ( array( 'Plugin Name:       Kevira Mail Gateway', 'Version:           0.1.0', 'Update URI:        https://github.com/kevira-wordpress/kevira-releases', 'register_activation_hook', 'plugins_loaded' ) as $needle ) {
	if ( ! str_contains( (string) $main, $needle ) ) {
		throw new RuntimeException( 'Missing plugin contract: ' . $needle );
	}
}
if ( ! str_contains( (string) $changelog, '## [0.1.0]' ) ) {
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
echo "Mail Gateway smoke checks passed.\n";
