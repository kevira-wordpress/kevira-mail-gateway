<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Mail;

use Kevira\MailGateway\Config;
use Kevira\MailGateway\Support\Json;

final class MessageFactory {
	public const MAX_PAYLOAD_BYTES      = 131072;
	public const MAX_RECIPIENTS         = 10;
	public const MAX_SUBJECT_CHARACTERS = 200;
	public function __construct(
		private readonly Config $config,
		private readonly HeaderNormalizer $headers,
		private readonly AttachmentLoader $attachments
	) {}

	/**
	 * @param array{to:mixed,subject:mixed,message:mixed,headers?:mixed,attachments?:mixed} $attributes
	 */
	public function createBody( array $attributes ): string {
		$this->attachments->load( is_array( $attributes['attachments'] ?? null ) ? $attributes['attachments'] : (string) ( $attributes['attachments'] ?? '' ) );

		$subject = trim( wp_strip_all_tags( (string) $attributes['subject'], true ) );
		if ( '' === $subject || str_contains( $subject, "\r" ) || str_contains( $subject, "\n" ) || $this->unicodeLength( $subject ) > self::MAX_SUBJECT_CHARACTERS ) {
			throw new \InvalidArgumentException( 'Email subject is invalid.' );
		}

		$to         = $this->headers->addresses( is_array( $attributes['to'] ) ? $attributes['to'] : (string) $attributes['to'] );
		$normalized = $this->headers->normalize( is_array( $attributes['headers'] ?? null ) ? $attributes['headers'] : (string) ( $attributes['headers'] ?? '' ) );
		$message    = (string) $attributes['message'];
		if ( strlen( $message ) > self::MAX_PAYLOAD_BYTES ) {
			throw new \InvalidArgumentException( 'Email content exceeds the Gateway v1 request limit.' );
		}
		$recipientCount = count( $to ) + count( $normalized['cc'] ) + count( $normalized['bcc'] );
		if ( 0 === $recipientCount ) {
			throw new \InvalidArgumentException( 'Email has no valid recipient.' );
		}
		if ( $recipientCount > self::MAX_RECIPIENTS ) {
			throw new \InvalidArgumentException( 'Email recipient count exceeds the Gateway v1 limit.' );
		}
		if ( count( $normalized['reply_to'] ) > 1 ) {
			throw new \InvalidArgumentException( 'Email contains more than one Reply-To address.' );
		}
		if ( 'UTF-8' !== strtoupper( $normalized['charset'] ) ) {
			throw new \InvalidArgumentException( 'Mail Gateway v1 requires UTF-8 content.' );
		}

		$text = 'text/plain' === $normalized['content_type'] ? $message : wp_strip_all_tags( $message );
		$html = 'text/html' === $normalized['content_type'] ? $message : '';
		if ( '' === trim( $text ) && '' === trim( $html ) ) {
			throw new \InvalidArgumentException( 'Email must contain text or HTML content.' );
		}

		$payload = array(
			'sender_profile' => $this->config->senderProfile(),
			'recipients'     => array(
				'to'  => $to,
				'cc'  => $normalized['cc'],
				'bcc' => $normalized['bcc'],
			),
			'reply_to'       => $normalized['reply_to'][0] ?? null,
			'subject'        => $subject,
			'text'           => $text,
			'html'           => $html,
			'charset'        => 'UTF-8',
		);

		$body = Json::encode( $payload );
		if ( strlen( $body ) > self::MAX_PAYLOAD_BYTES ) {
			throw new \InvalidArgumentException( 'The normalized email payload is too large.' );
		}
		return $body;
	}

	private function unicodeLength( string $value ): int {
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $value, 'UTF-8' );
		}
		$count = preg_match_all( '/./us', $value, $matches );
		return false === $count ? strlen( $value ) : $count;
	}
}
