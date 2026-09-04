import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf, _n } from '@wordpress/i18n';
import {
	dismissAllNotifications,
	dismissNotification,
	getNotifications,
} from '../api';

function BellIcon() {
	return (
		<svg
			width="20"
			height="20"
			viewBox="0 0 24 24"
			fill="none"
			aria-hidden="true"
		>
			<path
				d="M18 8A6 6 0 1 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"
				stroke="currentColor"
				strokeWidth="1.75"
				strokeLinecap="round"
				strokeLinejoin="round"
			/>
			<path
				d="M13.73 21a2 2 0 0 1-3.46 0"
				stroke="currentColor"
				strokeWidth="1.75"
				strokeLinecap="round"
				strokeLinejoin="round"
			/>
		</svg>
	);
}

function toneForSeverity( severity ) {
	const value = String( severity || '' ).toLowerCase();
	if ( value === 'critical' || value === 'error' || value === 'high' ) {
		return 'danger';
	}
	if ( value === 'warning' ) {
		return 'warning';
	}
	return 'info';
}

export default function NotificationBell() {
	const [ open, setOpen ] = useState( false );
	const [ items, setItems ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const rootRef = useRef( null );

	const load = useCallback( async () => {
		try {
			const data = await getNotifications();
			setItems( Array.isArray( data?.items ) ? data.items : [] );
		} catch {
			setItems( [] );
		} finally {
			setLoading( false );
		}
	}, [] );

	useEffect( () => {
		load();
	}, [ load ] );

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

	const count = items.length;
	const countLabel = sprintf(
		_n(
			'%d unread notification',
			'%d unread notifications',
			count,
			'complyops'
		),
		count
	);

	const toggle = () => {
		const next = ! open;
		setOpen( next );
		if ( next ) {
			load();
		}
	};

	const handleDismiss = async ( id ) => {
		try {
			await dismissNotification( id );
			setItems( ( current ) =>
				current.filter( ( item ) => item.id !== id )
			);
		} catch {
			load();
		}
	};

	const handleDismissAll = async () => {
		try {
			await dismissAllNotifications();
			setItems( [] );
		} catch {
			load();
		}
	};

	return (
		<div
			className={ `complyops-notice-bell${ open ? ' is-open' : '' }` }
			ref={ rootRef }
		>
			<button
				type="button"
				className="complyops-notice-bell__toggle"
				onClick={ toggle }
				aria-expanded={ open }
				aria-controls={ open ? 'complyops-notice-panel' : undefined }
				aria-haspopup="true"
				aria-label={
					count > 0
						? countLabel
						: __( 'Notifications', 'complyops' )
				}
			>
				<BellIcon />
				{ count > 0 && (
					<span className="complyops-notice-bell__count">
						{ count > 99 ? '99+' : count }
					</span>
				) }
			</button>
			{ open && (
				<div
					id="complyops-notice-panel"
					className="complyops-notice-panel"
					role="region"
					aria-labelledby="complyops-notice-panel-title"
				>
					<div className="complyops-notice-panel__header">
						<h2 id="complyops-notice-panel-title">
							{ __( 'Notifications', 'complyops' ) }
						</h2>
						{ count > 1 && (
							<button
								type="button"
								className="complyops-notice-panel__dismiss-all"
								onClick={ handleDismissAll }
							>
								{ __( 'Dismiss all', 'complyops' ) }
							</button>
						) }
					</div>
					{ loading && count === 0 ? (
						<p className="complyops-notice-panel__empty">
							{ __( 'Loading notifications…', 'complyops' ) }
						</p>
					) : count === 0 ? (
						<p className="complyops-notice-panel__empty">
							{ __( 'No notifications.', 'complyops' ) }
						</p>
					) : (
						<ul className="complyops-notice-panel__list">
							{ items.map( ( item ) => (
								<li
									key={ item.id }
									className={ `complyops-notice-panel__item complyops-notice-panel__item--${ toneForSeverity(
										item.severity
									) }` }
								>
									<p>{ item.message }</p>
									<button
										type="button"
										className="complyops-notice-panel__dismiss"
										onClick={ () =>
											handleDismiss( item.id )
										}
									>
										{ __( 'Dismiss', 'complyops' ) }
									</button>
								</li>
							) ) }
						</ul>
					) }
				</div>
			) }
		</div>
	);
}
