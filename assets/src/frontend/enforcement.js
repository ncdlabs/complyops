/**
 * ComplyOps enforcement — Consent Mode updates and script activation.
 */

import {
	applyAnalyticsPageLocation,
	installDynamicScriptGuard,
	matchingBlockRule,
} from './analytics-privacy';

function hasCategory( consent, category ) {
	if ( ! consent?.categories ) {
		return category === 'necessary';
	}
	return !! consent.categories[ category ];
}

function updateGtagConsent( consent ) {
	if ( typeof window.gtag !== 'function' ) {
		return;
	}

	const config = window.ComplyOps?.enforcement || {};
	const cats = consent?.categories || {};
	const denyAds = config.deny_ad_signals_by_default !== false;

	window.gtag( 'consent', 'update', {
		analytics_storage: cats.analytics ? 'granted' : 'denied',
		ad_storage: ! denyAds && cats.marketing ? 'granted' : 'denied',
		ad_user_data: ! denyAds && cats.marketing ? 'granted' : 'denied',
		ad_personalization: ! denyAds && cats.marketing ? 'granted' : 'denied',
		functionality_storage: cats.preferences ? 'granted' : 'denied',
		personalization_storage: cats.preferences ? 'granted' : 'denied',
	} );

	sanitizeAnalyticsPageLocation();
}

function sanitizeAnalyticsPageLocation() {
	const pii = window.ComplyOps?.pii;

	applyAnalyticsPageLocation( window.gtag, window.location.href, pii );
}

function activateBlockedScript( node, consent ) {
	const category =
		node.getAttribute( 'data-complyops-category' ) || 'analytics';

	if ( ! hasCategory( consent, category ) ) {
		return;
	}

	const src =
		node.getAttribute( 'data-complyops-src' ) || node.getAttribute( 'src' );

	if ( ! src || node.getAttribute( 'data-complyops-activated' ) === '1' ) {
		return;
	}

	const script = document.createElement( 'script' );
	script.src = src;
	script.async = true;
	script.setAttribute( 'data-complyops-activated', '1' );
	node.parentNode?.insertBefore( script, node.nextSibling );
	node.setAttribute( 'data-complyops-activated', '1' );
}

function activateBlockedScripts( consent ) {
	document
		.querySelectorAll(
			'script[type="text/plain"][data-complyops-blocked="1"]'
		)
		.forEach( ( node ) => activateBlockedScript( node, consent ) );
}

function blockScriptNode( node ) {
	if ( node.getAttribute( 'data-complyops-blocked' ) === '1' ) {
		return;
	}

	const src = node.src || node.getAttribute( 'src' ) || '';

	const rule = matchingBlockRule( src );

	if ( rule ) {
		node.type = 'text/plain';
		node.setAttribute( 'data-complyops-blocked', '1' );
		node.setAttribute( 'data-complyops-category', rule.category );
		node.setAttribute( 'data-complyops-src', src );
		node.removeAttribute( 'src' );
	}
}

function shouldBlockDynamic( src ) {
	return null !== matchingBlockRule( src );
}

function observeDynamicScripts( getConsent ) {
	if ( typeof window.MutationObserver !== 'function' ) {
		return;
	}

	const observer = new window.MutationObserver( ( mutations ) => {
		const consent = getConsent();

		for ( const mutation of mutations ) {
			mutation.addedNodes.forEach( ( node ) => {
				if ( node.tagName !== 'SCRIPT' ) {
					return;
				}

				const src = node.src || node.getAttribute( 'src' ) || '';

				if ( ! src || ! shouldBlockDynamic( src ) ) {
					return;
				}

				if ( hasCategory( consent, 'analytics' ) ) {
					return;
				}

				blockScriptNode( node );
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

function readStoredConsent() {
	try {
		const raw = localStorage.getItem( 'complyops_consent' );
		return raw ? JSON.parse( raw ) : null;
	} catch {
		return null;
	}
}

function boot() {
	const getConsent = () =>
		window.ComplyOpsConsent?.getConsent?.() || readStoredConsent();

	const apply = ( consent ) => {
		updateGtagConsent( consent );
		activateBlockedScripts( consent );
	};

	document.addEventListener( 'complyops:consent-updated', ( event ) => {
		apply( event.detail?.consent || null );
	} );

	const existing = getConsent();
	if ( existing ) {
		apply( existing );
	}

	sanitizeAnalyticsPageLocation();
}

const getCurrentConsent = () =>
	window.ComplyOpsConsent?.getConsent?.() || readStoredConsent();

installDynamicScriptGuard( getCurrentConsent );
observeDynamicScripts( getCurrentConsent );

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', boot );
} else {
	boot();
}
