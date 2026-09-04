import { useEffect, useState, useCallback, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getFindings, applyControlRemediation } from '../api';
import { Dialog } from '../components/Dialog';
import { ControlWorkflowPanel } from '../components/ControlWorkflowPanel';
import {
	ControlResultsTable,
	controlNeedsRemediation,
} from '../components/ControlResultsTable';
import {
	StatusBadge,
	SeverityBadge,
	LoadingState,
	ErrorState,
	useNotify,
	auditResultStatusLabel,
	HumanReviewIcon,
	requiresHumanReview,
} from '../components/ui';

function findingFailureSectionTitle( status ) {
	const normalized = String( status || '' ).toUpperCase();

	if ( normalized === 'WARNING' ) {
		return __( 'Why this needs attention', 'complyops' );
	}

	if ( normalized === 'UNKNOWN' ) {
		return __( 'Why this could not be verified', 'complyops' );
	}

	return __( 'Why this failed', 'complyops' );
}

function FindingMoreInfoDialog( { finding, onClose, canManage, onUpdated } ) {
	const controlDescription =
		finding?.control_description ||
		finding?.explanation ||
		finding?.more_info ||
		'';
	const failureExplanation = finding?.failure_explanation || '';
	const targetState = finding?.expected || finding?.recommended || '';
	const showFailureDetails = controlNeedsRemediation( finding );
	const status = String( finding?.status || '' ).toUpperCase();

	return (
		<Dialog
			isOpen={ !! finding }
			onClose={ onClose }
			title={ finding?.title || __( 'Finding details', 'complyops' ) }
			size="finding"
			footer={
				<div className="complyops-dialog__footer-actions complyops-dialog__footer-actions--end">
					{ showFailureDetails && finding?.resolve_url && (
						<a
							className="button button-primary"
							href={ finding.resolve_url }
						>
							{ finding.resolve_label ||
								__( 'Review & fix', 'complyops' ) }
						</a>
					) }
					<button
						type="button"
						className="button button-secondary"
						onClick={ onClose }
					>
						{ __( 'Close', 'complyops' ) }
					</button>
				</div>
			}
		>
			<div className="complyops-dialog__body complyops-finding-info-modal">
				<div className="complyops-finding-info-modal__meta">
					<code>{ finding?.control_id }</code>
					{ finding?.status && (
						<StatusBadge
							status={ finding.status }
							label={ auditResultStatusLabel( finding ) }
						/>
					) }
					{ finding?.severity && (
						<SeverityBadge severity={ finding.severity } />
					) }
					{ requiresHumanReview( finding?.capability ) && (
						<HumanReviewIcon />
					) }
				</div>

				{ controlDescription && (
					<section className="complyops-finding-info-modal__section">
						<h3>{ __( 'About this control', 'complyops' ) }</h3>
						<p>{ controlDescription }</p>
					</section>
				) }

				{ showFailureDetails && failureExplanation && (
					<section className="complyops-finding-info-modal__callout complyops-finding-info-modal__callout--failure">
						<h3>{ findingFailureSectionTitle( status ) }</h3>
						<p>{ failureExplanation }</p>
					</section>
				) }

				{ showFailureDetails && finding?.observed && (
					<section className="complyops-finding-info-modal__callout complyops-finding-info-modal__callout--observed">
						<h3>{ __( 'Observed on this site', 'complyops' ) }</h3>
						<p>{ finding.observed }</p>
					</section>
				) }

				{ showFailureDetails && targetState && (
					<section className="complyops-finding-info-modal__callout complyops-finding-info-modal__callout--target">
						<h3>
							{ finding?.expected
								? __( 'Expected', 'complyops' )
								: __( 'Recommended', 'complyops' ) }
						</h3>
						<p>{ targetState }</p>
					</section>
				) }

				{ showFailureDetails && finding?.remediation_summary && (
					<section className="complyops-finding-info-modal__callout complyops-finding-info-modal__callout--remediation">
						<h3>{ __( 'Apply fix', 'complyops' ) }</h3>
						<p>{ finding.remediation_summary }</p>
					</section>
				) }

				{ showFailureDetails && finding?.test_output && (
					<section className="complyops-finding-info-modal__section complyops-finding-info-modal__section--output">
						<h3>{ __( 'Test output', 'complyops' ) }</h3>
						<pre className="complyops-finding-info-modal__output">
							{ finding.test_output }
						</pre>
					</section>
				) }

				<ControlWorkflowPanel
					finding={ finding }
					canManage={ canManage }
					onUpdated={ onUpdated }
				/>
			</div>
		</Dialog>
	);
}

import { useUrlParam } from '../url-state';

const FINDINGS_FILTERS = [ 'fail', 'pass', 'warning', 'all' ];

function filterFindings( findings, filter ) {
	switch ( filter ) {
		case 'all':
			return findings;
		case 'pass':
			return findings.filter(
				( item ) => String( item.status ).toUpperCase() === 'PASS'
			);
		case 'warning':
			return findings.filter(
				( item ) => String( item.status ).toUpperCase() === 'WARNING'
			);
		default:
			return findings.filter(
				( item ) => String( item.status ).toUpperCase() === 'FAIL'
			);
	}
}

function emptyFilterMessage( filter ) {
	switch ( filter ) {
		case 'pass':
			return __( 'No passed controls in the latest audit.', 'complyops' );
		case 'warning':
			return __( 'No warnings in the latest audit.', 'complyops' );
		case 'all':
			return __( 'No control results were recorded.', 'complyops' );
		default:
			return __( 'No failed controls in the latest audit.', 'complyops' );
	}
}

function FindingsScopeToggle( { filter, onChange } ) {
	return (
		<div className="complyops-findings-toolbar">
			<div
				className="complyops-findings-scope"
				role="group"
				aria-label={ __( 'Results filter', 'complyops' ) }
			>
				<button
					type="button"
					aria-pressed={ filter === 'fail' }
					onClick={ () => onChange( 'fail' ) }
				>
					{ __( 'Failed only', 'complyops' ) }
				</button>
				<button
					type="button"
					aria-pressed={ filter === 'all' }
					onClick={ () => onChange( 'all' ) }
				>
					{ __( 'All results', 'complyops' ) }
				</button>
			</div>
		</div>
	);
}

export function FindingsPanel( { config, refreshKey = null } ) {
	const [ findings, setFindings ] = useState( [] );
	const [ auditId, setAuditId ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ applying, setApplying ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ infoFinding, setInfoFinding ] = useState( null );
	const [ filter, setFilter ] = useUrlParam(
		'filter',
		FINDINGS_FILTERS,
		'fail'
	);
	const notify = useNotify();

	const visibleFindings = useMemo(
		() => filterFindings( findings, filter ),
		[ findings, filter ]
	);

	const load = useCallback( async () => {
		setLoading( true );
		setError( null );

		try {
			const data = await getFindings( null, 'gdpr' );
			setFindings( data.findings || [] );
			setAuditId( data.audit_id || null );
		} catch ( err ) {
			if (
				err?.code === 'complyops_no_audit' ||
				err?.data?.status === 404
			) {
				setFindings( [] );
			} else {
				setError(
					err?.message ||
						__( 'Failed to load findings.', 'complyops' )
				);
			}
		} finally {
			setLoading( false );
		}
	}, [] );

	useEffect( () => {
		load();
	}, [ load, refreshKey ] );

	const applyFix = async ( controlId ) => {
		if ( ! config?.canRemediate ) {
			return;
		}

		setApplying( controlId );
		setError( null );

		try {
			const response = await applyControlRemediation(
				controlId,
				auditId
			);
			const applied = Array.isArray( response?.applied )
				? response.applied
				: [];
			const verification =
				applied.find( ( item ) => item.control_id === controlId ) ||
				applied[ 0 ];
			const passed =
				String( verification?.control_status || '' ).toUpperCase() ===
				'PASS';

			if ( passed ) {
				setFindings( ( current ) =>
					current.map( ( item ) => {
						if ( item.control_id !== controlId ) {
							return item;
						}

						return {
							...item,
							status: 'PASS',
							observed: verification?.observed ?? item.observed,
							remediation_available: false,
						};
					} )
				);
				notify.success(
					__(
						'Remediation applied. Control passed verification.',
						'complyops'
					)
				);
			} else {
				notify.success(
					__(
						'Remediation applied. The control still needs attention.',
						'complyops'
					)
				);
				await load();
			}
		} catch ( err ) {
			notify.error(
				err?.message || __( 'Remediation failed.', 'complyops' )
			);
		} finally {
			setApplying( null );
		}
	};

	if ( loading ) {
		return (
			<LoadingState message={ __( 'Loading findings…', 'complyops' ) } />
		);
	}

	if ( error && findings.length === 0 ) {
		return <ErrorState message={ error } onRetry={ load } />;
	}

	return (
		<div className="complyops-findings-page">
			{ auditId ? (
				<p className="complyops-muted complyops-findings-page__intro">
					{ `${ __( 'Audit #', 'complyops' ) }${ auditId } · ${ __(
						'Control results from the latest audit.',
						'complyops'
					) }` }
				</p>
			) : (
				<p className="complyops-muted complyops-findings-page__intro">
					{ __(
						'Control results from the latest audit.',
						'complyops'
					) }
				</p>
			) }

			{ findings.length === 0 ? (
				<p className="complyops-muted">
					{ __(
						'No findings. Run an audit to generate results.',
						'complyops'
					) }
				</p>
			) : (
				<>
					<FindingsScopeToggle
						filter={ filter }
						onChange={ setFilter }
					/>
					{ ( filter === 'pass' || filter === 'warning' ) && (
						<p className="complyops-muted complyops-findings-page__filter-note">
							{ filter === 'pass'
								? __( 'Showing passed controls.', 'complyops' )
								: __( 'Showing warnings.', 'complyops' ) }
						</p>
					) }
					{ visibleFindings.length === 0 ? (
						<p className="complyops-muted">
							{ emptyFilterMessage( filter ) }
						</p>
					) : (
						<ControlResultsTable
							rows={ visibleFindings }
							emptyMessage={ __(
								'No control results were recorded.',
								'complyops'
							) }
							statusLabel={ auditResultStatusLabel }
							remediation={ {
								applying: config?.canRemediate
									? applying
									: null,
								onApply: config?.canRemediate ? applyFix : null,
								onMoreInfo: setInfoFinding,
							} }
						/>
					) }
				</>
			) }
			<FindingMoreInfoDialog
				finding={ infoFinding }
				onClose={ () => setInfoFinding( null ) }
				canManage={ Boolean( config?.canManage ) }
				onUpdated={ ( patch ) => {
					if ( ! infoFinding?.control_id ) {
						return;
					}

					setFindings( ( current ) =>
						current.map( ( item ) =>
							item.control_id === infoFinding.control_id
								? { ...item, ...patch }
								: item
						)
					);
					setInfoFinding( ( current ) =>
						current ? { ...current, ...patch } : current
					);
				} }
			/>
		</div>
	);
}
