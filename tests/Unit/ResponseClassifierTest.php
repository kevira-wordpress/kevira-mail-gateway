<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Tests\Unit;

use Kevira\MailGateway\Gateway\ResponseClassifier;
use PHPUnit\Framework\TestCase;

final class ResponseClassifierTest extends TestCase {
	public function testStatusClasses(): void {
		$classifier = new ResponseClassifier();
		$this->assertTrue( $classifier->classify( 202, '{"message_id":"msg-1"}' )->acceptedByGateway() );
		$this->assertTrue( $classifier->classify( 429 )->retryable() );
		$this->assertFalse( $classifier->classify( 401 )->retryable() );
	}
}
