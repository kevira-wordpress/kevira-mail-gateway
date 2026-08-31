<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Tests\Unit;

use Kevira\MailGateway\Config;
use Kevira\MailGateway\Contracts\Clock;
use Kevira\MailGateway\Contracts\RandomSource;
use Kevira\MailGateway\Gateway\Signer;
use PHPUnit\Framework\TestCase;

final class SignerTest extends TestCase {
	private string $secretFile;

	protected function setUp(): void {
		$this->secretFile = (string) tempnam( sys_get_temp_dir(), 'kmg' );
		file_put_contents( $this->secretFile, str_repeat( 's', 64 ) );
	}

	protected function tearDown(): void {
		unlink( $this->secretFile );
	}

	public function testCanonicalRequestSignsExactBody(): void {
		$config = new Config( 'https://mail.example.test', 'site-one', $this->secretFile, 'transactional' );
		$clock = new class implements Clock { public function now(): int { return 1700000000; } };
		$random = new class implements RandomSource { public function bytes( int $length ): string { return str_repeat( "\x01", $length ); } public function integer( int $minimum, int $maximum ): int { return $minimum; } };
		$signature = ( new Signer( $config, $clock, $random ) )->signMessage( '{"subject":"Test"}', '123e4567-e89b-42d3-a456-426614174000' );
		$this->assertStringContainsString( "POST\n/v1/messages\n1700000000", $signature->canonical );
		$this->assertSame( '123e4567-e89b-42d3-a456-426614174000', $signature->headers['Idempotency-Key'] );
		$this->assertMatchesRegularExpression( '/^v1=[a-f0-9]{64}$/', $signature->headers['X-Kevira-Signature'] );
	}
}
