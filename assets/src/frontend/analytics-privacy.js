const BLOCK_PATTERNS = [
	{ pattern: /googletagmanager\.com\/gtag\/js/i, category: 'analytics' },
	{ pattern: /googletagmanager\.com\/gtm\.js/i, category: 'analytics' },
	{ pattern: /google-analytics\.com\/analytics\.js/i, category: 'analytics' },
];

export function sanitizeAnalyticsUrl( href, blockedParameters = [] ) {
	let url;

	try {
		url = new URL( href );
	} catch {
		return href;
	}

	const blocked = new Set(
		blockedParameters.map( ( parameter ) =>
			String( parameter ).toLowerCase()
		)
	);
	let changed = false;

	Array.from( url.searchParams.keys() ).forEach( ( parameter ) => {
		if ( blocked.has( parameter.toLowerCase() ) ) {
			url.searchParams.delete( parameter );
			changed = true;
		}
	} );

	return changed ? url.toString() : href;
}

export function applyAnalyticsPageLocation( gtag, href, piiConfig = {} ) {
	if ( ! piiConfig.enabled || typeof gtag !== 'function' ) {
		return href;
	}

	const sanitized = sanitizeAnalyticsUrl(
		href,
		piiConfig.blocked_parameters || []
	);

	if ( sanitized !== href ) {
		gtag( 'set', { page_location: sanitized } );
	}

	return sanitized;
}

export function matchingBlockRule( src ) {
	return BLOCK_PATTERNS.find( ( rule ) => rule.pattern.test( src ) ) || null;
}

export function blockScriptBeforeInsertion( node, getConsent ) {
	if ( ! node || node.nodeType !== 1 ) {
		return;
	}

	const scripts = [];

	if ( node.tagName === 'SCRIPT' ) {
		scripts.push( node );
	}

	if ( typeof node.querySelectorAll === 'function' ) {
		scripts.push( ...node.querySelectorAll( 'script[src]' ) );
	}

	const consent = getConsent();

	scripts.forEach( ( script ) => {
		if ( script.getAttribute( 'data-complyops-blocked' ) === '1' ) {
			return;
		}

		const src = script.getAttribute( 'src' ) || script.src || '';
		const rule = matchingBlockRule( src );

		if ( ! rule || consent?.categories?.[ rule.category ] ) {
			return;
		}

		script.type = 'text/plain';
		script.setAttribute( 'data-complyops-blocked', '1' );
		script.setAttribute( 'data-complyops-category', rule.category );
		script.setAttribute( 'data-complyops-src', src );
		script.removeAttribute( 'src' );
	} );
}

export function installDynamicScriptGuard( getConsent ) {
	const NodeConstructor = window.Node;

	if (
		typeof NodeConstructor === 'undefined' ||
		NodeConstructor.prototype.__complyOpsGuarded
	) {
		return () => {};
	}

	const restorers = [];
	const wrapNodeMethod = ( method, nodeIndex ) => {
		const original = NodeConstructor.prototype[ method ];

		if ( typeof original !== 'function' ) {
			return;
		}

		NodeConstructor.prototype[ method ] = function ( ...args ) {
			blockScriptBeforeInsertion( args[ nodeIndex ], getConsent );
			return original.apply( this, args );
		};
		restorers.push( () => {
			NodeConstructor.prototype[ method ] = original;
		} );
	};

	wrapNodeMethod( 'appendChild', 0 );
	wrapNodeMethod( 'insertBefore', 0 );
	wrapNodeMethod( 'replaceChild', 0 );

	const wrapVariadicMethod = ( prototype, method ) => {
		const original = prototype?.[ method ];

		if ( typeof original !== 'function' ) {
			return;
		}

		prototype[ method ] = function ( ...args ) {
			args.forEach( ( node ) =>
				blockScriptBeforeInsertion( node, getConsent )
			);
			return original.apply( this, args );
		};
		restorers.push( () => {
			prototype[ method ] = original;
		} );
	};

	[ window.Element?.prototype, window.DocumentFragment?.prototype ].forEach(
		( prototype ) => {
			[ 'append', 'prepend', 'before', 'after', 'replaceWith' ].forEach(
				( method ) => wrapVariadicMethod( prototype, method )
			);
		}
	);

	const elementPrototype = window.Element?.prototype;
	const originalInsertAdjacentElement =
		elementPrototype?.insertAdjacentElement;

	if ( typeof originalInsertAdjacentElement === 'function' ) {
		elementPrototype.insertAdjacentElement = function (
			position,
			element
		) {
			blockScriptBeforeInsertion( element, getConsent );
			return originalInsertAdjacentElement.call(
				this,
				position,
				element
			);
		};
		restorers.push( () => {
			elementPrototype.insertAdjacentElement =
				originalInsertAdjacentElement;
		} );
	}

	Object.defineProperty( NodeConstructor.prototype, '__complyOpsGuarded', {
		configurable: true,
		value: true,
	} );

	return () => {
		restorers.reverse().forEach( ( restore ) => restore() );
		delete NodeConstructor.prototype.__complyOpsGuarded;
	};
}
