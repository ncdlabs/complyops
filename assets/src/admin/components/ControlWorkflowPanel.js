import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { updateControlApplicability, saveManualEvidence } from '../api';
import { useNotify } from './ui';

function needsApplicabilityConfirmation( finding ) {
	const type = String( finding?.verification_type || '' ).toUpperCase();

	return (
		type === 'L' || finding?.applicability?.requires_confirmation === true
	);
}

function needsManualEvidence( finding ) {
	const type = String( finding?.verification_type || '' ).toUpperCase();

	return type === 'H' || type === 'L';
}

function workflowControlId( item ) {
	return item?.control_id || item?.id || '';
}

export function ControlWorkflowPanel( { finding, canManage, onUpdated } ) {
	const notify = useNotify();
	const [ saving, setSaving ] = useState( false );
	const [ applicabilityState, setApplicabilityState ] = useState(
		finding?.applicability?.state || 'pending'
	);
	const [ applicabilityReason, setApplicabilityReason ] = useState(
		finding?.applicability?.reason || ''
	);
	const [ evidenceNote, setEvidenceNote ] = useState(
		finding?.manual_evidence?.note || ''
	);
	const [ evidenceStale, setEvidenceStale ] = useState(
		Boolean( finding?.manual_evidence?.stale )
	);

	if ( ! canManage || ! workflowControlId( finding ) ) {
		return null;
	}

	const controlId = workflowControlId( finding );
	const framework = finding.framework || 'gdpr';
	const showApplicability = needsApplicabilityConfirmation( finding );
	const showEvidence = needsManualEvidence( finding );

	if ( ! showApplicability && ! showEvidence ) {
		return null;
	}

	const saveApplicability = async () => {
		setSaving( true );

		try {
			const response = await updateControlApplicability( controlId, {
				framework,
				state: applicabilityState,
				reason: applicabilityReason,
			} );
			onUpdated?.( {
				applicability: response.applicability,
			} );
			notify.success( __( 'Applicability updated.', 'complyops' ) );
		} catch ( error ) {
			notify.error(
				error?.message ||
					__( 'Could not update applicability.', 'complyops' )
			);
		} finally {
			setSaving( false );
		}
	};

	const saveEvidence = async () => {
		setSaving( true );

		try {
			const record = await saveManualEvidence( {
				controlId,
				framework,
				note: evidenceNote,
			} );
			setEvidenceStale( false );
			onUpdated?.( {
				manual_evidence: record,
				status: record.status || 'PASS',
			} );
			notify.success( __( 'Manual evidence recorded.', 'complyops' ) );
		} catch ( error ) {
			notify.error(
				error?.message ||
					__( 'Could not save manual evidence.', 'complyops' )
			);
		} finally {
			setSaving( false );
		}
	};

	return (
		<div className="complyops-control-workflow">
			{ showApplicability && (
				<section className="complyops-finding-info-modal__section">
					<h3>{ __( 'Applicability', 'complyops' ) }</h3>
					<p className="complyops-muted">
						{ __(
							'Confirm whether this control applies before it can be evaluated.',
							'complyops'
						) }
					</p>
					<label className="complyops-field">
						<span>{ __( 'Status', 'complyops' ) }</span>
						<select
							value={ applicabilityState }
							onChange={ ( event ) =>
								setApplicabilityState( event.target.value )
							}
						>
							<option value="pending">
								{ __( 'Pending confirmation', 'complyops' ) }
							</option>
							<option value="applicable">
								{ __( 'Applicable', 'complyops' ) }
							</option>
							<option value="not_applicable">
								{ __( 'Not applicable', 'complyops' ) }
							</option>
						</select>
					</label>
					{ applicabilityState === 'not_applicable' && (
						<label className="complyops-field">
							<span>{ __( 'Reason', 'complyops' ) }</span>
							<textarea
								rows={ 3 }
								value={ applicabilityReason }
								onChange={ ( event ) =>
									setApplicabilityReason( event.target.value )
								}
							/>
						</label>
					) }
					<button
						type="button"
						className="button button-secondary"
						onClick={ saveApplicability }
						disabled={ saving }
					>
						{ __( 'Save applicability', 'complyops' ) }
					</button>
				</section>
			) }

			{ showEvidence && (
				<section className="complyops-finding-info-modal__section">
					<h3>{ __( 'Manual evidence', 'complyops' ) }</h3>
					<p className="complyops-muted">
						{ __(
							'Record attestation for human or legal controls. Stale evidence is flagged but does not auto-fail.',
							'complyops'
						) }
					</p>
					{ evidenceStale && (
						<p className="complyops-alert complyops-alert--warning">
							{ __(
								'Evidence review is overdue. Update attestation to restore the evidence score.',
								'complyops'
							) }
						</p>
					) }
					<label className="complyops-field">
						<span>{ __( 'Attestation note', 'complyops' ) }</span>
						<textarea
							rows={ 4 }
							value={ evidenceNote }
							onChange={ ( event ) =>
								setEvidenceNote( event.target.value )
							}
						/>
					</label>
					<button
						type="button"
						className="button button-secondary"
						onClick={ saveEvidence }
						disabled={ saving }
					>
						{ __( 'Save attestation', 'complyops' ) }
					</button>
				</section>
			) }
		</div>
	);
}
