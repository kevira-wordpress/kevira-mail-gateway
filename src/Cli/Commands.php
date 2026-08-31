<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Cli;

use Kevira\MailGateway\Config;
use Kevira\MailGateway\Gateway\Client;
use Kevira\MailGateway\Outbox\Repository;
use Kevira\MailGateway\Outbox\Worker;

final class Commands {
	public function __construct( private readonly Config $config, private readonly Client $client, private readonly Repository $repository, private readonly Worker $worker ) {}

	/** @subcommand status */
	public function status(): void {
		\WP_CLI::line(
			wp_json_encode(
				array(
					'configured' => $this->config->isComplete(),
					'health'     => $this->client->health(),
					'queue'      => $this->repository->counts(),
				),
				JSON_PRETTY_PRINT
			)
		);
	}

	/** @param list<string> $args @param array<string,string> $assoc_args */
	public function test( array $args, array $assoc_args ): void {
		$recipient = sanitize_email( (string) ( $assoc_args['to'] ?? get_option( 'admin_email' ) ) );
		if ( '' === $recipient || ! wp_mail( $recipient, 'Kevira Mail Gateway test', 'WordPress submitted this test through Kevira Mail Gateway.' ) ) {
			\WP_CLI::error( 'The test message was rejected.' );
		}
		\WP_CLI::success( 'The test message was accepted or safely queued.' );
	}

	/** @subcommand process */
	public function process(): void {
		$this->worker->run();
		\WP_CLI::success( 'A bounded queue batch was processed.' );
	}
}
