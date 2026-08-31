<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Tests\Unit;

use Kevira\MailGateway\Outbox\Encryptor;
use PHPUnit\Framework\TestCase;

final class EncryptorTest extends TestCase {
	public function testRoundTripAndTamperDetection(): void {
		$encryptor = new Encryptor( str_repeat( 'k', 64 ) );
		$cipher = $encryptor->encrypt( '{"private":"message"}' );
		$this->assertSame( '{"private":"message"}', $encryptor->decrypt( $cipher ) );
		$cipher[ strlen( $cipher ) - 2 ] = 'A' === $cipher[ strlen( $cipher ) - 2 ] ? 'B' : 'A';
		$this->expectException( \RuntimeException::class );
		$encryptor->decrypt( $cipher );
	}
}
