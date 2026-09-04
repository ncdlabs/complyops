function controlTotal( audit ) {
	return (
		( audit?.passed || 0 ) +
		( audit?.failed || 0 ) +
		( audit?.warnings || 0 ) +
		( audit?.unknowns || 0 )
	);
}

export function aggregateComplianceAudits( audits, findings, plans ) {
	if ( ! audits.length ) {
		return { audit: null, findings: [], plan: null };
	}

	const totals = audits.reduce(
		( acc, audit ) => {
			const weight = controlTotal( audit );
			return {
				passed: acc.passed + ( audit.passed || 0 ),
				failed: acc.failed + ( audit.failed || 0 ),
				warnings: acc.warnings + ( audit.warnings || 0 ),
				unknowns: acc.unknowns + ( audit.unknowns || 0 ),
				manual_review: acc.manual_review + ( audit.manual_review || 0 ),
				weight: acc.weight + weight,
				weightedScore:
					acc.weightedScore + ( audit.score || 0 ) * weight,
			};
		},
		{
			passed: 0,
			failed: 0,
			warnings: 0,
			unknowns: 0,
			manual_review: 0,
			weight: 0,
			weightedScore: 0,
		}
	);

	const score = totals.weight
		? Math.round( totals.weightedScore / totals.weight )
		: 0;
	const results = audits.flatMap( ( audit ) =>
		( audit.results || [] ).map( ( result ) => ( {
			...result,
			framework: audit.framework,
		} ) )
	);

	const automatic = [];
	const seen = new Set();
	for ( const plan of plans ) {
		for ( const action of plan?.automatic || [] ) {
			if ( ! seen.has( action.action_id ) ) {
				seen.add( action.action_id );
				automatic.push( action );
			}
		}
	}

	const latest = audits.reduce( ( best, audit ) => {
		const bestTime = new Date(
			best?.completed_at || best?.started_at || 0
		).getTime();
		const auditTime = new Date(
			audit.completed_at || audit.started_at || 0
		).getTime();
		return auditTime > bestTime ? audit : best;
	}, audits[ 0 ] );

	return {
		audit: {
			...latest,
			score,
			passed: totals.passed,
			failed: totals.failed,
			warnings: totals.warnings,
			unknowns: totals.unknowns,
			manual_review: totals.manual_review,
			results,
			frameworks: audits.map( ( item ) => item.framework ),
		},
		findings,
		plan: automatic.length ? { automatic } : null,
	};
}

export function frameworkFilterSummary( frameworks, selectedIds ) {
	if ( ! frameworks.length || selectedIds.length === frameworks.length ) {
		return '';
	}

	const labels = frameworks
		.filter( ( item ) => selectedIds.includes( item.id ) )
		.map( ( item ) => item.label );

	if ( labels.length <= 2 ) {
		return labels.join( ', ' );
	}

	return `${ labels[ 0 ] } +${ labels.length - 1 }`;
}
