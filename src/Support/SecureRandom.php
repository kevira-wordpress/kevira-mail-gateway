<?php
declare(strict_types=1);
namespace Kevira\MailGateway\Support;

use Kevira\MailGateway\Contracts\RandomSource;
final class SecureRandom implements RandomSource {
	public function bytes( int $length ): string {
		if ( $length < 1 || $length > 1024 ) {
			throw new \InvalidArgumentException( 'Invalid secure-random byte length.' ); }
		return random_bytes( $length );
	}
	public function integer( int $minimum, int $maximum ): int {
		return random_int( $minimum, $maximum ); }
}
