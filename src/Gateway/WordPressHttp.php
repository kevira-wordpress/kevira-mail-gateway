<?php
declare(strict_types=1);
namespace Kevira\MailGateway\Gateway;

use Kevira\MailGateway\Contracts\HttpTransport;
final class WordPressHttp implements HttpTransport {
	public function request( string $method, string $url, array $arguments ): mixed {
		$arguments['method']             = $method;
		$arguments['sslverify']          = true;
		$arguments['redirection']        = 0;
		$arguments['reject_unsafe_urls'] = true;
		return wp_safe_remote_request( $url, $arguments );
	}
}
