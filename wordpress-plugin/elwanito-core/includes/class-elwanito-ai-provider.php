<?php
/**
 * Picks which AI client to use based on the owner's settings, so the
 * pipeline code never has to know or care which provider is active.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elwanito_AI_Provider {

	public static function current() {
		return get_option( 'elwanito_ai_provider', 'anthropic' );
	}

	public static function create() {
		if ( 'openai_compatible' === self::current() ) {
			return new Elwanito_AI_Client_OpenAI(
				get_option( 'elwanito_openai_base_url', '' ),
				get_option( 'elwanito_openai_api_key', '' )
			);
		}

		return new Elwanito_AI_Client( get_option( 'elwanito_anthropic_api_key', '' ) );
	}

	/**
	 * Model to use for a given mode ('outline' or 'bulk'), reading the
	 * option set that matches whichever provider is currently active.
	 */
	public static function model( $mode ) {
		if ( 'openai_compatible' === self::current() ) {
			return 'outline' === $mode
				? get_option( 'elwanito_openai_model_outline', 'llama-3.1-8b-instant' )
				: get_option( 'elwanito_openai_model_bulk', 'llama-3.1-8b-instant' );
		}

		return 'outline' === $mode
			? get_option( 'elwanito_model_outline', 'claude-sonnet-5' )
			: get_option( 'elwanito_model_bulk', 'claude-haiku-4-5' );
	}
}
