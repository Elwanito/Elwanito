<?php
/**
 * Client for any "OpenAI-compatible chat completions" endpoint - this one
 * class covers three different setups because they all speak the same
 * wire format: free-tier hosted open-source models (Groq, OpenRouter), and
 * a self-hosted open-source model on the owner's own computer (Ollama or
 * llama.cpp server both expose this same /v1/chat/completions shape).
 * Same generate() interface as Elwanito_AI_Client so the pipeline code
 * doesn't need to know which provider it's talking to.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elwanito_AI_Client_OpenAI {

	private $base_url;
	private $api_key;

	public function __construct( $base_url, $api_key ) {
		$this->base_url = rtrim( trim( $base_url ), '/' );
		$this->api_key  = $api_key;
	}

	public function generate( $system_prompt, $user_message, $model, $max_tokens = 8000 ) {
		if ( empty( $this->base_url ) ) {
			return new WP_Error( 'elwanito_no_base_url', 'No Base URL configured for the OpenAI-compatible provider in Elwanito AI settings.' );
		}

		$headers = array( 'Content-Type' => 'application/json' );
		if ( ! empty( $this->api_key ) ) {
			// Local Ollama/llama.cpp servers usually need no key at all;
			// Groq/OpenRouter require a Bearer token same as any REST API.
			$headers['Authorization'] = 'Bearer ' . $this->api_key;
		}

		$body = array(
			'model'      => $model,
			'max_tokens' => $max_tokens,
			'messages'   => array(
				array( 'role' => 'system', 'content' => $system_prompt ),
				array( 'role' => 'user', 'content' => $user_message ),
			),
		);

		$response = wp_remote_post(
			$this->base_url,
			array(
				// A home computer over a tunnel can be much slower than a
				// data-center API - generous timeout so a slow local model
				// doesn't get killed mid-generation.
				'timeout' => 180,
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code    = wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			$message = 'Unknown error';
			if ( isset( $decoded['error']['message'] ) ) {
				$message = $decoded['error']['message'];
			} elseif ( isset( $decoded['error'] ) && is_string( $decoded['error'] ) ) {
				$message = $decoded['error'];
			}
			return new WP_Error( 'elwanito_openai_api_error', "OpenAI-compatible API error (HTTP {$code}): {$message}" );
		}

		$text = isset( $decoded['choices'][0]['message']['content'] ) ? $decoded['choices'][0]['message']['content'] : '';

		if ( '' === trim( $text ) ) {
			return new WP_Error( 'elwanito_openai_empty', 'The OpenAI-compatible endpoint returned no content.' );
		}

		return array(
			'text'  => $text,
			'usage' => isset( $decoded['usage'] ) ? $decoded['usage'] : array(),
		);
	}
}
