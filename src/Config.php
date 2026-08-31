<?php
declare(strict_types=1);
namespace Kevira\MailGateway;

final class Config {
	public const DEFAULT_SECRET_PATH = '/run/secrets/kevira_mail_gateway_token';
	public function __construct(
		private readonly string $gatewayUrl,
		private readonly string $clientId,
		private readonly string $secretPath,
		private readonly string $senderProfile,
		private readonly string $environment = 'production'
	) {}
	public static function fromEnvironment(): self {
		return new self(
			defined( 'KEVIRA_MAIL_GATEWAY_URL' ) ? trim( (string) KEVIRA_MAIL_GATEWAY_URL ) : '',
			defined( 'KEVIRA_MAIL_CLIENT_ID' ) ? trim( (string) KEVIRA_MAIL_CLIENT_ID ) : '',
			defined( 'KEVIRA_MAIL_SECRET_FILE' ) ? trim( (string) KEVIRA_MAIL_SECRET_FILE ) : self::DEFAULT_SECRET_PATH,
			defined( 'KEVIRA_MAIL_SENDER_PROFILE' ) ? trim( (string) KEVIRA_MAIL_SENDER_PROFILE ) : 'default',
			function_exists( 'wp_get_environment_type' ) ? (string) wp_get_environment_type() : 'production'
		);
	}
	/** @return list<string> */
	public function errors(): array {
		$errors = array();
		if ( '' === $this->gatewayUrl || ! filter_var( $this->gatewayUrl, FILTER_VALIDATE_URL ) ) {
			$errors[] = 'gateway_url_missing'; } else {
			$parts  = wp_parse_url( $this->gatewayUrl );
			$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
			$host   = strtolower( (string) ( $parts['host'] ?? '' ) );
			$local  = in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true );
			if ( 'https' !== $scheme && ( 'production' === $this->environment || ! $local ) ) {
				$errors[] = 'gateway_https_required'; }
			if ( isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
				$errors[] = 'gateway_url_invalid'; }
			}
			if ( ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{2,127}$/', $this->clientId ) ) {
				$errors[] = 'client_id_missing'; }
			if ( ! preg_match( '/^[a-z0-9][a-z0-9_-]{0,63}$/', $this->senderProfile ) ) {
				$errors[] = 'sender_profile_invalid'; }
			try {
				$this->secret();
			} catch ( \RuntimeException ) {
				$errors[] = 'secret_unavailable'; }
			return array_values( array_unique( $errors ) );
	}
	public function isComplete(): bool {
		return array() === $this->errors(); }
	public function gatewayUrl(): string {
		return rtrim( $this->gatewayUrl, '/' ); }
	public function endpoint( string $path ): string {
		if ( ! str_starts_with( $path, '/v1/' ) ) {
			throw new \InvalidArgumentException( 'Only versioned Gateway paths are allowed.' ); }
		return $this->gatewayUrl() . $path;
	}
	public function clientId(): string {
		return $this->clientId; }
	public function senderProfile(): string {
		return $this->senderProfile; }
	public function siteId(): string {
		return strtolower( preg_replace( '/[^a-z0-9_-]+/i', '-', $this->clientId ) ?? 'WordPress' ); }
	public function secret(): string {
		$path = realpath( $this->secretPath );
		if ( false === $path || ! is_file( $path ) || ! is_readable( $path ) ) {
			throw new \RuntimeException( 'Mail Gateway secret is unavailable.' ); }
		$size = filesize( $path );
		if ( false === $size || $size < 32 || $size > 4096 ) {
			throw new \RuntimeException( 'Mail Gateway secret file is invalid.' ); }
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local secret file, never a URL.
		$secret = file_get_contents( $path );
		$secret = is_string( $secret ) ? trim( $secret ) : '';
		if ( strlen( $secret ) < 32 ) {
			throw new \RuntimeException( 'Mail Gateway secret is invalid.' ); }
		return $secret;
	}
}
