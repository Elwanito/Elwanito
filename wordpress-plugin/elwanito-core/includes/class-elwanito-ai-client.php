<?php
/**
 * Thin wrapper around the Anthropic Messages API using WordPress's HTTP API
 * (wp_remote_post) instead of the official SDK - this plugin targets
 * PHP/MySQL-only shared hosting with no SSH/Composer access, so the
 * dependency-free raw HTTP path is the only one that installs cleanly
 * via a plain zip upload.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elwanito_AI_Client {

	const API_URL     = 'https://api.anthropic.com/v1/messages';
	const API_VERSION = '2023-06-01';

	private $api_key;

	public function __construct( $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * Send one request to the Messages API.
	 *
	 * @param string $system_prompt Stable, reusable instructions (cached).
	 * @param string $user_message  The per-request/varying content.
	 * @param string $model         Model ID, e.g. claude-haiku-4-5.
	 * @param int    $max_tokens    Output token ceiling.
	 * @return array|WP_Error {'text' => string, 'usage' => array} on success.
	 */
	public function generate( $system_prompt, $user_message, $model, $max_tokens = 8000 ) {
		if ( empty( $this->api_key ) ) {
			return new WP_Error( 'elwanito_no_api_key', 'No Anthropic API key configured in Elwanito AI settings.' );
		}

		$body = array(
			'model'      => $model,
			'max_tokens' => $max_tokens,
			'system'     => array(
				array(
					'type'          => 'text',
					'text'          => $system_prompt,
					'cache_control' => array( 'type' => 'ephemeral' ),
				),
			),
			'messages'   => array(
				array( 'role' => 'user', 'content' => $user_message ),
			),
		);

		$response = wp_remote_post(
			self::API_URL,
			array(
				'timeout' => 120,
				'headers' => array(
					'Content-Type'      => 'application/json',
					'x-api-key'         => $this->api_key,
					'anthropic-version' => self::API_VERSION,
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code          = wp_remote_retrieve_response_code( $response );
		$decoded       = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 429 === $code ) {
			return new WP_Error( 'elwanito_rate_limited', 'Anthropic API rate limit hit; try again shortly.' );
		}

		if ( $code >= 500 ) {
			return new WP_Error( 'elwanito_server_error', 'Anthropic API server error (HTTP ' . $code . ').' );
		}

		if ( $code >= 400 ) {
			$message = isset( $decoded['error']['message'] ) ? $decoded['error']['message'] : 'Unknown error';
			return new WP_Error( 'elwanito_api_error', 'Anthropic API error (HTTP ' . $code . '): ' . $message );
		}

		if ( empty( $decoded['content'] ) || ! is_array( $decoded['content'] ) ) {
			return new WP_Error( 'elwanito_empty_response', 'Anthropic API returned no content.' );
		}

		if ( isset( $decoded['stop_reason'] ) && 'refusal' === $decoded['stop_reason'] ) {
			return new WP_Error( 'elwanito_refusal', 'The model declined this request (policy refusal).' );
		}

		$text = '';
		foreach ( $decoded['content'] as $block ) {
			if ( isset( $block['type'] ) && 'text' === $block['type'] ) {
				$text .= $block['text'];
			}
		}

		if ( '' === trim( $text ) ) {
			return new WP_Error( 'elwanito_empty_text', 'Anthropic API response had no text content.' );
		}

		return array(
			'text'  => $text,
			'usage' => isset( $decoded['usage'] ) ? $decoded['usage'] : array(),
		);
	}
}
