<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Outbox;

final class Repository {
	public const MAX_ITEMS              = 500;
	public const MAX_FAILED_RETRY_BATCH = 50;

	public function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'kevira_mail_outbox';
	}

	public function enqueue( string $idempotencyKey, string $encryptedPayload, string $error = '' ): bool {
		global $wpdb;
		if ( $this->totalCount() >= self::MAX_ITEMS ) {
			return false;
		}
		$now    = current_time( 'mysql', true );
		$result = $wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO ' . $this->table() . ' (idempotency_key,payload_encrypted,status,attempts,available_at,created_at,updated_at,last_error) VALUES (%s,%s,%s,0,%s,%s,%s,%s)',
				$idempotencyKey,
				$encryptedPayload,
				'pending',
				$now,
				$now,
				$now,
				substr( $error, 0, 500 )
			)
		);
		return false !== $result;
	}

	/** @return list<object> */
	public function claimBatch( int $limit = 5 ): array {
		global $wpdb;
		$limit   = max( 1, min( 10, $limit ) );
		$now     = current_time( 'mysql', true );
		$rows    = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table() . " WHERE status = 'pending' AND available_at <= %s ORDER BY id ASC LIMIT %d",
				$now,
				$limit
			)
		);
		$claimed = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$result = $wpdb->query(
				$wpdb->prepare(
					'UPDATE ' . $this->table() . " SET status = 'processing', updated_at = %s WHERE id = %d AND status = 'pending' AND available_at <= %s",
					$now,
					(int) $row->id,
					$now
				)
			);
			if ( 1 === $result ) {
				$row->status     = 'processing';
				$row->updated_at = $now;
				$claimed[]       = $row;
			}
		}
		return $claimed;
	}

	public function complete( int $id ): void {
		global $wpdb;
		$wpdb->delete( $this->table(), array( 'id' => $id ), array( '%d' ) );
	}

	public function retry( int $id, int $attempts, int $delay, string $error ): void {
		global $wpdb;
		$wpdb->update(
			$this->table(),
			array(
				'status'       => 'pending',
				'attempts'     => $attempts,
				'available_at' => gmdate( 'Y-m-d H:i:s', time() + $delay ),
				'updated_at'   => current_time( 'mysql', true ),
				'last_error'   => substr( $error, 0, 500 ),
			),
			array( 'id' => $id )
		);
	}

	public function fail( int $id, int $attempts, string $error ): void {
		global $wpdb;
		$wpdb->update(
			$this->table(),
			array(
				'status'     => 'failed',
				'attempts'   => $attempts,
				'updated_at' => current_time( 'mysql', true ),
				'last_error' => substr( $error, 0, 500 ),
			),
			array( 'id' => $id )
		);
	}

	public function retryFailed( int $limit = self::MAX_FAILED_RETRY_BATCH ): int {
		global $wpdb;
		$limit = max( 1, min( self::MAX_FAILED_RETRY_BATCH, $limit ) );
		$now   = current_time( 'mysql', true );
		return (int) $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . $this->table() . " SET status = 'pending', attempts = 0, available_at = %s, updated_at = %s WHERE id IN (SELECT id FROM (SELECT id FROM " . $this->table() . " WHERE status = 'failed' ORDER BY id ASC LIMIT %d) AS retryable)",
				$now,
				$now,
				$limit
			)
		);
	}

	/** @return list<array{id:int,status:string,attempts:int,available_at:string,last_error:string}> */
	public function summaries( int $limit = 50 ): array {
		global $wpdb;
		$limit  = max( 1, min( 100, $limit ) );
		$rows   = $wpdb->get_results( $wpdb->prepare( 'SELECT id,status,attempts,available_at,last_error FROM ' . $this->table() . ' ORDER BY id DESC LIMIT %d', $limit ), ARRAY_A );
		$result = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$result[] = array(
				'id'           => (int) $row['id'],
				'status'       => (string) $row['status'],
				'attempts'     => (int) $row['attempts'],
				'available_at' => (string) $row['available_at'],
				'last_error'   => (string) $row['last_error'],
			);
		}
		return $result;
	}

	public function purgeFailed(): int {
		global $wpdb;
		return (int) $wpdb->delete( $this->table(), array( 'status' => 'failed' ), array( '%s' ) );
	}

	public function resetProcessing(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . $this->table() . " SET status = 'pending', updated_at = %s WHERE status = 'processing' AND updated_at < %s", current_time( 'mysql', true ), gmdate( 'Y-m-d H:i:s', time() - 300 ) ) );
	}

	/** @return array{pending:int,processing:int,failed:int,total:int} */
	public function counts(): array {
		global $wpdb;
		$counts = array(
			'pending'    => 0,
			'processing' => 0,
			'failed'     => 0,
			'total'      => 0,
		);
		$rows   = $wpdb->get_results( 'SELECT status, COUNT(*) AS total FROM ' . $this->table() . ' GROUP BY status' );
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( isset( $counts[ $row->status ] ) ) {
				$counts[ $row->status ] = (int) $row->total;
			}
			$counts['total'] += (int) $row->total;
		}
		return $counts;
	}

	public function pendingCount(): int {
		$counts = $this->counts();
		return $counts['pending'] + $counts['processing'];
	}

	public function totalCount(): int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $this->table() );
	}

	public function undecryptableCount(): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $this->table() . ' WHERE status = %s AND last_error = %s', 'failed', 'payload_decryption_failed' ) );
	}

	public function cleanup(): int {
		global $wpdb;
		return (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $this->table() . " WHERE status = 'failed' AND updated_at < %s", gmdate( 'Y-m-d H:i:s', time() - WEEK_IN_SECONDS ) ) );
	}
}
