/**
 * ComplyOps consent store — client-side persistence.
 */
const STORAGE_KEY = 'complyops_consent';

export function defaultCategories( config ) {
	const defaults = {};
	( config?.categories || [] ).forEach( ( category ) => {
		defaults[ category.id ] = !! category.default;
	} );
	defaults.necessary = true;
	return defaults;
}

export function readConsent( config ) {
	try {
		const raw = localStorage.getItem( STORAGE_KEY );
		if ( ! raw ) {
			return null;
		}

		const parsed = JSON.parse( raw );
		if ( ! parsed || parsed.version !== config.version ) {
			return null;
		}

		return parsed;
	} catch {
		return null;
	}
}

export function writeConsent( config, categories, meta = {} ) {
	const payload = {
		version: config.version,
		categories: {
			...defaultCategories( config ),
			...categories,
			necessary: true,
		},
		timestamp: new Date().toISOString(),
		...meta,
	};

	localStorage.setItem( STORAGE_KEY, JSON.stringify( payload ) );
	document.cookie = `${ STORAGE_KEY }=1; path=/; max-age=31536000; SameSite=Lax`;

	return payload;
}

export function clearConsent() {
	localStorage.removeItem( STORAGE_KEY );
	document.cookie = `${ STORAGE_KEY }=; path=/; max-age=0; SameSite=Lax`;
}

export function hasCategory( consent, category ) {
	if ( ! consent?.categories ) {
		return category === 'necessary';
	}

	return !! consent.categories[ category ];
}
