import {
	createRoot,
	useCallback,
	useEffect,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	readConsent,
	writeConsent,
	clearConsent,
	defaultCategories,
	hasCategory,
} from './consent-store';
import './consent.css';

function BannerHtml( { tag: Tag, html, id, className } ) {
	if ( ! html ) {
		return null;
	}

	return (
		<Tag
			id={ id }
			className={ className }
			// nosemgrep: typescript.react.security.audit.react-dangerouslysetinnerhtml.react-dangerouslysetinnerhtml -- Banner HTML is sanitized server-side before render.
			dangerouslySetInnerHTML={ { __html: html } }
		/>
	);
}

function BannerActions( { onAcceptAll, onReject, onManage } ) {
	return (
		<div className="complyops-consent-banner__actions">
			<div className="complyops-consent-banner__actions-start">
				<button
					type="button"
					className="complyops-consent-btn complyops-consent-btn--link"
					onClick={ onManage }
				>
					{ __( 'Manage Preferences', 'complyops' ) }
				</button>
			</div>
			<div className="complyops-consent-banner__actions-end">
				<button
					type="button"
					className="complyops-consent-btn"
					onClick={ onReject }
				>
					{ __( 'Reject Non-Essential', 'complyops' ) }
				</button>
				<button
					type="button"
					className="complyops-consent-btn complyops-consent-btn--primary"
					onClick={ onAcceptAll }
				>
					{ __( 'Accept All', 'complyops' ) }
				</button>
			</div>
		</div>
	);
}

function BannerHeadline( { headline } ) {
	if ( headline ) {
		return (
			<BannerHtml
				tag="h2"
				id="complyops-consent-title"
				html={ headline }
			/>
		);
	}

	return (
		<h2 id="complyops-consent-title">
			{ __( 'Privacy & Cookies', 'complyops' ) }
		</h2>
	);
}

function BannerDescription( { description } ) {
	if ( ! description ) {
		return null;
	}

	return (
		<BannerHtml
			tag="div"
			id="complyops-consent-desc"
			className="complyops-consent-banner__description"
			html={ description }
		/>
	);
}

function BannerSiteLogo( { siteLogoUrl, siteName } ) {
	if ( ! siteLogoUrl ) {
		return null;
	}

	return (
		<img
			src={ siteLogoUrl }
			alt={ siteName || '' }
			className="complyops-consent-banner__site-logo"
			decoding="async"
		/>
	);
}

function ConsentBanner( { config, onAcceptAll, onReject, onManage } ) {
	const showIcon =
		!! config.banner?.show_site_logo && !! config.banner?.site_logo_url;

	return (
		<div
			className="complyops-consent-banner"
			role="dialog"
			aria-modal="false"
			aria-labelledby="complyops-consent-title"
			aria-describedby="complyops-consent-desc"
		>
			<div className="complyops-consent-banner__content">
				<div className="complyops-consent-banner__body">
					{ showIcon && (
						<BannerSiteLogo
							siteLogoUrl={ config.banner?.site_logo_url }
							siteName={ config.banner?.site_name }
						/>
					) }
					<div className="complyops-consent-banner__text">
						<BannerHeadline headline={ config.banner?.headline } />
						<BannerDescription
							description={ config.banner?.description }
						/>
					</div>
				</div>
				<BannerActions
					onAcceptAll={ onAcceptAll }
					onReject={ onReject }
					onManage={ onManage }
				/>
			</div>
		</div>
	);
}
function PreferencesDialog( { config, consent, onSave, onClose, onWithdraw } ) {
	const [ prefs, setPrefs ] = useState( {
		...defaultCategories( config ),
		...( consent?.categories || {} ),
	} );

	const toggle = ( id ) => {
		if ( id === 'necessary' ) {
			return;
		}
		setPrefs( ( current ) => ( { ...current, [ id ]: ! current[ id ] } ) );
	};

	return (
		<div
			className="complyops-consent-modal"
			role="dialog"
			aria-modal="true"
			aria-labelledby="complyops-consent-prefs-title"
		>
			<button
				type="button"
				className="complyops-consent-modal__backdrop"
				aria-label={ __( 'Close dialog', 'complyops' ) }
				onClick={ onClose }
			></button>
			<div className="complyops-consent-modal__panel">
				<h2 id="complyops-consent-prefs-title">
					{ __( 'Privacy preferences', 'complyops' ) }
				</h2>
				<ul className="complyops-consent-categories">
					{ ( config.categories || [] ).map( ( category ) => (
						<li key={ category.id }>
							<label>
								<input
									type="checkbox"
									checked={ !! prefs[ category.id ] }
									disabled={ category.required }
									onChange={ () => toggle( category.id ) }
								/>
								<span>{ category.label }</span>
							</label>
						</li>
					) ) }
				</ul>
				<div className="complyops-consent-modal__actions">
					<button
						type="button"
						className="complyops-consent-btn complyops-consent-btn--primary"
						onClick={ () => onSave( prefs ) }
					>
						{ __( 'Save preferences', 'complyops' ) }
					</button>
					<button
						type="button"
						className="complyops-consent-btn"
						onClick={ onClose }
					>
						{ __( 'Close', 'complyops' ) }
					</button>
					<button
						type="button"
						className="complyops-consent-btn complyops-consent-btn--danger"
						onClick={ onWithdraw }
					>
						{ __( 'Withdraw consent', 'complyops' ) }
					</button>
				</div>
			</div>
		</div>
	);
}

function ConsentApp( { config, api } ) {
	const [ consent, setConsent ] = useState( () => readConsent( config ) );
	const [ showBanner, setShowBanner ] = useState(
		() => ! readConsent( config )
	);
	const [ showPrefs, setShowPrefs ] = useState( false );

	const applyConsent = useCallback(
		( categories, meta = {} ) => {
			const saved = writeConsent( config, categories, meta );
			setConsent( saved );
			setShowBanner( false );
			setShowPrefs( false );
			api._notify( saved );
		},
		[ api, config ]
	);

	useEffect( () => {
		const openHandler = () => {
			setShowPrefs( true );
			setShowBanner( false );
		};
		const grantHandler = ( event ) => {
			const updates = event.detail?.categories;
			if ( ! updates || typeof updates !== 'object' ) {
				return;
			}
			const base = consent?.categories || defaultCategories( config );
			applyConsent(
				{ ...base, ...updates },
				event.detail?.meta || { action: 'grant_categories' }
			);
		};
		document.addEventListener( 'complyops:open-preferences', openHandler );
		document.addEventListener( 'complyops:grant-categories', grantHandler );
		return () => {
			document.removeEventListener(
				'complyops:open-preferences',
				openHandler
			);
			document.removeEventListener(
				'complyops:grant-categories',
				grantHandler
			);
		};
	}, [ config, consent, applyConsent ] );

	const acceptAll = () => {
		const all = defaultCategories( config );
		Object.keys( all ).forEach( ( key ) => {
			all[ key ] = true;
		} );
		applyConsent( all, { action: 'accept_all' } );
	};

	const rejectNonEssential = () => {
		applyConsent( defaultCategories( config ), {
			action: 'reject_nonessential',
		} );
	};

	const withdraw = () => {
		clearConsent();
		setConsent( null );
		setShowBanner( true );
		setShowPrefs( false );
		api._notify( null );
	};

	return (
		<>
			{ showBanner && ! showPrefs && (
				<ConsentBanner
					config={ config }
					onAcceptAll={ acceptAll }
					onReject={ rejectNonEssential }
					onManage={ () => {
						setShowPrefs( true );
						setShowBanner( false );
					} }
				/>
			) }
			{ showPrefs && (
				<PreferencesDialog
					config={ config }
					consent={ consent }
					onSave={ ( prefs ) =>
						applyConsent( prefs, { action: 'save_preferences' } )
					}
					onClose={ () => {
						setShowPrefs( false );
						if ( ! consent ) {
							setShowBanner( true );
						}
					} }
					onWithdraw={ withdraw }
				/>
			) }
		</>
	);
}

function updateReopenButton( visible ) {
	const button = document.getElementById( 'complyops-consent-reopen' );
	if ( button ) {
		button.style.display = visible ? 'inline-flex' : 'none';
	}
}

function createConsentApi( config ) {
	const listeners = new Set();
	let current = readConsent( config );

	return {
		getConsent() {
			return current;
		},
		hasConsent( category ) {
			return hasCategory( current, category );
		},
		getVersion() {
			return config.version;
		},
		when( category, callback ) {
			const run = () => callback( this.hasConsent( category ), current );
			run();
			return this.onChange( run );
		},
		onChange( callback ) {
			listeners.add( callback );
			return () => listeners.delete( callback );
		},
		openPreferences() {
			document.dispatchEvent(
				new CustomEvent( 'complyops:open-preferences' )
			);
		},
		grantCategories( updates, meta = {} ) {
			const base = current?.categories || defaultCategories( config );
			const categories = { ...base, ...updates, necessary: true };
			const saved = writeConsent( config, categories, {
				action: 'grant_categories',
				...meta,
			} );
			this._notify( saved );
			return saved;
		},
		_notify( consent ) {
			current = consent;
			document.dispatchEvent(
				new CustomEvent( 'complyops:consent-updated', {
					detail: { consent },
				} )
			);
			listeners.forEach( ( listener ) => listener( consent ) );
			updateReopenButton( !! consent );
		},
	};
}

function boot() {
	const config = window.complyopsConsent;

	if ( ! config?.enabled ) {
		return;
	}

	const mount = document.getElementById( 'complyops-consent-root' );
	if ( ! mount ) {
		return;
	}

	const api = createConsentApi( config );
	window.ComplyOpsConsent = api;

	createRoot( mount ).render( <ConsentApp config={ config } api={ api } /> );

	const reopen = document.getElementById( 'complyops-consent-reopen' );
	if ( reopen ) {
		reopen.addEventListener( 'click', () => api.openPreferences() );
	}

	updateReopenButton( !! readConsent( config ) );
}

boot();
