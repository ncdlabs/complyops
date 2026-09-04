const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const { resolve } = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		admin: resolve( __dirname, 'assets/src/admin/admin.js' ),
		consent: resolve( __dirname, 'assets/src/frontend/consent.js' ),
		enforcement: resolve( __dirname, 'assets/src/frontend/enforcement.js' ),
		'youtube-gate': resolve(
			__dirname,
			'assets/src/frontend/youtube-gate.js'
		),
	},
};
