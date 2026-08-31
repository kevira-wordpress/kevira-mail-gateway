<?php
declare(strict_types=1);
namespace Kevira\MailGateway\Gateway;

final class DeliveryResult {
	private function __construct(
		public readonly string $state,
		public readonly string $code,
		public readonly string $message,
		public readonly int $httpStatus = 0,
		public readonly string $messageId = ''
	) {}
	public static function accepted( int $status, string $messageId = '' ): self {
		return new self( 'accepted', 'accepted', 'Gateway accepted the message.', $status, $messageId ); }
	public static function transient( string $code, string $message, int $status = 0 ): self {
		return new self( 'transient', $code, $message, $status ); }
	public static function permanent( string $code, string $message, int $status = 0 ): self {
		return new self( 'permanent', $code, $message, $status ); }
	public function acceptedByGateway(): bool {
		return 'accepted' === $this->state; }
	public function retryable(): bool {
		return 'transient' === $this->state; }
}
