<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Tests\Unit;

use Kevira\MailGateway\Support\Redactor;
use PHPUnit\Framework\TestCase;

final class RedactorTest extends TestCase {
	public function testSensitiveValuesAreRemovedFromDiagnostics(): void {
		$message = 'recipient@example.com X-Kevira-Nonce: ' . str_repeat( 'c', 32 ) . ' v1=' . str_repeat( 'a', 64 ) . ' token=' . str_repeat( 'b', 64 ) . ' Idempotency-Key: 123e4567-e89b-42d3-a456-426614174000';
		$redacted = Redactor::message( $message );
		$this->assertStringNotContainsString( 'recipient@example.com', $redacted );
		$this->assertStringNotContainsString( str_repeat( 'a', 64 ), $redacted );
		$this->assertStringNotContainsString( str_repeat( 'b', 64 ), $redacted );
		$this->assertStringNotContainsString( str_repeat( 'c', 32 ), $redacted );
		$this->assertStringNotContainsString( '123e4567-e89b-42d3-a456-426614174000', $redacted );
	}
}
