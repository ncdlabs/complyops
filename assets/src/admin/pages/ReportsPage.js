import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	getLatestAudit,
	getAuditHistory,
	getEvidence,
	getActivityLog,
} from '../api';
import ExportFlyout from '../components/ExportFlyout';
import {
	PageHeader,
	LoadingState,
	ErrorState,
	EmptyState,
	StatCard,
	SectionHeader,
	ProgressBar,
	StatusBadge,
	SeverityBadge,
	ControlTitle,
	formatDate,
	auditResultStatusLabel,
	requiresHumanReview,
} from '../components/ui';

const STATUS_SORT_ORDER = {
	FAIL: 0,
	WARNING: 1,
	UNKNOWN: 2,
	PASS: 3,
	INFO: 4,
	NOT_APPLICABLE: 5,
};

const EXPORT_OPTIONS = [
	{ value: 'json', label: __( 'JSON', 'complyops' ) },
	{ value: 'csv', label: __( 'CSV', 'complyops' ) },
];

function sortControlResults( results ) {
	return [ ...( results || [] ) ].sort( ( a, b ) => {
		const orderA =
			STATUS_SORT_ORDER[ String( a.status || '' ).toUpperCase() ] ?? 99;
		const orderB =
			STATUS_SORT_ORDER[ String( b.status || '' ).toUpperCase() ] ?? 99;

		if ( orderA !== orderB ) {
			return orderA - orderB;
		}

		return String( a.control_id ).localeCompare( String( b.control_id ) );
	} );
}

function isUnresolvedFinding( item ) {
	const status = String( item?.status || '' ).toUpperCase();
	return status === 'FAIL' || status === 'WARNING';
}

function isManualReviewItem( item ) {
	if ( requiresHumanReview( item?.capability ) ) {
		return true;
	}

	return String( item?.status || '' ).toUpperCase() === 'UNKNOWN';
}

function toCsv( rows ) {
	return rows
		.map( ( row ) =>
			row
				.map(
					( value ) =>
						`"${ String( value ?? '' ).replaceAll( '"', '""' ) }"`
				)
				.join( ',' )
		)
		.join( '\n' );
}

function downloadBlob( content, filename, type ) {
	const link = document.createElement( 'a' );
	link.href = URL.createObjectURL( new Blob( [ content ], { type } ) );
	link.download = filename;
	link.click();
	URL.revokeObjectURL( link.href );
}

function frameworkLabel( framework ) {
	return String( framework || 'gdpr' ).toUpperCase();
}

export default function ReportsPage() {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		Promise.all( [
			getLatestAudit(),
			getAuditHistory( 'gdpr', 10 ),
			getActivityLog( { action: 'remediation_applied', limit: 25 } ),
		] )
			.then( async ( [ latest, history, activity ] ) => {
				let evidence = { records: [], total: 0 };

				if ( latest?.id ) {
					try {
						evidence = await getEvidence( {
							audit_id: latest.id,
							limit: 25,
						} );
					} catch {
						evidence = { records: [], total: 0 };
					}
				}

				setData( {
					latest,
					history: history.audits || [],
					activity: activity.records || [],
					evidence: evidence.records || [],
					evidenceTotal: evidence.total || 0,
				} );
			} )
			.catch( ( err ) => setError( err?.message ) );
	}, [] );

	if ( error ) {
		return <ErrorState message={ error } />;
	}

	if ( ! data ) {
		return (
			<LoadingState message={ __( 'Preparing reports…', 'complyops' ) } />
		);
	}

	const results = sortControlResults( data.latest?.results || [] );
	const unresolved = results.filter( isUnresolvedFinding );
	const manualReview = results.filter( isManualReviewItem );
	const framework = frameworkLabel( data.latest?.framework );
	const auditDate = formatDate(
		data.latest?.completed_at || data.latest?.started_at
	);
	const siteUrl =
		data.latest?.site_url ||
		( typeof window !== 'undefined' ? window.location.origin : '' );

	const buildReportPackage = () => ( {
		exported_at: new Date().toISOString(),
		site_url: siteUrl,
		framework: data.latest?.framework || 'gdpr',
		plugin_version: data.latest?.plugin_version || null,
		audit: data.latest
			? {
					id: data.latest.id,
					date: data.latest.completed_at || data.latest.started_at,
					score: data.latest.score,
					technical_score: data.latest.technical_score,
					evidence_score: data.latest.evidence_score,
					legal_review_score: data.latest.legal_review_score,
					passed: data.latest.passed,
					warnings: data.latest.warnings,
					failed: data.latest.failed,
					manual_review: data.latest.manual_review,
			  }
			: null,
		controls: results,
		unresolved_findings: unresolved,
		manual_review_items: manualReview,
		remediation_history: data.activity,
		verification_evidence: data.evidence,
		audit_history: data.history,
	} );

	const exportJson = () => {
		downloadBlob(
			JSON.stringify( buildReportPackage(), null, 2 ),
			'complyops-technical-report.json',
			'application/json;charset=utf-8'
		);
	};

	const exportCsv = () => {
		const controlRows = [
			[
				'Control ID',
				'Title',
				'Category',
				'Status',
				'Result',
				'Severity',
				'Observed',
			],
			...results.map( ( item ) => [
				item.control_id,
				item.title,
				item.category,
				item.status,
				auditResultStatusLabel( item ),
				item.severity,
				item.observed,
			] ),
		];

		const historyRows = [
			[
				'Audit',
				'Date',
				'Framework',
				'Score',
				'Passed',
				'Warnings',
				'Failed',
				'Manual review',
			],
			...data.history.map( ( item ) => [
				item.id,
				item.completed_at || item.started_at,
				item.framework,
				item.score,
				item.passed,
				item.warnings,
				item.failed,
				item.manual_review,
			] ),
		];

		const csv = [
			__( 'Control inventory', 'complyops' ),
			toCsv( controlRows ),
			'',
			__( 'Audit history', 'complyops' ),
			toCsv( historyRows ),
		].join( '\n' );

		downloadBlob( csv, 'complyops-technical-report.csv', 'text/csv' );
	};

	const handleExport = ( format ) => {
		if ( format === 'csv' ) {
			exportCsv();
			return;
		}
		exportJson();
	};

	return (
		<div className="complyops-reports">
			<PageHeader
				eyebrow={ __( 'Assure', 'complyops' ) }
				title={ __( 'Reports', 'complyops' ) }
				description={ __(
					'Turn verified technical audit data into clear stakeholder-ready summaries.',
					'complyops'
				) }
				actions={
					data.latest ? (
						<>
							<span data-complyops-tour="export-report">
								<ExportFlyout
									options={ EXPORT_OPTIONS }
									onExport={ handleExport }
									label={ __( 'Export report', 'complyops' ) }
								/>
							</span>
							<button
								type="button"
								className="button button-primary"
								data-complyops-tour="print-report"
								onClick={ () => window.print() }
							>
								{ __( 'Print report', 'complyops' ) }
							</button>
						</>
					) : null
				}
			/>
			{ ! data.latest ? (
				<EmptyState
					title={ __( 'No report data yet', 'complyops' ) }
					description={ __(
						'Complete an audit before generating a technical readiness report.',
						'complyops'
					) }
					icon="dashicons-media-spreadsheet"
				/>
			) : (
				<>
					<div className="complyops-panel">
						<SectionHeader
							title={ __( 'Report metadata', 'complyops' ) }
							description={ __(
								'Site, framework, and audit context for this technical readiness package.',
								'complyops'
							) }
						/>
						<dl className="complyops-report-meta">
							<div>
								<dt>{ __( 'Site', 'complyops' ) }</dt>
								<dd>{ siteUrl || '—' }</dd>
							</div>
							<div>
								<dt>{ __( 'Framework', 'complyops' ) }</dt>
								<dd>{ framework }</dd>
							</div>
							<div>
								<dt>{ __( 'Audit date', 'complyops' ) }</dt>
								<dd>{ auditDate }</dd>
							</div>
							<div>
								<dt>{ __( 'Plugin version', 'complyops' ) }</dt>
								<dd>{ data.latest.plugin_version || '—' }</dd>
							</div>
							<div>
								<dt>{ __( 'Audit', 'complyops' ) }</dt>
								<dd>#{ data.latest.id }</dd>
							</div>
						</dl>
					</div>

					<div className="complyops-metrics">
						<StatCard
							label={ __( 'Current readiness', 'complyops' ) }
							value={ `${ data.latest.score }%` }
							tone="success"
						/>
						<StatCard
							label={ __( 'Controls passed', 'complyops' ) }
							value={ data.latest.passed || 0 }
						/>
						<StatCard
							label={ __( 'Unresolved findings', 'complyops' ) }
							value={ unresolved.length }
							tone={ unresolved.length ? 'warning' : 'success' }
						/>
						<StatCard
							label={ __( 'Manual review', 'complyops' ) }
							value={
								data.latest.manual_review ||
								manualReview.length ||
								0
							}
						/>
					</div>

					<div className="complyops-panel">
						<SectionHeader
							title={ __(
								'Executive technical summary',
								'complyops'
							) }
							description={ `${ __(
								'Based on audit',
								'complyops'
							) } #${ data.latest.id } · ${ auditDate }` }
						/>
						<p>
							{ sprintf(
								/* translators: 1: framework name, 2: readiness score */
								__(
									'ComplyOps evaluated the site against its %1$s technical control catalog and calculated a readiness score of %2$s%%.',
									'complyops'
								),
								framework,
								String( data.latest.score ?? 0 )
							) }
						</p>
						<ProgressBar
							label={ __( 'Technical readiness', 'complyops' ) }
							value={ data.latest.score }
						/>
						<p className="complyops-muted">
							{ __(
								'This report documents technical observations. It is not legal certification or legal advice.',
								'complyops'
							) }
						</p>
					</div>

					<div className="complyops-panel">
						<SectionHeader
							title={ __( 'Unresolved findings', 'complyops' ) }
							description={ __(
								'Failed and warning controls that still need attention.',
								'complyops'
							) }
						/>
						{ unresolved.length === 0 ? (
							<p className="complyops-muted">
								{ __(
									'No unresolved findings in the latest audit.',
									'complyops'
								) }
							</p>
						) : (
							<div className="complyops-table-wrap">
								<table className="widefat striped complyops-table">
									<thead>
										<tr>
											<th>
												{ __(
													'Control',
													'complyops'
												) }
											</th>
											<th>
												{ __( 'Status', 'complyops' ) }
											</th>
											<th>
												{ __(
													'Severity',
													'complyops'
												) }
											</th>
											<th>
												{ __(
													'Observed',
													'complyops'
												) }
											</th>
										</tr>
									</thead>
									<tbody>
										{ unresolved.map( ( item ) => (
											<tr key={ item.control_id }>
												<td>
													<ControlTitle
														title={ item.title }
														controlId={
															item.control_id
														}
														capability={
															item.capability
														}
													/>
												</td>
												<td>
													<StatusBadge
														status={ item.status }
														label={ auditResultStatusLabel(
															item
														) }
													/>
												</td>
												<td>
													<SeverityBadge
														severity={
															item.severity
														}
													/>
												</td>
												<td>{ item.observed || '—' }</td>
											</tr>
										) ) }
									</tbody>
								</table>
							</div>
						) }
					</div>

					<div className="complyops-panel">
						<SectionHeader
							title={ __( 'Manual-review items', 'complyops' ) }
							description={ __(
								'Controls that require human or legal attestation.',
								'complyops'
							) }
						/>
						{ manualReview.length === 0 ? (
							<p className="complyops-muted">
								{ __(
									'No manual-review items in the latest audit.',
									'complyops'
								) }
							</p>
						) : (
							<div className="complyops-table-wrap">
								<table className="widefat striped complyops-table">
									<thead>
										<tr>
											<th>
												{ __(
													'Control',
													'complyops'
												) }
											</th>
											<th>
												{ __( 'Status', 'complyops' ) }
											</th>
											<th>
												{ __(
													'Observed',
													'complyops'
												) }
											</th>
										</tr>
									</thead>
									<tbody>
										{ manualReview.map( ( item ) => (
											<tr key={ item.control_id }>
												<td>
													<ControlTitle
														title={ item.title }
														controlId={
															item.control_id
														}
														capability={
															item.capability
														}
													/>
												</td>
												<td>
													<StatusBadge
														status={ item.status }
														label={ auditResultStatusLabel(
															item
														) }
													/>
												</td>
												<td>{ item.observed || '—' }</td>
											</tr>
										) ) }
									</tbody>
								</table>
							</div>
						) }
					</div>

					<div className="complyops-panel">
						<SectionHeader
							title={ __( 'Control inventory', 'complyops' ) }
							description={ __(
								'Every evaluated control with pass/fail status from the latest audit.',
								'complyops'
							) }
						/>
						<div className="complyops-table-wrap">
							<table className="widefat striped complyops-table">
								<thead>
									<tr>
										<th>
											{ __( 'Control', 'complyops' ) }
										</th>
										<th>
											{ __( 'Category', 'complyops' ) }
										</th>
										<th>
											{ __( 'Status', 'complyops' ) }
										</th>
										<th>
											{ __( 'Severity', 'complyops' ) }
										</th>
										<th>
											{ __( 'Observed', 'complyops' ) }
										</th>
									</tr>
								</thead>
								<tbody>
									{ results.map( ( item ) => (
										<tr key={ item.control_id }>
											<td>
												<ControlTitle
													title={ item.title }
													controlId={ item.control_id }
													capability={
														item.capability
													}
												/>
											</td>
											<td>{ item.category || '—' }</td>
											<td>
												<StatusBadge
													status={ item.status }
													label={ auditResultStatusLabel(
														item
													) }
												/>
											</td>
											<td>
												<SeverityBadge
													severity={ item.severity }
												/>
											</td>
											<td>{ item.observed || '—' }</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</div>
					</div>

					<div className="complyops-panel">
						<SectionHeader
							title={ __( 'Remediation history', 'complyops' ) }
							description={ __(
								'Recent remediation actions recorded in the activity log.',
								'complyops'
							) }
						/>
						{ data.activity.length === 0 ? (
							<p className="complyops-muted">
								{ __(
									'No remediation actions recorded yet.',
									'complyops'
								) }
							</p>
						) : (
							<div className="complyops-table-wrap">
								<table className="widefat striped complyops-table">
									<thead>
										<tr>
											<th>
												{ __( 'When', 'complyops' ) }
											</th>
											<th>
												{ __( 'Action', 'complyops' ) }
											</th>
											<th>
												{ __(
													'Details',
													'complyops'
												) }
											</th>
										</tr>
									</thead>
									<tbody>
										{ data.activity.map( ( item ) => (
											<tr
												key={
													item.id ||
													`${ item.created_at }-${ item.action }`
												}
											>
												<td>
													{ formatDate(
														item.created_at
													) }
												</td>
												<td>
													<code>
														{ item.action ||
															'remediation_applied' }
													</code>
												</td>
												<td>
													{ item.message || '—' }
												</td>
											</tr>
										) ) }
									</tbody>
								</table>
							</div>
						) }
					</div>

					<div className="complyops-panel">
						<SectionHeader
							title={ __(
								'Verification evidence',
								'complyops'
							) }
							description={ __(
								'Evidence captured for the latest audit run.',
								'complyops'
							) }
						/>
						{ data.evidence.length === 0 ? (
							<p className="complyops-muted">
								{ __(
									'No evidence records for this audit yet.',
									'complyops'
								) }
							</p>
						) : (
							<>
								<p className="complyops-muted">
									{ sprintf(
										/* translators: %d: evidence record count */
										__(
											'Showing %d recent evidence records from this audit.',
											'complyops'
										),
										data.evidence.length
									) }
									{ data.evidenceTotal >
										data.evidence.length &&
										` ${ __(
											'Export the full evidence package from Evidence for complete archives.',
											'complyops'
										) }` }
								</p>
								<div className="complyops-table-wrap">
									<table className="widefat striped complyops-table">
										<thead>
											<tr>
												<th>
													{ __(
														'Control',
														'complyops'
													) }
												</th>
												<th>
													{ __(
														'Source',
														'complyops'
													) }
												</th>
												<th>
													{ __(
														'Status',
														'complyops'
													) }
												</th>
												<th>
													{ __(
														'Observed',
														'complyops'
													) }
												</th>
											</tr>
										</thead>
										<tbody>
											{ data.evidence.map( ( item ) => (
												<tr key={ item.id }>
													<td>
														<ControlTitle
															title={
																item.control_title ||
																item.control_id
															}
															controlId={
																item.control_id
															}
														/>
													</td>
													<td>
														{ item.source || '—' }
													</td>
													<td>
														<StatusBadge
															status={
																item.status
															}
														/>
													</td>
													<td>
														{ item.observation ||
															'—' }
													</td>
												</tr>
											) ) }
										</tbody>
									</table>
								</div>
							</>
						) }
					</div>

					<div className="complyops-panel">
						<SectionHeader
							title={ __( 'Audit history', 'complyops' ) }
							description={ __(
								'Recent completed audits retained for comparison.',
								'complyops'
							) }
						/>
						<div className="complyops-table-wrap">
							<table className="widefat striped complyops-table">
								<thead>
									<tr>
										<th>
											{ __( 'Audit', 'complyops' ) }
										</th>
										<th>
											{ __( 'Completed', 'complyops' ) }
										</th>
										<th>
											{ __( 'Framework', 'complyops' ) }
										</th>
										<th>
											{ __( 'Score', 'complyops' ) }
										</th>
										<th>
											{ __( 'Passed', 'complyops' ) }
										</th>
										<th>
											{ __( 'Exceptions', 'complyops' ) }
										</th>
									</tr>
								</thead>
								<tbody>
									{ data.history.map( ( item ) => (
										<tr key={ item.id }>
											<td>
												<strong>#{ item.id }</strong>
											</td>
											<td>
												{ formatDate(
													item.completed_at ||
														item.started_at
												) }
											</td>
											<td>
												{ frameworkLabel(
													item.framework
												) }
											</td>
											<td>{ item.score ?? '—' }%</td>
											<td>{ item.passed || 0 }</td>
											<td>
												{ ( item.failed || 0 ) +
													( item.warnings || 0 ) }
											</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</div>
					</div>
				</>
			) }
		</div>
	);
}
