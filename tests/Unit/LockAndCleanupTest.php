<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Tests\Unit;

use Kevira\MailGateway\Outbox\Lock;
use Kevira\MailGateway\Outbox\Repository;
use PHPUnit\Framework\TestCase;

final class LockAndCleanupTest extends TestCase {
	public function testOverlappingAndAbandonedLocks(): void {
		$GLOBALS['kmg_test_options'] = array();
		$first = new Lock();
		$second = new Lock();
		$this->assertTrue( $first->acquire( 60 ) );
		$this->assertFalse( $second->acquire( 60 ) );
		$GLOBALS['kmg_test_options']['kevira_mail_gateway_worker_lock']['expires'] = time() - 1;
		$this->assertTrue( $second->acquire( 60 ) );
		$second->release();
	}

	public function testCleanupIsRestrictedToExpiredFailedRows(): void {
		$GLOBALS['wpdb'] = new class {
			public string $prefix = 'wp_';
			public string $sql = '';
			public function prepare( string $query, mixed ...$args ): string { return str_replace( '%s', "'date'", $query ); }
			public function query( string $query ): int { $this->sql = $query; return 3; }
		};
		$this->assertSame( 3, ( new Repository() )->cleanup() );
		$this->assertStringContainsString( "status = 'failed'", $GLOBALS['wpdb']->sql );
		$this->assertStringContainsString( 'updated_at <', $GLOBALS['wpdb']->sql );
	}
}
