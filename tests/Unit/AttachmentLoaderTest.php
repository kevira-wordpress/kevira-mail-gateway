<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Tests\Unit;

use Kevira\MailGateway\Mail\AttachmentLoader;
use PHPUnit\Framework\TestCase;

final class AttachmentLoaderTest extends TestCase {
	public function testAttachmentsAreRejectedBeforeAnyFileRead(): void {
		$this->assertSame( array(), ( new AttachmentLoader() )->load( array() ) );
		$this->expectException( \Kevira\MailGateway\Mail\UnsupportedAttachmentsException::class );
		$this->expectExceptionMessage( 'not supported' );
		( new AttachmentLoader() )->load( array( '/path/that/must/not/be/read.pdf' ) );
	}
}
