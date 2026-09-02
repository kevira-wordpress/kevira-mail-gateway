<?php
declare(strict_types=1);
namespace Kevira\MailGateway\Gateway;

use Kevira\MailGateway\Support\Json;
use Kevira\MailGateway\Support\Redactor;
final class ResponseClassifier {
	public function classify( int $status, string $body = '', string $networkError = '' ): DeliveryResult {
		if ( '' !== $networkError ) {
			return DeliveryResult::transient( 'network_error', Redactor::message( $networkError ) ); }
		if ( 200 === $status || 202 === $status ) {
			try {
				$data = Json::decodeObject( $body, 16384 );
			} catch ( \UnexpectedValueException ) {
				return DeliveryResult::permanent( 'invalid_response', 'Gateway returned an invalid response.', $status );
			}
			$messageId = isset( $data['id'] ) && is_string( $data['id'] ) ? $data['id'] : '';
			if ( ! preg_match( '/^[a-zA-Z0-9][a-zA-Z0-9._:-]{0,127}$/D', $messageId ) || 'queued' !== ( $data['status'] ?? null ) ) {
				return DeliveryResult::permanent( 'invalid_response', 'Gateway returned an invalid response.', $status );
			}
			return DeliveryResult::accepted( $status, $messageId );
		}

		$errorCode = $this->errorCode( $body );
		$specific  = array(
			'nonce_replay'               => array( 'transient', 'Gateway rejected the request nonce; the next attempt will use a fresh nonce.' ),
			'idempotency_conflict'       => array( 'permanent', 'Gateway detected an idempotency contract conflict.' ),
			'idempotency_in_progress'    => array( 'transient', 'Gateway is still processing the idempotent request.' ),
			'rate_limited'               => array( 'transient', 'Gateway rate limit reached.' ),
			'daily_quota_exceeded'       => array( 'transient', 'Gateway daily quota is temporarily exhausted.' ),
			'invalid_signature'          => array( 'permanent', 'Gateway authentication failed.' ),
			'stale_timestamp'            => array( 'permanent', 'Gateway rejected a stale timestamp.' ),
			'invalid_payload'            => array( 'permanent', 'Gateway rejected the message payload.' ),
			'sender_profile_not_allowed' => array( 'permanent', 'Gateway rejected the configured sender profile.' ),
		);
		if ( in_array( $status, array( 408, 425, 429 ), true ) || $status >= 500 || 0 === $status ) {
			if ( isset( $specific[ $errorCode ] ) && 'transient' === $specific[ $errorCode ][0] ) {
				return DeliveryResult::transient( $errorCode, $specific[ $errorCode ][1], $status );
			}
			return DeliveryResult::transient( 429 === $status ? 'rate_limited' : 'gateway_unavailable', 429 === $status ? 'Gateway rate limit reached.' : 'Gateway is temporarily unavailable.', $status );
		}
		if ( isset( $specific[ $errorCode ] ) ) {
			return 'transient' === $specific[ $errorCode ][0]
				? DeliveryResult::transient( $errorCode, $specific[ $errorCode ][1], $status )
				: DeliveryResult::permanent( $errorCode, $specific[ $errorCode ][1], $status );
		}

		$map    = array(
			400 => array( 'invalid_payload', 'Gateway rejected the message payload.' ),
			401 => array( 'invalid_signature', 'Gateway authentication failed.' ),
			403 => array( 'sender_profile_not_allowed', 'Gateway client or sender policy rejected the message.' ),
			413 => array( 'payload_too_large', 'Gateway rejected an oversized payload.' ),
		);
		$mapped = $map[ $status ] ?? array( 'gateway_rejected', 'Gateway rejected the request.' );
		return DeliveryResult::permanent( $mapped[0], $mapped[1], $status );
	}

	private function errorCode( string $body ): string {
		if ( '' === $body ) {
			return '';
		}
		try {
			$data = Json::decodeObject( $body, 16384 );
		} catch ( \UnexpectedValueException ) {
			return '';
		}
		$error = $data['error'] ?? null;
		$code  = is_array( $error ) && isset( $error['code'] ) && is_string( $error['code'] ) ? $error['code'] : '';
		return preg_match( '/^[a-z][a-z0-9_]{0,63}$/D', $code ) ? $code : '';
	}
}
