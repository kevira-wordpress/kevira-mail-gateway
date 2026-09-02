<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Tests\Unit;

use Kevira\MailGateway\Config;
use Kevira\MailGateway\Mail\AttachmentLoader;
use Kevira\MailGateway\Mail\HeaderNormalizer;
use Kevira\MailGateway\Mail\MessageFactory;
use PHPUnit\Framework\TestCase;

final class MessageFactoryTest extends TestCase {
	private MessageFactory $factory;

	protected function setUp(): void {
		$this->factory = new MessageFactory(
			new Config( 'https://mail.example.test', 'site-one', '/not-read', 'transactional' ),
			new HeaderNormalizer(),
			new AttachmentLoader()
		);
	}

	public function testProducesOnlyTheStrictGatewayV1Payload(): void {
		$body = $this->factory->createBody(
			array(
				'to'          => 'Recipient@Example.com',
				'subject'     => 'Subject',
				'message'     => '<p>Hello</p>',
				'headers'     => "Content-Type: text/html; charset=UTF-8\nReply-To: reply@example.com\nFrom: Caller <caller@example.com>",
				'attachments' => array(),
				'metadata'    => array( 'caller' => 'must-not-pass' ),
			)
		);
		$payload = json_decode( $body, true, 32, JSON_THROW_ON_ERROR );
		$this->assertSame( array( 'charset', 'html', 'recipients', 'reply_to', 'sender_profile', 'subject', 'text' ), array_keys( $payload ) );
		$this->assertSame( 'transactional', $payload['sender_profile'] );
		$this->assertSame( array( 'bcc' => array(), 'cc' => array(), 'to' => array( 'recipient@example.com' ) ), $payload['recipients'] );
		$this->assertSame( 'reply@example.com', $payload['reply_to'] );
		$this->assertSame( '<p>Hello</p>', $payload['html'] );
		$this->assertSame( 'Hello', $payload['text'] );
		$this->assertSame( 'UTF-8', $payload['charset'] );
		$this->assertArrayNotHasKey( 'attachments', $payload );
		$this->assertArrayNotHasKey( 'metadata', $payload );
	}

	public function testRecipientLimitCoversToCcAndBccTogether(): void {
		$to = array();
		for ( $index = 0; $index < 9; ++$index ) {
			$to[] = 'to' . $index . '@example.com';
		}
		$this->expectException( \InvalidArgumentException::class );
		$this->factory->createBody(
			array(
				'to'          => $to,
				'subject'     => 'Subject',
				'message'     => 'Body',
				'headers'     => "Cc: cc@example.com\nBcc: bcc@example.com",
				'attachments' => array(),
			)
		);
	}

	public function testAtLeastOneRecipientMayComeFromCcOrBcc(): void {
		$body = $this->factory->createBody(
			array(
				'to'      => array(),
				'subject' => 'Subject',
				'message' => 'Body',
				'headers' => 'Cc: cc@example.com',
			)
		);
		$payload = json_decode( $body, true, 32, JSON_THROW_ON_ERROR );
		$this->assertSame( array(), $payload['recipients']['to'] );
		$this->assertSame( array( 'cc@example.com' ), $payload['recipients']['cc'] );
	}

	public function testSubjectUsesUnicodeCharacterLimit(): void {
		$valid = str_repeat( 'ق', 200 );
		$this->assertNotSame( '', $this->factory->createBody( array( 'to' => 'to@example.com', 'subject' => $valid, 'message' => 'Body' ) ) );
		$this->expectException( \InvalidArgumentException::class );
		$this->factory->createBody( array( 'to' => 'to@example.com', 'subject' => $valid . 'ق', 'message' => 'Body' ) );
	}

	public function testUtf8ContentAndBodyAreRequired(): void {
		try {
			$this->factory->createBody( array( 'to' => 'to@example.com', 'subject' => 'Subject', 'message' => '', 'headers' => 'Content-Type: text/plain; charset=UTF-8' ) );
			$this->fail( 'Empty content should fail.' );
		} catch ( \InvalidArgumentException $error ) {
			$this->assertStringContainsString( 'content', $error->getMessage() );
		}

		$this->expectException( \InvalidArgumentException::class );
		$this->factory->createBody( array( 'to' => 'to@example.com', 'subject' => 'Subject', 'message' => 'Body', 'headers' => 'Content-Type: text/plain; charset=ISO-8859-1' ) );
	}

	public function testEncodedRequestCannotExceed128Kib(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->factory->createBody( array( 'to' => 'to@example.com', 'subject' => 'Subject', 'message' => str_repeat( 'x', MessageFactory::MAX_PAYLOAD_BYTES ) ) );
	}
}
