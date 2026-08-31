<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Admin;

use Kevira\MailGateway\Outbox\Repository;
use Kevira\MailGateway\Scheduling\WordPressScheduler;

final class Actions {
	public function __construct( private readonly Repository $repository, private readonly WordPressScheduler $scheduler ) {}

	public function test(): void {
		$this->authorize( 'kevira_mail_gateway_test' );
		$user      = wp_get_current_user();
		$recipient = sanitize_email( (string) $user->user_email );
		$ok        = '' !== $recipient && wp_mail( $recipient, __( 'Kevira Mail Gateway test', 'kevira-mail-gateway' ), __( 'This message confirms that WordPress can submit mail to Kevira Mail Gateway.', 'kevira-mail-gateway' ) );
		$this->redirect( $ok ? 'test_accepted' : 'test_failed' );
	}

	public function retry(): void {
		$this->authorize( 'kevira_mail_gateway_retry' );
		$count = $this->repository->retryFailed();
		if ( $count > 0 ) {
			$this->scheduler->schedule( 1 );
		}
		$this->redirect( 'retry_scheduled', $count );
	}

	public function refresh(): void {
		$this->authorize( 'kevira_mail_gateway_refresh' );
		delete_transient( 'kevira_mail_gateway_health' );
		$this->redirect( 'refreshed' );
	}

	private function authorize( string $nonce ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage Mail Gateway.', 'kevira-mail-gateway' ), 403 );
		}
		check_admin_referer( $nonce );
	}

	private function redirect( string $notice, int $count = 0 ): never {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'          => 'kevira-mail-gateway',
					'kevira_notice' => $notice,
					'count'         => $count,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
