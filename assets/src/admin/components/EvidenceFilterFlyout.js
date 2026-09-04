import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { formatDate } from './ui';

export default function EvidenceFilterFlyout( {
	source,
	onSourceChange,
	auditId,
	onAuditIdChange,
	testMethod,
	onTestMethodChange,
	audits = [],
} ) {
	const [ open, setOpen ] = useState( false );
	const rootRef = useRef( null );
	const isFiltered = Boolean( source || auditId || testMethod );
	const label = __( 'Filter evidence', 'complyops' );

	const clearFilters = () => {
		onSourceChange( '' );
		onAuditIdChange( '' );
		onTestMethodChange( '' );
	};

	useEffect( () => {
		if ( ! open ) {
			return undefined;
		}

		const handlePointerDown = ( event ) => {
			if (
				rootRef.current &&
				! rootRef.current.contains( event.target )
			) {
				setOpen( false );
			}
		};

		const handleKeyDown = ( event ) => {
			if ( event.key === 'Escape' ) {
				setOpen( false );
			}
		};

		document.addEventListener( 'mousedown', handlePointerDown );
		document.addEventListener( 'keydown', handleKeyDown );
		return () => {
			document.removeEventListener( 'mousedown', handlePointerDown );
			document.removeEventListener( 'keydown', handleKeyDown );
		};
	}, [ open ] );

	return (
		<div className="complyops-form-flyout" ref={ rootRef }>
			<button
				type="button"
				className={ `complyops-icon-button complyops-form-flyout__trigger${
					open ? ' is-open' : ''
				}${ isFiltered ? ' is-active' : '' }` }
				onClick={ () => setOpen( ( current ) => ! current ) }
				aria-expanded={ open }
				aria-haspopup="dialog"
				aria-label={ label }
				title={ label }
			>
				<span
					className="dashicons dashicons-filter"
					aria-hidden="true"
				/>
				{ isFiltered && (
					<span
						className="complyops-form-flyout__badge"
						aria-hidden="true"
					/>
				) }
			</button>
			{ open && (
				<div
					className="complyops-form-flyout__panel"
					role="dialog"
					aria-label={ label }
				>
					<div className="complyops-form-flyout__header">
						<strong>{ __( 'Filters', 'complyops' ) }</strong>
						<button
							type="button"
							className="button-link"
							onClick={ clearFilters }
							disabled={ ! isFiltered }
						>
							{ __( 'Clear all', 'complyops' ) }
						</button>
					</div>
					<div className="complyops-form-flyout__fields">
						<div className="complyops-field">
							<label htmlFor="complyops-evidence-filter-source">
								{ __( 'Source', 'complyops' ) }
							</label>
							<select
								id="complyops-evidence-filter-source"
								value={ source }
								onChange={ ( event ) =>
									onSourceChange( event.target.value )
								}
							>
								<option value="">
									{ __( 'All sources', 'complyops' ) }
								</option>
								<option value="audit">
									{ __( 'Audit', 'complyops' ) }
								</option>
								<option value="remediation">
									{ __( 'Remediation', 'complyops' ) }
								</option>
								<option value="drift">
									{ __( 'Drift', 'complyops' ) }
								</option>
							</select>
						</div>
						<div className="complyops-field">
							<label htmlFor="complyops-evidence-filter-audit">
								{ __( 'Audit', 'complyops' ) }
							</label>
							<select
								id="complyops-evidence-filter-audit"
								value={ auditId }
								onChange={ ( event ) =>
									onAuditIdChange( event.target.value )
								}
							>
								<option value="">
									{ __( 'All audits', 'complyops' ) }
								</option>
								{ audits.map( ( audit ) => (
									<option
										key={ audit.id }
										value={ audit.id }
									>{ `#${ audit.id } · ${ formatDate(
										audit.completed_at || audit.started_at
									) }` }</option>
								) ) }
							</select>
						</div>
						<div className="complyops-field">
							<label htmlFor="complyops-evidence-filter-method">
								{ __( 'Test method', 'complyops' ) }
							</label>
							<select
								id="complyops-evidence-filter-method"
								value={ testMethod }
								onChange={ ( event ) =>
									onTestMethodChange( event.target.value )
								}
							>
								<option value="">
									{ __( 'All methods', 'complyops' ) }
								</option>
								<option value="static">
									{ __( 'Static', 'complyops' ) }
								</option>
								<option value="runtime">
									{ __( 'Runtime', 'complyops' ) }
								</option>
								<option value="api">
									{ __( 'API', 'complyops' ) }
								</option>
								<option value="manual">
									{ __( 'Manual', 'complyops' ) }
								</option>
							</select>
						</div>
					</div>
				</div>
			) }
		</div>
	);
}
