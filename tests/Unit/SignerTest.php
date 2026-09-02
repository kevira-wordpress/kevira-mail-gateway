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
		$config = new Config( 'https://mail.example.test', 'site-one', $this->secretFile, 'transactional', 'production', '', array(), null );
		$clock = new class implements Clock { public function now(): int { return 1700000000; } };
		$random = new class implements RandomSource { public function bytes( int $length ): string { return str_repeat( "\x01", $length ); } public function integer( int $minimum, int $maximum ): int { return $minimum; } };
		$body = '{"charset":"UTF-8","html":"<p>Hello</p>","recipients":{"bcc":[],"cc":[],"to":["recipient@example.com"]},"reply_to":null,"sender_profile":"transactional","subject":"Subject","text":"Hello"}';
		$signature = ( new Signer( $config, $clock, $random ) )->signMessage( $body, '123e4567-e89b-42d3-a456-426614174000' );
		$canonical = "POST\n/v1/messages\n1700000000\n01010101010101010101010101010101\n123e4567-e89b-42d3-a456-426614174000\n165b6781057a165218a66b9fd772ac0478ad3d805a5f0bbad70789d33f424501";
		$this->assertSame( $canonical, $signature->canonical );
		$this->assertSame( 'v1=4a519c26cdee98a07a81d045364c454e621b8b5da4559ba86333dd0a7750dd39', $signature->headers['X-Kevira-Signature'] );
		$this->assertSame( '123e4567-e89b-42d3-a456-426614174000', $signature->headers['Idempotency-Key'] );
		$this->assertMatchesRegularExpression( '/^v1=[a-f0-9]{64}$/', $signature->headers['X-Kevira-Signature'] );
		$this->assertSame(
			array( 'Content-Type', 'X-Kevira-Client-Id', 'X-Kevira-Timestamp', 'X-Kevira-Nonce', 'X-Kevira-Signature', 'Idempotency-Key' ),
			array_keys( $signature->headers )
		);
		$this->assertFalse( str_ends_with( $signature->canonical, "\n" ) );
	}
}
