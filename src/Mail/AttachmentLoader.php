<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Mail;

final class AttachmentLoader {
	/**
	 * @param string|list<string> $attachments
	 * @return array{}
	 */
	public function load( string|array $attachments ): array {
		$paths = is_array( $attachments ) ? $attachments : preg_split( '/\r?\n/', $attachments );
		foreach ( is_array( $paths ) ? $paths : array() as $path ) {
			if ( '' !== trim( (string) $path ) ) {
				throw new UnsupportedAttachmentsException( 'Attachments are not supported by Mail Gateway v1.' );
			}
		}
		return array();
	}
}
