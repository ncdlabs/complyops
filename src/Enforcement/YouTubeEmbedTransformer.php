<?php

declare(strict_types=1);

namespace ComplyOps\Enforcement;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Integration\YouTube\YouTubeIntegration;

/**
 * Replaces YouTube iframe markup with gated placeholders.
 */
final class YouTubeEmbedTransformer {

	public function __construct(
		private readonly YouTubeIntegration $youtube = new YouTubeIntegration(),
	) {
	}

	public function transform_html( string $html, string $placeholder_message ): string {
		if ( '' === trim( $html ) || ! str_contains( strtolower( $html ), 'youtube' ) ) {
			return $html;
		}

		return (string) preg_replace_callback(
			'/<iframe\b[^>]*\ssrc=(["\'])([^"\']+)\1[^>]*>.*?<\/iframe>/is',
			function ( array $matches ) use ( $placeholder_message ): string {
				$src = html_entity_decode( $matches[2], ENT_QUOTES );

				if ( ! $this->youtube->is_youtube_url( $src ) ) {
					return $matches[0];
				}

				return $this->placeholder_markup( $src, $placeholder_message );
			},
			$html
		);
	}

	private function placeholder_markup( string $src, string $message ): string {
		$escaped_src = esc_url( $src );
		$escaped_msg = esc_html( $message );

		return sprintf(
			'<div class="complyops-youtube-gate" data-complyops-youtube-blocked="1" data-complyops-youtube-src="%1$s" role="group" aria-label="%2$s">'
			. '<div class="complyops-youtube-gate__placeholder">'
			. '<p class="complyops-youtube-gate__message">%3$s</p>'
			. '<div class="complyops-youtube-gate__actions">'
			. '<button type="button" class="complyops-youtube-gate__allow" data-complyops-youtube-action="allow">%4$s</button>'
			. '<button type="button" class="complyops-youtube-gate__prefs" data-complyops-youtube-action="preferences">%5$s</button>'
			. '</div></div></div>',
			$escaped_src,
			esc_attr__( 'YouTube video placeholder', 'complyops' ),
			$escaped_msg,
			esc_html__( 'Allow YouTube', 'complyops' ),
			esc_html__( 'Privacy settings', 'complyops' )
		);
	}
}
