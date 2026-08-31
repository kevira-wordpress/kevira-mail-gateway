<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Mail;

final class AttachmentLoader {
	public const MAX_TOTAL_BYTES = 10485760;
	public const MAX_FILES       = 10;

	/**
	 * @param string|list<string> $attachments
	 * @return list<array{filename:string,mime_type:string,content_base64:string}>
	 */
	public function load( string|array $attachments ): array {
		$paths = is_array( $attachments ) ? $attachments : preg_split( '/\r?\n/', $attachments );
		if ( is_array( $paths ) && count( array_filter( $paths ) ) > self::MAX_FILES ) {
			throw new \RuntimeException( 'Email attachments exceed the 10-file limit.' );
		}
		$result = array();
		$total  = 0;

		foreach ( is_array( $paths ) ? $paths : array() as $path ) {
			$path = realpath( trim( (string) $path ) );
			if ( false === $path || ! $this->isAllowedPath( $path ) || ! is_file( $path ) || ! is_readable( $path ) ) {
				throw new \RuntimeException( 'An email attachment is invalid or outside an allowed WordPress directory.' );
			}
			$size = filesize( $path );
			if ( false === $size || 0 >= $size || ( $total + $size ) > self::MAX_TOTAL_BYTES ) {
				throw new \RuntimeException( 'Email attachments exceed the 10 MB limit.' );
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Validated local attachment path.
			$content = file_get_contents( $path );
			if ( ! is_string( $content ) || strlen( $content ) !== $size ) {
				throw new \RuntimeException( 'An email attachment could not be read safely.' );
			}
			$mime     = function_exists( 'wp_check_filetype_and_ext' ) ? wp_check_filetype_and_ext( $path, basename( $path ) ) : array();
			$mimeType = is_array( $mime ) && ! empty( $mime['type'] ) ? (string) $mime['type'] : ( new \finfo( FILEINFO_MIME_TYPE ) )->file( $path );
			if ( ! is_string( $mimeType ) || ! preg_match( '#^[a-z0-9.+-]+/[a-z0-9.+-]+$#i', $mimeType ) ) {
				throw new \RuntimeException( 'An email attachment MIME type is invalid.' );
			}
			$result[] = array(
				'filename'       => sanitize_file_name( basename( $path ) ),
				'mime_type'      => strtolower( $mimeType ),
				'content_base64' => base64_encode( $content ),
			);
			$total   += $size;
		}

		return $result;
	}

	private function isAllowedPath( string $path ): bool {
		$upload = wp_upload_dir();
		$roots  = array_filter(
			array(
				isset( $upload['basedir'] ) ? realpath( (string) $upload['basedir'] ) : false,
				realpath( get_temp_dir() ),
			),
			'is_string'
		);
		foreach ( $roots as $root ) {
			$root = rtrim( (string) $root, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
			if ( str_starts_with( $path . DIRECTORY_SEPARATOR, $root ) ) {
				return true;
			}
		}
		return false;
	}
}
