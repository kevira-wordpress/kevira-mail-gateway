<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Cli;

use Kevira\MailGateway\Outbox\Repository;
use Kevira\MailGateway\Scheduling\WordPressScheduler;

final class QueueCommands {
	public function __construct( private readonly Repository $repository, private readonly WordPressScheduler $scheduler ) {}

	public function list(): void {
		$rows = $this->repository->summaries();
		if ( array() === $rows ) {
			\WP_CLI::success( 'The outbox is empty.' );
			return;
		}
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'id', 'status', 'attempts', 'available_at', 'last_error' ) );
	}

	/** @param list<string> $args @param array<string,string> $assocArgs */
	public function retry( array $args, array $assocArgs ): void {
		unset( $args );
		$requested = isset( $assocArgs['limit'] ) ? absint( $assocArgs['limit'] ) : Repository::MAX_FAILED_RETRY_BATCH;
		$limit     = max( 1, min( Repository::MAX_FAILED_RETRY_BATCH, $requested ) );
		$count     = $this->repository->retryFailed( $limit );
		if ( $count > 0 ) {
			$this->scheduler->schedule( 1 );
		}
		\WP_CLI::success( sprintf( '%d failed messages were returned to the queue.', $count ) );
	}

	/** @subcommand purge-failed */
	/** @param list<string> $args @param array<string,string> $assocArgs */
	public function purgeFailed( array $args, array $assocArgs ): void {
		unset( $args );
		if ( ! isset( $assocArgs['yes'] ) ) {
			\WP_CLI::confirm( 'Permanently purge all failed Mail Gateway records?' );
		}
		\WP_CLI::success( sprintf( '%d failed messages were permanently removed.', $this->repository->purgeFailed() ) );
	}
}
