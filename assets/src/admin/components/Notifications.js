import {
	createContext,
	useCallback,
	useContext,
	useMemo,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const NotificationContext = createContext( null );

const AUTO_DISMISS_MS = 4500;
const FADE_MS = 300;

let idCounter = 0;

function NotificationItem( { item, onDismiss } ) {
	return (
		<div
			className={ `complyops-notification complyops-notification--${
				item.tone
			}${ item.exiting ? ' complyops-notification--exiting' : '' }` }
			role={ item.tone === 'danger' ? 'alert' : 'status' }
			aria-live={ item.tone === 'danger' ? 'assertive' : 'polite' }
		>
			<p className="complyops-notification__message">{ item.message }</p>
			<button
				type="button"
				className="complyops-notification__close"
				onClick={ () => onDismiss( item.id ) }
				aria-label={ __( 'Dismiss', 'complyops' ) }
			>
				<span aria-hidden="true">&times;</span>
			</button>
		</div>
	);
}

function NotificationHost() {
	const { items, dismiss } = useContext( NotificationContext );

	if ( ! items.length ) {
		return null;
	}

	return (
		<div className="complyops-notifications" aria-live="polite">
			{ items.map( ( item ) => (
				<NotificationItem
					key={ item.id }
					item={ item }
					onDismiss={ dismiss }
				/>
			) ) }
		</div>
	);
}

export function NotificationProvider( { children } ) {
	const [ items, setItems ] = useState( [] );

	const dismiss = useCallback( ( id ) => {
		setItems( ( current ) =>
			current.map( ( item ) =>
				item.id === id ? { ...item, exiting: true } : item
			)
		);

		window.setTimeout( () => {
			setItems( ( current ) =>
				current.filter( ( item ) => item.id !== id )
			);
		}, FADE_MS );
	}, [] );

	const push = useCallback(
		( message, tone = 'success' ) => {
			if ( ! message ) {
				return;
			}

			const id = ++idCounter;
			setItems( ( current ) => [
				...current,
				{ id, message, tone, exiting: false },
			] );
			window.setTimeout( () => dismiss( id ), AUTO_DISMISS_MS );
		},
		[ dismiss ]
	);

	const notify = useMemo(
		() => ( {
			success: ( message ) => push( message, 'success' ),
			error: ( message ) => push( message, 'danger' ),
			warning: ( message ) => push( message, 'warning' ),
			info: ( message ) => push( message, 'info' ),
		} ),
		[ push ]
	);

	const value = useMemo(
		() => ( { items, dismiss, notify } ),
		[ items, dismiss, notify ]
	);

	return (
		<NotificationContext.Provider value={ value }>
			{ children }
			<NotificationHost />
		</NotificationContext.Provider>
	);
}

export function useNotify() {
	const context = useContext( NotificationContext );

	if ( ! context ) {
		throw new Error( 'useNotify must be used within NotificationProvider' );
	}

	return context.notify;
}
