<?php
declare(strict_types=1);
namespace Kevira\MailGateway;

final class Config {
	public const DEFAULT_SECRET_PATH = '/run/secrets/kevira_mail_gateway_token';
	private const MAX_KEY_FILE_BYTES = 4096;

	/** @param list<string> $additionalProtectedRoots */
	public function __construct(
		private readonly string $gatewayUrl,
		private readonly string $clientId,
		private readonly string $secretPath,
		private readonly string $senderProfile,
		private readonly string $environment = 'production',
		private readonly string $queueKeyPath = '',
		private readonly array $additionalProtectedRoots = array(),
		private readonly ?int $requiredKeyOwnerUid = 0
	) {}
	public static function fromEnvironment(): self {
		return new self(
			defined( 'KEVIRA_MAIL_GATEWAY_URL' ) ? trim( (string) KEVIRA_MAIL_GATEWAY_URL ) : '',
			defined( 'KEVIRA_MAIL_CLIENT_ID' ) ? trim( (string) KEVIRA_MAIL_CLIENT_ID ) : '',
			defined( 'KEVIRA_MAIL_SECRET_FILE' ) ? trim( (string) KEVIRA_MAIL_SECRET_FILE ) : self::DEFAULT_SECRET_PATH,
			defined( 'KEVIRA_MAIL_SENDER_PROFILE' ) ? trim( (string) KEVIRA_MAIL_SENDER_PROFILE ) : 'transactional',
			function_exists( 'wp_get_environment_type' ) ? (string) wp_get_environment_type() : 'production',
			defined( 'KEVIRA_MAIL_QUEUE_KEY_FILE' ) ? trim( (string) KEVIRA_MAIL_QUEUE_KEY_FILE ) : ''
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
			if ( ! preg_match( '/^[a-z0-9_-]{3,64}$/D', $this->clientId ) ) {
				$errors[] = 'client_id_invalid'; }
			if ( ! preg_match( '/^[a-z0-9][a-z0-9_-]{0,63}$/', $this->senderProfile ) ) {
				$errors[] = 'sender_profile_invalid'; }
			try {
				$this->secret();
			} catch ( \RuntimeException ) {
				$errors[] = 'secret_unavailable'; }
			try {
				$this->queueKey();
			} catch ( \RuntimeException ) {
				$errors[] = 'queue_key_unavailable'; }
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
		$secret = trim( $this->readKeyFile( $this->secretPath ) );
		if ( strlen( $secret ) < 32 ) {
			throw new \RuntimeException( 'Mail Gateway secret is invalid.' ); }
		return $secret;
	}

	/** Return the independent 32-byte authenticated-encryption key. */
	public function queueKey(): string {
		$material = $this->readKeyFile( $this->queueKeyPath );
		if ( 32 === strlen( $material ) ) {
			return $material;
		}

		$encoded = trim( $material );
		if ( 64 === strlen( $encoded ) && ctype_xdigit( $encoded ) ) {
			$key = hex2bin( $encoded );
			if ( is_string( $key ) && 32 === strlen( $key ) ) {
				return $key;
			}
		}

		$key = base64_decode( $encoded, true );
		if ( is_string( $key ) && 32 === strlen( $key ) ) {
			return $key;
		}

		throw new \RuntimeException( 'Mail Gateway queue key is invalid.' );
	}

	/** Read a bounded regular key file after validating its location and permissions. */
	private function readKeyFile( string $configuredPath ): string {
		if ( '' === $configuredPath || is_link( $configuredPath ) ) {
			throw new \RuntimeException( 'Mail Gateway key file is unsafe.' );
		}

		$path = realpath( $configuredPath );
		if ( false === $path || ! is_file( $path ) || ! is_readable( $path ) || is_link( $path ) ) {
			throw new \RuntimeException( 'Mail Gateway key file is unavailable.' );
		}

		$permissions = fileperms( $path );
		if ( false === $permissions || 0 !== ( $permissions & 0022 ) ) {
			throw new \RuntimeException( 'Mail Gateway key file permissions are unsafe.' );
		}
		$owner = fileowner( $path );
		if ( null !== $this->requiredKeyOwnerUid && ( false === $owner || $owner !== $this->requiredKeyOwnerUid ) ) {
			throw new \RuntimeException( 'Mail Gateway key file ownership is unsafe.' );
		}

		$roots = $this->additionalProtectedRoots;
		if ( defined( 'ABSPATH' ) ) {
			$roots[] = (string) ABSPATH;
		}
		if ( defined( 'WP_CONTENT_DIR' ) ) {
			$roots[] = (string) WP_CONTENT_DIR;
		}
		if ( defined( 'KEVIRA_MAIL_GATEWAY_PATH' ) ) {
			$roots[] = (string) KEVIRA_MAIL_GATEWAY_PATH;
		}
		if ( function_exists( 'wp_upload_dir' ) ) {
			$uploads = wp_upload_dir( null, false );
			if ( is_array( $uploads ) && isset( $uploads['basedir'] ) ) {
				$roots[] = (string) $uploads['basedir'];
			}
		}

		foreach ( $roots as $root ) {
			$resolvedRoot = realpath( $root );
			if ( is_string( $resolvedRoot ) && $this->isInside( $path, $resolvedRoot ) ) {
				throw new \RuntimeException( 'Mail Gateway key file location is unsafe.' );
			}
		}

		$size = filesize( $path );
		if ( false === $size || $size < 1 || $size > self::MAX_KEY_FILE_BYTES ) {
			throw new \RuntimeException( 'Mail Gateway key file size is invalid.' );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Validated local key file, never a URL.
		$material = file_get_contents( $path );
		if ( ! is_string( $material ) || strlen( $material ) !== $size ) {
			throw new \RuntimeException( 'Mail Gateway key file could not be read safely.' );
		}
		return $material;
	}

	private function isInside( string $path, string $root ): bool {
		$root = rtrim( $root, DIRECTORY_SEPARATOR );
		return $path === $root || str_starts_with( $path, $root . DIRECTORY_SEPARATOR );
	}
}
