<?php
declare(strict_types=1);
namespace Kevira\MailGateway\Contracts;

interface Scheduler {
	public function schedule( int $delay = 5 ): void;
	public function clear(): void;
}
