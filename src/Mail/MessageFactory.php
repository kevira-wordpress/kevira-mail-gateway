<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Mail;

use Kevira\MailGateway\Config;
use Kevira\MailGateway\Support\Json;

final class MessageFactory {
	private const MAX_MESSAGE_BYTES = 1048576;
	private const MAX_PAYLOAD_BYTES = 16777216;
	private const MAX_RECIPIENTS    = 100;
	public function __construct(
		private readonly Config $config,
		private readonly HeaderNormalizer $headers,
		private readonly AttachmentLoader $attachments
	) {}

	/**
	 * @param array{to:mixed,subject:mixed,message:mixed,headers?:mixed,attachments?:mixed} $attributes
	 */
	public function createBody( array $attributes ): string {
		$to = $this->headers->addresses( is_array( $attributes['to'] ) ? $attributes['to'] : (string) $attributes['to'] );
		if ( array() === $to ) {
			throw new \InvalidArgumentException( 'Email has no valid recipient.' );
		}

		$subject = trim( wp_strip_all_tags( (string) $attributes['subject'], true ) );
		if ( '' === $subject || str_contains( $subject, "\r" ) || str_contains( $subject, "\n" ) || strlen( $subject ) > 998 ) {
			throw new \InvalidArgumentException( 'Email subject is invalid.' );
		}

		$normalized = $this->headers->normalize( is_array( $attributes['headers'] ?? null ) ? $attributes['headers'] : (string) ( $attributes['headers'] ?? '' ) );
		$message    = (string) $attributes['message'];
		if ( strlen( $message ) > self::MAX_MESSAGE_BYTES || count( $to ) + count( $normalized['cc'] ) + count( $normalized['bcc'] ) > self::MAX_RECIPIENTS ) {
			throw new \InvalidArgumentException( 'Email content or recipient count exceeds the configured safety limit.' );
		}
		$payload = array(
			'sender_profile' => $this->config->senderProfile(),
			'recipients'     => array(
				'to'  => $to,
				'cc'  => $normalized['cc'],
				'bcc' => $normalized['bcc'],
			),
			'reply_to'       => $normalized['reply_to'],
			'subject'        => $subject,
			'text'           => 'text/plain' === $normalized['content_type'] ? $message : wp_strip_all_tags( $message ),
			'html'           => 'text/html' === $normalized['content_type'] ? $message : null,
			'charset'        => $normalized['charset'],
			'attachments'    => $this->attachments->load( is_array( $attributes['attachments'] ?? null ) ? $attributes['attachments'] : (string) ( $attributes['attachments'] ?? '' ) ),
			'metadata'       => array(
				'site_id' => $this->config->siteId(),
				'source'  => 'wordpress',
			),
		);

		$body = Json::encode( $payload );
		if ( strlen( $body ) > self::MAX_PAYLOAD_BYTES ) {
			throw new \InvalidArgumentException( 'The normalized email payload is too large.' );
		}
		return $body;
	}
}
