import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export default function FrameworkFilterFlyout( {
	frameworks,
	selected,
	onChange,
	summary = '',
} ) {
	const [ open, setOpen ] = useState( false );
	const rootRef = useRef( null );
	const isFiltered =
		frameworks.length > 0 && selected.length < frameworks.length;

	const toggleFramework = ( frameworkId ) => {
		if ( selected.includes( frameworkId ) ) {
			if ( selected.length === 1 ) {
				return;
			}
			onChange( selected.filter( ( id ) => id !== frameworkId ) );
			return;
		}

		onChange( [ ...selected, frameworkId ] );
	};

	const selectAll = () => {
		onChange( frameworks.map( ( item ) => item.id ) );
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
		<div className="complyops-filter-flyout" ref={ rootRef }>
			<button
				type="button"
				className={ `complyops-filter-flyout__trigger${
					open ? ' is-open' : ''
				}${ isFiltered ? ' is-active' : '' }` }
				onClick={ () => setOpen( ( current ) => ! current ) }
				aria-expanded={ open }
				aria-haspopup="true"
				title={ summary || __( 'Filter by framework', 'complyops' ) }
			>
				<span
					className="dashicons dashicons-filter"
					aria-hidden="true"
				/>
				<span className="screen-reader-text">
					{ __( 'Filter frameworks', 'complyops' ) }
				</span>
				{ isFiltered && (
					<span
						className="complyops-filter-flyout__badge"
						aria-hidden="true"
					/>
				) }
			</button>
			{ open && (
				<div
					className="complyops-filter-flyout__panel"
					role="dialog"
					aria-label={ __( 'Framework filter', 'complyops' ) }
				>
					<div className="complyops-filter-flyout__header">
						<strong>{ __( 'Frameworks', 'complyops' ) }</strong>
						<button
							type="button"
							className="button-link"
							onClick={ selectAll }
						>
							{ __( 'Select all', 'complyops' ) }
						</button>
					</div>
					<ul className="complyops-filter-flyout__list">
						{ frameworks.map( ( framework ) => {
							const checked = selected.includes( framework.id );
							const inputId = `complyops-framework-filter-${ framework.id }`;
							return (
								<li key={ framework.id }>
									<label htmlFor={ inputId }>
										<input
											id={ inputId }
											type="checkbox"
											checked={ checked }
											onChange={ () =>
												toggleFramework( framework.id )
											}
										/>
										<span>{ framework.label }</span>
									</label>
								</li>
							);
						} ) }
					</ul>
					{ summary && (
						<p className="complyops-filter-flyout__summary">
							{ summary }
						</p>
					) }
				</div>
			) }
		</div>
	);
}
