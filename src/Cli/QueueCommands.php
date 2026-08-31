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

	public function retry(): void {
		$count = $this->repository->retryFailed();
		if ( $count > 0 ) {
			$this->scheduler->schedule( 1 );
		}
		\WP_CLI::success( sprintf( '%d failed messages were returned to the queue.', $count ) );
	}

	/** @subcommand purge-failed */
	public function purgeFailed(): void {
		\WP_CLI::success( sprintf( '%d failed messages were permanently removed.', $this->repository->purgeFailed() ) );
	}
}
