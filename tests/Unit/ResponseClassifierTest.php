<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Tests\Unit;

use Kevira\MailGateway\Gateway\ResponseClassifier;
use PHPUnit\Framework\TestCase;

final class ResponseClassifierTest extends TestCase {
	public function testStatusClasses(): void {
		$classifier = new ResponseClassifier();
		$this->assertTrue( $classifier->classify( 202, '{"id":"msg-1","status":"queued"}' )->acceptedByGateway() );
		$this->assertTrue( $classifier->classify( 200, '{"id":"msg-1","status":"queued"}' )->acceptedByGateway() );
		$this->assertTrue( $classifier->classify( 429 )->retryable() );
		$this->assertFalse( $classifier->classify( 401 )->retryable() );
	}

	public function testStructuredConflictCodesAreNotConflated(): void {
		$classifier = new ResponseClassifier();
		$nonce      = $classifier->classify( 409, '{"error":{"code":"nonce_replay","message":"Request rejected"},"request_id":"req-1"}' );
		$conflict   = $classifier->classify( 409, '{"error":{"code":"idempotency_conflict","message":"Request rejected"},"request_id":"req-2"}' );
		$inProgress = $classifier->classify( 409, '{"error":{"code":"idempotency_in_progress","message":"Request rejected"}}' );
		$unknown    = $classifier->classify( 409, '{"error":{"code":"other_conflict","message":"Request rejected"}}' );
		$this->assertSame( 'nonce_replay', $nonce->code );
		$this->assertTrue( $nonce->retryable() );
		$this->assertSame( 'idempotency_conflict', $conflict->code );
		$this->assertFalse( $conflict->retryable() );
		$this->assertTrue( $inProgress->retryable() );
		$this->assertSame( 'gateway_rejected', $unknown->code );
	}

	public function testMalformedAndDuplicateSuccessResponsesFailClosed(): void {
		$classifier = new ResponseClassifier();
		$this->assertSame( 'invalid_response', $classifier->classify( 202, '{broken' )->code );
		$this->assertSame( 'invalid_response', $classifier->classify( 202, '{"id":"first","id":"second","status":"queued"}' )->code );
		$this->assertSame( 'invalid_response', $classifier->classify( 202, '{"message_id":"legacy","status":"queued"}' )->code );
	}

	public function testDocumentedTransientAndPermanentErrors(): void {
		$classifier = new ResponseClassifier();
		foreach ( array( 408, 425, 429, 500, 503 ) as $status ) {
			$this->assertTrue( $classifier->classify( $status )->retryable() );
		}
		foreach ( array( 'invalid_signature', 'stale_timestamp', 'invalid_payload', 'sender_profile_not_allowed', 'idempotency_conflict' ) as $code ) {
			$this->assertFalse( $classifier->classify( 400, '{"error":{"code":"' . $code . '","message":"rejected"}}' )->retryable() );
		}
		foreach ( array( 'rate_limited', 'daily_quota_exceeded', 'idempotency_in_progress' ) as $code ) {
			$this->assertTrue( $classifier->classify( 429, '{"error":{"code":"' . $code . '","message":"retry"}}' )->retryable() );
		}
	}
}
