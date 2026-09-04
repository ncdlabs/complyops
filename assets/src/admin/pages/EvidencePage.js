import { useEffect, useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	getEvidence,
	getActivityLog,
	getAuditHistory,
	exportEvidence,
} from '../api';
import ExportFlyout from '../components/ExportFlyout';
import EvidenceFilterFlyout from '../components/EvidenceFilterFlyout';
import SearchFlyout from '../components/SearchFlyout';
import {
	PageHeader,
	LoadingState,
	ErrorState,
	EmptyState,
	StatusBadge,
	Tabs,
	formatDate,
} from '../components/ui';

const EXPORT_OPTIONS = [
	{ value: 'json', label: __( 'JSON', 'complyops' ) },
	{ value: 'csv', label: __( 'CSV', 'complyops' ) },
	{ value: 'pdf', label: __( 'PDF', 'complyops' ) },
];

const SOURCE_LABELS = {
	audit: __( 'Audit', 'complyops' ),
	remediation: __( 'Remediation', 'complyops' ),
	drift: __( 'Drift', 'complyops' ),
};

function downloadExport( payload, fallbackName ) {
	if ( ! payload ) {
		return;
	}

	const format = payload.format || 'json';
	let blob;
	let filename = payload.filename || fallbackName;

	if ( format === 'pdf' && payload.encoding === 'base64' ) {
		const binary = atob( payload.content || '' );
		const bytes = Uint8Array.from( binary, ( char ) =>
			char.charCodeAt( 0 )
		);
		blob = new Blob( [ bytes ], { type: 'application/pdf' } );
	} else if ( format === 'csv' ) {
		blob = new Blob( [ payload.content || '' ], {
			type: 'text/csv;charset=utf-8',
		} );
	} else {
		blob = new Blob( [ JSON.stringify( payload, null, 2 ) ], {
			type: 'application/json;charset=utf-8',
		} );
		filename = filename.endsWith( '.json' )
			? filename
			: 'complyops-evidence.json';
	}

	const url = URL.createObjectURL( blob );
	const link = document.createElement( 'a' );
	link.href = url;
	link.download = filename;
	link.click();
	URL.revokeObjectURL( url );
}

export default function EvidencePage() {
	const [ tab, setTab ] = useState( 'evidence' );
	const [ evidence, setEvidence ] = useState( {
		total: 0,
		records: [],
		meta: {},
	} );
	const [ activity, setActivity ] = useState( { total: 0, records: [] } );
	const [ audits, setAudits ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ query, setQuery ] = useState( '' );
	const [ source, setSource ] = useState( '' );
	const [ auditId, setAuditId ] = useState( '' );
	const [ testMethod, setTestMethod ] = useState( '' );

	const filters = useCallback(
		() => ( {
			framework: 'gdpr',
			search: query || undefined,
			source: source || undefined,
			audit_id: auditId ? Number( auditId ) : undefined,
			test_method: testMethod || undefined,
			limit: 100,
		} ),
		[ query, source, auditId, testMethod ]
	);

	const load = useCallback( async () => {
		setLoading( true );
		try {
			const filterParams = filters();
			const [ evidenceData, activityData, historyData ] =
				await Promise.all( [
					getEvidence( filterParams ),
					getActivityLog( { limit: 50 } ),
					getAuditHistory( 'gdpr', 20 ),
				] );
			setEvidence( evidenceData || { total: 0, records: [], meta: {} } );
			setActivity( activityData || { total: 0, records: [] } );
			setAudits( historyData?.audits || [] );
			setError( null );
		} catch ( err ) {
			setError( err?.message );
		} finally {
			setLoading( false );
		}
	}, [ filters ] );

	useEffect( () => {
		load();
	}, [ load ] );

	const handleExport = async ( format ) => {
		try {
			const payload = await exportEvidence( format, filters() );
			downloadExport( payload, `complyops-evidence.${ format }` );
		} catch ( err ) {
			setError( err?.message || __( 'Export failed.', 'complyops' ) );
		}
	};

	if ( loading ) {
		return (
			<LoadingState
				message={ __( 'Loading evidence records…', 'complyops' ) }
			/>
		);
	}

	if ( error ) {
		return <ErrorState message={ error } onRetry={ load } />;
	}

	const records = evidence.records || [];
	const collected = records.filter(
		( item ) => ! [ 'UNKNOWN', 'INFO' ].includes( item.status )
	).length;
	const auditRecords = records.filter(
		( item ) => item.source === 'audit'
	).length;
	const remediationRecords = records.filter(
		( item ) => item.source === 'remediation'
	).length;
	const driftRecords = records.filter(
		( item ) => item.source === 'drift'
	).length;

	return (
		<div>
			<PageHeader
				eyebrow={ __( 'Manage', 'complyops' ) }
				title={ __( 'Evidence', 'complyops' ) }
				description={ __(
					'Trace technical control observations, remediation verification, configuration drift, and administrator activity.',
					'complyops'
				) }
			/>

			<Tabs
				active={ tab }
				onChange={ setTab }
				items={ [
					{ value: 'evidence', label: __( 'Evidence', 'complyops' ) },
					{
						value: 'activity',
						label: __( 'Activity log', 'complyops' ),
					},
				] }
			/>

			{ tab === 'evidence' &&
				( records.length === 0 ? (
					<EmptyState
						title={ __( 'No evidence collected yet', 'complyops' ) }
						description={ __(
							'Evidence is captured when audits run, remediations verify, or configuration drift is detected.',
							'complyops'
						) }
						icon="dashicons-media-document"
					/>
				) : (
					<>
						<div className="complyops-summary-card complyops-summary-card--evidence">
							<div className="complyops-summary-card__site">
								<span className="complyops-summary-card__label">
									{ __( 'Site', 'complyops' ) }
								</span>
								<span className="complyops-summary-card__value">
									{ evidence.meta?.site_url || '—' }
								</span>
							</div>
							<div
								className="complyops-summary-card__metrics"
								role="list"
							>
								<div
									className="complyops-summary-card__item"
									role="listitem"
								>
									<span className="complyops-summary-card__label">
										{ __(
											'Evidence records',
											'complyops'
										) }
									</span>
									<span className="complyops-summary-card__value complyops-summary-card__value--numeric">
										{ evidence.total || records.length }
									</span>
								</div>
								<div
									className="complyops-summary-card__item"
									role="listitem"
								>
									<span className="complyops-summary-card__label">
										{ __( 'Collected', 'complyops' ) }
									</span>
									<span className="complyops-summary-card__value complyops-summary-card__value--success complyops-summary-card__value--numeric">
										{ collected }
									</span>
								</div>
								<div
									className="complyops-summary-card__item"
									role="listitem"
								>
									<span className="complyops-summary-card__label">
										{ __(
											'Audit / remediation / drift',
											'complyops'
										) }
									</span>
									<span className="complyops-summary-card__value complyops-summary-card__value--numeric">
										{ auditRecords } /{ ' ' }
										{ remediationRecords } /{ ' ' }
										{ driftRecords }
									</span>
								</div>
							</div>
						</div>
						<div className="complyops-toolbar complyops-toolbar--wrap">
							<span className="complyops-toolbar__actions">
								<SearchFlyout
									value={ query }
									onChange={ setQuery }
								/>
								<EvidenceFilterFlyout
									source={ source }
									onSourceChange={ setSource }
									auditId={ auditId }
									onAuditIdChange={ setAuditId }
									testMethod={ testMethod }
									onTestMethodChange={ setTestMethod }
									audits={ audits }
								/>
								<ExportFlyout
									options={ EXPORT_OPTIONS }
									onExport={ handleExport }
								/>
								<button
									type="button"
									className="complyops-icon-button"
									onClick={ () => window.print() }
									aria-label={ __( 'Print', 'complyops' ) }
									title={ __( 'Print', 'complyops' ) }
								>
									<span
										className="dashicons dashicons-printer"
										aria-hidden="true"
									/>
								</button>
							</span>
						</div>
						<div className="complyops-panel complyops-panel--flush">
							<div className="complyops-table-wrap">
								<table className="complyops-table">
									<thead>
										<tr>
											<th>
												{ __( 'Control', 'complyops' ) }
											</th>
											<th>
												{ __( 'Source', 'complyops' ) }
											</th>
											<th>
												{ __(
													'Observation',
													'complyops'
												) }
											</th>
											<th>
												{ __( 'Result', 'complyops' ) }
											</th>
											<th>
												{ __( 'Method', 'complyops' ) }
											</th>
											<th>
												{ __(
													'Captured',
													'complyops'
												) }
											</th>
										</tr>
									</thead>
									<tbody>
										{ records.map( ( item ) => (
											<tr key={ item.id }>
												<td>
													<span className="complyops-table__primary">
														{ item.control_title ||
															item.title }
													</span>
													<code>
														{ item.control_id }
													</code>
												</td>
												<td>
													<span className="complyops-badge complyops-badge--info">
														{ SOURCE_LABELS[
															item.source
														] || item.source }
													</span>
													{ item.audit_id ? (
														<span className="complyops-muted">
															{ ' ' }
															#{ item.audit_id }
														</span>
													) : null }
													{ item.remediation_id ? (
														<span className="complyops-muted">
															{ ' ' }
															R
															{
																item.remediation_id
															}
														</span>
													) : null }
												</td>
												<td>
													{ item.observation ||
														item.observed ||
														__(
															'No observation recorded',
															'complyops'
														) }
												</td>
												<td>
													<StatusBadge
														status={ item.status }
													/>
												</td>
												<td>
													{ item.test_method ||
														'static' }
												</td>
												<td>
													{ formatDate(
														item.created_at
													) }
												</td>
											</tr>
										) ) }
									</tbody>
								</table>
							</div>
						</div>
					</>
				) ) }

			{ tab === 'activity' &&
				( ( activity.records || [] ).length === 0 ? (
					<EmptyState
						title={ __( 'No activity recorded yet', 'complyops' ) }
						description={ __(
							'Administrator actions such as audits, settings changes, and exports appear here.',
							'complyops'
						) }
						icon="dashicons-backup"
					/>
				) : (
					<div className="complyops-panel complyops-panel--flush">
						<div className="complyops-table-wrap">
							<table className="complyops-table">
								<thead>
									<tr>
										<th>{ __( 'When', 'complyops' ) }</th>
										<th>{ __( 'Action', 'complyops' ) }</th>
										<th>
											{ __( 'Summary', 'complyops' ) }
										</th>
										<th>{ __( 'Object', 'complyops' ) }</th>
									</tr>
								</thead>
								<tbody>
									{ ( activity.records || [] ).map(
										( item ) => (
											<tr key={ item.id }>
												<td>
													{ formatDate(
														item.created_at
													) }
												</td>
												<td>
													<code>{ item.action }</code>
												</td>
												<td>{ item.summary }</td>
												<td>
													{ item.object_type
														? `${
																item.object_type
														  }${
																item.object_id
																	? `: ${ item.object_id }`
																	: ''
														  }`
														: '—' }
												</td>
											</tr>
										)
									) }
								</tbody>
							</table>
						</div>
					</div>
				) ) }
		</div>
	);
}
