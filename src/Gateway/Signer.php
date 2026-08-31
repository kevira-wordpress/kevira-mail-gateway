<?php
declare(strict_types=1);
namespace Kevira\MailGateway\Gateway;

use Kevira\MailGateway\Config;
use Kevira\MailGateway\Contracts\Clock;
use Kevira\MailGateway\Contracts\RandomSource;
final class Signer {
	public function __construct( private readonly Config $config, private readonly Clock $clock, private readonly RandomSource $random ) {}
	public function signMessage( string $body, string $idempotencyKey ): Signature {
		if ( ! preg_match( '/^[a-f0-9-]{32,64}$/', $idempotencyKey ) ) {
			throw new \InvalidArgumentException( 'Invalid idempotency key.' ); }
		$timestamp = (string) $this->clock->now();
		$nonce     = bin2hex( $this->random->bytes( 16 ) );
		$canonical = implode( "\n", array( 'POST', '/v1/messages', $timestamp, $nonce, $idempotencyKey, hash( 'sha256', $body ) ) );
		$signature = hash_hmac( 'sha256', $canonical, $this->config->secret() );
		return new Signature(
			array(
				'Content-Type'       => 'application/json',
				'X-Kevira-Client-Id' => $this->config->clientId(),
				'X-Kevira-Timestamp' => $timestamp,
				'X-Kevira-Nonce'     => $nonce,
				'X-Kevira-Signature' => 'v1=' . $signature,
				'Idempotency-Key'    => $idempotencyKey,
			),
			$canonical
		);
	}
}
