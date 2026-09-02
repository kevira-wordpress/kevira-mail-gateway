<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Outbox;

final class Encryptor {
	private const CONTEXT_V1 = 'kevira-mail-gateway-outbox-v1';
	private const CONTEXT_V2 = 'kevira-mail-gateway-outbox-v2';

	public function __construct( private readonly string $key, private readonly ?string $legacyHmacSecret = null ) {
		if ( 32 !== strlen( $this->key ) ) {
			throw new \InvalidArgumentException( 'The queue encryption key must contain exactly 32 bytes.' );
		}
	}

	public function encrypt( string $plainText ): string {
		if ( function_exists( 'sodium_crypto_aead_xchacha20poly1305_ietf_encrypt' ) ) {
			$nonce  = random_bytes( SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES );
			$cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt( $plainText, self::CONTEXT_V2, $nonce, $this->key );
			return 's2.' . base64_encode( $nonce . $cipher );
		}

		if ( ! function_exists( 'openssl_encrypt' ) ) {
			throw new \RuntimeException( 'No authenticated encryption engine is available.' );
		}
		$iv     = random_bytes( 12 );
		$tag    = '';
		$cipher = openssl_encrypt( $plainText, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag, self::CONTEXT_V2 );
		if ( ! is_string( $cipher ) ) {
			throw new \RuntimeException( 'Unable to encrypt the outbox payload.' );
		}
		return 'o2.' . base64_encode( $iv . $tag . $cipher );
	}

	public function decrypt( string $encoded ): string {
		$raw = base64_decode( substr( $encoded, 3 ), true );
		if ( ! is_string( $raw ) ) {
			throw new \RuntimeException( 'Outbox payload encoding is invalid.' );
		}
		if ( str_starts_with( $encoded, 's2.' ) && function_exists( 'sodium_crypto_aead_xchacha20poly1305_ietf_decrypt' ) ) {
			$nonceSize = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
			$plain     = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt( substr( $raw, $nonceSize ), self::CONTEXT_V2, substr( $raw, 0, $nonceSize ), $this->key );
			if ( is_string( $plain ) ) {
				return $plain;
			}
		}
		if ( str_starts_with( $encoded, 'o2.' ) && function_exists( 'openssl_decrypt' ) ) {
			$plain = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ), self::CONTEXT_V2 );
			if ( is_string( $plain ) ) {
				return $plain;
			}
		}

		if ( null !== $this->legacyHmacSecret ) {
			$legacyKey = hash_hkdf( 'sha256', $this->legacyHmacSecret, 32, self::CONTEXT_V1, 'WordPress' );
			if ( str_starts_with( $encoded, 's1.' ) && function_exists( 'sodium_crypto_aead_xchacha20poly1305_ietf_decrypt' ) ) {
				$nonceSize = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
				$plain     = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt( substr( $raw, $nonceSize ), self::CONTEXT_V1, substr( $raw, 0, $nonceSize ), $legacyKey );
				if ( is_string( $plain ) ) {
					return $plain;
				}
			}
			if ( str_starts_with( $encoded, 'o1.' ) && function_exists( 'openssl_decrypt' ) ) {
				$plain = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', $legacyKey, OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ), self::CONTEXT_V1 );
				if ( is_string( $plain ) ) {
					return $plain;
				}
			}
		}
		throw new \RuntimeException( 'Outbox payload authentication failed.' );
	}
}
