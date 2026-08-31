<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Health;

use Kevira\MailGateway\Config;
use Kevira\MailGateway\Gateway\Client;
use Kevira\MailGateway\Outbox\Repository;

final class SiteHealth {
	public function __construct( private readonly Config $config, private readonly Client $client, private readonly Repository $repository ) {}

	/**
	 * @param array<string,mixed> $tests Registered Site Health tests.
	 * @return array<string,mixed>
	 */
	public function register( array $tests ): array {
		$tests['direct']['kevira_mail_gateway_configuration'] = array(
			'label' => __( 'Kevira Mail Gateway configuration', 'kevira-mail-gateway' ),
			'test'  => array( $this, 'configuration' ),
		);
		$tests['direct']['kevira_mail_gateway_queue']         = array(
			'label' => __( 'Kevira Mail Gateway queue', 'kevira-mail-gateway' ),
			'test'  => array( $this, 'queue' ),
		);
		return $tests;
	}

	/** @return array<string,mixed> */
	public function configuration(): array {
		$health = $this->client->health();
		$good   = $this->config->isComplete() && $health['healthy'];
		return array(
			'label'       => $good ? __( 'Mail Gateway is ready', 'kevira-mail-gateway' ) : __( 'Mail Gateway needs attention', 'kevira-mail-gateway' ),
			'status'      => $good ? 'good' : 'critical',
			'badge'       => array(
				'label' => 'Kevira Mail Gateway',
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html( $good ? __( 'Server configuration and Gateway health are valid.', 'kevira-mail-gateway' ) : __( 'Review server constants, secret-file access, HTTPS and Gateway health.', 'kevira-mail-gateway' ) ) . '</p>',
			'test'        => 'kevira_mail_gateway_configuration',
		);
	}

	/** @return array<string,mixed> */
	public function queue(): array {
		$counts = $this->repository->counts();
		$good   = $counts['failed'] < 1 && $counts['total'] < 100;
		return array(
			'label'       => $good ? __( 'Mail queue is healthy', 'kevira-mail-gateway' ) : __( 'Mail queue requires review', 'kevira-mail-gateway' ),
			'status'      => $good ? 'good' : 'recommended',
			'badge'       => array(
				'label' => 'Kevira Mail Gateway',
				'color' => 'blue',
			),
			/* translators: 1: queued message count, 2: failed message count. */
			'description' => '<p>' . esc_html( sprintf( __( '%1$d queued and %2$d failed messages.', 'kevira-mail-gateway' ), $counts['pending'] + $counts['processing'], $counts['failed'] ) ) . '</p>',
			'test'        => 'kevira_mail_gateway_queue',
		);
	}
}
