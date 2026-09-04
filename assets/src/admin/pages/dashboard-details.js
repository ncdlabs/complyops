import { __ } from '@wordpress/i18n';
import {
	ScoreRing,
	StatusBadge,
	SectionHeader,
	ProgressBar,
	Alert,
	DetailBackLink,
	controlResultStatusLabel,
} from '../components/ui';
import { ControlResultsTable } from '../components/ControlResultsTable';
import { adminPageUrl } from '../url-state';

const MANUAL_CAPABILITIES = [
	'MANUAL_REVIEW',
	'LEGAL_REVIEW',
	'INFORMATIONAL',
];

function filterResults( results, predicate ) {
	return ( results || [] ).filter( predicate );
}

function StatusSection( { title, status, badgeStatus, rows } ) {
	if ( ! rows.length ) {
		return null;
	}

	return (
		<div className="complyops-detail-screen__section">
			<SectionHeader
				title={ title }
				action={
					<StatusBadge
						status={ badgeStatus || status }
						label={ String( rows.length ) }
					/>
				}
			/>
			<ControlResultsTable
				rows={ rows }
				emptyMessage=""
				statusLabel={ controlResultStatusLabel }
			/>
		</div>
	);
}

export function DashboardDetailScreen( {
	detail,
	audit,
	findings,
	plan,
	discovery,
	settings,
	canManage,
	savingSettings,
	onSaveSettings,
	onBack,
} ) {
	const results = audit?.results || [];
	const detected = Object.entries( discovery?.integrations || {} ).filter(
		( [ , item ] ) => item.detected
	);
	const auditRequired = ! [
		'monitoring',
		'detected-services',
		'system-checks',
	].includes( detail );

	if ( auditRequired && ! audit ) {
		return (
			<div className="complyops-detail-screen">
				<DetailBackLink onClick={ onBack } />
				<Alert tone="info">
					{ __( 'Run an audit to view this detail.', 'complyops' ) }
				</Alert>
			</div>
		);
	}

	const total = audit
		? ( audit.passed || 0 ) +
		  ( audit.failed || 0 ) +
		  ( audit.warnings || 0 ) +
		  ( audit.unknowns || 0 )
		: 0;

	let title = '';
	let description = '';
	let content = null;

	switch ( detail ) {
		case 'readiness':
			title = __( 'Readiness', 'complyops' );
			description = __(
				'Technical readiness score from the latest GDPR audit.',
				'complyops'
			);
			content = (
				<>
					<div className="complyops-dashboard__hero complyops-dashboard__hero--scores">
						<div className="complyops-panel">
							<SectionHeader
								title={ __(
									'Technical readiness',
									'complyops'
								) }
							/>
							<ScoreRing
								score={ audit.technical_score ?? audit.score }
							/>
						</div>
						<div className="complyops-panel">
							<SectionHeader
								title={ __(
									'Evidence readiness',
									'complyops'
								) }
							/>
							<ScoreRing score={ audit.evidence_score ?? 0 } />
						</div>
						<div className="complyops-panel">
							<SectionHeader
								title={ __(
									'Legal review readiness',
									'complyops'
								) }
							/>
							<ScoreRing
								score={ audit.legal_review_score ?? 0 }
							/>
						</div>
					</div>
					<div className="complyops-dashboard__hero">
						<div className="complyops-panel">
							<ScoreRing score={ audit.score } />
						</div>
						<div className="complyops-panel">
							<SectionHeader
								title={ __( 'Audit summary', 'complyops' ) }
							/>
							<ProgressBar
								label={ __( 'Effective', 'complyops' ) }
								value={
									total
										? Math.round(
												( ( audit.passed || 0 ) /
													total ) *
													100
										  )
										: 0
								}
							/>
							<ul className="complyops-list">
								<li className="complyops-list__row">
									<span>{ __( 'Passed', 'complyops' ) }</span>
									<StatusBadge
										status="PASS"
										label={ String( audit.passed || 0 ) }
									/>
								</li>
								<li className="complyops-list__row">
									<span>
										{ __( 'Warnings', 'complyops' ) }
									</span>
									<StatusBadge
										status="WARNING"
										label={ String( audit.warnings || 0 ) }
									/>
								</li>
								<li className="complyops-list__row">
									<span>{ __( 'Failed', 'complyops' ) }</span>
									<StatusBadge
										status="FAIL"
										label={ String( audit.failed || 0 ) }
									/>
								</li>
							</ul>
							<p>
								<a href={ adminPageUrl( 'complyops-reports' ) }>
									{ __(
										'Open executive report →',
										'complyops'
									) }
								</a>
							</p>
						</div>
					</div>
				</>
			);
			break;
		case 'effective-controls':
			title = __( 'Effective controls', 'complyops' );
			description = __(
				'Controls verified as effective in the latest audit.',
				'complyops'
			);
			content = (
				<ControlResultsTable
					rows={ filterResults(
						results,
						( item ) => item.status === 'PASS'
					) }
					emptyMessage={ __(
						'No effective controls were recorded in the latest audit.',
						'complyops'
					) }
					statusLabel={ controlResultStatusLabel }
				/>
			);
			break;
		case 'action-required':
			title = __( 'Action required', 'complyops' );
			description = __(
				'Findings that still need attention from the latest audit.',
				'complyops'
			);
			content = (
				<>
					<ControlResultsTable
						rows={ findings }
						emptyMessage={ __(
							'No findings require attention.',
							'complyops'
						) }
						statusLabel={ controlResultStatusLabel }
					/>
					<p>
						<a
							href={ adminPageUrl( 'complyops-audit', {
								tab: 'findings',
							} ) }
						>
							{ __( 'Open findings workspace →', 'complyops' ) }
						</a>
					</p>
				</>
			);
			break;
		case 'manual-review':
			title = __( 'Manual review', 'complyops' );
			description = __(
				'Controls that require human judgment rather than automatic verification.',
				'complyops'
			);
			content = (
				<ControlResultsTable
					rows={ filterResults( results, ( item ) =>
						MANUAL_CAPABILITIES.includes(
							String( item.capability || '' ).toUpperCase()
						)
					) }
					emptyMessage={ __(
						'No manual review controls were evaluated in the latest audit.',
						'complyops'
					) }
					statusLabel={ controlResultStatusLabel }
				/>
			);
			break;
		case 'control-health':
			title = __( 'Control health', 'complyops' );
			description = __(
				'Latest audit results grouped by control status.',
				'complyops'
			);
			content = (
				<>
					<StatusSection
						title={ __( 'Warnings', 'complyops' ) }
						status="WARNING"
						rows={ filterResults(
							results,
							( item ) => item.status === 'WARNING'
						) }
					/>
					<StatusSection
						title={ __( 'Failed', 'complyops' ) }
						status="FAIL"
						rows={ filterResults(
							results,
							( item ) => item.status === 'FAIL'
						) }
					/>
					<StatusSection
						title={ __( 'Not tested', 'complyops' ) }
						status="UNKNOWN"
						rows={ filterResults(
							results,
							( item ) => item.status === 'UNKNOWN'
						) }
					/>
					<StatusSection
						title={ __( 'Manual review', 'complyops' ) }
						status="UNKNOWN"
						rows={ filterResults( results, ( item ) =>
							MANUAL_CAPABILITIES.includes(
								String( item.capability || '' ).toUpperCase()
							)
						) }
					/>
				</>
			);
			break;
		case 'recommended-controls':
			title = __( 'Recommended controls', 'complyops' );
			description = __(
				'Safe automatic remediations calculated from the latest audit.',
				'complyops'
			);
			content = ! plan?.automatic?.length ? (
				<p className="complyops-muted">
					{ __(
						'No automatic remediations are pending.',
						'complyops'
					) }
				</p>
			) : (
				<div className="complyops-panel">
					<ul className="complyops-list">
						{ plan.automatic.map( ( action ) => (
							<li key={ action.action_id }>
								<strong>{ action.label }</strong>
								<p className="complyops-muted">
									{ action.description }
								</p>
							</li>
						) ) }
					</ul>
				</div>
			);
			break;
		case 'monitoring':
			title = __( 'Monitoring', 'complyops' );
			description = __(
				'Scheduled audits, drift detection, and optional public status publishing.',
				'complyops'
			);
			content = (
				<>
					<div className="complyops-panel">
						<ul className="complyops-list">
							<li className="complyops-list__row">
								<span>
									{ __(
										'Monitoring scheduler',
										'complyops'
									) }
								</span>
								<StatusBadge
									status={
										settings?.monitoring_interval ===
										'disabled'
											? 'UNKNOWN'
											: 'PASS'
									}
									label={
										settings?.monitoring_interval ===
										'disabled'
											? __( 'Disabled', 'complyops' )
											: __( 'Active', 'complyops' )
									}
								/>
							</li>
							<li className="complyops-list__row">
								<span>
									{ __( 'Drift detection', 'complyops' ) }
								</span>
								<StatusBadge
									status="PASS"
									label={ __( 'Available', 'complyops' ) }
								/>
							</li>
							<li className="complyops-list__row">
								<span>
									{ __( 'Public status page', 'complyops' ) }
								</span>
								<StatusBadge
									status={
										settings?.public_status?.page_enabled
											? 'PASS'
											: 'UNKNOWN'
									}
									label={
										settings?.public_status?.page_enabled
											? __( 'Published', 'complyops' )
											: __( 'Disabled', 'complyops' )
									}
								/>
							</li>
							<li className="complyops-list__row">
								<span>
									{ __( 'Public status API', 'complyops' ) }
								</span>
								<StatusBadge
									status={
										settings?.public_status?.api_enabled
											? 'PASS'
											: 'UNKNOWN'
									}
									label={
										settings?.public_status?.api_enabled
											? __( 'Published', 'complyops' )
											: __( 'Disabled', 'complyops' )
									}
								/>
							</li>
						</ul>
					</div>
					{ canManage && (
						<div className="complyops-panel">
							<SectionHeader
								title={ __(
									'Monitoring settings',
									'complyops'
								) }
							/>
							<label className="complyops-field">
								<span>
									{ __( 'Monitoring interval', 'complyops' ) }
								</span>
								<select
									value={
										settings?.monitoring_interval ||
										'weekly'
									}
									disabled={ savingSettings }
									onChange={ ( event ) =>
										onSaveSettings( {
											monitoring_interval:
												event.target.value,
										} )
									}
								>
									<option value="disabled">
										{ __( 'Disabled', 'complyops' ) }
									</option>
									<option value="daily">
										{ __( 'Daily', 'complyops' ) }
									</option>
									<option value="weekly">
										{ __( 'Weekly', 'complyops' ) }
									</option>
									<option value="monthly">
										{ __( 'Monthly', 'complyops' ) }
									</option>
								</select>
							</label>
							<label className="complyops-field complyops-field--checkbox">
								<input
									type="checkbox"
									checked={
										!! settings?.public_status?.page_enabled
									}
									disabled={ savingSettings }
									onChange={ ( event ) =>
										onSaveSettings( {
											public_status: {
												...( settings?.public_status ||
													{} ),
												page_enabled:
													event.target.checked,
											},
										} )
									}
								/>
								<span>
									{ __(
										'Publish public compliance status page',
										'complyops'
									) }
								</span>
							</label>
							{ settings?.public_status?.page_enabled && (
								<p className="complyops-muted">
									<a
										href={ settings.public_status.page_url }
										target="_blank"
										rel="noreferrer"
									>
										{ settings.public_status.page_url }
									</a>
								</p>
							) }
							<label className="complyops-field complyops-field--checkbox">
								<input
									type="checkbox"
									checked={
										!! settings?.public_status?.api_enabled
									}
									disabled={ savingSettings }
									onChange={ ( event ) =>
										onSaveSettings( {
											public_status: {
												...( settings?.public_status ||
													{} ),
												api_enabled:
													event.target.checked,
											},
										} )
									}
								/>
								<span>
									{ __(
										'Publish public compliance status API',
										'complyops'
									) }
								</span>
							</label>
							{ settings?.public_status?.api_enabled && (
								<p className="complyops-muted">
									<code>
										{ settings.public_status.api_url }
									</code>
								</p>
							) }
							<p className="complyops-muted">
								{ __(
									'Public status exposes only administrator-approved, non-sensitive fields after a completed audit.',
									'complyops'
								) }
							</p>
						</div>
					) }
					<p>
						<a href={ adminPageUrl( 'complyops-evidence' ) }>
							{ __(
								'Open evidence and activity log →',
								'complyops'
							) }
						</a>
					</p>
				</>
			);
			break;
		case 'detected-services':
			title = __( 'Detected services', 'complyops' );
			description = __(
				'Integrations discovered on this WordPress site.',
				'complyops'
			);
			content = (
				<>
					{ detected.length === 0 ? (
						<p className="complyops-muted">
							{ __(
								'No third-party integrations were detected.',
								'complyops'
							) }
						</p>
					) : (
						<div className="complyops-panel">
							<ul className="complyops-list">
								{ detected.map( ( [ key, item ] ) => (
									<li
										key={ key }
										className="complyops-list__row"
									>
										<div>
											<strong>
												{ item.label || key }
											</strong>
											<p className="complyops-muted">
												{ item.summary ||
													item.type ||
													key }
											</p>
										</div>
										<StatusBadge
											status="PASS"
											label={ __(
												'Detected',
												'complyops'
											) }
										/>
									</li>
								) ) }
							</ul>
						</div>
					) }
					<p>
						<a href={ adminPageUrl( 'complyops-integrations' ) }>
							{ __( 'Open integrations →', 'complyops' ) }
						</a>
					</p>
				</>
			);
			break;
		case 'latest-audit':
			title = __( 'Latest audit', 'complyops' );
			description = __(
				'Point-in-time results from the most recent technical audit.',
				'complyops'
			);
			content = audit ? (
				<>
					<div className="complyops-panel">
						<SectionHeader
							title={ `${ __( 'Audit', 'complyops' ) } #${
								audit.id
							}` }
							description={
								audit.completed_at || audit.started_at
							}
						/>
						<ul className="complyops-list">
							<li className="complyops-list__row">
								<span>{ __( 'Score', 'complyops' ) }</span>
								<strong>{ audit.score ?? 0 }%</strong>
							</li>
							<li className="complyops-list__row">
								<span>{ __( 'Passed', 'complyops' ) }</span>
								<StatusBadge
									status="PASS"
									label={ String( audit.passed || 0 ) }
								/>
							</li>
							<li className="complyops-list__row">
								<span>{ __( 'Failed', 'complyops' ) }</span>
								<StatusBadge
									status="FAIL"
									label={ String( audit.failed || 0 ) }
								/>
							</li>
						</ul>
					</div>
					<ControlResultsTable
						rows={ results }
						emptyMessage={ __(
							'No control results were recorded.',
							'complyops'
						) }
						statusLabel={ controlResultStatusLabel }
					/>
					<p>
						<a
							href={ adminPageUrl( 'complyops-audit', {
								tab: 'history',
							} ) }
						>
							{ __( 'Open audit history →', 'complyops' ) }
						</a>
					</p>
				</>
			) : (
				<Alert tone="info">
					{ __(
						'Run an audit to establish compliance readiness.',
						'complyops'
					) }
				</Alert>
			);
			break;
		case 'open-failures':
			title = __( 'Open failures', 'complyops' );
			description = __(
				'Controls that failed verification in the latest audit.',
				'complyops'
			);
			content = (
				<>
					<ControlResultsTable
						rows={ filterResults(
							results,
							( item ) => item.status === 'FAIL'
						) }
						emptyMessage={ __(
							'No failed controls in the latest audit.',
							'complyops'
						) }
						statusLabel={ controlResultStatusLabel }
					/>
					<p>
						<a
							href={ adminPageUrl( 'complyops-audit', {
								tab: 'findings',
							} ) }
						>
							{ __( 'Open findings workspace →', 'complyops' ) }
						</a>
					</p>
				</>
			);
			break;
		case 'system-checks':
			title = __( 'System checks', 'complyops' );
			description = __(
				'Operational signals available to ComplyOps.',
				'complyops'
			);
			content = (
				<div className="complyops-panel">
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
							<span>
								{ __( 'Technical audit engine', 'complyops' ) }
							</span>
							<StatusBadge
								status={ audit ? 'PASS' : 'UNKNOWN' }
								label={
									audit
										? __( 'Reporting', 'complyops' )
										: __(
												'Awaiting first run',
												'complyops'
										  )
								}
							/>
						</li>
						<li className="complyops-list__row">
							<span>
								{ __( 'Integration discovery', 'complyops' ) }
							</span>
							<StatusBadge
								status="PASS"
								label={ __( 'Active', 'complyops' ) }
							/>
						</li>
					</ul>
				</div>
			);
			break;
		default:
			return null;
	}

	return (
		<div className="complyops-detail-screen">
			<DetailBackLink onClick={ onBack } />
			<SectionHeader title={ title } description={ description } />
			{ content }
		</div>
	);
}

export const COMPLIANCE_DETAILS = [
	'readiness',
	'effective-controls',
	'action-required',
	'manual-review',
	'control-health',
	'recommended-controls',
];

export const SYSTEM_DETAILS = [
	'monitoring',
	'detected-services',
	'latest-audit',
	'open-failures',
	'system-checks',
];
