<?php

declare(strict_types=1);

namespace ComplyOps\Evidence;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a printable PDF summary of an evidence package.
 */
final class EvidencePdfExporter {

	/**
	 * @param array<string, mixed> $package
	 */
	public function export( array $package ): string {
		$lines   = array();
		$lines[] = 'ComplyOps Technical Compliance Evidence';
		$lines[] = 'Site: ' . (string) ( $package['site_url'] ?? '' );
		$lines[] = 'Exported: ' . (string) ( $package['exported_at'] ?? gmdate( 'c' ) );
		$lines[] = 'Plugin: ' . (string) ( $package['plugin_version'] ?? '' );
		$lines[] = 'Framework: ' . strtoupper( (string) ( $package['framework'] ?? 'gdpr' ) );

		$summary = $package['audit_summary'] ?? null;
		if ( is_array( $summary ) ) {
			$lines[] = sprintf(
				'Audit #%s score %s%% completed %s',
				(string) ( $summary['id'] ?? '' ),
				(string) ( $summary['score'] ?? '' ),
				(string) ( $summary['completed_at'] ?? $summary['started_at'] ?? '' )
			);
		}

		$lines[] = '';
		$lines[] = 'Evidence records:';

		$records = $package['records'] ?? array();
		if ( is_array( $records ) ) {
			foreach ( $records as $record ) {
				if ( ! is_array( $record ) ) {
					continue;
				}

				$lines[] = sprintf(
					'- [%s] %s (%s): %s',
					(string) ( $record['source'] ?? '' ),
					(string) ( $record['control_id'] ?? '' ),
					(string) ( $record['status'] ?? '' ),
					$this->truncate( (string) ( $record['observation'] ?? '' ), 120 )
				);
			}
		}

		$manual = $package['unresolved_manual_review'] ?? array();
		if ( is_array( $manual ) && array() !== $manual ) {
			$lines[] = '';
			$lines[] = 'Manual review items:';
			foreach ( $manual as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}

				$lines[] = sprintf(
					'- %s (%s)',
					(string) ( $item['control_id'] ?? '' ),
					(string) ( $item['title'] ?? '' )
				);
			}
		}

		return $this->render_pdf( $lines );
	}

	/**
	 * @param list<string> $lines
	 */
	private function render_pdf( array $lines ): string {
		$content = '';
		$y       = 780;
		$content .= "BT\n/F1 11 Tf\n";

		foreach ( $lines as $line ) {
			$safe = $this->escape_pdf_text( $line );
			$content .= sprintf( "1 0 0 1 50 %d Tm (%s) Tj\n", $y, $safe );
			$y -= 16;

			if ( $y < 60 ) {
				break;
			}
		}

		$content .= "ET";

		$objects = array();
		$objects[] = "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n";
		$objects[] = "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n";
		$objects[] = "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj\n";
		$objects[] = '4 0 obj << /Length ' . strlen( $content ) . " >> stream\n" . $content . "\nendstream endobj\n";
		$objects[] = "5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n";

		$pdf = "%PDF-1.4\n";
		$offsets = array( 0 );

		foreach ( $objects as $object ) {
			$offsets[] = strlen( $pdf );
			$pdf      .= $object;
		}

		$xref_offset = strlen( $pdf );
		$pdf        .= "xref\n0 " . count( $offsets ) . "\n";
		$pdf        .= "0000000000 65535 f \n";

		for ( $i = 1; $i < count( $offsets ); $i++ ) {
			$pdf .= sprintf( "%010d 00000 n \n", $offsets[ $i ] );
		}

		$pdf .= "trailer << /Size " . count( $offsets ) . " /Root 1 0 R >>\n";
		$pdf .= "startxref\n{$xref_offset}\n%%EOF";

		return $pdf;
	}

	private function escape_pdf_text( string $text ): string {
		return str_replace(
			array( '\\', '(', ')' ),
			array( '\\\\', '\\(', '\\)' ),
			preg_replace( '/[^\x20-\x7E]/', '?', $text ) ?? $text
		);
	}

	private function truncate( string $text, int $length ): string {
		if ( strlen( $text ) <= $length ) {
			return $text;
		}

		return substr( $text, 0, $length - 1 ) . '…';
	}
}
