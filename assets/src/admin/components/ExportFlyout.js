import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export default function ExportFlyout( {
	options,
	onExport,
	label = __( 'Export', 'complyops' ),
	icon = 'dashicons-download',
} ) {
	const [ open, setOpen ] = useState( false );
	const rootRef = useRef( null );

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

	const handleSelect = ( format ) => {
		setOpen( false );
		onExport( format );
	};

	return (
		<div className="complyops-export-flyout" ref={ rootRef }>
			<button
				type="button"
				className={ `complyops-icon-button complyops-export-flyout__trigger${
					open ? ' is-open' : ''
				}` }
				onClick={ () => setOpen( ( current ) => ! current ) }
				aria-expanded={ open }
				aria-haspopup="menu"
				aria-label={ label }
				title={ label }
			>
				<span className={ `dashicons ${ icon }` } aria-hidden="true" />
			</button>
			{ open && (
				<div
					className="complyops-export-flyout__panel"
					role="menu"
					aria-label={ label }
				>
					<ul className="complyops-export-flyout__list">
						{ options.map( ( option ) => (
							<li key={ option.value } role="none">
								<button
									type="button"
									role="menuitem"
									className="complyops-export-flyout__option"
									onClick={ () =>
										handleSelect( option.value )
									}
								>
									{ option.label }
								</button>
							</li>
						) ) }
					</ul>
				</div>
			) }
		</div>
	);
}
