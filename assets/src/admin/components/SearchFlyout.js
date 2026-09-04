import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export default function SearchFlyout( {
	value,
	onChange,
	placeholder = __( 'Search evidence…', 'complyops' ),
	label = __( 'Search evidence', 'complyops' ),
} ) {
	const [ open, setOpen ] = useState( false );
	const [ draft, setDraft ] = useState( value );
	const rootRef = useRef( null );
	const inputRef = useRef( null );
	const isActive = Boolean( value );

	const close = useCallback( () => {
		setDraft( value );
		setOpen( false );
	}, [ value ] );

	const submit = () => {
		onChange( draft );
		setOpen( false );
	};

	useEffect( () => {
		if ( ! open ) {
			return undefined;
		}

		setDraft( value );
		inputRef.current?.focus();
		inputRef.current?.select();

		const handlePointerDown = ( event ) => {
			if (
				rootRef.current &&
				! rootRef.current.contains( event.target )
			) {
				close();
			}
		};

		const handleKeyDown = ( event ) => {
			if ( event.key === 'Escape' ) {
				close();
			}
		};

		document.addEventListener( 'mousedown', handlePointerDown );
		document.addEventListener( 'keydown', handleKeyDown );
		return () => {
			document.removeEventListener( 'mousedown', handlePointerDown );
			document.removeEventListener( 'keydown', handleKeyDown );
		};
	}, [ close, open, value ] );

	const handleTriggerClick = () => {
		setOpen( true );
	};

	const handleInputKeyDown = ( event ) => {
		if ( event.key === 'Enter' ) {
			event.preventDefault();
			submit();
		}
	};

	return (
		<div
			className={ `complyops-search-flyout${ open ? ' is-open' : '' }` }
			ref={ rootRef }
		>
			{ open ? (
				<div className="complyops-search-flyout__panel">
					<span
						className="complyops-search-flyout__icon"
						aria-hidden="true"
					>
						<span className="dashicons dashicons-search" />
					</span>
					<input
						ref={ inputRef }
						type="search"
						className="complyops-search-flyout__input"
						value={ draft }
						onChange={ ( event ) => setDraft( event.target.value ) }
						onKeyDown={ handleInputKeyDown }
						placeholder={ placeholder }
						aria-label={ label }
					/>
					<button
						type="button"
						className="complyops-search-flyout__submit"
						onClick={ submit }
						aria-label={ __( 'Submit search', 'complyops' ) }
						title={ __( 'Submit search', 'complyops' ) }
					>
						<span
							className="dashicons dashicons-arrow-right-alt2"
							aria-hidden="true"
						/>
					</button>
				</div>
			) : (
				<button
					type="button"
					className={ `complyops-icon-button complyops-search-flyout__trigger${
						isActive ? ' is-active' : ''
					}` }
					onClick={ handleTriggerClick }
					aria-expanded={ false }
					aria-haspopup="dialog"
					aria-label={ label }
					title={ label }
				>
					<span
						className="dashicons dashicons-search"
						aria-hidden="true"
					/>
					{ isActive && (
						<span
							className="complyops-search-flyout__badge"
							aria-hidden="true"
						/>
					) }
				</button>
			) }
		</div>
	);
}
