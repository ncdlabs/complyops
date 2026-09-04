import { __ } from '@wordpress/i18n';
import {
	HumanReviewIcon,
	SeverityBadge,
	StatusBadge,
	auditResultStatusLabel,
	requiresHumanReview,
} from './ui';

function controlDescription( item ) {
	return (
		item.control_description ||
		item.explanation ||
		item.more_info ||
		item.title ||
		''
	);
}

export function controlNeedsRemediation( item ) {
	const status = String( item?.status || '' ).toUpperCase();

	return status !== 'PASS' && status !== 'NOT_APPLICABLE';
}

function ControlIdCell( { item } ) {
	return (
		<div className="complyops-table__control-id">
			<code>{ item.control_id }</code>
			{ requiresHumanReview( item.capability ) && <HumanReviewIcon /> }
		</div>
	);
}

function DescriptionCell( { value } ) {
	const text = String( value || '' ).trim();

	if ( ! text ) {
		return (
			<span
				className="complyops-table__description complyops-table__description--empty"
				aria-hidden="true"
			>
				—
			</span>
		);
	}

	return (
		<span className="complyops-table__description" title={ text }>
			{ text }
		</span>
	);
}

function ObservedCell( { value } ) {
	const text = String( value || '' ).trim();

	if ( ! text ) {
		return (
			<span
				className="complyops-table__observed complyops-table__observed--empty"
				aria-hidden="true"
			>
				—
			</span>
		);
	}

	return (
		<span className="complyops-table__observed" title={ text }>
			{ text }
		</span>
	);
}

function RemediationActions( { item, applying, onApply, onMoreInfo } ) {
	const manualReviewLabel = __( 'Manual review', 'complyops' );
	const showRemediation = controlNeedsRemediation( item );

	if ( ! showRemediation ) {
		return item.control_description ||
			item.explanation ||
			item.more_info ||
			item.failure_explanation ? (
			<div className="complyops-findings-remediation">
				<button
					type="button"
					className="button-link complyops-findings-remediation__more"
					onClick={ () => onMoreInfo( item ) }
				>
					{ __( 'More Info', 'complyops' ) }
				</button>
			</div>
		) : null;
	}

	return (
		<div className="complyops-findings-remediation">
			{ item.remediation_available && onApply ? (
				<button
					type="button"
					className="button-link complyops-findings-remediation__apply"
					onClick={ () => onApply( item.control_id ) }
					disabled={ applying === item.control_id }
				>
					{ applying === item.control_id
						? __( 'Applying…', 'complyops' )
						: __( 'Apply Fix', 'complyops' ) }
				</button>
			) : item.resolve_url ? (
				<a
					className="complyops-findings-remediation__manual complyops-findings-remediation__manual--link"
					href={ item.resolve_url }
				>
					{ requiresHumanReview( item.capability ) && (
						<HumanReviewIcon />
					) }
					<span>{ item.resolve_label || manualReviewLabel }</span>
				</a>
			) : (
				<button
					type="button"
					className="complyops-findings-remediation__manual complyops-findings-remediation__manual--link"
					onClick={ () => onMoreInfo( item ) }
				>
					{ requiresHumanReview( item.capability ) && (
						<HumanReviewIcon />
					) }
					<span>{ manualReviewLabel }</span>
				</button>
			) }
			{ ( item.control_description ||
				item.explanation ||
				item.more_info ||
				item.failure_explanation ) && (
				<button
					type="button"
					className="button-link complyops-findings-remediation__more"
					onClick={ () => onMoreInfo( item ) }
				>
					{ __( 'More Info', 'complyops' ) }
				</button>
			) }
		</div>
	);
}

function ControlResultRow( { item, statusLabel, remediation } ) {
	const resolveStatusLabel = statusLabel || auditResultStatusLabel;

	return (
		<tr>
			<td className="complyops-table__cell complyops-table__cell--control">
				<ControlIdCell item={ item } />
			</td>
			<td className="complyops-table__cell complyops-table__cell--description">
				<DescriptionCell value={ controlDescription( item ) } />
			</td>
			<td className="complyops-table__cell complyops-table__cell--status">
				<StatusBadge
					status={ item.status }
					label={ resolveStatusLabel( item ) }
				/>
			</td>
			<td className="complyops-table__cell complyops-table__cell--observed">
				<ObservedCell value={ item.observed } />
			</td>
			<td className="complyops-table__cell complyops-table__cell--severity">
				<SeverityBadge severity={ item.severity } />
			</td>
			{ remediation && (
				<td className="complyops-table__cell complyops-table__cell--actions">
					<RemediationActions
						item={ item }
						applying={ remediation.applying }
						onApply={ remediation.onApply }
						onMoreInfo={ remediation.onMoreInfo }
					/>
				</td>
			) }
		</tr>
	);
}

export function ControlResultsTable( {
	rows,
	emptyMessage,
	statusLabel,
	remediation,
} ) {
	if ( ! rows.length ) {
		return <p className="complyops-muted">{ emptyMessage }</p>;
	}

	return (
		<div className="complyops-panel complyops-panel--flush">
			<div className="complyops-table-wrap">
				<table className="widefat striped complyops-table complyops-table--control-results">
					<thead>
						<tr>
							<th>{ __( 'Control', 'complyops' ) }</th>
							<th>{ __( 'Description', 'complyops' ) }</th>
							<th>{ __( 'Status', 'complyops' ) }</th>
							<th>{ __( 'Observed', 'complyops' ) }</th>
							<th>{ __( 'Severity', 'complyops' ) }</th>
							{ remediation && (
								<th>{ __( 'Remediation', 'complyops' ) }</th>
							) }
						</tr>
					</thead>
					<tbody>
						{ rows.map( ( item ) => (
							<ControlResultRow
								key={ item.control_id }
								item={ item }
								statusLabel={ statusLabel }
								remediation={ remediation }
							/>
						) ) }
					</tbody>
				</table>
			</div>
		</div>
	);
}
