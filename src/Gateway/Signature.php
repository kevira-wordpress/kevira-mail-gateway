<?php
declare(strict_types=1);
namespace Kevira\MailGateway\Gateway;

final class Signature {
	/** @param array<string,string> $headers */
	public function __construct( public readonly array $headers, public readonly string $canonical ) {}
}
