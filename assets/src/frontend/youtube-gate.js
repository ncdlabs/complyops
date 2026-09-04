import './youtube-gate.css';

const YOUTUBE_IFRAME_PATTERN = /youtube(?:-nocookie)?\.com/i;

function hasCategory( consent, category ) {
	if ( ! consent?.categories ) {
		return category === 'necessary';
	}
	return !! consent.categories[ category ];
}

function readStoredConsent() {
	try {
		const raw = localStorage.getItem( 'complyops_consent' );
		return raw ? JSON.parse( raw ) : null;
	} catch {
		return null;
	}
}

function getGateConfig() {
	return window.complyopsYoutubeGate || {};
}

function normalizeYoutubeSrc( src ) {
	const config = getGateConfig().config || {};

	if ( config.use_youtube_nocookie === false ) {
		return src;
	}

	return src
		.replace( '://www.youtube.com/', '://www.youtube-nocookie.com/' )
		.replace( '://youtube.com/', '://www.youtube-nocookie.com/' );
}

function createIframe( src ) {
	const iframe = document.createElement( 'iframe' );
	iframe.src = normalizeYoutubeSrc( src );
	iframe.setAttribute( 'allowfullscreen', 'true' );
	iframe.setAttribute(
		'allow',
		'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share'
	);
	iframe.setAttribute( 'referrerpolicy', 'strict-origin-when-cross-origin' );
	iframe.setAttribute( 'loading', 'lazy' );
	iframe.setAttribute( 'title', 'YouTube video' );
	return iframe;
}

function activateGate( node, consent ) {
	const category = getGateConfig().category || 'external_media';

	if ( node.getAttribute( 'data-complyops-youtube-activated' ) === '1' ) {
		return;
	}

	if ( ! hasCategory( consent, category ) ) {
		return;
	}

	const src = node.getAttribute( 'data-complyops-youtube-src' );

	if ( ! src ) {
		return;
	}

	const iframe = createIframe( src );
	node.replaceWith( iframe );
}

function activateGates( consent ) {
	document
		.querySelectorAll( '[data-complyops-youtube-blocked="1"]' )
		.forEach( ( node ) => activateGate( node, consent ) );
}

function createPlaceholder( src, message ) {
	const wrapper = document.createElement( 'div' );
	wrapper.className = 'complyops-youtube-gate';
	wrapper.setAttribute( 'data-complyops-youtube-blocked', '1' );
	wrapper.setAttribute( 'data-complyops-youtube-src', src );
	wrapper.setAttribute( 'role', 'group' );
	wrapper.setAttribute( 'aria-label', 'YouTube video placeholder' );
	wrapper.innerHTML = `
		<div class="complyops-youtube-gate__placeholder">
			<p class="complyops-youtube-gate__message">${ message }</p>
			<div class="complyops-youtube-gate__actions">
				<button type="button" class="complyops-youtube-gate__allow" data-complyops-youtube-action="allow">Allow YouTube</button>
				<button type="button" class="complyops-youtube-gate__prefs" data-complyops-youtube-action="preferences">Privacy settings</button>
			</div>
		</div>
	`;
	return wrapper;
}

function isYoutubeIframe( node ) {
	if ( node.tagName !== 'IFRAME' ) {
		return false;
	}

	const src = node.getAttribute( 'src' ) || '';

	return YOUTUBE_IFRAME_PATTERN.test( src );
}

function blockIframe( iframe, message ) {
	if ( iframe.getAttribute( 'data-complyops-youtube-blocked' ) === '1' ) {
		return;
	}

	const src = iframe.getAttribute( 'src' );

	if ( ! src ) {
		return;
	}

	iframe.replaceWith( createPlaceholder( src, message ) );
}

function defaultPlaceholderMessage() {
	return 'This video is hosted by YouTube. Loading it may allow YouTube to process information about your device or activity.';
}

function observeDynamicIframes( getConsent ) {
	const message =
		getGateConfig().config?.youtube_placeholder_message ||
		defaultPlaceholderMessage();

	if ( typeof window.MutationObserver !== 'function' ) {
		return;
	}

	const observer = new window.MutationObserver( ( mutations ) => {
		const consent = getConsent();
		const category = getGateConfig().category || 'external_media';

		if ( hasCategory( consent, category ) ) {
			return;
		}

		for ( const mutation of mutations ) {
			mutation.addedNodes.forEach( ( node ) => {
				if ( node.nodeType !== 1 ) {
					return;
				}

				if ( isYoutubeIframe( node ) ) {
					blockIframe( node, message );
					return;
				}

				node.querySelectorAll?.( 'iframe' ).forEach( ( iframe ) => {
					if ( isYoutubeIframe( iframe ) ) {
						blockIframe( iframe, message );
					}
				} );
			} );
		}
	} );

	if ( document.documentElement ) {
		observer.observe( document.documentElement, {
			childList: true,
			subtree: true,
		} );
	}
}

function grantExternalMedia() {
	if ( window.ComplyOpsConsent?.grantCategories ) {
		window.ComplyOpsConsent.grantCategories(
			{ external_media: true },
			{ action: 'allow_youtube' }
		);
		return;
	}

	document.dispatchEvent(
		new CustomEvent( 'complyops:grant-categories', {
			detail: {
				categories: { external_media: true },
				meta: { action: 'allow_youtube' },
			},
		} )
	);
}

function bindGateActions() {
	document.addEventListener( 'click', ( event ) => {
		const target = event.target;

		if ( ! target?.matches?.( '[data-complyops-youtube-action]' ) ) {
			return;
		}

		const action = target.getAttribute( 'data-complyops-youtube-action' );

		if ( action === 'allow' ) {
			event.preventDefault();
			grantExternalMedia();
			return;
		}

		if ( action === 'preferences' ) {
			event.preventDefault();
			window.ComplyOpsConsent?.openPreferences?.();
		}
	} );
}

function boot() {
	const getConsent = () =>
		window.ComplyOpsConsent?.getConsent?.() || readStoredConsent();

	const apply = ( consent ) => activateGates( consent );

	bindGateActions();
	observeDynamicIframes( getConsent );

	document.addEventListener( 'complyops:consent-updated', ( event ) => {
		apply( event.detail?.consent || null );
	} );

	const existing = getConsent();
	if ( existing ) {
		apply( existing );
	}
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', boot );
} else {
	boot();
}
