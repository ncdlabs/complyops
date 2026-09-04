<?php

declare(strict_types=1);

namespace ComplyOps\Integration\Google;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves Google OAuth endpoints and credentials for ComplyOps integrations.
 */
class GoogleOAuthConfig {

	public const DEFAULT_PROXY_START_URL    = 'https://ncdlabs.com/products/complyops/api/google/oauth/start';
	public const DEFAULT_PROXY_EXCHANGE_URL = 'https://ncdlabs.com/products/complyops/api/google/oauth/exchange';
	public const GOOGLE_AUTH_URL            = 'https://accounts.google.com/o/oauth2/v2/auth';
	public const GOOGLE_TOKEN_URL           = 'https://oauth2.googleapis.com/token';
	public const GOOGLE_USERINFO_URL        = 'https://www.googleapis.com/oauth2/v3/userinfo';
	public const GA_ADMIN_ACCOUNT_SUMMARIES = 'https://analyticsadmin.googleapis.com/v1beta/accountSummaries';

	public const SCOPES = array(
		'openid',
		'email',
		'profile',
		'https://www.googleapis.com/auth/analytics.readonly',
		'https://www.googleapis.com/auth/tagmanager.readonly',
	);

	/**
	 * @return array{
	 *   mode: 'proxy'|'direct',
	 *   proxy_start_url: string,
	 *   proxy_exchange_url: string,
	 *   client_id: string,
	 *   client_secret: string,
	 *   redirect_uri: string
	 * }
	 */
	public function resolve(): array {
		$defaults = array(
			'proxy_start_url'    => self::DEFAULT_PROXY_START_URL,
			'proxy_exchange_url' => self::DEFAULT_PROXY_EXCHANGE_URL,
			'client_id'          => defined( 'COMPLYOPS_GOOGLE_OAUTH_CLIENT_ID' )
				? (string) COMPLYOPS_GOOGLE_OAUTH_CLIENT_ID
				: '',
			'client_secret'      => defined( 'COMPLYOPS_GOOGLE_OAUTH_CLIENT_SECRET' )
				? (string) COMPLYOPS_GOOGLE_OAUTH_CLIENT_SECRET
				: '',
			'redirect_uri'       => rest_url( COMPLYOPS_REST_NAMESPACE . '/integrations/google/oauth/callback' ),
		);

		/** @var array<string, string> $config */
		$config = apply_filters( 'complyops_google_oauth_config', $defaults );

		$client_id     = sanitize_text_field( (string) ( $config['client_id'] ?? '' ) );
		$client_secret = (string) ( $config['client_secret'] ?? '' );

		return array(
			'mode'               => ( '' !== $client_id && '' !== $client_secret ) ? 'direct' : 'proxy',
			'proxy_start_url'    => esc_url_raw( (string) ( $config['proxy_start_url'] ?? self::DEFAULT_PROXY_START_URL ) ),
			'proxy_exchange_url' => esc_url_raw( (string) ( $config['proxy_exchange_url'] ?? self::DEFAULT_PROXY_EXCHANGE_URL ) ),
			'client_id'          => $client_id,
			'client_secret'      => $client_secret,
			'redirect_uri'       => esc_url_raw( (string) ( $config['redirect_uri'] ?? $defaults['redirect_uri'] ) ),
		);
	}
}
