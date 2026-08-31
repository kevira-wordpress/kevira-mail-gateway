<?php
declare(strict_types=1);

require dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! defined( 'WEEK_IN_SECONDS' ) ) { define( 'WEEK_IN_SECONDS', 604800 ); }

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $value ): string {
		return trim( preg_replace( '/[^\pL\pN._:-]+/u', '', $value ) ?? '' );
	}
}
if ( ! function_exists( 'wp_parse_url' ) ) { function wp_parse_url( string $url ): array|false { return parse_url( $url ); } }
if ( ! function_exists( 'wp_json_encode' ) ) { function wp_json_encode( mixed $value, int $flags = 0, int $depth = 512 ): string|false { return json_encode( $value, $flags, $depth ); } }
if ( ! function_exists( 'wp_strip_all_tags' ) ) { function wp_strip_all_tags( string $value ): string { return strip_tags( $value ); } }
if ( ! function_exists( 'wp_upload_dir' ) ) { function wp_upload_dir(): array { return array( 'basedir' => sys_get_temp_dir() . '/kmg-uploads' ); } }
if ( ! function_exists( 'get_temp_dir' ) ) { function get_temp_dir(): string { return sys_get_temp_dir(); } }
if ( ! function_exists( 'sanitize_file_name' ) ) { function sanitize_file_name( string $name ): string { return basename( $name ); } }
if ( ! function_exists( 'wp_check_filetype_and_ext' ) ) { function wp_check_filetype_and_ext( string $path, string $name ): array { return array( 'type' => 'text/plain' ); } }
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public function __construct( private readonly string $code = '', private readonly string $message = '' ) {}
		public function get_error_message(): string { return $this->message; }
	}
}
if ( ! function_exists( 'is_wp_error' ) ) { function is_wp_error( mixed $value ): bool { return $value instanceof WP_Error; } }
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) { function wp_remote_retrieve_response_code( array $response ): int { return (int) ( $response['response']['code'] ?? 0 ); } }
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) { function wp_remote_retrieve_body( array $response ): string { return (string) ( $response['body'] ?? '' ); } }
$GLOBALS['kmg_test_options'] = array();
if ( ! function_exists( 'add_option' ) ) { function add_option( string $name, mixed $value ): bool { if ( array_key_exists( $name, $GLOBALS['kmg_test_options'] ) ) { return false; } $GLOBALS['kmg_test_options'][ $name ] = $value; return true; } }
if ( ! function_exists( 'get_option' ) ) { function get_option( string $name, mixed $default = false ): mixed { return $GLOBALS['kmg_test_options'][ $name ] ?? $default; } }
if ( ! function_exists( 'delete_option' ) ) { function delete_option( string $name ): bool { unset( $GLOBALS['kmg_test_options'][ $name ] ); return true; } }
