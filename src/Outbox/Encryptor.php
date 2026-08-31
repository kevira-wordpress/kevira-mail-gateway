<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Outbox;

final class Encryptor {
	private const CONTEXT = 'kevira-mail-gateway-outbox-v1';

	public function __construct( private readonly string $secret ) {}

	public function encrypt( string $plainText ): string {
		$key = hash_hkdf( 'sha256', $this->secret, 32, self::CONTEXT, 'WordPress' );
		if ( function_exists( 'sodium_crypto_aead_xchacha20poly1305_ietf_encrypt' ) ) {
			$nonce  = random_bytes( SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES );
			$cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt( $plainText, self::CONTEXT, $nonce, $key );
			return 's1.' . base64_encode( $nonce . $cipher );
		}

		if ( ! function_exists( 'openssl_encrypt' ) ) {
			throw new \RuntimeException( 'No authenticated encryption engine is available.' );
		}
		$iv     = random_bytes( 12 );
		$tag    = '';
		$cipher = openssl_encrypt( $plainText, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, self::CONTEXT );
		if ( ! is_string( $cipher ) ) {
			throw new \RuntimeException( 'Unable to encrypt the outbox payload.' );
		}
		return 'o1.' . base64_encode( $iv . $tag . $cipher );
	}

	public function decrypt( string $encoded ): string {
		$key = hash_hkdf( 'sha256', $this->secret, 32, self::CONTEXT, 'WordPress' );
		$raw = base64_decode( substr( $encoded, 3 ), true );
		if ( ! is_string( $raw ) ) {
			throw new \RuntimeException( 'Outbox payload encoding is invalid.' );
		}
		if ( str_starts_with( $encoded, 's1.' ) && function_exists( 'sodium_crypto_aead_xchacha20poly1305_ietf_decrypt' ) ) {
			$nonceSize = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
			$plain     = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt( substr( $raw, $nonceSize ), self::CONTEXT, substr( $raw, 0, $nonceSize ), $key );
			if ( is_string( $plain ) ) {
				return $plain;
			}
		}
		if ( str_starts_with( $encoded, 'o1.' ) && function_exists( 'openssl_decrypt' ) ) {
			$plain = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ), self::CONTEXT );
			if ( is_string( $plain ) ) {
				return $plain;
			}
		}
		throw new \RuntimeException( 'Outbox payload authentication failed.' );
	}
}
