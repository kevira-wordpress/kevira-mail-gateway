<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Outbox;

final class Backoff {
	public function seconds( int $attempt ): int {
		$base = min( 21600, 60 * ( 2 ** max( 0, min( 8, $attempt - 1 ) ) ) );
		return $base + random_int( 0, max( 1, (int) floor( $base * 0.2 ) ) );
	}
}
