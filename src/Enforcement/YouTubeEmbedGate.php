<?php

declare(strict_types=1);

namespace ComplyOps\Enforcement;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ComplyOps\Consent\ConsentSettings;

/**
 * Gates YouTube embeds until External Media consent is granted.
 */
final class YouTubeEmbedGate {

	public function __construct(
		private readonly EnforcementSettings $settings = new EnforcementSettings(),
		private readonly ConsentSettings $consent = new ConsentSettings(),
		private readonly YouTubeEmbedTransformer $transformer = new YouTubeEmbedTransformer(),
	) {
	}

	public function register(): void {
		add_filter( 'embed_oembed_html', array( $this, 'filter_embed' ), 10, 4 );
		add_filter( 'the_content', array( $this, 'filter_content' ), 20 );
		add_filter( 'widget_text_content', array( $this, 'filter_content' ), 20 );
		add_filter( 'widget_block_content', array( $this, 'filter_content' ), 20 );
	}

	public function filter_embed( string $html, string $url, array $attr, int $post_id ): string {
		if ( ! $this->should_gate() ) {
			return $html;
		}

		return $this->transformer->transform_html( $html, $this->placeholder_message() );
	}

	public function filter_content( string $content ): string {
		if ( ! $this->should_gate() ) {
			return $content;
		}

		return $this->transformer->transform_html( $content, $this->placeholder_message() );
	}

	public function should_gate(): bool {
		return $this->consent->should_load_native()
			&& $this->settings->is_enabled()
			&& $this->settings->gate_youtube_before_consent();
	}

	private function placeholder_message(): string {
		$message = (string) ( $this->settings->all()['youtube_placeholder_message'] ?? '' );

		if ( '' !== trim( $message ) ) {
			return $message;
		}

		return __(
			'This video is hosted by YouTube. Loading it may allow YouTube to process information about your device or activity.',
			'complyops'
		);
	}
}
