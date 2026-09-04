import { useCallback, useEffect, useState } from '@wordpress/element';

export function getQueryParam( name ) {
	if ( typeof window === 'undefined' ) {
		return '';
	}

	return new URLSearchParams( window.location.search ).get( name ) || '';
}

export function setQueryParam( name, value ) {
	if ( typeof window === 'undefined' ) {
		return;
	}

	const url = new URL( window.location.href );
	if ( value === null || value === undefined || value === '' ) {
		url.searchParams.delete( name );
	} else {
		url.searchParams.set( name, String( value ) );
	}

	const next = `${ url.pathname }${ url.search }${ url.hash }`;
	const current = `${ window.location.pathname }${ window.location.search }${ window.location.hash }`;
	if ( next !== current ) {
		window.history.replaceState( null, '', next );
	}
}

export function useUrlTab( param, allowedValues, defaultValue ) {
	const read = useCallback( () => {
		const value = getQueryParam( param );
		return allowedValues.includes( value ) ? value : defaultValue;
	}, [ param, allowedValues, defaultValue ] );

	const [ activeTab, setActiveTabState ] = useState( read );

	const setActiveTab = useCallback(
		( value ) => {
			const next = allowedValues.includes( value ) ? value : defaultValue;
			setActiveTabState( next );
			setQueryParam( param, next === defaultValue ? null : next );
		},
		[ param, allowedValues, defaultValue ]
	);

	useEffect( () => {
		const onPopState = () => setActiveTabState( read() );
		window.addEventListener( 'popstate', onPopState );
		return () => window.removeEventListener( 'popstate', onPopState );
	}, [ read ] );

	return [ activeTab, setActiveTab ];
}

export function useUrlParam( param, allowedValues, defaultValue = '' ) {
	const read = useCallback( () => {
		const value = getQueryParam( param );
		return allowedValues.includes( value ) ? value : defaultValue;
	}, [ param, allowedValues, defaultValue ] );

	const [ value, setValueState ] = useState( read );

	const setValue = useCallback(
		( nextValue ) => {
			const next = allowedValues.includes( nextValue )
				? nextValue
				: defaultValue;
			setValueState( next );
			setQueryParam( param, next === defaultValue ? null : next );
		},
		[ param, allowedValues, defaultValue ]
	);

	useEffect( () => {
		const onPopState = () => setValueState( read() );
		window.addEventListener( 'popstate', onPopState );
		return () => window.removeEventListener( 'popstate', onPopState );
	}, [ read ] );

	return [ value, setValue ];
}

export function useUrlMultiParam( param, allowedValues, defaultValues ) {
	const normalize = useCallback(
		( values ) => {
			const filtered = values.filter( ( value ) =>
				allowedValues.includes( value )
			);
			if ( filtered.length ) {
				return filtered;
			}

			return defaultValues.filter( ( value ) =>
				allowedValues.includes( value )
			);
		},
		[ allowedValues, defaultValues ]
	);

	const read = useCallback( () => {
		const raw = getQueryParam( param );
		if ( ! raw ) {
			return normalize( defaultValues );
		}

		return normalize(
			raw
				.split( ',' )
				.map( ( value ) => value.trim() )
				.filter( Boolean )
		);
	}, [ param, defaultValues, normalize ] );

	const [ values, setValuesState ] = useState( read );

	const setValues = useCallback(
		( nextValues ) => {
			const next = normalize( nextValues );
			setValuesState( next );

			const isDefault =
				next.length === defaultValues.length &&
				next.every( ( value ) => defaultValues.includes( value ) );
			setQueryParam( param, isDefault ? null : next.join( ',' ) );
		},
		[ param, defaultValues, normalize ]
	);

	useEffect( () => {
		const onPopState = () => setValuesState( read() );
		window.addEventListener( 'popstate', onPopState );
		return () => window.removeEventListener( 'popstate', onPopState );
	}, [ read ] );

	useEffect( () => {
		if ( ! allowedValues.length ) {
			return;
		}

		setValuesState( ( current ) => {
			const next = normalize( current );
			const isDefault =
				next.length === defaultValues.length &&
				next.every( ( value ) => defaultValues.includes( value ) );
			setQueryParam( param, isDefault ? null : next.join( ',' ) );
			return next;
		} );
	}, [ allowedValues, defaultValues, normalize, param ] );

	return [ values, setValues ];
}

export function adminPageUrl( pageSlug, params = {} ) {
	const query = new URLSearchParams( { page: pageSlug } );
	Object.entries( params ).forEach( ( [ key, value ] ) => {
		if ( value !== null && value !== undefined && value !== '' ) {
			query.set( key, String( value ) );
		}
	} );
	return `admin.php?${ query.toString() }`;
}

export function useUrlFlag( param ) {
	const [ active, setActiveState ] = useState(
		() => getQueryParam( param ) === '1'
	);

	const setActive = useCallback(
		( value ) => {
			const next = !! value;
			setActiveState( next );
			setQueryParam( param, next ? '1' : null );
		},
		[ param ]
	);

	useEffect( () => {
		const onPopState = () =>
			setActiveState( getQueryParam( param ) === '1' );
		window.addEventListener( 'popstate', onPopState );
		return () => window.removeEventListener( 'popstate', onPopState );
	}, [ param ] );

	return [ active, setActive ];
}
