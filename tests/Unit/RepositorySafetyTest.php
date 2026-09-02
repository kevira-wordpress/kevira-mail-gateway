<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Tests\Unit;

use Kevira\MailGateway\Outbox\Repository;
use PHPUnit\Framework\TestCase;

final class RepositorySafetyTest extends TestCase {
	public function testTotalOutboxCapIncludesFailedRows(): void {
		$GLOBALS['wpdb'] = new class {
			public string $prefix = 'wp_';
			public int $writes = 0;
			public function get_var( string $query ): int { unset( $query ); return Repository::MAX_ITEMS; }
			public function query( string $query ): int { unset( $query ); ++$this->writes; return 1; }
			public function prepare( string $query, mixed ...$args ): string { unset( $args ); return $query; }
		};
		$this->assertFalse( ( new Repository() )->enqueue( 'idempotency-key', 'encrypted' ) );
		$this->assertSame( 0, $GLOBALS['wpdb']->writes );
	}

	public function testFailedRetryIsBounded(): void {
		$GLOBALS['wpdb'] = new class {
			public string $prefix = 'wp_';
			/** @var list<mixed> */ public array $preparedArgs = array();
			public string $sql = '';
			public function prepare( string $query, mixed ...$args ): string { $this->preparedArgs = $args; return $query . ' LIMIT-ARG-' . end( $args ); }
			public function query( string $query ): int { $this->sql = $query; return Repository::MAX_FAILED_RETRY_BATCH; }
		};
		$count = ( new Repository() )->retryFailed( 9999 );
		$this->assertSame( Repository::MAX_FAILED_RETRY_BATCH, $count );
		$this->assertSame( Repository::MAX_FAILED_RETRY_BATCH, end( $GLOBALS['wpdb']->preparedArgs ) );
		$this->assertStringContainsString( "status = 'failed'", $GLOBALS['wpdb']->sql );
	}

	public function testClaimUsesConditionalAtomicStateTransition(): void {
		$GLOBALS['wpdb'] = new class {
			public string $prefix = 'wp_';
			public int $updates = 0;
			/** @var list<string> */ public array $queries = array();
			public function prepare( string $query, mixed ...$args ): string { unset( $args ); return $query; }
			/** @return list<object> */ public function get_results( string $query ): array {
				unset( $query );
				return array(
					(object) array( 'id' => 1, 'status' => 'pending', 'attempts' => 0 ),
					(object) array( 'id' => 2, 'status' => 'pending', 'attempts' => 0 ),
				);
			}
			public function query( string $query ): int {
				$this->queries[] = $query;
				++$this->updates;
				return 1 === $this->updates ? 1 : 0;
			}
		};
		$claimed = ( new Repository() )->claimBatch( 2 );
		$this->assertCount( 1, $claimed );
		$this->assertSame( 1, (int) $claimed[0]->id );
		$this->assertStringContainsString( "status = 'pending'", $GLOBALS['wpdb']->queries[0] );
		$this->assertStringContainsString( 'available_at <=', $GLOBALS['wpdb']->queries[0] );
	}
}
