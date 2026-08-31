<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Tests\Unit;

use Kevira\MailGateway\Mail\HeaderNormalizer;
use PHPUnit\Framework\TestCase;

final class HeaderNormalizerTest extends TestCase {
	public function testOnlySupportedHeadersAreUsed(): void {
		$result = ( new HeaderNormalizer() )->normalize( "Content-Type: text/html; charset=UTF-8\nCc: valid@example.com\nX-Evil: injected" );
		$this->assertSame( 'text/html', $result['content_type'] );
		$this->assertSame( array( 'valid@example.com' ), $result['cc'] );
		$this->assertArrayNotHasKey( 'x-evil', $result );
	}

	public function testCrlfRecipientInjectionIsRejected(): void {
		$this->assertSame( array(), ( new HeaderNormalizer() )->addresses( "victim@example.com\r\nBcc: attacker@example.com" ) );
	}
}
