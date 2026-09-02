<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Outbox;

final class Lock {
	private const OPTION     = 'kevira_mail_gateway_worker_lock';
	public const DEFAULT_TTL = 150;
	private ?string $token   = null;

	public function acquire( int $ttl = self::DEFAULT_TTL ): bool {
		$ttl         = max( 120, $ttl );
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

	public function renew( int $ttl = self::DEFAULT_TTL ): bool {
		$current = get_option( self::OPTION );
		if ( null === $this->token || ! is_array( $current ) || ! hash_equals( (string) ( $current['token'] ?? '' ), $this->token ) ) {
			return false;
		}
		$newExpiry = time() + max( 120, $ttl );
		if ( (int) ( $current['expires'] ?? 0 ) >= $newExpiry ) {
			return true;
		}
		$current['expires'] = $newExpiry;
		return update_option( self::OPTION, $current, false );
	}

	public function release(): void {
		$current = get_option( self::OPTION );
		if ( is_array( $current ) && hash_equals( (string) ( $current['token'] ?? '' ), (string) $this->token ) ) {
			delete_option( self::OPTION );
		}
		$this->token = null;
	}
}
