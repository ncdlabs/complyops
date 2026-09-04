/**
 * Helpers for audit history comparisons and trend charts.
 */

export function auditTimestamp( audit ) {
	return audit?.completed_at || audit?.started_at || audit?.created_at || '';
}

export function chronologically( audits ) {
	return [ ...( audits || [] ) ].reverse();
}

/**
 * Compare the two most recent audits (newest-first input).
 *
 * @param {Array<object>} audits
 */
export function compareToPrevious( audits ) {
	if ( ! audits || audits.length < 2 ) {
		return null;
	}

	const latest = audits[ 0 ];
	const previous = audits[ 1 ];
	const latestScore = Number( latest.score ) || 0;
	const previousScore = Number( previous.score ) || 0;
	const latestFailed = Number( latest.failed ) || 0;
	const previousFailed = Number( previous.failed ) || 0;

	return {
		latest,
		previous,
		scoreDelta: latestScore - previousScore,
		failedDelta: latestFailed - previousFailed,
		warningDelta:
			( Number( latest.warnings ) || 0 ) -
			( Number( previous.warnings ) || 0 ),
	};
}

/**
 * @param {Array<object>} audits Newest-first audit rows.
 */
export function chartPoints( audits ) {
	return chronologically( audits ).map( ( audit ) => ( {
		id: audit.id,
		score: Math.max( 0, Math.min( 100, Number( audit.score ) || 0 ) ),
		failed: Number( audit.failed ) || 0,
		at: auditTimestamp( audit ),
	} ) );
}

/**
 * @param {Array<{ score: number }>} points Chronological chart points.
 */
export function chartDomain( points ) {
	if ( ! points.length ) {
		return { min: 0, max: 100 };
	}

	const scores = points.map( ( point ) => point.score );
	const min = Math.max( 0, Math.min( ...scores ) - 5 );
	const max = Math.min( 100, Math.max( ...scores ) + 5 );

	if ( max - min < 20 ) {
		const mid = ( max + min ) / 2;
		return { min: Math.max( 0, mid - 10 ), max: Math.min( 100, mid + 10 ) };
	}

	return { min, max };
}
