<?php
declare(strict_types=1);
namespace Kevira\MailGateway\Gateway;

use Kevira\MailGateway\Support\Redactor;
final class ResponseClassifier {
	public function classify( int $status, string $body = '', string $networkError = '' ): DeliveryResult {
		if ( '' !== $networkError ) {
			return DeliveryResult::transient( 'network_error', Redactor::message( $networkError ) ); }
		if ( 200 === $status || 202 === $status ) {
			$data      = json_decode( $body, true );
			$messageId = is_array( $data ) ? sanitize_text_field( (string) ( $data['message_id'] ?? '' ) ) : '';
			return DeliveryResult::accepted( $status, $messageId );
		}
		if ( 429 === $status ) {
			return DeliveryResult::transient( 'rate_limited', 'Gateway rate limit reached.', $status ); }
		if ( $status >= 500 || 0 === $status ) {
			return DeliveryResult::transient( 'gateway_unavailable', 'Gateway is temporarily unavailable.', $status ); }
		$map    = array(
			400 => array( 'invalid_payload', 'Gateway rejected the message payload.' ),
			401 => array( 'authentication_failed', 'Gateway authentication failed.' ),
			403 => array( 'policy_violation', 'Gateway client or sender policy rejected the message.' ),
			409 => array( 'nonce_replay', 'Gateway rejected a repeated nonce.' ),
			413 => array( 'payload_too_large', 'Gateway rejected an oversized payload.' ),
		);
		$mapped = $map[ $status ] ?? array( 'gateway_rejected', 'Gateway rejected the request.' );
		return DeliveryResult::permanent( $mapped[0], $mapped[1], $status );
	}
}
