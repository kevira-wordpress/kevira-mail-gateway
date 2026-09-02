<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Tests\Unit;

use Kevira\MailGateway\Outbox\Encryptor;
use PHPUnit\Framework\TestCase;

final class EncryptorTest extends TestCase {
	public function testRoundTripAndTamperDetection(): void {
		$encryptor = new Encryptor( str_repeat( 'k', 32 ) );
		$cipher = $encryptor->encrypt( '{"private":"message"}' );
		$this->assertSame( '{"private":"message"}', $encryptor->decrypt( $cipher ) );
		$cipher[ strlen( $cipher ) - 2 ] = 'A' === $cipher[ strlen( $cipher ) - 2 ] ? 'B' : 'A';
		$this->expectException( \RuntimeException::class );
		$encryptor->decrypt( $cipher );
	}

	public function testHmacSecretRotationDoesNotChangeQueueKey(): void {
		$key        = str_repeat( 'q', 32 );
		$encrypted  = ( new Encryptor( $key, str_repeat( 'a', 64 ) ) )->encrypt( 'queued-body' );
		$afterRotate = new Encryptor( $key, str_repeat( 'b', 64 ) );
		$this->assertSame( 'queued-body', $afterRotate->decrypt( $encrypted ) );
	}

	public function testQueueKeyRotationCannotSilentlyDecryptExistingRecord(): void {
		$encrypted = ( new Encryptor( str_repeat( 'q', 32 ) ) )->encrypt( 'queued-body' );
		$this->expectException( \RuntimeException::class );
		( new Encryptor( str_repeat( 'r', 32 ) ) )->decrypt( $encrypted );
	}

	public function testLegacyQueueRecordCanBeDrainedDuringUpgrade(): void {
		$legacySecret = str_repeat( 's', 64 );
		$legacyKey    = hash_hkdf( 'sha256', $legacySecret, 32, 'kevira-mail-gateway-outbox-v1', 'WordPress' );
		if ( function_exists( 'sodium_crypto_aead_xchacha20poly1305_ietf_encrypt' ) ) {
			$nonce   = str_repeat( "\x01", SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES );
			$cipher  = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt( 'legacy-body', 'kevira-mail-gateway-outbox-v1', $nonce, $legacyKey );
			$encoded = 's1.' . base64_encode( $nonce . $cipher );
		} else {
			$iv      = str_repeat( "\x01", 12 );
			$tag     = '';
			$cipher  = openssl_encrypt( 'legacy-body', 'aes-256-gcm', $legacyKey, OPENSSL_RAW_DATA, $iv, $tag, 'kevira-mail-gateway-outbox-v1' );
			$encoded = 'o1.' . base64_encode( $iv . $tag . $cipher );
		}
		$this->assertSame( 'legacy-body', ( new Encryptor( str_repeat( 'q', 32 ), $legacySecret ) )->decrypt( $encoded ) );
	}
}
