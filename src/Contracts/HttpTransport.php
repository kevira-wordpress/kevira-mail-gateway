<?php
declare(strict_types=1);
namespace Kevira\MailGateway\Contracts;

interface HttpTransport {
	/** @param array<string,mixed> $arguments */
	public function request( string $method, string $url, array $arguments ): mixed;
}
