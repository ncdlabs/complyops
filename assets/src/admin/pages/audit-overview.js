import { useState, useEffect, useCallback, useMemo } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { runAudit, getLatestAudit, getAuditHistory } from '../api';
import { AuditTrendChart } from '../components/AuditTrendChart';
import { NonCertificationNotice } from '../components/PackDisclaimer';
import {
	Alert,
	EmptyState,
	ErrorState,
	formatDate,
	LoadingState,
	ProgressBar,
	ScoreRing,
	SectionHeader,
	StatCard,
	useNotify,
} from '../components/ui';
import { adminPageUrl } from '../url-state';
import {
	auditTimestamp,
	chartPoints,
	compareToPrevious,
} from '../audit-trends';

export function useAuditRunner( config, onComplete ) {
	const [ running, setRunning ] = useState( false );
	const [ result, setResult ] = useState( null );
	const notify = useNotify();

	const handleRun = async () => {
		if ( ! config.canRunAudit ) {
			return;
		}

		setRunning( true );
		setResult( null );

		try {
			const audit = await runAudit();
			setResult( audit );
			onComplete?.( audit );
		} catch ( err ) {
			notify.error( err?.message || __( 'Audit failed.', 'complyops' ) );
		} finally {
			setRunning( false );
		}
	};

	const handleRefresh = async () => {
		setRunning( true );
		try {
			const audit = await getLatestAudit();
			setResult( audit );
		} catch ( err ) {
			notify.error(
				err?.message ||
					__( 'Could not load latest audit.', 'complyops' )
			);
		} finally {
			setRunning( false );
		}
	};

	return {
		running,
		result,
		handleRun,
		handleRefresh,
	};
}

function driftMessage( drift ) {
	if ( ! drift ) {
		return null;
	}

	const { scoreDelta, failedDelta, previous } = drift;
	const parts = [];

	if ( scoreDelta > 0 ) {
		parts.push(
			sprintf(
				/* translators: %d: score increase */
				__( 'readiness improved %d points', 'complyops' ),
				scoreDelta
			)
		);
	} else if ( scoreDelta < 0 ) {
		parts.push(
			sprintf(
				/* translators: %d: score decrease */
				__( 'readiness dropped %d points', 'complyops' ),
				Math.abs( scoreDelta )
			)
		);
	} else {
		parts.push( __( 'readiness score unchanged', 'complyops' ) );
	}

	if ( failedDelta > 0 ) {
		parts.push(
			sprintf(
				/* translators: %d: additional failures */
				__( '%d more failed controls', 'complyops' ),
				failedDelta
			)
		);
	} else if ( failedDelta < 0 ) {
		parts.push(
			sprintf(
				/* translators: %d: resolved failures */
				__( '%d fewer failed controls', 'complyops' ),
				Math.abs( failedDelta )
			)
		);
	}

	return sprintf(
		/* translators: 1: comparison summary, 2: previous audit date */
		__( 'Since the previous audit (%2$s): %1$s.', 'complyops' ),
		parts.join( ', ' ),
		formatDate( auditTimestamp( previous ) )
	);
}

function driftTone( drift ) {
	if ( ! drift ) {
		return 'info';
	}

	if ( drift.scoreDelta > 0 || drift.failedDelta < 0 ) {
		return 'success';
	}

	if ( drift.scoreDelta < 0 || drift.failedDelta > 0 ) {
		return 'warning';
	}

	return 'info';
}

export function AuditOverviewPanel( {
	config,
	running,
	refreshKey = null,
	isActive = true,
	onRun,
} ) {
	const [ latest, setLatest ] = useState( null );
	const [ history, setHistory ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	const load = useCallback( async () => {
		setLoading( true );
		setError( null );

		try {
			const [ latestAudit, historyData ] = await Promise.all( [
				getLatestAudit(),
				getAuditHistory( 'gdpr', 12 ),
			] );

			setLatest( latestAudit );
			setHistory( historyData.audits || [] );
		} catch ( err ) {
			if (
				err?.code === 'complyops_no_audit' ||
				err?.data?.status === 404
			) {
				setLatest( null );
				setHistory( [] );
			} else {
				setError(
					err?.message ||
						__( 'Failed to load audit overview.', 'complyops' )
				);
			}
		} finally {
			setLoading( false );
		}
	}, [] );

	useEffect( () => {
		if ( isActive ) {
			load();
		}
	}, [ isActive, refreshKey, load ] );

	const drift = useMemo( () => compareToPrevious( history ), [ history ] );
	const trendPoints = useMemo( () => chartPoints( history ), [ history ] );
	const total = latest
		? ( latest.passed || 0 ) +
		  ( latest.failed || 0 ) +
		  ( latest.warnings || 0 ) +
		  ( latest.unknowns || 0 )
		: 0;

	if ( loading && ! latest ) {
		return (
			<LoadingState
				message={ __( 'Loading audit overview…', 'complyops' ) }
			/>
		);
	}

	if ( error && ! latest ) {
		return <ErrorState message={ error } onRetry={ load } />;
	}

	if ( ! latest && ! running ) {
		return (
			<div className="complyops-audit-overview">
				<EmptyState
					title={ __( 'No audits yet', 'complyops' ) }
					description={ __(
						'Run an audit to evaluate technical controls, capture evidence, and establish a point-in-time baseline.',
						'complyops'
					) }
					icon="dashicons-chart-line"
					action={
						config.canRunAudit ? (
							<button
								type="button"
								className="button button-primary"
								data-complyops-tour="run-audit"
								onClick={ onRun }
							>
								{ __( 'Run Audit', 'complyops' ) }
							</button>
						) : null
					}
				/>
				{ ! config.canRunAudit && (
					<p className="complyops-muted">
						{ __(
							'You do not have permission to run audits.',
							'complyops'
						) }
					</p>
				) }
			</div>
		);
	}

	return (
		<div className="complyops-audit-overview">
			<NonCertificationNotice />
			{ running && (
				<LoadingState
					message={ __( 'Running compliance audit…', 'complyops' ) }
				/>
			) }

			{ drift && (
				<Alert tone={ driftTone( drift ) }>
					{ driftMessage( drift ) }
				</Alert>
			) }

			<div className="complyops-metrics">
				<StatCard
					label={ __( 'Latest readiness', 'complyops' ) }
					value={ `${ latest.score ?? 0 }%` }
					detail={ formatDate( auditTimestamp( latest ) ) }
					tone={ ( latest.score ?? 0 ) >= 80 ? 'success' : 'warning' }
					icon="dashicons-chart-area"
					centered
					href={ `${ adminPageUrl( 'complyops-audit', {
						tab: 'overview',
					} ) }#audit-score` }
				/>
				<StatCard
					label={ __( 'Passed', 'complyops' ) }
					value={ latest.passed ?? 0 }
					detail={ __( 'Controls verified', 'complyops' ) }
					icon="dashicons-yes-alt"
					centered
					href={ adminPageUrl( 'complyops-audit', {
						tab: 'findings',
						filter: 'pass',
					} ) }
				/>
				<StatCard
					label={ __( 'Failed', 'complyops' ) }
					value={ latest.failed ?? 0 }
					tone={ latest.failed ? 'danger' : 'success' }
					icon="dashicons-dismiss"
					centered
					href={ adminPageUrl( 'complyops-audit', {
						tab: 'findings',
						filter: 'fail',
					} ) }
				/>
				<StatCard
					label={ __( 'Warnings', 'complyops' ) }
					value={ latest.warnings ?? 0 }
					tone={ latest.warnings ? 'warning' : 'default' }
					icon="dashicons-warning"
					centered
					href={ adminPageUrl( 'complyops-audit', {
						tab: 'findings',
						filter: 'warning',
					} ) }
				/>
			</div>

			<div className="complyops-grid">
				<div
					id="audit-score"
					className="complyops-panel complyops-audit-overview__score"
				>
					<SectionHeader
						title={ __( 'Latest audit', 'complyops' ) }
						description={ sprintf(
							/* translators: 1: audit id, 2: framework */
							__( 'Audit #%1$d · %2$s', 'complyops' ),
							latest.id,
							String( latest.framework || 'gdpr' ).toUpperCase()
						) }
					/>
					<ScoreRing score={ latest.score } />
					<ProgressBar
						label={ __( 'Effective controls', 'complyops' ) }
						value={
							total
								? Math.round(
										( ( latest.passed || 0 ) / total ) * 100
								  )
								: 0
						}
					/>
					<p className="complyops-muted complyops-audit-overview__actions">
						<a
							href={ adminPageUrl( 'complyops-audit', {
								tab: 'findings',
							} ) }
						>
							{ __( 'View findings →', 'complyops' ) }
						</a>
					</p>
				</div>

				<div className="complyops-panel">
					<SectionHeader
						title={ __( 'Result breakdown', 'complyops' ) }
						description={ __(
							'Counts from the most recent audit run.',
							'complyops'
						) }
					/>
					<ul className="complyops-summary-list">
						<li>
							<strong>{ __( 'Passed:', 'complyops' ) }</strong>{ ' ' }
							{ latest.passed ?? 0 }
						</li>
						<li>
							<strong>{ __( 'Failed:', 'complyops' ) }</strong>{ ' ' }
							{ latest.failed ?? 0 }
						</li>
						<li>
							<strong>{ __( 'Warnings:', 'complyops' ) }</strong>{ ' ' }
							{ latest.warnings ?? 0 }
						</li>
						<li>
							<strong>{ __( 'Unknown:', 'complyops' ) }</strong>{ ' ' }
							{ latest.unknowns ?? 0 }
						</li>
						<li>
							<strong>
								{ __( 'Manual review:', 'complyops' ) }
							</strong>{ ' ' }
							{ latest.manual_review ?? 0 }
						</li>
						<li>
							<strong>
								{ __( 'Not applicable:', 'complyops' ) }
							</strong>{ ' ' }
							{ latest.not_applicable ?? 0 }
						</li>
					</ul>
					{ drift && (
						<div className="complyops-audit-overview__compare">
							<h3>{ __( 'Previous audit', 'complyops' ) }</h3>
							<p className="complyops-muted">
								{ sprintf(
									/* translators: 1: audit id, 2: date, 3: score, 4: failed control count */
									__(
										'Audit #%1$d on %2$s scored %3$s%% with %4$d failed controls.',
										'complyops'
									),
									drift.previous.id,
									formatDate(
										auditTimestamp( drift.previous )
									),
									drift.previous.score ?? 0,
									drift.previous.failed ?? 0
								) }
							</p>
						</div>
					) }
				</div>

				<div
					id="audit-trend"
					className="complyops-panel complyops-panel--wide"
				>
					<SectionHeader
						title={ __( 'Readiness over time', 'complyops' ) }
						description={ __(
							'Track improvement or drift across recent audit runs.',
							'complyops'
						) }
					/>
					<AuditTrendChart points={ trendPoints } />
				</div>
			</div>
		</div>
	);
}
