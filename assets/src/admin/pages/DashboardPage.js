import { useEffect, useState, useCallback, useMemo } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	getLatestAudit,
	getFindings,
	runAudit,
	getRemediationPlan,
	applyRemediation,
	getIntegrations,
	getFrameworks,
	getSettings,
	updateSettings,
} from '../api';
import {
	ScoreRing,
	StatCard,
	StatusBadge,
	SeverityBadge,
	LoadingState,
	ErrorState,
	EmptyState,
	PageHeader,
	SectionHeader,
	ProgressBar,
	Alert,
	Tabs,
	ClickablePanel,
	formatDate,
} from '../components/ui';
import FrameworkFilterFlyout from '../components/FrameworkFilterFlyout';
import { NonCertificationNotice } from '../components/PackDisclaimer';
import {
	adminPageUrl,
	useUrlMultiParam,
	useUrlParam,
	useUrlTab,
} from '../url-state';
import {
	aggregateComplianceAudits,
	frameworkFilterSummary,
} from '../dashboard-compliance';
import {
	COMPLIANCE_DETAILS,
	DashboardDetailScreen,
	SYSTEM_DETAILS,
} from './dashboard-details';

const DASHBOARD_TABS = [
	{ value: 'compliance', label: __( 'Compliance health', 'complyops' ) },
	{ value: 'system', label: __( 'System health', 'complyops' ) },
];

const DASHBOARD_TAB_VALUES = DASHBOARD_TABS.map( ( tab ) => tab.value );
const ALL_DETAILS = [ ...COMPLIANCE_DETAILS, ...SYSTEM_DETAILS ];

function dashboardDetailUrl( tab, detail ) {
	return adminPageUrl( 'complyops', { tab, detail } );
}

function DashboardAuditActions( {
	canRemediate,
	canRunAudit,
	plan,
	audit,
	applying,
	running,
	onApply,
	onRun,
} ) {
	if ( ! canRunAudit && ! ( canRemediate && plan?.automatic?.length > 0 ) ) {
		return null;
	}

	return (
		<div className="complyops-dashboard__hero-actions">
			{ canRemediate && plan?.automatic?.length > 0 && (
				<button
					type="button"
					className="button button-secondary"
					onClick={ onApply }
					disabled={ applying || running }
				>
					{ applying
						? __( 'Applying…', 'complyops' )
						: __( 'Apply recommended controls', 'complyops' ) }
				</button>
			) }
			{ canRunAudit && (
				<button
					type="button"
					className="button button-primary"
					data-complyops-tour="run-audit"
					onClick={ onRun }
					disabled={ running || applying }
				>
					{ running
						? __( 'Running audit…', 'complyops' )
						: audit
						? __( 'Run audit', 'complyops' )
						: __( 'Run first audit', 'complyops' ) }
				</button>
			) }
		</div>
	);
}

function SystemChecksPanel( { audit, href } ) {
	return (
		<ClickablePanel href={ href }>
			<SectionHeader
				title={ __( 'System checks', 'complyops' ) }
				description={ __(
					'Operational signals available to ComplyOps.',
					'complyops'
				) }
			/>
			<ul className="complyops-list">
				<li className="complyops-list__row">
					<span>{ __( 'Monitoring', 'complyops' ) }</span>
					<StatusBadge
						status="PASS"
						label={ __( 'Active', 'complyops' ) }
					/>
				</li>
				<li className="complyops-list__row">
					<span>{ __( 'REST API', 'complyops' ) }</span>
					<StatusBadge
						status="PASS"
						label={ __( 'Available', 'complyops' ) }
					/>
				</li>
				<li className="complyops-list__row">
					<span>{ __( 'Technical audit engine', 'complyops' ) }</span>
					<StatusBadge
						status={ audit ? 'PASS' : 'UNKNOWN' }
						label={
							audit
								? __( 'Reporting', 'complyops' )
								: __( 'Awaiting first run', 'complyops' )
						}
					/>
				</li>
				<li className="complyops-list__row">
					<span>{ __( 'Integration discovery', 'complyops' ) }</span>
					<StatusBadge
						status="PASS"
						label={ __( 'Active', 'complyops' ) }
					/>
				</li>
			</ul>
		</ClickablePanel>
	);
}

export default function DashboardPage( { config } ) {
	const [ frameworks, setFrameworks ] = useState( [] );
	const [ audit, setAudit ] = useState( null );
	const [ systemAudit, setSystemAudit ] = useState( null );
	const [ findings, setFindings ] = useState( [] );
	const [ plan, setPlan ] = useState( null );
	const [ discovery, setDiscovery ] = useState( null );
	const [ detectedCount, setDetectedCount ] = useState( 0 );
	const [ loading, setLoading ] = useState( true );
	const [ running, setRunning ] = useState( false );
	const [ applying, setApplying ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ settings, setSettings ] = useState( null );
	const [ savingSettings, setSavingSettings ] = useState( false );
	const [ activeTab, setActiveTab ] = useUrlTab(
		'tab',
		DASHBOARD_TAB_VALUES,
		'compliance'
	);
	const [ detail, setDetail ] = useUrlParam( 'detail', ALL_DETAILS, '' );
	const frameworkIds = useMemo(
		() => frameworks.map( ( item ) => item.id ),
		[ frameworks ]
	);
	const [ selectedFrameworks, setSelectedFrameworks ] = useUrlMultiParam(
		'frameworks',
		frameworkIds,
		frameworkIds
	);
	const allowedDetails =
		activeTab === 'system' ? SYSTEM_DETAILS : COMPLIANCE_DETAILS;
	const activeDetail = allowedDetails.includes( detail ) ? detail : '';
	const frameworkSummary = frameworkFilterSummary(
		frameworks,
		selectedFrameworks
	);
	const selectedFrameworkLabels = frameworks
		.filter( ( item ) => selectedFrameworks.includes( item.id ) )
		.map( ( item ) => item.label )
		.join( ', ' );

	useEffect( () => {
		getFrameworks()
			.then( ( data ) => setFrameworks( data.frameworks || [] ) )
			.catch( () => setFrameworks( [] ) );
	}, [] );

	const load = useCallback( async () => {
		setLoading( true );
		setError( null );
		try {
			const loadedSettings = await getSettings();
			setSettings( loadedSettings );

			const integrations = await getIntegrations();
			const detected = Object.values(
				integrations?.integrations || {}
			).filter( ( item ) => item.detected );
			setDiscovery( integrations );
			setDetectedCount( detected.length );

			const latestSystem = await getLatestAudit();
			setSystemAudit( latestSystem );

			if ( ! selectedFrameworks.length ) {
				setAudit( null );
				setFindings( [] );
				setPlan( null );
				return;
			}

			const audits = await Promise.all(
				selectedFrameworks.map( ( frameworkId ) =>
					getLatestAudit( frameworkId )
				)
			);
			const validAudits = audits.filter( Boolean );
			let allFindings = [];
			const plans = [];

			for ( const frameworkAudit of validAudits ) {
				const data = await getFindings( frameworkAudit.id );
				allFindings = allFindings.concat(
					( data.findings || [] ).map( ( item ) => ( {
						...item,
						framework: frameworkAudit.framework,
					} ) )
				);
				if ( config.canRemediate ) {
					try {
						plans.push(
							await getRemediationPlan( frameworkAudit.id )
						);
					} catch {
						// No remediation plan for this audit.
					}
				}
			}

			const aggregated = aggregateComplianceAudits(
				validAudits,
				allFindings,
				plans
			);
			setAudit( aggregated.audit );
			setFindings( aggregated.findings );
			setPlan( aggregated.plan );
		} catch ( err ) {
			setError(
				err?.message || __( 'Failed to load dashboard.', 'complyops' )
			);
		} finally {
			setLoading( false );
		}
	}, [ config.canRemediate, selectedFrameworks ] );

	useEffect( () => {
		load();
	}, [ load ] );

	useEffect( () => {
		if ( detail && ! allowedDetails.includes( detail ) ) {
			setDetail( '' );
		}
	}, [ detail, allowedDetails, setDetail ] );

	const handleTabChange = ( tab ) => {
		setActiveTab( tab );
		setDetail( '' );
	};

	const primaryFramework = selectedFrameworks[ 0 ] || 'gdpr';

	const handleRun = async () => {
		setRunning( true );
		setError( null );
		try {
			await runAudit( primaryFramework );
			window.location.assign(
				adminPageUrl( 'complyops-audit', { tab: 'findings' } )
			);
		} catch ( err ) {
			setError( err?.message || __( 'Audit failed.', 'complyops' ) );
		} finally {
			setRunning( false );
		}
	};

	const handleApply = async () => {
		setApplying( true );
		setError( null );
		try {
			await applyRemediation(
				plan.automatic.map( ( item ) => item.action_id ),
				audit.id,
				primaryFramework
			);
			await load();
		} catch ( err ) {
			setError(
				err?.message || __( 'Remediation failed.', 'complyops' )
			);
		} finally {
			setApplying( false );
		}
	};

	const saveSettings = async ( patch ) => {
		setSavingSettings( true );
		setError( null );
		try {
			const saved = await updateSettings( patch );
			setSettings( saved );
		} catch ( err ) {
			setError(
				err?.message || __( 'Failed to save settings.', 'complyops' )
			);
		} finally {
			setSavingSettings( false );
		}
	};

	if ( loading ) {
		return (
			<LoadingState
				message={ __(
					'Loading your compliance posture…',
					'complyops'
				) }
			/>
		);
	}

	if ( error && ! audit && ! discovery ) {
		return <ErrorState message={ error } onRetry={ load } />;
	}

	const total = audit
		? ( audit.passed || 0 ) +
		  ( audit.failed || 0 ) +
		  ( audit.warnings || 0 ) +
		  ( audit.unknowns || 0 )
		: 0;
	const urgent = findings.filter( ( item ) =>
		[ 'CRITICAL', 'HIGH' ].includes( item.severity )
	);
	const detailUrl = ( key ) => dashboardDetailUrl( activeTab, key );
	const complianceDetail =
		selectedFrameworkLabels || __( 'selected frameworks', 'complyops' );

	return (
		<div>
			<PageHeader
				eyebrow={ __( 'Monitor', 'complyops' ) }
				title={ __( 'Dashboard', 'complyops' ) }
				description={ __(
					'Your technical compliance posture, operational status, and audit readiness.',
					'complyops'
				) }
				meta={
					audit ? (
						<span>
							{ __( 'Last audit', 'complyops' ) }:{ ' ' }
							{ formatDate(
								audit.completed_at || audit.started_at
							) }
							{ frameworkSummary
								? ` · ${ frameworkSummary }`
								: '' }
						</span>
					) : null
				}
			/>
			{ error && <Alert tone="danger">{ error }</Alert> }
			<div className="complyops-tabs-bar">
				<Tabs
					items={ DASHBOARD_TABS }
					active={ activeTab }
					onChange={ handleTabChange }
				/>
				{ activeTab === 'compliance' &&
					! activeDetail &&
					frameworks.length > 0 && (
						<FrameworkFilterFlyout
							frameworks={ frameworks }
							selected={ selectedFrameworks }
							onChange={ setSelectedFrameworks }
							summary={
								frameworkSummary
									? sprintf(
											/* translators: %s: comma-separated framework labels */
											__( 'Showing %s', 'complyops' ),
											frameworkSummary
									  )
									: __( 'All frameworks', 'complyops' )
							}
						/>
					) }
			</div>

			{ activeDetail ? (
				<DashboardDetailScreen
					detail={ activeDetail }
					audit={ activeTab === 'system' ? systemAudit : audit }
					findings={ findings }
					plan={ plan }
					discovery={ discovery }
					settings={ settings }
					canManage={ config.canManage }
					savingSettings={ savingSettings }
					onSaveSettings={ saveSettings }
					onBack={ () => setDetail( '' ) }
				/>
			) : (
				<>
					<NonCertificationNotice />
					{ activeTab === 'compliance' &&
						( ! audit ? (
							<EmptyState
								title={ __(
									'Establish your baseline',
									'complyops'
								) }
								description={
									selectedFrameworks.length
										? sprintf(
												/* translators: %s: comma-separated framework labels */
												__(
													'Run the first technical audit for %s to evaluate the controls ComplyOps can verify on this site.',
													'complyops'
												),
												complianceDetail
										  )
										: __(
												'Select at least one framework to evaluate compliance health.',
												'complyops'
										  )
								}
								icon="dashicons-chart-area"
								action={
									<DashboardAuditActions
										canRemediate={ false }
										canRunAudit={ config.canRunAudit }
										plan={ null }
										audit={ audit }
										applying={ applying }
										running={ running }
										onApply={ handleApply }
										onRun={ handleRun }
									/>
								}
							/>
						) : (
							<>
								<div className="complyops-metrics">
									<StatCard
										label={ __( 'Technical readiness', 'complyops' ) }
										value={ `${ audit.technical_score ?? audit.score ?? 0 }%` }
										detail={
											frameworkSummary ||
											__( 'Automatic controls', 'complyops' )
										}
										tone="success"
										icon="dashicons-chart-area"
										href={ detailUrl( 'readiness' ) }
									/>
									<StatCard
										label={ __( 'Evidence readiness', 'complyops' ) }
										value={ `${ audit.evidence_score ?? 0 }%` }
										detail={ __(
											'Human-attested controls',
											'complyops'
										) }
										icon="dashicons-clipboard"
										href={ detailUrl( 'readiness' ) }
									/>
									<StatCard
										label={ __( 'Legal review', 'complyops' ) }
										value={ `${ audit.legal_review_score ?? 0 }%` }
										detail={ __(
											'Legal and policy controls',
											'complyops'
										) }
										icon="dashicons-book"
										href={ detailUrl( 'readiness' ) }
									/>
									<StatCard
										label={ __(
											'Effective controls',
											'complyops'
										) }
										value={ `${
											audit.passed || 0
										} / ${ total }` }
										detail={ __(
											'Verified in the latest audit',
											'complyops'
										) }
										icon="dashicons-yes-alt"
										href={ detailUrl(
											'effective-controls'
										) }
									/>
									<StatCard
										label={ __(
											'Action required',
											'complyops'
										) }
										value={ findings.length }
										detail={ `${ urgent.length } ${ __(
											'high or critical',
											'complyops'
										) }` }
										tone={
											findings.length
												? 'warning'
												: 'success'
										}
										icon="dashicons-warning"
										href={ detailUrl( 'action-required' ) }
									/>
									<StatCard
										label={ __(
											'Manual review',
											'complyops'
										) }
										value={ audit.manual_review || 0 }
										detail={ __(
											'Checks requiring human judgment',
											'complyops'
										) }
										icon="dashicons-visibility"
										href={ detailUrl( 'manual-review' ) }
									/>
								</div>
								<div className="complyops-dashboard__hero">
									<ClickablePanel className="complyops-dashboard__hero-score">
										<a
											className="complyops-dashboard__hero-score-link"
											href={ detailUrl( 'readiness' ) }
										>
											<ScoreRing score={ audit.score } />
										</a>
										<DashboardAuditActions
											canRemediate={ config.canRemediate }
											canRunAudit={ config.canRunAudit }
											plan={ plan }
											audit={ audit }
											applying={ applying }
											running={ running }
											onApply={ handleApply }
											onRun={ handleRun }
										/>
									</ClickablePanel>
									<ClickablePanel
										href={ detailUrl( 'action-required' ) }
									>
										<SectionHeader
											title={ __(
												'Action required',
												'complyops'
											) }
											description={ __(
												'Start with the items that most affect technical readiness.',
												'complyops'
											) }
											action={
												<span>
													{ __(
														'View detail →',
														'complyops'
													) }
												</span>
											}
										/>
										{ urgent.length === 0 ? (
											<Alert tone="success">
												{ __(
													'No high or critical findings require attention.',
													'complyops'
												) }
											</Alert>
										) : (
											<ul className="complyops-list">
												{ urgent
													.slice( 0, 4 )
													.map( ( item ) => (
														<li
															key={ `${
																item.framework ||
																'gdpr'
															}-${
																item.control_id
															}` }
															className="complyops-list__row"
														>
															<div>
																<strong>
																	{
																		item.title
																	}
																</strong>
																<p>
																	{
																		item.observed
																	}
																</p>
															</div>
															<SeverityBadge
																severity={
																	item.severity
																}
															/>
														</li>
													) ) }
											</ul>
										) }
									</ClickablePanel>
								</div>
								<div className="complyops-grid">
									<ClickablePanel
										href={ detailUrl( 'control-health' ) }
									>
										<SectionHeader
											title={ __(
												'Control health',
												'complyops'
											) }
											description={ __(
												'Distribution from the latest technical audit.',
												'complyops'
											) }
											action={
												<span>
													{ __(
														'View detail →',
														'complyops'
													) }
												</span>
											}
										/>
										<ProgressBar
											label={ __(
												'Effective',
												'complyops'
											) }
											value={
												total
													? Math.round(
															( ( audit.passed ||
																0 ) /
																total ) *
																100
													  )
													: 0
											}
										/>
										<ul className="complyops-list">
											<li className="complyops-list__row">
												<span>
													{ __(
														'Warnings',
														'complyops'
													) }
												</span>
												<StatusBadge
													status="WARNING"
													label={ String(
														audit.warnings || 0
													) }
												/>
											</li>
											<li className="complyops-list__row">
												<span>
													{ __(
														'Failed',
														'complyops'
													) }
												</span>
												<StatusBadge
													status="FAIL"
													label={ String(
														audit.failed || 0
													) }
												/>
											</li>
											<li className="complyops-list__row">
												<span>
													{ __(
														'Not tested',
														'complyops'
													) }
												</span>
												<StatusBadge
													status="UNKNOWN"
													label={ String(
														audit.unknowns || 0
													) }
												/>
											</li>
											<li className="complyops-list__row">
												<span>
													{ __(
														'Manual review',
														'complyops'
													) }
												</span>
												<StatusBadge
													status="UNKNOWN"
													label={ String(
														audit.manual_review || 0
													) }
												/>
											</li>
										</ul>
									</ClickablePanel>
									<ClickablePanel
										href={ detailUrl(
											'recommended-controls'
										) }
									>
										<SectionHeader
											title={ __(
												'Recommended controls',
												'complyops'
											) }
											description={ __(
												'Safe, available actions calculated from this audit.',
												'complyops'
											) }
											action={
												<span>
													{ __(
														'View detail →',
														'complyops'
													) }
												</span>
											}
										/>
										{ ! plan?.automatic?.length ? (
											<p className="complyops-muted">
												{ __(
													'No automatic remediations are pending.',
													'complyops'
												) }
											</p>
										) : (
											<ul className="complyops-list">
												{ plan.automatic
													.slice( 0, 4 )
													.map( ( action ) => (
														<li
															key={
																action.action_id
															}
														>
															<strong>
																{ action.label }
															</strong>
															<p className="complyops-muted">
																{
																	action.description
																}
															</p>
														</li>
													) ) }
											</ul>
										) }
									</ClickablePanel>
								</div>
							</>
						) ) }

					{ activeTab === 'system' && (
						<>
							<div className="complyops-metrics">
								<StatCard
									label={ __( 'Monitoring', 'complyops' ) }
									value={ __( 'Active', 'complyops' ) }
									tone="success"
									icon="dashicons-visibility"
									href={ detailUrl( 'monitoring' ) }
								/>
								<StatCard
									label={ __(
										'Detected services',
										'complyops'
									) }
									value={ detectedCount }
									detail={ __(
										'Integrations discovered on this site',
										'complyops'
									) }
									icon="dashicons-admin-plugins"
									href={ detailUrl( 'detected-services' ) }
								/>
								<StatCard
									label={ __( 'Latest audit', 'complyops' ) }
									value={
										systemAudit
											? `${ systemAudit.score }%`
											: '—'
									}
									tone={
										systemAudit?.score >= 80
											? 'success'
											: systemAudit
											? 'warning'
											: undefined
									}
									icon="dashicons-chart-line"
									href={ detailUrl( 'latest-audit' ) }
								/>
								<StatCard
									label={ __( 'Open failures', 'complyops' ) }
									value={ systemAudit?.failed || 0 }
									tone={
										systemAudit?.failed
											? 'danger'
											: 'success'
									}
									icon="dashicons-warning"
									href={ detailUrl( 'open-failures' ) }
								/>
							</div>
							<div className="complyops-grid">
								<ClickablePanel
									href={ detailUrl( 'latest-audit' ) }
								>
									{ systemAudit ? (
										<>
											<ScoreRing
												score={ systemAudit.score }
											/>
											<p
												className="complyops-muted"
												style={ {
													textAlign: 'center',
												} }
											>
												{ __(
													'Last evaluated',
													'complyops'
												) }{ ' ' }
												{ formatDate(
													systemAudit.completed_at ||
														systemAudit.started_at
												) }
											</p>
										</>
									) : (
										<p className="complyops-muted">
											{ __(
												'Run an audit to establish compliance readiness.',
												'complyops'
											) }
										</p>
									) }
								</ClickablePanel>
								<SystemChecksPanel
									audit={ systemAudit }
									href={ detailUrl( 'system-checks' ) }
								/>
							</div>
						</>
					) }
				</>
			) }
		</div>
	);
}
