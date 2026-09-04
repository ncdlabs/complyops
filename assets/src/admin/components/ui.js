import { __ } from '@wordpress/i18n';
import NotificationBell from './NotificationBell';

const STATUS_LABELS = {
	PASS: [ 'success', __( 'Passed', 'complyops' ) ],
	FAIL: [ 'danger', __( 'Failed', 'complyops' ) ],
	WARNING: [ 'warning', __( 'Attention needed', 'complyops' ) ],
	UNKNOWN: [ 'neutral', __( 'Not tested', 'complyops' ) ],
	INFO: [ 'info', __( 'Informational', 'complyops' ) ],
	NOT_APPLICABLE: [ 'neutral', __( 'Not applicable', 'complyops' ) ],
	COMPLETED: [ 'success', __( 'Complete', 'complyops' ) ],
	RUNNING: [ 'info', __( 'Running', 'complyops' ) ],
};

export function StatusBadge( { status, label } ) {
	const normalized = String( status || 'UNKNOWN' ).toUpperCase();
	const [ tone, defaultLabel ] = STATUS_LABELS[ normalized ] || [
		'neutral',
		normalized.replaceAll( '_', ' ' ),
	];
	return (
		<span className={ `complyops-badge complyops-badge--${ tone }` }>
			<span aria-hidden="true" />
			{ label || defaultLabel }
		</span>
	);
}

const MANUAL_REVIEW_CAPABILITIES = new Set( [
	'MANUAL_REVIEW',
	'LEGAL_REVIEW',
] );

export function requiresHumanReview( capability ) {
	return MANUAL_REVIEW_CAPABILITIES.has(
		String( capability || '' ).toUpperCase()
	);
}

export function HumanReviewIcon( { label } ) {
	const text = label || __( 'Requires manual review', 'complyops' );

	return (
		<span
			className="complyops-human-review-icon"
			title={ text }
			aria-label={ text }
		>
			<span
				className="dashicons dashicons-admin-users"
				aria-hidden="true"
			/>
		</span>
	);
}

export function ControlTitle( {
	title,
	controlId,
	capability,
	muted,
	children,
} ) {
	return (
		<div className="complyops-control-title">
			<div className="complyops-control-title__heading">
				<span className="complyops-table__primary">{ title }</span>
				{ requiresHumanReview( capability ) && <HumanReviewIcon /> }
			</div>
			{ controlId && (
				<div className="complyops-control-title__meta">
					<code>{ controlId }</code>
				</div>
			) }
			{ muted && (
				<div className="complyops-control-title__muted complyops-muted">
					{ muted }
				</div>
			) }
			{ children }
		</div>
	);
}

/**
 * Maps audit result rows to human-readable status labels.
 * @param {Object} item Audit result row.
 */
export function controlResultStatusLabel( item ) {
	const capability = String( item?.capability || '' ).toUpperCase();

	if ( MANUAL_REVIEW_CAPABILITIES.has( capability ) ) {
		return __( 'Manual review', 'complyops' );
	}

	if ( capability === 'INFORMATIONAL' ) {
		return __( 'Informational', 'complyops' );
	}

	return undefined;
}

const AUDIT_RESULT_STATUS_LABELS = {
	PASS: __( 'Passed', 'complyops' ),
	FAIL: __( 'Failed', 'complyops' ),
	WARNING: __( 'Warning', 'complyops' ),
	UNKNOWN: __( 'Not tested', 'complyops' ),
	INFO: __( 'Informational', 'complyops' ),
	NOT_APPLICABLE: __( 'Not applicable', 'complyops' ),
};

/**
 * Audit-oriented status labels for findings and result tables.
 * @param {Object} item Audit result row.
 */
export function auditResultStatusLabel( item ) {
	const capabilityLabel = controlResultStatusLabel( item );

	if ( capabilityLabel ) {
		return capabilityLabel;
	}

	const status = String( item?.status || 'UNKNOWN' ).toUpperCase();

	return (
		AUDIT_RESULT_STATUS_LABELS[ status ] || status.replaceAll( '_', ' ' )
	);
}

export function SeverityBadge( { severity } ) {
	const value = String( severity || 'INFO' ).toUpperCase();
	const tone =
		value === 'CRITICAL' || value === 'HIGH'
			? 'danger'
			: value === 'MEDIUM'
			? 'warning'
			: value === 'LOW'
			? 'info'
			: 'neutral';
	return (
		<span className={ `complyops-badge complyops-badge--${ tone }` }>
			<span aria-hidden="true" />
			{ value.charAt( 0 ) + value.slice( 1 ).toLowerCase() }
		</span>
	);
}

export function ScoreRing( {
	score,
	label = __( 'Technical readiness', 'complyops' ),
} ) {
	const value = Math.max( 0, Math.min( 100, Number( score ) || 0 ) );
	return (
		<div
			className="complyops-score-ring"
			style={ { '--score': `${ value * 3.6 }deg` } }
			aria-label={ `${ label }: ${ value }%` }
		>
			<div>
				<strong>{ value }%</strong>
				<span>{ label }</span>
			</div>
		</div>
	);
}

export function StatCard( {
	label,
	value,
	tone = 'default',
	detail,
	icon,
	href,
	onClick,
	centered = false,
} ) {
	const interactive = !! href || !! onClick;
	const className = `complyops-stat-card complyops-stat-card--${ tone }${
		interactive ? ' complyops-stat-card--interactive' : ''
	}${ centered ? ' complyops-stat-card--centered' : '' }`;
	const content = (
		<>
			<div className="complyops-stat-card__top">
				{ icon && (
					<span
						className={ `dashicons ${ icon }` }
						aria-hidden="true"
					/>
				) }
				<span>{ label }</span>
			</div>
			<strong className="complyops-stat-card__value">{ value }</strong>
			{ detail && (
				<span className="complyops-stat-card__detail">{ detail }</span>
			) }
		</>
	);

	if ( href ) {
		return (
			<a className={ className } href={ href }>
				{ content }
			</a>
		);
	}

	if ( onClick ) {
		return (
			<button type="button" className={ className } onClick={ onClick }>
				{ content }
			</button>
		);
	}

	return <div className={ className }>{ content }</div>;
}

export function ClickablePanel( { children, href, onClick, className = '' } ) {
	const interactive = !! href || !! onClick;
	const classes = `complyops-panel${
		interactive ? ' complyops-panel--interactive' : ''
	}${ className ? ` ${ className }` : '' }`;

	if ( href ) {
		return (
			<a className={ classes } href={ href }>
				{ children }
			</a>
		);
	}

	if ( onClick ) {
		return (
			<button type="button" className={ classes } onClick={ onClick }>
				{ children }
			</button>
		);
	}

	return (
		<div
			className={ classes.replace( ' complyops-panel--interactive', '' ) }
		>
			{ children }
		</div>
	);
}

export function DetailBackLink( {
	href,
	onClick,
	label = __( 'Back to dashboard', 'complyops' ),
} ) {
	if ( onClick ) {
		return (
			<button
				type="button"
				className="complyops-detail-back"
				onClick={ onClick }
			>
				<span
					className="dashicons dashicons-arrow-left-alt2"
					aria-hidden="true"
				/>
				{ label }
			</button>
		);
	}

	return (
		<a className="complyops-detail-back" href={ href }>
			<span
				className="dashicons dashicons-arrow-left-alt2"
				aria-hidden="true"
			/>
			{ label }
		</a>
	);
}

export function LoadingState( { message } ) {
	return (
		<div className="complyops-state">
			<span className="spinner is-active" />
			<p>{ message || __( 'Loading…', 'complyops' ) }</p>
		</div>
	);
}

export function ErrorState( { message, onRetry } ) {
	return (
		<div className="complyops-state complyops-state--error">
			<span className="dashicons dashicons-warning" aria-hidden="true" />
			<h2>{ __( 'We could not load this view', 'complyops' ) }</h2>
			<p>
				{ message ||
					__( 'No error details were returned.', 'complyops' ) }
			</p>
			{ onRetry && (
				<button
					type="button"
					className="button button-secondary"
					onClick={ onRetry }
				>
					{ __( 'Try again', 'complyops' ) }
				</button>
			) }
		</div>
	);
}

export function EmptyState( {
	title,
	description,
	action,
	icon = 'dashicons-yes-alt',
} ) {
	return (
		<div className="complyops-state">
			<span className={ `dashicons ${ icon }` } aria-hidden="true" />
			<h2>{ title }</h2>
			{ description && <p>{ description }</p> }
			{ action }
		</div>
	);
}

export function PageHeader( { title, eyebrow, description, actions, meta } ) {
	return (
		<header className="complyops-page-header">
			<div className="complyops-page-header__copy">
				{ eyebrow && (
					<span className="complyops-eyebrow">{ eyebrow }</span>
				) }
				<h1>{ title }</h1>
				{ description && <p>{ description }</p> }
				{ meta && (
					<div className="complyops-page-header__meta">{ meta }</div>
				) }
			</div>
			<div className="complyops-page-header__actions">
				{ actions }
				<NotificationBell />
			</div>
		</header>
	);
}

export function SectionHeader( { title, description, action } ) {
	return (
		<div className="complyops-section-header">
			<div>
				<h2>{ title }</h2>
				{ description && <p>{ description }</p> }
			</div>
			{ action }
		</div>
	);
}

export function ProgressBar( { value, label } ) {
	const amount = Math.max( 0, Math.min( 100, Number( value ) || 0 ) );
	return (
		<div className="complyops-progress">
			<div className="complyops-progress__meta">
				<span>{ label }</span>
				<strong>{ amount }%</strong>
			</div>
			<div className="complyops-progress__track">
				<span style={ { width: `${ amount }%` } } />
			</div>
		</div>
	);
}

export function Tabs( { items, active, onChange } ) {
	return (
		<div className="complyops-tabs" role="tablist">
			{ items.map( ( item ) => (
				<button
					key={ item.value }
					type="button"
					role="tab"
					aria-selected={ active === item.value }
					onClick={ () => onChange( item.value ) }
				>
					{ item.label }
				</button>
			) ) }
		</div>
	);
}

export function Alert( { tone = 'info', children } ) {
	return (
		<div
			className={ `complyops-alert complyops-alert--${ tone }` }
			role={ tone === 'danger' ? 'alert' : 'status' }
		>
			{ children }
		</div>
	);
}

export function formatDate( value ) {
	if ( ! value ) {
		return __( 'Not available', 'complyops' );
	}
	const date = new Date( String( value ).replace( ' ', 'T' ) );
	return Number.isNaN( date.getTime() )
		? value
		: new Intl.DateTimeFormat( undefined, {
				dateStyle: 'medium',
				timeStyle: 'short',
		  } ).format( date );
}

export function isFormDirty( baseline, current ) {
	if ( ! baseline || ! current ) {
		return false;
	}

	return JSON.stringify( baseline ) !== JSON.stringify( current );
}

export function SaveFooter( {
	onSave,
	onCancel,
	saving = false,
	dirty = false,
	saveLabel = __( 'Save', 'complyops' ),
	cancelLabel = __( 'Cancel', 'complyops' ),
} ) {
	return (
		<footer
			className="complyops-save-footer"
			aria-label={ __( 'Form actions', 'complyops' ) }
		>
			<div className="complyops-save-footer__actions">
				<button
					type="button"
					className="button button-secondary"
					onClick={ onCancel }
					disabled={ saving || ! dirty }
				>
					{ cancelLabel }
				</button>
				<button
					type="button"
					className="button button-primary"
					onClick={ onSave }
					disabled={ saving || ! dirty }
				>
					{ saving ? __( 'Saving…', 'complyops' ) : saveLabel }
				</button>
			</div>
		</footer>
	);
}

export function FormPage( { children, footer } ) {
	return (
		<div className="complyops-page complyops-page--with-footer">
			<div className="complyops-page__body">{ children }</div>
			{ footer }
		</div>
	);
}

export { AlertDialog, ConfirmDialog, Dialog } from './Dialog';
export { useNotify } from './Notifications';
