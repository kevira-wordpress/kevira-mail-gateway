<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Tests\Unit;

use Kevira\MailGateway\Mail\AttachmentLoader;
use PHPUnit\Framework\TestCase;

final class AttachmentLoaderTest extends TestCase {
	public function testLocalTemporaryAttachmentAndLimit(): void {
		$file = (string) tempnam( sys_get_temp_dir(), 'kmg' );
		file_put_contents( $file, 'receipt' );
		$loaded = ( new AttachmentLoader() )->load( array( $file ) );
		$this->assertSame( base64_encode( 'receipt' ), $loaded[0]['content_base64'] );
		unlink( $file );

		$large = (string) tempnam( sys_get_temp_dir(), 'kmg' );
		$handle = fopen( $large, 'wb' );
		ftruncate( $handle, AttachmentLoader::MAX_TOTAL_BYTES + 1 );
		fclose( $handle );
		$this->expectException( \RuntimeException::class );
		try { ( new AttachmentLoader() )->load( array( $large ) ); } finally { unlink( $large ); }
	}
}
