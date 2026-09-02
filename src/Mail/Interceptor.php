<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Mail;

use Kevira\MailGateway\Config;
use Kevira\MailGateway\Contracts\RandomSource;
use Kevira\MailGateway\Contracts\Scheduler;
use Kevira\MailGateway\Gateway\Client;
use Kevira\MailGateway\Outbox\Encryptor;
use Kevira\MailGateway\Outbox\Repository;

final class Interceptor {
	public function __construct(
		private readonly Config $config,
		private readonly MessageFactory $factory,
		private readonly Client $client,
		private readonly Repository $repository,
		private readonly Scheduler $scheduler,
		private readonly RandomSource $random,
		private readonly Encryptor $encryptor
	) {}

	/**
	 * @param null|bool          $shortCircuit Existing short-circuit value.
	 * @param array<string,mixed> $attributes Mail attributes.
	 */
	public function intercept( null|bool $shortCircuit, array $attributes ): bool {
		if ( null !== $shortCircuit ) {
			return $shortCircuit;
		}
		if ( ! $this->config->isComplete() ) {
			return $this->fail( 'configuration_incomplete', 'Kevira Mail Gateway is not configured.' );
		}
		try {
			$body        = $this->factory->createBody( $attributes );
			$idempotency = $this->uuid4();
			$result      = $this->client->send( $body, $idempotency );
			if ( $result->acceptedByGateway() ) {
				$this->recordAccepted( $result->messageId );
				return true;
			}
			if ( $result->retryable() ) {
				$encrypted = $this->encryptor->encrypt( $body );
				if ( $this->repository->enqueue( $idempotency, $encrypted, $result->code ) ) {
					$this->scheduler->schedule();
					return true;
				}
			}
			return $this->fail( $result->code, $result->message );
		} catch ( UnsupportedAttachmentsException ) {
			return $this->fail( 'attachments_unsupported', 'Attachments are not supported by Mail Gateway v1.' );
		} catch ( \Throwable ) {
			return $this->fail( 'message_rejected', 'The email could not be normalized or queued safely.' );
		}
	}

	private function recordAccepted( string $messageId ): void {
		update_option(
			'kevira_mail_gateway_last_accepted',
			array(
				'time' => time(),
				'id'   => $messageId,
			),
			false
		);
	}

	private function fail( string $code, string $message ): false {
		update_option(
			'kevira_mail_gateway_last_failure',
			array(
				'time' => time(),
				'code' => $code,
			),
			false
		);
		do_action( 'wp_mail_failed', new \WP_Error( 'kevira_mail_gateway_' . sanitize_key( $code ), $message ) );
		return false;
	}

	private function uuid4(): string {
		$bytes    = $this->random->bytes( 16 );
		$bytes[6] = chr( ( ord( $bytes[6] ) & 0x0f ) | 0x40 );
		$bytes[8] = chr( ( ord( $bytes[8] ) & 0x3f ) | 0x80 );
		$hex      = bin2hex( $bytes );
		return substr( $hex, 0, 8 ) . '-' . substr( $hex, 8, 4 ) . '-' . substr( $hex, 12, 4 ) . '-' . substr( $hex, 16, 4 ) . '-' . substr( $hex, 20 );
	}
}
