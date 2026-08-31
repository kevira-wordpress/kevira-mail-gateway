<?php
declare(strict_types=1);
namespace Kevira\MailGateway\Contracts;

interface RandomSource {
	public function bytes( int $length ): string;
	public function integer( int $minimum, int $maximum ): int;
}
