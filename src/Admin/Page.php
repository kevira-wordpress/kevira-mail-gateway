<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Admin;

use Kevira\MailGateway\Config;
use Kevira\MailGateway\Gateway\Client;
use Kevira\MailGateway\Outbox\Repository;

final class Page {
	public function __construct( private readonly Config $config, private readonly Client $client, private readonly Repository $repository ) {}

	public function assets( string $hook ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page routing.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'kevira-mail-gateway' !== $page && ! str_contains( $hook, 'kevira-mail-gateway' ) ) {
			return;
		}
		$path    = KEVIRA_MAIL_GATEWAY_PATH . 'assets/admin.css';
		$version = is_readable( $path ) ? KEVIRA_MAIL_GATEWAY_VERSION . '.' . filemtime( $path ) : KEVIRA_MAIL_GATEWAY_VERSION;
		wp_enqueue_style( 'kevira-mail-gateway-admin', KEVIRA_MAIL_GATEWAY_ASSET_URL . 'assets/admin.css', array(), $version );
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
		$counts        = $this->repository->counts();
		$accepted      = get_option( 'kevira_mail_gateway_last_accepted', array() );
		$acceptedTime  = is_array( $accepted ) ? (int) ( $accepted['time'] ?? 0 ) : (int) $accepted;
		$failure       = get_option( 'kevira_mail_gateway_last_failure', array() );
		$undecryptable = $this->repository->undecryptableCount();
		?>
		<div class="wrap kevira-admin kmg-admin" dir="rtl">
			<header class="kevira-admin__header">
				<div><p class="kevira-admin__eyebrow">Kevira Plugins</p><h1><?php esc_html_e( 'درگاه ایمیل کویرا', 'kevira-mail-gateway' ); ?></h1><p><?php esc_html_e( 'ارسال امن ایمیل‌های وردپرس و مدیریت صف تلاش مجدد، بدون نگهداری اطلاعات محرمانه در پنل.', 'kevira-mail-gateway' ); ?></p></div>
				<div class="kmg-header-state"><span class="kevira-admin__version"><?php echo esc_html( KEVIRA_MAIL_GATEWAY_VERSION ); ?></span><span class="kevira-admin__status <?php echo empty( $errors ) && ! empty( $health['healthy'] ) ? 'kevira-admin__status--success' : 'kevira-admin__status--warning'; ?>"><?php echo empty( $errors ) && ! empty( $health['healthy'] ) ? esc_html__( 'آماده', 'kevira-mail-gateway' ) : esc_html__( 'نیازمند بررسی', 'kevira-mail-gateway' ); ?></span></div>
			</header>
			<?php $this->productNavigation(); ?>
			<?php $this->notice(); ?>
			<section class="kevira-admin__summary">
				<?php $this->stat( __( 'در انتظار', 'kevira-mail-gateway' ), $counts['pending'] ); ?>
				<?php $this->stat( __( 'در حال پردازش', 'kevira-mail-gateway' ), $counts['processing'] ); ?>
				<?php $this->stat( __( 'ناموفق', 'kevira-mail-gateway' ), $counts['failed'] ); ?>
				<?php $this->stat( __( 'کل صف', 'kevira-mail-gateway' ), $counts['total'] ); ?>
			</section>
			<div class="kmg-grid kmg-columns">
				<section class="kevira-admin__panel"><div class="kevira-admin__section-header"><div><h2><?php esc_html_e( 'اتصال امن', 'kevira-mail-gateway' ); ?></h2><p><?php esc_html_e( 'این اطلاعات فقط از تنظیمات محافظت‌شده سرور خوانده می‌شوند.', 'kevira-mail-gateway' ); ?></p></div><span class="dashicons dashicons-shield-alt"></span></div>
					<dl class="kmg-details"><div><dt><?php esc_html_e( 'آدرس Gateway', 'kevira-mail-gateway' ); ?></dt><dd><?php echo '' === $this->config->gatewayUrl() ? '—' : esc_html( $this->config->gatewayUrl() ); ?></dd></div><div><dt><?php esc_html_e( 'شناسه Client', 'kevira-mail-gateway' ); ?></dt><dd><?php echo '' === $this->config->clientId() ? '—' : esc_html( $this->config->clientId() ); ?></dd></div><div><dt><?php esc_html_e( 'پروفایل فرستنده', 'kevira-mail-gateway' ); ?></dt><dd><?php echo esc_html( $this->config->senderProfile() ); ?></dd></div><div><dt><?php esc_html_e( 'کلید امضا', 'kevira-mail-gateway' ); ?></dt><dd><?php echo in_array( 'secret_unavailable', $errors, true ) ? esc_html__( 'در دسترس نیست', 'kevira-mail-gateway' ) : esc_html__( 'امن بارگذاری شد', 'kevira-mail-gateway' ); ?></dd></div><div><dt><?php esc_html_e( 'کلید رمزنگاری صف', 'kevira-mail-gateway' ); ?></dt><dd><?php echo in_array( 'queue_key_unavailable', $errors, true ) ? esc_html__( 'در دسترس نیست', 'kevira-mail-gateway' ) : esc_html__( 'امن بارگذاری شد', 'kevira-mail-gateway' ); ?></dd></div></dl>
				</section>
				<section class="kevira-admin__panel"><div class="kevira-admin__section-header"><div><h2><?php esc_html_e( 'وضعیت ارسال', 'kevira-mail-gateway' ); ?></h2><p><?php esc_html_e( 'سلامت سرویس و آخرین رویدادها، بدون نمایش متن یا گیرندگان ایمیل.', 'kevira-mail-gateway' ); ?></p></div><span class="dashicons dashicons-email-alt"></span></div>
					<dl class="kmg-details"><div><dt><?php esc_html_e( 'سلامت Gateway', 'kevira-mail-gateway' ); ?></dt><dd><?php echo ! empty( $health['healthy'] ) ? esc_html__( 'در دسترس', 'kevira-mail-gateway' ) : esc_html__( 'در دسترس نیست', 'kevira-mail-gateway' ); ?></dd></div><div><dt><?php esc_html_e( 'احراز هویت Client', 'kevira-mail-gateway' ); ?></dt><dd><?php echo empty( $errors ) ? esc_html__( 'اطلاعات آماده است؛ با اولین ارسال تأیید می‌شود', 'kevira-mail-gateway' ) : esc_html__( 'آماده نیست', 'kevira-mail-gateway' ); ?></dd></div><div><dt><?php esc_html_e( 'آخرین پذیرش', 'kevira-mail-gateway' ); ?></dt><dd><?php echo $acceptedTime > 0 ? esc_html( wp_date( 'Y-m-d H:i', $acceptedTime ) ) : '—'; ?></dd></div><div><dt><?php esc_html_e( 'شناسه پیام Gateway', 'kevira-mail-gateway' ); ?></dt><dd><?php echo is_array( $accepted ) && ! empty( $accepted['id'] ) ? esc_html( (string) $accepted['id'] ) : '—'; ?></dd></div><div><dt><?php esc_html_e( 'آخرین خطا', 'kevira-mail-gateway' ); ?></dt><dd><?php echo is_array( $failure ) && ! empty( $failure['code'] ) ? esc_html( $this->diagnosticLabel( (string) $failure['code'] ) ) : '—'; ?></dd></div><div><dt><?php esc_html_e( 'رکوردهای غیرقابل رمزگشایی', 'kevira-mail-gateway' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $undecryptable ) ); ?></dd></div></dl>
					<div class="kmg-actions"><?php $this->actionForm( 'kevira_mail_gateway_test', __( 'ارسال ایمیل آزمایشی', 'kevira-mail-gateway' ), true ); ?><?php $this->actionForm( 'kevira_mail_gateway_retry', __( 'تلاش مجدد ناموفق‌ها', 'kevira-mail-gateway' ) ); ?><?php $this->actionForm( 'kevira_mail_gateway_refresh', __( 'بررسی دوباره سلامت', 'kevira-mail-gateway' ) ); ?></div>
				</section>
			</div>
			<section class="kevira-admin__panel kmg-help"><div class="kevira-admin__section-header"><div><h2><?php esc_html_e( 'تنظیمات موردنیاز سرور', 'kevira-mail-gateway' ); ?></h2><p><?php esc_html_e( 'پنج ثابت KEVIRA_MAIL_GATEWAY_URL، KEVIRA_MAIL_CLIENT_ID، KEVIRA_MAIL_SECRET_FILE، KEVIRA_MAIL_QUEUE_KEY_FILE و KEVIRA_MAIL_SENDER_PROFILE باید خارج از دیتابیس تعریف شوند. مقدار کلیدها هرگز در وردپرس ذخیره یا نمایش داده نمی‌شود.', 'kevira-mail-gateway' ); ?></p></div></div>
			<?php
			if ( $errors ) :
				?>
				<ul>
				<?php
				foreach ( $errors as $error ) :
					?>
				<li><?php echo esc_html( $this->diagnosticLabel( $error ) ); ?></li><?php endforeach; ?></ul><?php endif; ?></section>
		</div>
		<?php
	}

	private function stat( string $label, int $number ): void {
		echo '<article class="kevira-admin__summary-card"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( number_format_i18n( $number ) ) . '</strong></article>';
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
			'test_accepted'   => __( 'ایمیل آزمایشی پذیرفته شد یا با امنیت در صف قرار گرفت.', 'kevira-mail-gateway' ),
			'test_failed'     => __( 'ایمیل آزمایشی پذیرفته نشد؛ وضعیت اتصال و تنظیمات سرور را بررسی کنید.', 'kevira-mail-gateway' ),
			'retry_scheduled' => __( 'پیام‌های ناموفق به‌صورت کنترل‌شده به صف بازگردانده شدند.', 'kevira-mail-gateway' ),
			'refreshed'       => __( 'وضعیت سلامت Gateway دوباره بررسی شد.', 'kevira-mail-gateway' ),
		);
		if ( isset( $messages[ $notice ] ) ) {
			echo '<div class="kevira-admin__notice kevira-admin__notice--success" role="status"><p>' . esc_html( $messages[ $notice ] ) . '</p></div>';
		}
	}

	private function productNavigation(): void {
		$products      = array(
			'auth'            => array( 'Auth', 'kevira-auth', 'kevira-auth/kevira-auth.php' ),
			'sms'             => array( 'SMS', 'kevira-sms', 'kevira-sms/kevira-sms.php' ),
			'shipping'        => array( 'Shipping', 'kevira-shipping', 'kevira-shipping/kevira-shipping.php' ),
			'checkout'        => array( 'Checkout', 'kevira-checkout', 'kevira-checkout/kevira-checkout.php' ),
			'payment'         => array( 'Payment', 'kevira-payment', 'kevira-payment/kevira-payment.php' ),
			'localization'    => array( 'Localization', 'kevira-plugins', 'kevira-localization/kevira-localization.php' ),
			'coffee-blocks'   => array( 'Coffee Blocks', 'kevira-coffee-blocks', 'kevira-coffee-blocks/kevira-coffee-blocks.php' ),
			'image-optimizer' => array( 'Image Optimizer', 'kevira-image-optimizer', 'kevira-image-optimizer/kevira-image-optimizer.php' ),
			'mail-gateway'    => array( 'Mail Gateway', 'kevira-mail-gateway', 'kevira-mail-gateway/kevira-mail-gateway.php' ),
		);
		$active        = array_flip( (array) get_option( 'active_plugins', array() ) );
		$networkActive = is_multisite() ? (array) get_site_option( 'active_sitewide_plugins', array() ) : array();
		?>
		<nav class="kevira-admin__products" aria-label="<?php esc_attr_e( 'افزونه‌های Kevira', 'kevira-mail-gateway' ); ?>">
			<?php foreach ( $products as $id => $product ) : ?>
				<?php
				if ( ! defined( 'WP_PLUGIN_DIR' ) || ! is_file( WP_PLUGIN_DIR . '/' . $product[2] ) || ( ! isset( $active[ $product[2] ] ) && ! isset( $networkActive[ $product[2] ] ) ) ) {
					continue; }
				?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $product[1] ) ); ?>" <?php echo 'mail-gateway' === $id ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $product[0] ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	private function diagnosticLabel( string $code ): string {
		return array(
			'gateway_url_missing'       => 'آدرس Gateway تعریف نشده یا معتبر نیست.',
			'gateway_https_required'    => 'در محیط عملیاتی، آدرس Gateway باید HTTPS باشد.',
			'gateway_url_invalid'       => 'آدرس Gateway شامل بخش غیرمجاز است.',
			'client_id_invalid'         => 'شناسه Client معتبر نیست.',
			'sender_profile_invalid'    => 'پروفایل فرستنده معتبر نیست.',
			'secret_unavailable'        => 'فایل کلید امضا در دسترس یا ایمن نیست.',
			'queue_key_unavailable'     => 'فایل کلید رمزنگاری صف در دسترس یا ایمن نیست.',
			'payload_decryption_failed' => 'یک پیام صف با کلید فعلی قابل رمزگشایی نیست.',
		)[ $code ] ?? $code;
	}
}
