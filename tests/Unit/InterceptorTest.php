<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Tests\Unit;

use Kevira\MailGateway\Config;
use Kevira\MailGateway\Contracts\Clock;
use Kevira\MailGateway\Contracts\HttpTransport;
use Kevira\MailGateway\Contracts\RandomSource;
use Kevira\MailGateway\Contracts\Scheduler;
use Kevira\MailGateway\Gateway\Client;
use Kevira\MailGateway\Gateway\ResponseClassifier;
use Kevira\MailGateway\Gateway\Signer;
use Kevira\MailGateway\Mail\AttachmentLoader;
use Kevira\MailGateway\Mail\HeaderNormalizer;
use Kevira\MailGateway\Mail\Interceptor;
use Kevira\MailGateway\Mail\MessageFactory;
use Kevira\MailGateway\Outbox\Encryptor;
use Kevira\MailGateway\Outbox\Repository;
use PHPUnit\Framework\TestCase;

final class InterceptorTest extends TestCase {
	public function testAttachmentFailureIsReportedThroughWpMailFailed(): void {
		$secret = (string) tempnam( sys_get_temp_dir(), 'kmg-secret-' );
		$queue  = (string) tempnam( sys_get_temp_dir(), 'kmg-queue-' );
		file_put_contents( $secret, str_repeat( 's', 64 ) );
		file_put_contents( $queue, str_repeat( 'q', 32 ) );
		chmod( $secret, 0600 );
		chmod( $queue, 0600 );
		$config = new Config( 'https://mail.example.test', 'site-one', $secret, 'transactional', 'production', $queue, array(), null );
		$clock  = new class implements Clock { public function now(): int { return 1700000000; } };
		$random = new class implements RandomSource { public function bytes( int $length ): string { return str_repeat( "\x01", $length ); } public function integer( int $minimum, int $maximum ): int { return $minimum; } };
		$http   = new class implements HttpTransport { public function request( string $method, string $url, array $arguments ): mixed { throw new \RuntimeException( 'HTTP must not run for attachments.' ); } };
		$scheduler = new class implements Scheduler { public function schedule( int $delay = 5 ): void {} public function clear(): void {} };
		$client = new Client( $config, $http, new Signer( $config, $clock, $random ), new ResponseClassifier() );
		$factory = new MessageFactory( $config, new HeaderNormalizer(), new AttachmentLoader() );
		$interceptor = new Interceptor( $config, $factory, $client, new Repository(), $scheduler, $random, new Encryptor( $config->queueKey(), $config->secret() ) );
		$GLOBALS['kmg_test_actions'] = array();

		$result = $interceptor->intercept( null, array( 'to' => 'to@example.com', 'subject' => 'Subject', 'message' => 'Body', 'attachments' => array( '/must/not/be/read' ) ) );
		$this->assertFalse( $result );
		$error = $GLOBALS['kmg_test_actions']['wp_mail_failed'][0][0] ?? null;
		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'kevira_mail_gateway_attachments_unsupported', $error->get_error_code() );
		$this->assertStringContainsString( 'not supported', $error->get_error_message() );

		unlink( $secret );
		unlink( $queue );
	}

	public function testAcceptedGatewayIdIsStoredWithoutMessageContent(): void {
		$secret = (string) tempnam( sys_get_temp_dir(), 'kmg-secret-' );
		$queue  = (string) tempnam( sys_get_temp_dir(), 'kmg-queue-' );
		file_put_contents( $secret, str_repeat( 's', 64 ) );
		file_put_contents( $queue, str_repeat( 'q', 32 ) );
		chmod( $secret, 0600 );
		chmod( $queue, 0600 );
		$config = new Config( 'https://mail.example.test', 'site-one', $secret, 'transactional', 'production', $queue, array(), null );
		$clock  = new class implements Clock { public function now(): int { return 1700000000; } };
		$random = new class implements RandomSource { public function bytes( int $length ): string { return str_repeat( "\x02", $length ); } public function integer( int $minimum, int $maximum ): int { return $minimum; } };
		$http   = new class implements HttpTransport { public function request( string $method, string $url, array $arguments ): mixed { return array( 'response' => array( 'code' => 202 ), 'body' => '{"id":"gateway-message-uuid","status":"queued"}' ); } };
		$scheduler = new class implements Scheduler { public function schedule( int $delay = 5 ): void {} public function clear(): void {} };
		$client = new Client( $config, $http, new Signer( $config, $clock, $random ), new ResponseClassifier() );
		$factory = new MessageFactory( $config, new HeaderNormalizer(), new AttachmentLoader() );
		$interceptor = new Interceptor( $config, $factory, $client, new Repository(), $scheduler, $random, new Encryptor( $config->queueKey(), $config->secret() ) );
		$GLOBALS['kmg_test_options'] = array();

		$this->assertTrue( $interceptor->intercept( null, array( 'to' => 'to@example.com', 'subject' => 'Subject', 'message' => 'private body' ) ) );
		$stored = $GLOBALS['kmg_test_options']['kevira_mail_gateway_last_accepted'];
		$this->assertSame( 'gateway-message-uuid', $stored['id'] );
		$this->assertStringNotContainsString( 'private body', serialize( $stored ) );

		unlink( $secret );
		unlink( $queue );
	}
}
