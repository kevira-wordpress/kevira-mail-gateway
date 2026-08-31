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
	private static function normalize( mixed $value ): mixed {
		if ( ! is_array( $value ) ) {
			return $value; }
		if ( ! array_is_list( $value ) ) {
			ksort( $value, SORT_STRING ); }
		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::normalize( $item ); }
		return $value;
	}
}
