<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Outbox;

final class Lock {
	private const OPTION   = 'kevira_mail_gateway_worker_lock';
	private ?string $token = null;

	public function acquire( int $ttl = 60 ): bool {
		$this->token = bin2hex( random_bytes( 16 ) );
		$value       = array(
			'token'   => $this->token,
			'expires' => time() + $ttl,
		);
		if ( add_option( self::OPTION, $value, '', false ) ) {
			return true;
		}
		$current = get_option( self::OPTION );
		if ( is_array( $current ) && (int) ( $current['expires'] ?? 0 ) < time() ) {
			delete_option( self::OPTION );
			return add_option( self::OPTION, $value, '', false );
		}
		return false;
	}

	public function release(): void {
		$current = get_option( self::OPTION );
		if ( is_array( $current ) && hash_equals( (string) ( $current['token'] ?? '' ), (string) $this->token ) ) {
			delete_option( self::OPTION );
		}
		$this->token = null;
	}
}
