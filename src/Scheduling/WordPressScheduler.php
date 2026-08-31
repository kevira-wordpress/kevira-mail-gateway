<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Scheduling;

use Kevira\MailGateway\Contracts\Scheduler;

final class WordPressScheduler implements Scheduler {
	public const HOOK = 'kevira_mail_gateway_process_outbox';

	public function schedule( int $delay = 5 ): void {
		if ( function_exists( 'as_next_scheduled_action' ) && function_exists( 'as_schedule_single_action' ) ) {
			if ( ! as_next_scheduled_action( self::HOOK, array(), 'kevira-mail-gateway' ) ) {
				as_schedule_single_action( time() + max( 1, $delay ), self::HOOK, array(), 'kevira-mail-gateway', true );
			}
			return;
		}
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time() + max( 1, $delay ), self::HOOK );
		}
	}

	public function clear(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK, array(), 'kevira-mail-gateway' );
		}
		wp_clear_scheduled_hook( self::HOOK );
	}
}
