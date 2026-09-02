<?php
declare(strict_types=1);

namespace Kevira\MailGateway\Tests\Unit;

use Kevira\MailGateway\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase {
	/** @return array{secret:string,queue:string} */
	private function keyFiles(): array {
		$secret = (string) tempnam( sys_get_temp_dir(), 'kmg-secret-' );
		$queue  = (string) tempnam( sys_get_temp_dir(), 'kmg-queue-' );
		file_put_contents( $secret, str_repeat( 's', 64 ) );
		file_put_contents( $queue, base64_encode( str_repeat( 'q', 32 ) ) );
		chmod( $secret, 0600 );
		chmod( $queue, 0600 );
		return compact( 'secret', 'queue' );
	}

	public function testMissingAndInsecureProductionConfigurationFailClosed(): void {
		$missing = new Config( '', '', '/missing/secret', 'default' );
		$this->assertContains( 'gateway_url_missing', $missing->errors() );
		$this->assertFalse( $missing->isComplete() );

		$files    = $this->keyFiles();
		$insecure = new Config( 'http://mail.example.test', 'site-one', $files['secret'], 'transactional', 'production', $files['queue'], array(), null );
		$this->assertContains( 'gateway_https_required', $insecure->errors() );
		unlink( $files['secret'] );
		unlink( $files['queue'] );
	}

	public function testClientIdMustBeLowercaseAndBounded(): void {
		$files = $this->keyFiles();
		$this->assertContains( 'client_id_invalid', ( new Config( 'https://mail.example.test', 'Site.One', $files['secret'], 'transactional', 'production', $files['queue'], array(), null ) )->errors() );
		$this->assertContains( 'client_id_invalid', ( new Config( 'https://mail.example.test', 'ab', $files['secret'], 'transactional', 'production', $files['queue'], array(), null ) )->errors() );
		$this->assertTrue( ( new Config( 'https://mail.example.test', 'site_one-2', $files['secret'], 'transactional', 'production', $files['queue'], array(), null ) )->isComplete() );
		unlink( $files['secret'] );
		unlink( $files['queue'] );
	}

	public function testProtectedSymlinkAndWritableKeyFilesAreRejected(): void {
		$files = $this->keyFiles();
		chmod( $files['secret'], 0660 );
		$this->assertContains( 'secret_unavailable', ( new Config( 'https://mail.example.test', 'site-one', $files['secret'], 'transactional', 'production', $files['queue'], array(), null ) )->errors() );
		chmod( $files['secret'], 0600 );

		$link = $files['secret'] . '-link';
		symlink( $files['secret'], $link );
		$this->assertContains( 'secret_unavailable', ( new Config( 'https://mail.example.test', 'site-one', $link, 'transactional', 'production', $files['queue'], array(), null ) )->errors() );

		$protected = sys_get_temp_dir() . '/kmg-protected-' . bin2hex( random_bytes( 4 ) );
		mkdir( $protected );
		$inside = $protected . '/secret';
		file_put_contents( $inside, str_repeat( 'x', 64 ) );
		chmod( $inside, 0600 );
		$this->assertContains( 'secret_unavailable', ( new Config( 'https://mail.example.test', 'site-one', $inside, 'transactional', 'production', $files['queue'], array( $protected ), null ) )->errors() );

		unlink( $link );
		unlink( $inside );
		rmdir( $protected );
		unlink( $files['secret'] );
		unlink( $files['queue'] );
	}

	public function testQueueKeyIsSeparateAndExactlyThirtyTwoBytes(): void {
		$files  = $this->keyFiles();
		$config = new Config( 'https://mail.example.test', 'site-one', $files['secret'], 'transactional', 'production', $files['queue'], array(), null );
		$this->assertSame( str_repeat( 'q', 32 ), $config->queueKey() );
		$this->assertNotSame( $config->secret(), $config->queueKey() );
		file_put_contents( $files['queue'], 'short' );
		$this->assertContains( 'queue_key_unavailable', $config->errors() );
		unlink( $files['secret'] );
		unlink( $files['queue'] );
	}

	public function testKeyInsideUploadsAndWritableQueueKeyAreRejected(): void {
		$files   = $this->keyFiles();
		$uploads = sys_get_temp_dir() . '/kmg-uploads';
		if ( ! is_dir( $uploads ) ) {
			mkdir( $uploads );
		}
		$inside = $uploads . '/mail-secret-' . bin2hex( random_bytes( 4 ) );
		file_put_contents( $inside, str_repeat( 's', 64 ) );
		chmod( $inside, 0600 );
		$this->assertContains( 'secret_unavailable', ( new Config( 'https://mail.example.test', 'site-one', $inside, 'transactional', 'production', $files['queue'], array(), null ) )->errors() );

		chmod( $files['queue'], 0666 );
		$this->assertContains( 'queue_key_unavailable', ( new Config( 'https://mail.example.test', 'site-one', $files['secret'], 'transactional', 'production', $files['queue'], array(), null ) )->errors() );
		unlink( $inside );
		unlink( $files['secret'] );
		unlink( $files['queue'] );
	}

	public function testUnexpectedKeyFileOwnerIsRejected(): void {
		$files = $this->keyFiles();
		$owner = fileowner( $files['secret'] );
		$this->assertIsInt( $owner );
		$requiredOwner = 0 === $owner ? 1 : 0;
		$config = new Config( 'https://mail.example.test', 'site-one', $files['secret'], 'transactional', 'production', $files['queue'], array(), $requiredOwner );
		$this->assertContains( 'secret_unavailable', $config->errors() );
		$this->assertContains( 'queue_key_unavailable', $config->errors() );
		unlink( $files['secret'] );
		unlink( $files['queue'] );
	}
}
