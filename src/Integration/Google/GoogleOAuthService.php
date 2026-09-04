<?php

declare(strict_types=1);

namespace ComplyOps\Integration\Google;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Security\Capabilities;
use WP_Error;

/**
 * Shared Google OAuth connect, refresh, and disconnect flows.
 */
final class GoogleOAuthService {

	private const STATE_TRANSIENT_PREFIX = 'complyops_google_oauth_state_';
	private const STATE_TTL              = 600;

	public const PACK_GOOGLE_ANALYTICS   = 'google-analytics';
	public const PACK_GOOGLE_TAG_MANAGER = 'google-tag-manager';

	public function __construct(
		private readonly GoogleOAuthConfig $config = new GoogleOAuthConfig(),
		private readonly GoogleOAuthTokens $tokens = new GoogleOAuthTokens(),
	) {
	}

	public function is_connected(): bool {
		return $this->tokens->is_connected();
	}

	/**
	 * @return array{authorization_url: string, state: string, mode: string}|WP_Error
	 */
	public function start( string $pack = self::PACK_GOOGLE_ANALYTICS ): array|WP_Error {
		$user_id = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;

		if ( $user_id <= 0 || ! current_user_can( Capabilities::MANAGE ) ) {
			return new WP_Error(
				'complyops_google_oauth_auth',
				__( 'Sign in as a ComplyOps administrator before connecting Google.', 'complyops' ),
				array( 'status' => 401 )
			);
		}

		if ( ! $this->is_valid_pack( $pack ) ) {
			return new WP_Error(
				'complyops_google_oauth_pack',
				__( 'Unknown Google integration.', 'complyops' ),
				array( 'status' => 400 )
			);
		}

		$config = $this->config->resolve();
		$state  = wp_generate_password( 32, false, false );
		$return_url = $this->admin_return_url( $pack );

		set_transient(
			self::STATE_TRANSIENT_PREFIX . $state,
			array(
				'mode'       => $config['mode'],
				'pack'       => $pack,
				'created_at' => time(),
				'user_id'    => $user_id,
			),
			self::STATE_TTL
		);

		if ( 'direct' === $config['mode'] ) {
			$url = add_query_arg(
				array(
					'client_id'     => $config['client_id'],
					'redirect_uri'  => $config['redirect_uri'],
					'response_type' => 'code',
					'scope'         => implode( ' ', GoogleOAuthConfig::SCOPES ),
					'access_type'   => 'offline',
					'prompt'        => 'consent',
					'state'         => $state,
				),
				GoogleOAuthConfig::GOOGLE_AUTH_URL
			);

			return array(
				'authorization_url' => $url,
				'state'             => $state,
				'mode'              => 'direct',
			);
		}

		$url = add_query_arg(
			array(
				'return_url' => $return_url,
				'site_url'   => home_url(),
				'state'      => $state,
			),
			$config['proxy_start_url']
		);

		return array(
			'authorization_url' => $url,
			'state'             => $state,
			'mode'              => 'proxy',
		);
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public function complete( string $exchange_code, string $state ): array|WP_Error {
		$state_check = $this->validate_oauth_state_for_current_user( $state );

		if ( is_wp_error( $state_check ) ) {
			return $state_check;
		}

		$config = $this->config->resolve();

		if ( 'direct' === $config['mode'] ) {
			return new WP_Error(
				'complyops_google_oauth_mode',
				__( 'Use the OAuth callback route to finish direct Google sign-in.', 'complyops' ),
				array( 'status' => 400 )
			);
		}

		$payload = $this->exchange_proxy_code( $exchange_code, $config['proxy_exchange_url'] );

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$result = $this->persist_token_payload( $payload );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->consume_oauth_state( $state );

		return $result;
	}

	/**
	 * Direct OAuth callback handler.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function handle_direct_callback( string $code, string $state ): array|WP_Error {
		$state_check = $this->validate_oauth_state_for_current_user( $state );

		if ( is_wp_error( $state_check ) ) {
			return $state_check;
		}

		$config = $this->config->resolve();

		if ( 'direct' !== $config['mode'] ) {
			return new WP_Error(
				'complyops_google_oauth_mode',
				__( 'Direct Google OAuth is not enabled on this site.', 'complyops' ),
				array( 'status' => 400 )
			);
		}

		$token_response = $this->exchange_authorization_code(
			$code,
			$config['client_id'],
			$config['client_secret'],
			$config['redirect_uri']
		);

		if ( is_wp_error( $token_response ) ) {
			return $token_response;
		}

		$result = $this->persist_token_payload( $token_response );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->consume_oauth_state( $state );

		return $result;
	}

	public function disconnect(): void {
		$this->tokens->clear();
	}

	/**
	 * @return string|WP_Error
	 */
	public function access_token(): string|WP_Error {
		$access_token = $this->tokens->access_token();
		$expires_at   = $this->tokens->expires_at();

		if ( '' !== $access_token && $expires_at > ( time() + 60 ) ) {
			return $access_token;
		}

		$refresh_token = $this->tokens->refresh_token();

		if ( '' === $refresh_token ) {
			return new WP_Error(
				'complyops_google_oauth_expired',
				__( 'Google access expired. Connect again to continue.', 'complyops' )
			);
		}

		$config = $this->config->resolve();

		if ( 'direct' !== $config['mode'] ) {
			return new WP_Error(
				'complyops_google_oauth_refresh_unavailable',
				__( 'Google token refresh requires reconnecting your account.', 'complyops' )
			);
		}

		$refreshed = $this->refresh_access_token(
			$refresh_token,
			$config['client_id'],
			$config['client_secret']
		);

		if ( is_wp_error( $refreshed ) ) {
			return $refreshed;
		}

		$this->store_token_payload( $refreshed );

		return (string) ( $refreshed['access_token'] ?? '' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function public_status(): array {
		return array(
			'connected'     => $this->is_connected(),
			'account_email' => sanitize_email( (string) ( $this->tokens->all()['account_email'] ?? '' ) ),
			'connected_at'  => sanitize_text_field( (string) ( $this->tokens->all()['connected_at'] ?? '' ) ),
		);
	}

	/**
	 * @return string|WP_Error
	 */
	public function api_access_token(): string|WP_Error {
		$access_token = $this->access_token();

		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		if ( '' === $access_token ) {
			return new WP_Error(
				'complyops_google_oauth_expired',
				__( 'Google access expired. Connect again to continue.', 'complyops' ),
				array( 'status' => 401 )
			);
		}

		return $access_token;
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>|WP_Error
	 */
	public function persist_token_payload( array $payload ): array|WP_Error {
		$access_token = (string) ( $payload['access_token'] ?? '' );

		if ( '' === $access_token ) {
			return new WP_Error(
				'complyops_google_oauth_tokens',
				__( 'Google did not return an access token.', 'complyops' ),
				array( 'status' => 502 )
			);
		}

		$this->store_token_payload( $payload );

		$email = sanitize_email( (string) ( $payload['email'] ?? '' ) );

		if ( '' === $email ) {
			$userinfo = $this->fetch_userinfo( $access_token );

			if ( is_wp_error( $userinfo ) ) {
				return $userinfo;
			}

			$email = sanitize_email( (string) ( $userinfo['email'] ?? '' ) );
		}

		$this->tokens->save(
			array(
				'account_email' => $email,
				'connected_at'  => gmdate( 'c' ),
			)
		);

		return $this->public_status();
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	public function store_token_payload( array $payload ): void {
		$expires_in = (int) ( $payload['expires_in'] ?? 3600 );

		$this->tokens->save(
			array(
				'access_token'  => (string) ( $payload['access_token'] ?? '' ),
				'refresh_token' => (string) ( $payload['refresh_token'] ?? $this->tokens->refresh_token() ),
				'expires_at'    => time() + max( 60, $expires_in ),
				'token_type'    => (string) ( $payload['token_type'] ?? 'Bearer' ),
			)
		);
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private function exchange_proxy_code( string $code, string $exchange_url ): array|WP_Error {
		$response = wp_remote_post(
			$exchange_url,
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'code'     => sanitize_text_field( $code ),
						'site_url' => home_url(),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );
		$data   = json_decode( $body, true );

		if ( $status < 200 || $status >= 300 || ! is_array( $data ) ) {
			$message = is_array( $data ) ? (string) ( $data['error'] ?? $body ) : $body;

			return new WP_Error(
				'complyops_google_oauth_exchange',
				$message !== '' ? $message : __( 'Could not complete Google sign-in.', 'complyops' ),
				array( 'status' => $status > 0 ? $status : 502 )
			);
		}

		return $data;
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private function exchange_authorization_code(
		string $code,
		string $client_id,
		string $client_secret,
		string $redirect_uri
	): array|WP_Error {
		$response = wp_remote_post(
			GoogleOAuthConfig::GOOGLE_TOKEN_URL,
			array(
				'timeout' => 20,
				'body'    => array(
					'code'          => $code,
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'redirect_uri'  => $redirect_uri,
					'grant_type'    => 'authorization_code',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );
		$data   = json_decode( $body, true );

		if ( $status < 200 || $status >= 300 || ! is_array( $data ) ) {
			$message = is_array( $data ) ? (string) ( $data['error_description'] ?? $data['error'] ?? $body ) : $body;

			return new WP_Error(
				'complyops_google_oauth_token',
				$message !== '' ? $message : __( 'Google token exchange failed.', 'complyops' ),
				array( 'status' => $status > 0 ? $status : 502 )
			);
		}

		$userinfo = $this->fetch_userinfo( (string) ( $data['access_token'] ?? '' ) );

		if ( is_wp_error( $userinfo ) ) {
			return $userinfo;
		}

		return array_merge(
			$data,
			array(
				'email' => sanitize_email( (string) ( $userinfo['email'] ?? '' ) ),
			)
		);
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private function refresh_access_token(
		string $refresh_token,
		string $client_id,
		string $client_secret
	): array|WP_Error {
		$response = wp_remote_post(
			GoogleOAuthConfig::GOOGLE_TOKEN_URL,
			array(
				'timeout' => 20,
				'body'    => array(
					'refresh_token' => $refresh_token,
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'grant_type'    => 'refresh_token',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );
		$data   = json_decode( $body, true );

		if ( $status < 200 || $status >= 300 || ! is_array( $data ) ) {
			return new WP_Error(
				'complyops_google_oauth_refresh',
				__( 'Could not refresh the Google access token.', 'complyops' ),
				array( 'status' => $status > 0 ? $status : 502 )
			);
		}

		$data['refresh_token'] = $refresh_token;

		return $data;
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private function fetch_userinfo( string $access_token ): array|WP_Error {
		$response = wp_remote_get(
			GoogleOAuthConfig::GOOGLE_USERINFO_URL,
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );
		$data   = json_decode( $body, true );

		if ( $status < 200 || $status >= 300 || ! is_array( $data ) ) {
			return new WP_Error(
				'complyops_google_oauth_userinfo',
				__( 'Could not read the connected Google account.', 'complyops' ),
				array( 'status' => $status > 0 ? $status : 502 )
			);
		}

		return $data;
	}

	private function normalize_oauth_state( string $state ): string {
		return (string) preg_replace( '/[^A-Za-z0-9]/', '', $state );
	}

	private function oauth_state_transient_key( string $state ): string {
		return self::STATE_TRANSIENT_PREFIX . $this->normalize_oauth_state( $state );
	}

	/**
	 * @return true|WP_Error
	 */
	private function validate_oauth_state_for_current_user( string $state ): true|WP_Error {
		$state = $this->normalize_oauth_state( $state );

		if ( 32 !== strlen( $state ) ) {
			return new WP_Error(
				'complyops_google_oauth_state',
				__( 'The Google sign-in session expired. Please try again.', 'complyops' ),
				array( 'status' => 400 )
			);
		}

		$payload = get_transient( $this->oauth_state_transient_key( $state ) );

		if ( ! is_array( $payload ) ) {
			return new WP_Error(
				'complyops_google_oauth_state',
				__( 'The Google sign-in session expired. Please try again.', 'complyops' ),
				array( 'status' => 400 )
			);
		}

		$expected_user = (int) ( $payload['user_id'] ?? 0 );
		$current_user  = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;

		if ( $expected_user <= 0 || $current_user !== $expected_user ) {
			return new WP_Error(
				'complyops_google_oauth_state',
				__( 'This Google sign-in session does not match the signed-in administrator.', 'complyops' ),
				array( 'status' => 403 )
			);
		}

		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			return new WP_Error(
				'complyops_google_oauth_auth',
				__( 'You do not have permission to connect Google integrations.', 'complyops' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	private function consume_oauth_state( string $state ): void {
		delete_transient( $this->oauth_state_transient_key( $state ) );
	}

	private function admin_return_url( string $pack ): string {
		return add_query_arg(
			array(
				'page' => 'complyops-integrations',
				'pack' => $pack,
			),
			admin_url( 'admin.php' )
		);
	}

	private function is_valid_pack( string $pack ): bool {
		return in_array(
			$pack,
			array(
				self::PACK_GOOGLE_ANALYTICS,
				self::PACK_GOOGLE_TAG_MANAGER,
			),
			true
		);
	}
}
