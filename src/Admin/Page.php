<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Admin;

use Kevira\MailGateway\Config;
use Kevira\MailGateway\Gateway\Client;
use Kevira\MailGateway\Outbox\Repository;

final class Page {
	public function __construct( private readonly Config $config, private readonly Client $client, private readonly Repository $repository ) {}

	public function assets( string $hook ): void {
		if ( 'kevira_page_kevira-mail-gateway' !== $hook && 'toplevel_page_kevira-mail-gateway' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'kevira-mail-gateway-admin', KEVIRA_MAIL_GATEWAY_ASSET_URL . 'assets/admin.css', array(), KEVIRA_MAIL_GATEWAY_VERSION );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'kevira-mail-gateway' ) );
		}
		$errors = $this->config->errors();
		$health = get_transient( 'kevira_mail_gateway_health' );
		if ( ! is_array( $health ) ) {
			$health = $this->client->health();
			set_transient( 'kevira_mail_gateway_health', $health, MINUTE_IN_SECONDS );
		}
		$counts   = $this->repository->counts();
		$accepted = (int) get_option( 'kevira_mail_gateway_last_accepted', 0 );
		$failure  = get_option( 'kevira_mail_gateway_last_failure', array() );
		?>
		<div class="wrap kmg-wrap" dir="rtl">
			<header class="kmg-hero">
				<div><span class="kmg-kicker">KEVIRA INFRASTRUCTURE</span><h1><?php esc_html_e( 'Mail Gateway', 'kevira-mail-gateway' ); ?></h1><p><?php esc_html_e( 'Secure delivery and durable retry for every WordPress transactional email.', 'kevira-mail-gateway' ); ?></p></div>
				<span class="kmg-status <?php echo empty( $errors ) && ! empty( $health['healthy'] ) ? 'is-good' : 'is-warning'; ?>"><?php echo empty( $errors ) && ! empty( $health['healthy'] ) ? esc_html__( 'Ready', 'kevira-mail-gateway' ) : esc_html__( 'Needs attention', 'kevira-mail-gateway' ); ?></span>
			</header>
			<?php $this->notice(); ?>
			<section class="kmg-grid kmg-stats">
				<?php $this->stat( __( 'Pending', 'kevira-mail-gateway' ), $counts['pending'] ); ?>
				<?php $this->stat( __( 'Processing', 'kevira-mail-gateway' ), $counts['processing'] ); ?>
				<?php $this->stat( __( 'Failed', 'kevira-mail-gateway' ), $counts['failed'] ); ?>
				<?php $this->stat( __( 'Queue total', 'kevira-mail-gateway' ), $counts['total'] ); ?>
			</section>
			<div class="kmg-grid kmg-columns">
				<section class="kmg-card"><div class="kmg-card-head"><div><h2><?php esc_html_e( 'Connection', 'kevira-mail-gateway' ); ?></h2><p><?php esc_html_e( 'Configuration is read only from server constants and a secret file.', 'kevira-mail-gateway' ); ?></p></div><span class="dashicons dashicons-shield-alt"></span></div>
					<dl><div><dt><?php esc_html_e( 'Gateway URL', 'kevira-mail-gateway' ); ?></dt><dd><?php echo '' === $this->config->gatewayUrl() ? '—' : esc_html( $this->config->gatewayUrl() ); ?></dd></div><div><dt><?php esc_html_e( 'Client ID', 'kevira-mail-gateway' ); ?></dt><dd><?php echo '' === $this->config->clientId() ? '—' : esc_html( $this->config->clientId() ); ?></dd></div><div><dt><?php esc_html_e( 'Sender profile', 'kevira-mail-gateway' ); ?></dt><dd><?php echo esc_html( $this->config->senderProfile() ); ?></dd></div><div><dt><?php esc_html_e( 'Secret', 'kevira-mail-gateway' ); ?></dt><dd><?php echo in_array( 'secret_unavailable', $errors, true ) ? esc_html__( 'Unavailable', 'kevira-mail-gateway' ) : esc_html__( 'Loaded securely', 'kevira-mail-gateway' ); ?></dd></div></dl>
				</section>
				<section class="kmg-card"><div class="kmg-card-head"><div><h2><?php esc_html_e( 'Operations', 'kevira-mail-gateway' ); ?></h2><p><?php esc_html_e( 'Health and recent delivery state without exposing message data.', 'kevira-mail-gateway' ); ?></p></div><span class="dashicons dashicons-email-alt"></span></div>
					<dl><div><dt><?php esc_html_e( 'Gateway health', 'kevira-mail-gateway' ); ?></dt><dd><?php echo ! empty( $health['healthy'] ) ? esc_html__( 'Healthy', 'kevira-mail-gateway' ) : esc_html__( 'Unavailable', 'kevira-mail-gateway' ); ?></dd></div><div><dt><?php esc_html_e( 'Client authentication', 'kevira-mail-gateway' ); ?></dt><dd><?php echo empty( $errors ) ? esc_html__( 'Credentials loaded; verified on first delivery', 'kevira-mail-gateway' ) : esc_html__( 'Not ready', 'kevira-mail-gateway' ); ?></dd></div><div><dt><?php esc_html_e( 'Last accepted', 'kevira-mail-gateway' ); ?></dt><dd><?php echo $accepted ? esc_html( wp_date( 'Y-m-d H:i', $accepted ) ) : '—'; ?></dd></div><div><dt><?php esc_html_e( 'Last failure', 'kevira-mail-gateway' ); ?></dt><dd><?php echo is_array( $failure ) && ! empty( $failure['code'] ) ? esc_html( (string) $failure['code'] ) : '—'; ?></dd></div></dl>
					<div class="kmg-actions"><?php $this->actionForm( 'kevira_mail_gateway_test', __( 'Send test email', 'kevira-mail-gateway' ), true ); ?><?php $this->actionForm( 'kevira_mail_gateway_retry', __( 'Retry failed', 'kevira-mail-gateway' ) ); ?><?php $this->actionForm( 'kevira_mail_gateway_refresh', __( 'Refresh health', 'kevira-mail-gateway' ) ); ?></div>
				</section>
			</div>
			<section class="kmg-card kmg-help"><h2><?php esc_html_e( 'Server configuration', 'kevira-mail-gateway' ); ?></h2><p><?php esc_html_e( 'Define KEVIRA_MAIL_GATEWAY_URL, KEVIRA_MAIL_CLIENT_ID, KEVIRA_MAIL_SECRET_FILE and KEVIRA_MAIL_SENDER_PROFILE outside the database. The secret value is never shown or stored by WordPress.', 'kevira-mail-gateway' ); ?></p>
			<?php
			if ( $errors ) :
				?>
				<ul>
				<?php
				foreach ( $errors as $error ) :
					?>
				<li><?php echo esc_html( $error ); ?></li><?php endforeach; ?></ul><?php endif; ?></section>
		</div>
		<?php
	}

	private function stat( string $label, int $number ): void {
		echo '<article class="kmg-stat"><strong>' . esc_html( number_format_i18n( $number ) ) . '</strong><span>' . esc_html( $label ) . '</span></article>';
	}

	private function actionForm( string $action, string $label, bool $primary = false ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
		wp_nonce_field( $action );
		echo '<button class="button ' . ( $primary ? 'button-primary' : '' ) . '" type="submit">' . esc_html( $label ) . '</button></form>';
	}

	private function notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status code set after a nonced action.
		$notice   = isset( $_GET['kevira_notice'] ) ? sanitize_key( wp_unslash( $_GET['kevira_notice'] ) ) : '';
		$messages = array(
			'test_accepted'   => __( 'Test email was accepted or safely queued.', 'kevira-mail-gateway' ),
			'test_failed'     => __( 'Test email was rejected. Review connection health and server configuration.', 'kevira-mail-gateway' ),
			'retry_scheduled' => __( 'Failed messages were returned to the controlled queue.', 'kevira-mail-gateway' ),
			'refreshed'       => __( 'Gateway health was refreshed.', 'kevira-mail-gateway' ),
		);
		if ( isset( $messages[ $notice ] ) ) {
			echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( $messages[ $notice ] ) . '</p></div>';
		}
	}
}
