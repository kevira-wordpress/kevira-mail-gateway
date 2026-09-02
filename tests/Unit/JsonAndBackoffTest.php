<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Tests\Unit;

use Kevira\MailGateway\Outbox\Backoff;
use Kevira\MailGateway\Support\Json;
use PHPUnit\Framework\TestCase;

final class JsonAndBackoffTest extends TestCase {
	public function testJsonIsDeterministicAndBackoffIsBounded(): void {
		$this->assertSame( '{"a":{"x":1,"y":2},"z":3}', Json::encode( array( 'z' => 3, 'a' => array( 'y' => 2, 'x' => 1 ) ) ) );
		$delay = ( new Backoff() )->seconds( 1 );
		$this->assertGreaterThanOrEqual( 60, $delay );
		$this->assertLessThanOrEqual( 72, $delay );
	}

	public function testResponseDecoderRejectsDuplicateKeys(): void {
		$this->expectException( \UnexpectedValueException::class );
		Json::decodeObject( '{"id":"one","nested":{"code":"ok","code":"changed"}}' );
	}
}
