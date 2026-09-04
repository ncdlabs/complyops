/**
 * ComplyOps admin application entry.
 */
import { createRoot } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import App from './App';
import './style.css';

const config = window.complyopsAdmin || {};

if ( config.restNonce ) {
	apiFetch.use( apiFetch.createNonceMiddleware( config.restNonce ) );
}

const root = document.getElementById( 'complyops-admin-root' );

if ( root ) {
	createRoot( root ).render( <App config={ config } /> );
}
