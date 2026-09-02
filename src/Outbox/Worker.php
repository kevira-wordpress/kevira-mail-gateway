<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Outbox;

use Kevira\MailGateway\Contracts\Scheduler;
use Kevira\MailGateway\Gateway\Client;

final class Worker {
	public const MAX_ATTEMPTS = 5;

	public function __construct(
		private readonly Repository $repository,
		private readonly Encryptor $encryptor,
		private readonly Client $client,
		private readonly Lock $lock,
		private readonly Backoff $backoff,
		private readonly Scheduler $scheduler
	) {}

	public function run(): void {
		if ( ! $this->lock->acquire( Lock::DEFAULT_TTL ) ) {
			return;
		}
		try {
			$this->repository->resetProcessing();
			$rows = $this->repository->claimBatch();
			foreach ( $rows as $row ) {
				if ( ! $this->lock->renew( Lock::DEFAULT_TTL ) ) {
					break;
				}
				$attempts = (int) $row->attempts + 1;
				try {
					$body = $this->encryptor->decrypt( (string) $row->payload_encrypted );
				} catch ( \Throwable ) {
					$this->repository->fail( (int) $row->id, $attempts, 'payload_decryption_failed' );
					$this->recordFailure( 'payload_decryption_failed' );
					continue;
				}
				$result = $this->client->send( $body, (string) $row->idempotency_key );
				if ( $result->acceptedByGateway() ) {
					$this->repository->complete( (int) $row->id );
					update_option(
						'kevira_mail_gateway_last_accepted',
						array(
							'time' => time(),
							'id'   => $result->messageId,
						),
						false
					);
				} elseif ( $result->retryable() && $attempts < self::MAX_ATTEMPTS ) {
					$this->repository->retry( (int) $row->id, $attempts, $this->backoff->seconds( $attempts ), $result->code );
				} else {
					$this->repository->fail( (int) $row->id, $attempts, $result->code );
					$this->recordFailure( $result->code );
				}
			}
			$this->repository->cleanup();
			if ( $this->repository->pendingCount() > 0 ) {
				$this->scheduler->schedule( 60 );
			}
		} finally {
			$this->lock->release();
		}
	}

	private function recordFailure( string $code ): void {
		update_option(
			'kevira_mail_gateway_last_failure',
			array(
				'time' => time(),
				'code' => $code,
			),
			false
		);
	}
}
