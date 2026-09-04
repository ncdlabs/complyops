<?php

declare(strict_types=1);

namespace ComplyOps\Verification;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Locates a Chromium/Chrome binary for headless verification.
 */
final class ChromiumLauncher {

	/** @var list<string> */
	private const CANDIDATE_BINARIES = array(
		'/usr/bin/chromium',
		'/usr/bin/chromium-browser',
		'/usr/bin/google-chrome-stable',
		'/usr/bin/google-chrome',
		'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
	);

	public function is_available(): bool {
		return null !== $this->binary_path();
	}

	public function binary_path(): ?string {
		$from_env = getenv( 'CHROME_PATH' );

		if ( is_string( $from_env ) && '' !== trim( $from_env ) && is_executable( $from_env ) ) {
			return $from_env;
		}

		/**
		 * Filter the Chromium binary path used for browser verification.
		 *
		 * @param string|null $path Detected binary path.
		 */
		$filtered = apply_filters( 'complyops_chromium_binary_path', null );

		if ( is_string( $filtered ) && '' !== trim( $filtered ) && is_executable( $filtered ) ) {
			return $filtered;
		}

		foreach ( self::CANDIDATE_BINARIES as $candidate ) {
			if ( is_executable( $candidate ) ) {
				return $candidate;
			}
		}

		return null;
	}
}
