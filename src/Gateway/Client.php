<?php
declare(strict_types=1);
namespace Kevira\MailGateway\Gateway;

use Kevira\MailGateway\Config;
use Kevira\MailGateway\Contracts\HttpTransport;
final class Client {
	public function __construct(
		private readonly Config $config,
		private readonly HttpTransport $http,
		private readonly Signer $signer,
		private readonly ResponseClassifier $classifier
	) {}
	public function send( string $body, string $idempotencyKey ): DeliveryResult {
		if ( ! $this->config->isComplete() ) {
			return DeliveryResult::permanent( 'configuration_incomplete', 'Mail Gateway configuration is incomplete.' ); }
		try {
			$signature = $this->signer->signMessage( $body, $idempotencyKey ); } catch ( \Throwable $error ) {
			return DeliveryResult::permanent( 'signing_failed', 'Unable to authenticate the Gateway request.' ); }
			$response = $this->http->request(
				'POST',
				$this->config->endpoint( '/v1/messages' ),
				array(
					'timeout'     => 12,
					'headers'     => $signature->headers,
					'body'        => $body,
					'data_format' => 'body',
				)
			);
		if ( is_wp_error( $response ) ) {
			return $this->classifier->classify( 0, '', $response->get_error_message() ); }
		return $this->classifier->classify( (int) wp_remote_retrieve_response_code( $response ), (string) wp_remote_retrieve_body( $response ) );
	}
	/** @return array{healthy:bool,status:int,message:string} */
	public function health(): array {
		if ( ! $this->config->isComplete() ) {
			return array(
				'healthy' => false,
				'status'  => 0,
				'message' => 'configuration_incomplete',
			); }
		$response = $this->http->request(
			'GET',
			$this->config->endpoint( '/v1/health' ),
			array(
				'timeout' => 5,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array(
				'healthy' => false,
				'status'  => 0,
				'message' => 'network_error',
			); }
		$status = (int) wp_remote_retrieve_response_code( $response );
		return array(
			'healthy' => $status >= 200 && $status < 300,
			'status'  => $status,
			'message' => $status >= 200 && $status < 300 ? 'healthy' : 'unavailable',
		);
	}
	/**
	 * The signed GET canonical contract is intentionally not invented.
	 * A server implementation may provide a result through this versioned filter.
	 */
	public function clientStatus(): mixed {
		return apply_filters( 'kevira_mail_gateway_client_status_v1', new \WP_Error( 'status_contract_unavailable', 'Signed client-status contract has not been confirmed.' ), $this );
	}
}
