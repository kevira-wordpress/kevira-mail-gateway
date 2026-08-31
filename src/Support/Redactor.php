<?php
declare(strict_types=1);
namespace Kevira\MailGateway\Support;

final class Redactor {
	public static function email( string $email ): string {
		$parts = explode( '@', strtolower( trim( $email ) ), 2 );
		if ( 2 !== count( $parts ) ) {
			return '[invalid-email]'; }
		$host = explode( '.', $parts[1] );
		return ( '' === $parts[0] ? '*' : substr( $parts[0], 0, 1 ) . '***' ) . '@***.' . ( count( $host ) > 1 ? end( $host ) : 'invalid' );
	}
	public static function message( string $message ): string {
		$message = preg_replace( '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[redacted-email]', $message ) ?? '';
		$message = preg_replace( '/v1=[a-f0-9]{64}/i', '[redacted-signature]', $message ) ?? '';
		$message = preg_replace( '/\b[a-f0-9]{64,}\b/i', '[redacted-token]', $message ) ?? '';
		return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $message ) : trim( wp_strip_all_tags( $message ) );
	}
}
