<?php
declare(strict_types=1);
namespace Kevira\MailGateway\Support;

final class Json {
	/** @param array<string|int,mixed> $value */
	public static function encode( array $value ): string {
		$encoded = wp_json_encode( self::normalize( $value ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR );
		if ( ! is_string( $encoded ) ) {
			throw new \RuntimeException( 'Unable to serialize the mail payload.' ); }
		return $encoded;
	}

	/** @return array<string,mixed> */
	public static function decodeObject( string $json, int $maxBytes = 131072 ): array {
		if ( '' === $json || strlen( $json ) > $maxBytes ) {
			throw new \UnexpectedValueException( 'JSON response size is invalid.' );
		}
		try {
			$value = json_decode( $json, true, 32, JSON_THROW_ON_ERROR );
		} catch ( \JsonException ) {
			throw new \UnexpectedValueException( 'JSON response is malformed.' );
		}
		if ( ! is_array( $value ) || array_is_list( $value ) ) {
			throw new \UnexpectedValueException( 'JSON response must be an object.' );
		}

		$offset = 0;
		self::scanValue( $json, $offset );
		self::skipWhitespace( $json, $offset );
		if ( strlen( $json ) !== $offset ) {
			throw new \UnexpectedValueException( 'JSON response has trailing data.' );
		}
		return $value;
	}

	private static function normalize( mixed $value ): mixed {
		if ( ! is_array( $value ) ) {
			return $value; }
		if ( ! array_is_list( $value ) ) {
			ksort( $value, SORT_STRING ); }
		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::normalize( $item ); }
		return $value;
	}

	private static function scanValue( string $json, int &$offset ): void {
		self::skipWhitespace( $json, $offset );
		$character = $json[ $offset ] ?? '';
		if ( '{' === $character ) {
			self::scanObject( $json, $offset );
			return;
		}
		if ( '[' === $character ) {
			self::scanArray( $json, $offset );
			return;
		}
		if ( '"' === $character ) {
			self::scanString( $json, $offset );
			return;
		}

		$length = strlen( $json );
		while ( $offset < $length && ! str_contains( ",]} \t\r\n", $json[ $offset ] ) ) {
			++$offset;
		}
	}

	private static function scanObject( string $json, int &$offset ): void {
		++$offset;
		$keys = array();
		self::skipWhitespace( $json, $offset );
		if ( '}' === ( $json[ $offset ] ?? '' ) ) {
			++$offset;
			return;
		}

		while ( true ) {
			self::skipWhitespace( $json, $offset );
			if ( '"' !== ( $json[ $offset ] ?? '' ) ) {
				throw new \UnexpectedValueException( 'JSON object key is invalid.' );
			}
			$key = self::scanString( $json, $offset );
			if ( array_key_exists( $key, $keys ) ) {
				throw new \UnexpectedValueException( 'JSON response contains a duplicate object key.' );
			}
			$keys[ $key ] = true;
			self::skipWhitespace( $json, $offset );
			if ( ':' !== ( $json[ $offset ] ?? '' ) ) {
				throw new \UnexpectedValueException( 'JSON object separator is invalid.' );
			}
			++$offset;
			self::scanValue( $json, $offset );
			self::skipWhitespace( $json, $offset );
			$separator = $json[ $offset ] ?? '';
			++$offset;
			if ( '}' === $separator ) {
				return;
			}
			if ( ',' !== $separator ) {
				throw new \UnexpectedValueException( 'JSON object is malformed.' );
			}
		}
	}

	private static function scanArray( string $json, int &$offset ): void {
		++$offset;
		self::skipWhitespace( $json, $offset );
		if ( ']' === ( $json[ $offset ] ?? '' ) ) {
			++$offset;
			return;
		}
		while ( true ) {
			self::scanValue( $json, $offset );
			self::skipWhitespace( $json, $offset );
			$separator = $json[ $offset ] ?? '';
			++$offset;
			if ( ']' === $separator ) {
				return;
			}
			if ( ',' !== $separator ) {
				throw new \UnexpectedValueException( 'JSON array is malformed.' );
			}
		}
	}

	private static function scanString( string $json, int &$offset ): string {
		$start  = $offset;
		$length = strlen( $json );
		++$offset;
		while ( $offset < $length ) {
			$character = $json[ $offset ];
			if ( '\\' === $character ) {
				$offset += 2;
				continue;
			}
			++$offset;
			if ( '"' === $character ) {
				$encoded = substr( $json, $start, $offset - $start );
				$value   = json_decode( $encoded, true, 2, JSON_THROW_ON_ERROR );
				return is_string( $value ) ? $value : '';
			}
		}
		throw new \UnexpectedValueException( 'JSON string is malformed.' );
	}

	private static function skipWhitespace( string $json, int &$offset ): void {
		$length = strlen( $json );
		while ( $offset < $length && str_contains( " \t\r\n", $json[ $offset ] ) ) {
			++$offset;
		}
	}
}
