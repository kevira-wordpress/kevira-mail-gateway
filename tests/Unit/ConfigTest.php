<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Tests\Unit;

use Kevira\MailGateway\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase {
	public function testMissingAndInsecureProductionConfigurationFailClosed(): void {
		$missing = new Config( '', '', '/missing/secret', 'default' );
		$this->assertContains( 'gateway_url_missing', $missing->errors() );
		$this->assertFalse( $missing->isComplete() );

		$file = (string) tempnam( sys_get_temp_dir(), 'kmg' );
		file_put_contents( $file, str_repeat( 's', 64 ) );
		$insecure = new Config( 'http://mail.example.test', 'site-one', $file, 'default', 'production' );
		$this->assertContains( 'gateway_https_required', $insecure->errors() );
		unlink( $file );
	}
}
