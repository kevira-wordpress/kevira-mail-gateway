<?php
declare(strict_types=1);
namespace Kevira\MailGateway\Support;

use Kevira\MailGateway\Contracts\Clock;
final class SystemClock implements Clock {
	public function now(): int {
		return time(); }
}
