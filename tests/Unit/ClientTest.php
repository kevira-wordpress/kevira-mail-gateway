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
		file_put_contents( $secret, str_repeat( 's', 64 ) );
		$config = new Config( 'https://mail.example.test', 'site-one', $secret, 'default' );
		$clock = new class implements Clock { public function now(): int { return 1700000000; } };
		$random = new class implements RandomSource { public function bytes( int $length ): string { return str_repeat( "\x02", $length ); } public function integer( int $minimum, int $maximum ): int { return $minimum; } };
		$acceptedHttp = new class implements HttpTransport { public function request( string $method, string $url, array $arguments ): mixed { return array( 'response' => array( 'code' => 202 ), 'body' => '{"message_id":"mock-1"}' ); } };
		$accepted = ( new Client( $config, $acceptedHttp, new Signer( $config, $clock, $random ), new ResponseClassifier() ) )->send( '{}', '123e4567-e89b-42d3-a456-426614174000' );
		$this->assertTrue( $accepted->acceptedByGateway() );

		$failedHttp = new class implements HttpTransport { public function request( string $method, string $url, array $arguments ): mixed { return new \WP_Error( 'network', 'Connection timeout for admin@example.test' ); } };
		$failed = ( new Client( $config, $failedHttp, new Signer( $config, $clock, $random ), new ResponseClassifier() ) )->send( '{}', '123e4567-e89b-42d3-a456-426614174000' );
		$this->assertTrue( $failed->retryable() );
		$this->assertStringNotContainsString( 'admin@example.test', $failed->message );
		unlink( $secret );
	}
}
