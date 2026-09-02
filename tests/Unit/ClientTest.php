<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Tests\Unit;

use Kevira\MailGateway\Config;
use Kevira\MailGateway\Contracts\Clock;
use Kevira\MailGateway\Contracts\HttpTransport;
use Kevira\MailGateway\Contracts\RandomSource;
use Kevira\MailGateway\Gateway\Client;
use Kevira\MailGateway\Gateway\ResponseClassifier;
use Kevira\MailGateway\Gateway\Signer;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase {
	public function testMockedHttpAcceptanceAndNetworkFailure(): void {
		$secret = (string) tempnam( sys_get_temp_dir(), 'kmg' );
		$queue = (string) tempnam( sys_get_temp_dir(), 'kmg' );
		file_put_contents( $secret, str_repeat( 's', 64 ) );
		file_put_contents( $queue, str_repeat( 'q', 32 ) );
		chmod( $secret, 0600 );
		chmod( $queue, 0600 );
		$config = new Config( 'https://mail.example.test', 'site-one', $secret, 'transactional', 'production', $queue, array(), null );
		$clock = new class implements Clock { public function now(): int { return 1700000000; } };
		$random = new class implements RandomSource { public function bytes( int $length ): string { return str_repeat( "\x02", $length ); } public function integer( int $minimum, int $maximum ): int { return $minimum; } };
		$acceptedHttp = new class implements HttpTransport { public function request( string $method, string $url, array $arguments ): mixed { return array( 'response' => array( 'code' => 202 ), 'body' => '{"id":"mock-1","status":"queued"}' ); } };
		$accepted = ( new Client( $config, $acceptedHttp, new Signer( $config, $clock, $random ), new ResponseClassifier() ) )->send( '{}', '123e4567-e89b-42d3-a456-426614174000' );
		$this->assertTrue( $accepted->acceptedByGateway() );
		$this->assertSame( 'mock-1', $accepted->messageId );

		$failedHttp = new class implements HttpTransport { public function request( string $method, string $url, array $arguments ): mixed { return new \WP_Error( 'network', 'Connection timeout for admin@example.test' ); } };
		$failed = ( new Client( $config, $failedHttp, new Signer( $config, $clock, $random ), new ResponseClassifier() ) )->send( '{}', '123e4567-e89b-42d3-a456-426614174000' );
		$this->assertTrue( $failed->retryable() );
		$this->assertStringNotContainsString( 'admin@example.test', $failed->message );
		unlink( $secret );
		unlink( $queue );
	}

	public function testRetryKeepsBodyAndIdempotencyButRefreshesAuthentication(): void {
		$secret = (string) tempnam( sys_get_temp_dir(), 'kmg' );
		$queue  = (string) tempnam( sys_get_temp_dir(), 'kmg' );
		file_put_contents( $secret, str_repeat( 's', 64 ) );
		file_put_contents( $queue, str_repeat( 'q', 32 ) );
		chmod( $secret, 0600 );
		chmod( $queue, 0600 );
		$config = new Config( 'https://mail.example.test', 'site-one', $secret, 'transactional', 'production', $queue, array(), null );
		$clock = new class implements Clock { private int $now = 1700000000; public function now(): int { return $this->now++; } };
		$random = new class implements RandomSource { private int $byte = 1; public function bytes( int $length ): string { return str_repeat( chr( $this->byte++ ), $length ); } public function integer( int $minimum, int $maximum ): int { return $minimum; } };
		$http = new class implements HttpTransport {
			/** @var list<array<string,mixed>> */ public array $requests = array();
			public function request( string $method, string $url, array $arguments ): mixed {
				$this->requests[] = array( 'method' => $method, 'url' => $url, 'arguments' => $arguments );
				return 1 === count( $this->requests )
					? array( 'response' => array( 'code' => 503 ), 'body' => '' )
					: array( 'response' => array( 'code' => 202 ), 'body' => '{"id":"mock-2","status":"queued"}' );
			}
		};
		$client = new Client( $config, $http, new Signer( $config, $clock, $random ), new ResponseClassifier() );
		$body = '{"exact":"body"}';
		$key  = '123e4567-e89b-42d3-a456-426614174000';
		$this->assertTrue( $client->send( $body, $key )->retryable() );
		$this->assertTrue( $client->send( $body, $key )->acceptedByGateway() );
		$this->assertSame( $body, $http->requests[0]['arguments']['body'] );
		$this->assertSame( $body, $http->requests[1]['arguments']['body'] );
		$this->assertSame( $key, $http->requests[0]['arguments']['headers']['Idempotency-Key'] );
		$this->assertSame( $key, $http->requests[1]['arguments']['headers']['Idempotency-Key'] );
		$this->assertNotSame( $http->requests[0]['arguments']['headers']['X-Kevira-Nonce'], $http->requests[1]['arguments']['headers']['X-Kevira-Nonce'] );
		$this->assertNotSame( $http->requests[0]['arguments']['headers']['X-Kevira-Timestamp'], $http->requests[1]['arguments']['headers']['X-Kevira-Timestamp'] );
		$this->assertSame( 'https://mail.example.test/v1/messages', $http->requests[0]['url'] );
		unlink( $secret );
		unlink( $queue );
	}

	public function testHealthUsesUnsignedV1HealthEndpoint(): void {
		$secret = (string) tempnam( sys_get_temp_dir(), 'kmg' );
		$queue  = (string) tempnam( sys_get_temp_dir(), 'kmg' );
		file_put_contents( $secret, str_repeat( 's', 64 ) );
		file_put_contents( $queue, str_repeat( 'q', 32 ) );
		chmod( $secret, 0600 );
		chmod( $queue, 0600 );
		$config = new Config( 'https://mail.example.test', 'site-one', $secret, 'transactional', 'production', $queue, array(), null );
		$clock = new class implements Clock { public function now(): int { return 1700000000; } };
		$random = new class implements RandomSource { public function bytes( int $length ): string { return str_repeat( "\x01", $length ); } public function integer( int $minimum, int $maximum ): int { return $minimum; } };
		$http = new class implements HttpTransport {
			/** @var array<string,mixed> */ public array $request = array();
			public function request( string $method, string $url, array $arguments ): mixed { $this->request = compact( 'method', 'url', 'arguments' ); return array( 'response' => array( 'code' => 200 ), 'body' => '{}' ); }
		};
		$health = ( new Client( $config, $http, new Signer( $config, $clock, $random ), new ResponseClassifier() ) )->health();
		$this->assertTrue( $health['healthy'] );
		$this->assertSame( 'GET', $http->request['method'] );
		$this->assertSame( 'https://mail.example.test/v1/health', $http->request['url'] );
		$this->assertSame( array( 'Accept' => 'application/json' ), $http->request['arguments']['headers'] );
		unlink( $secret );
		unlink( $queue );
	}
}
