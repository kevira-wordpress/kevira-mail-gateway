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
		if ( ! $this->lock->acquire() ) {
			return;
		}
		try {
			$this->repository->resetProcessing();
			foreach ( $this->repository->claimBatch() as $row ) {
				$attempts = (int) $row->attempts + 1;
				try {
					$body   = $this->encryptor->decrypt( (string) $row->payload_encrypted );
					$result = $this->client->send( $body, (string) $row->idempotency_key );
				} catch ( \Throwable ) {
					$this->repository->fail( (int) $row->id, $attempts, 'payload_authentication_failed' );
					continue;
				}
				if ( $result->acceptedByGateway() ) {
					$this->repository->complete( (int) $row->id );
					update_option( 'kevira_mail_gateway_last_accepted', time(), false );
				} elseif ( $result->retryable() && $attempts < self::MAX_ATTEMPTS ) {
					$this->repository->retry( (int) $row->id, $attempts, $this->backoff->seconds( $attempts ), $result->code );
				} else {
					$this->repository->fail( (int) $row->id, $attempts, $result->code );
					update_option(
						'kevira_mail_gateway_last_failure',
						array(
							'time' => time(),
							'code' => $result->code,
						),
						false
					);
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
}
