<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Mail;

final class HeaderNormalizer {
	/**
	 * @param string|list<string> $headers
	 * @return array{content_type:string,charset:string,cc:list<string>,bcc:list<string>,reply_to:list<string>}
	 */
	public function normalize( string|array $headers ): array {
		$lines  = is_array( $headers ) ? $headers : preg_split( '/\r?\n/', $headers );
		$result = array(
			'content_type' => 'text/plain',
			'charset'      => 'UTF-8',
			'cc'           => array(),
			'bcc'          => array(),
			'reply_to'     => array(),
		);

		foreach ( array_slice( is_array( $lines ) ? $lines : array(), 0, 100 ) as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line || strlen( $line ) > 8192 || str_contains( $line, "\0" ) ) {
				continue;
			}

			if ( preg_match( '/^Content-Type:\s*([^;\s]+)(?:;\s*charset=([A-Za-z0-9._-]+))?/i', $line, $matches ) ) {
				$type = strtolower( $matches[1] );
				if ( in_array( $type, array( 'text/plain', 'text/html' ), true ) ) {
					$result['content_type'] = $type;
				}
				if ( isset( $matches[2] ) && preg_match( '/^[A-Za-z0-9._-]{1,32}$/', $matches[2] ) ) {
					$result['charset'] = strtoupper( $matches[2] );
				}
				continue;
			}

			if ( preg_match( '/^(Cc|Bcc|Reply-To):\s*(.+)$/i', $line, $matches ) ) {
				$key = match ( strtolower( $matches[1] ) ) {
					'cc' => 'cc',
					'bcc' => 'bcc',
					default => 'reply_to',
				};
				$result[ $key ] = array_merge( $result[ $key ], $this->addresses( $matches[2] ) );
			}
		}

		foreach ( array( 'cc', 'bcc', 'reply_to' ) as $key ) {
			$result[ $key ] = array_values( array_unique( $result[ $key ] ) );
		}

		return $result;
	}

	/** @return list<string> */
	/**
	 * @param string|list<string> $value Recipient input.
	 * @return list<string>
	 */
	public function addresses( string|array $value ): array {
		$items = is_array( $value ) ? $value : preg_split( '/\s*,\s*/', $value );
		$valid = array();
		foreach ( is_array( $items ) ? $items : array() as $item ) {
			$item = trim( (string) $item );
			if ( preg_match( '/<([^<>]+)>/', $item, $match ) ) {
				$item = trim( $match[1] );
			}
			if ( ! str_contains( $item, "\r" ) && ! str_contains( $item, "\n" ) && filter_var( $item, FILTER_VALIDATE_EMAIL ) ) {
				$valid[] = strtolower( $item );
			}
		}
		return array_values( array_unique( $valid ) );
	}
}
