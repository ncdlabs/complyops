import {
	applyAnalyticsPageLocation,
	installDynamicScriptGuard,
	sanitizeAnalyticsUrl,
} from './analytics-privacy';

describe( 'analytics privacy enforcement', () => {
	test( 'removes blocked parameters case-insensitively before analytics configuration', () => {
		expect(
			sanitizeAnalyticsUrl(
				'https://example.com/account?Email=user%40example.com&utm_source=test&TOKEN=secret',
				[ 'email', 'token' ]
			)
		).toBe( 'https://example.com/account?utm_source=test' );
	} );

	test( 'sets a sanitized global page location without sending a duplicate pageview', () => {
		const gtag = jest.fn();

		applyAnalyticsPageLocation(
			gtag,
			'https://example.com/?email=user%40example.com',
			{ enabled: true, blocked_parameters: [ 'email' ] }
		);

		expect( gtag ).toHaveBeenCalledWith( 'set', {
			page_location: 'https://example.com/',
		} );
		expect( gtag ).not.toHaveBeenCalledWith(
			'event',
			'page_view',
			expect.anything()
		);
	} );

	test( 'blocks matching dynamic scripts before insertion', () => {
		const restore = installDynamicScriptGuard( () => ( {
			categories: { analytics: false },
		} ) );
		const script = document.createElement( 'script' );
		script.src = 'https://www.googletagmanager.com/gtm.js?id=GTM-TEST';

		document.body.appendChild( script );

		expect( script.type ).toBe( 'text/plain' );
		expect( script.getAttribute( 'src' ) ).toBeNull();
		expect( script.getAttribute( 'data-complyops-src' ) ).toContain(
			'googletagmanager.com/gtm.js'
		);

		script.remove();
		restore();
	} );

	test( 'blocks scripts inserted through modern append APIs', () => {
		const restore = installDynamicScriptGuard( () => ( {
			categories: { analytics: false },
		} ) );
		const script = document.createElement( 'script' );
		script.src = 'https://www.google-analytics.com/analytics.js';

		document.body.append( script );

		expect( script.type ).toBe( 'text/plain' );
		expect( script.getAttribute( 'src' ) ).toBeNull();

		script.remove();
		restore();
	} );
} );
