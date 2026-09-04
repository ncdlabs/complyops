<?php

declare(strict_types=1);

namespace ComplyOps\Framework;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exceptions; messages escaped at REST/WP_Error boundaries.

use InvalidArgumentException;
use RuntimeException;

/**
 * Split-key encryption for ComplyOps framework packs.
 *
 * The decryption key is derived from an embedded plugin half plus a
 * customer unlock key delivered with purchase. Encrypted packs cannot be
 * read or imported without both halves.
 */
final class FrameworkPackCipher {

	public const PACK_FORMAT_ENCRYPTED = 'complyops-framework-pack-encrypted';

	public const ALGORITHM = 'aes-256-gcm';

	/**
	 * Embedded key half (hex). Paired with the customer unlock key at import.
	 */
	private const EMBEDDED_KEY_HALF = 'f4e8c2a91b7d6035e4f2a8c6b0d9e3f7';

	public function is_encrypted_envelope( array $data ): bool {
		return isset( $data['pack_format'] )
			&& is_string( $data['pack_format'] )
			&& self::PACK_FORMAT_ENCRYPTED === $data['pack_format'];
	}

	/**
	 * @param array<string, mixed> $envelope
	 * @return array<string, mixed>
	 */
	public function decrypt_envelope( array $envelope, string $unlock_key ): array {
		$this->assert_encrypted_envelope( $envelope );

		$iv         = $this->decode_field( $envelope, 'iv' );
		$tag        = $this->decode_field( $envelope, 'tag' );
		$ciphertext = $this->decode_field( $envelope, 'ciphertext' );
		$key        = $this->derive_key( $unlock_key );

		$plaintext = openssl_decrypt(
			$ciphertext,
			self::ALGORITHM,
			$key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag
		);

		if ( false === $plaintext ) {
			throw new InvalidArgumentException(
				__( 'Invalid pack unlock key. Check the key from your purchase confirmation.', 'complyops' )
			);
		}

		/** @var array<string, mixed>|null $pack */
		$pack = json_decode( $plaintext, true );

		if ( ! is_array( $pack ) ) {
			throw new InvalidArgumentException(
				__( 'Decrypted compliance pack is not valid JSON.', 'complyops' )
			);
		}

		return $pack;
	}

	/**
	 * @param array<string, mixed> $pack
	 * @return array<string, mixed>
	 */
	public function encrypt_payload( array $pack, string $unlock_key ): array {
		$encoded = wp_json_encode( $pack, JSON_UNESCAPED_SLASHES );

		if ( ! is_string( $encoded ) ) {
			throw new RuntimeException(
				__( 'ComplyOps could not encode the framework pack for encryption.', 'complyops' )
			);
		}

		$key = $this->derive_key( $unlock_key );
		$iv  = random_bytes( 12 );

		$tag        = '';
		$ciphertext = openssl_encrypt(
			$encoded,
			self::ALGORITHM,
			$key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag
		);

		if ( false === $ciphertext ) {
			throw new RuntimeException(
				__( 'ComplyOps could not encrypt the framework pack.', 'complyops' )
			);
		}

		$framework = isset( $pack['framework'] ) && is_string( $pack['framework'] ) ? $pack['framework'] : '';
		$label     = isset( $pack['label'] ) && is_string( $pack['label'] ) ? $pack['label'] : '';

		return array(
			'pack_format'    => self::PACK_FORMAT_ENCRYPTED,
			'pack_version'   => '1.0.0',
			'algorithm'      => self::ALGORITHM,
			'framework_hint' => $framework,
			'label_hint'     => $label,
			'iv'             => base64_encode( $iv ),
			'tag'            => base64_encode( $tag ),
			'ciphertext'     => base64_encode( $ciphertext ),
		);
	}

	public function embedded_key_half(): string {
		return self::EMBEDDED_KEY_HALF;
	}

	private function derive_key( string $unlock_key ): string {
		$embedded = hex2bin( self::EMBEDDED_KEY_HALF );
		$customer = $this->normalize_unlock_key( $unlock_key );

		if ( false === $embedded || '' === $customer ) {
			throw new InvalidArgumentException(
				__( 'A valid pack unlock key is required.', 'complyops' )
			);
		}

		return hash( 'sha256', $embedded . $customer, true );
	}

	private function normalize_unlock_key( string $unlock_key ): string {
		$normalized = strtolower( preg_replace( '/[^0-9a-f]/', '', $unlock_key ) ?? '' );

		if ( 32 !== strlen( $normalized ) || ! ctype_xdigit( $normalized ) ) {
			throw new InvalidArgumentException(
				__( 'Pack unlock key must be 32 hexadecimal characters (with or without dashes).', 'complyops' )
			);
		}

		return $normalized;
	}

	/**
	 * @param array<string, mixed> $envelope
	 */
	private function assert_encrypted_envelope( array $envelope ): void {
		if ( ! $this->is_encrypted_envelope( $envelope ) ) {
			throw new InvalidArgumentException(
				__( 'Expected an encrypted ComplyOps compliance pack.', 'complyops' )
			);
		}

		if ( ! isset( $envelope['algorithm'] ) || self::ALGORITHM !== $envelope['algorithm'] ) {
			throw new InvalidArgumentException(
				__( 'Unsupported compliance pack encryption algorithm.', 'complyops' )
			);
		}
	}

	/**
	 * @param array<string, mixed> $envelope
	 */
	private function decode_field( array $envelope, string $field ): string {
		if ( ! isset( $envelope[ $field ] ) || ! is_string( $envelope[ $field ] ) || '' === $envelope[ $field ] ) {
			throw new InvalidArgumentException(
				sprintf(
					/* translators: %s: envelope field name */
					__( 'Encrypted compliance pack is missing required field: %s', 'complyops' ),
					$field
				)
			);
		}

		$decoded = base64_decode( $envelope[ $field ], true );

		if ( false === $decoded ) {
			throw new InvalidArgumentException(
				sprintf(
					/* translators: %s: envelope field name */
					__( 'Encrypted compliance pack field is not valid base64: %s', 'complyops' ),
					$field
				)
			);
		}

		return $decoded;
	}
}
