import apiFetch from '@wordpress/api-fetch';

const NS = 'complyops/v1';

export async function getLatestAudit( framework = 'gdpr' ) {
	try {
		return await apiFetch( {
			path: `/${ NS }/audit/latest?framework=${ framework }`,
		} );
	} catch ( error ) {
		if (
			error?.code === 'complyops_no_audit' ||
			error?.data?.status === 404
		) {
			return null;
		}
		throw error;
	}
}

export async function runAudit( framework = 'gdpr' ) {
	return apiFetch( {
		path: `/${ NS }/audits`,
		method: 'POST',
		data: { framework, trigger: 'manual' },
	} );
}

export async function getFindings(
	auditId = null,
	framework = 'gdpr',
	scope = 'issues'
) {
	const params = new URLSearchParams();
	if ( auditId ) {
		params.set( 'audit_id', String( auditId ) );
	} else {
		params.set( 'framework', framework );
	}
	if ( scope && scope !== 'issues' ) {
		params.set( 'scope', scope );
	}
	return apiFetch( { path: `/${ NS }/findings?${ params.toString() }` } );
}

export async function getAuditHistory( framework = 'gdpr', limit = 20 ) {
	return apiFetch( {
		path: `/${ NS }/audits?framework=${ framework }&limit=${ limit }`,
	} );
}

export async function getControls( framework = 'gdpr' ) {
	return apiFetch( { path: `/${ NS }/controls?framework=${ framework }` } );
}

export async function getDiscovery() {
	return apiFetch( { path: `/${ NS }/discovery` } );
}

export async function getIntegrations() {
	return apiFetch( { path: `/${ NS }/integrations` } );
}

export async function applySiteKitRecommended() {
	return apiFetch( {
		path: `/${ NS }/integrations/site-kit/apply-recommended`,
		method: 'POST',
	} );
}

export async function startGoogleAnalyticsOAuth() {
	return apiFetch( {
		path: `/${ NS }/integrations/google-analytics/oauth/start`,
		method: 'POST',
	} );
}

export async function completeGoogleAnalyticsOAuth( code, state ) {
	return apiFetch( {
		path: `/${ NS }/integrations/google-analytics/oauth/complete`,
		method: 'POST',
		data: { code, state },
	} );
}

export async function disconnectGoogleAnalyticsOAuth() {
	return apiFetch( {
		path: `/${ NS }/integrations/google-analytics/oauth/disconnect`,
		method: 'POST',
	} );
}

export async function getGoogleAnalyticsProperties() {
	return apiFetch( {
		path: `/${ NS }/integrations/google-analytics/properties`,
	} );
}

export async function selectGoogleAnalyticsProperty( propertyId ) {
	return apiFetch( {
		path: `/${ NS }/integrations/google-analytics/property`,
		method: 'POST',
		data: { property_id: propertyId },
	} );
}

export async function startGoogleTagManagerOAuth() {
	return apiFetch( {
		path: `/${ NS }/integrations/google-tag-manager/oauth/start`,
		method: 'POST',
	} );
}

export async function completeGoogleTagManagerOAuth( code, state ) {
	return apiFetch( {
		path: `/${ NS }/integrations/google-tag-manager/oauth/complete`,
		method: 'POST',
		data: { code, state },
	} );
}

export async function disconnectGoogleTagManagerOAuth() {
	return apiFetch( {
		path: `/${ NS }/integrations/google-tag-manager/oauth/disconnect`,
		method: 'POST',
	} );
}

export async function getGoogleTagManagerContainers() {
	return apiFetch( {
		path: `/${ NS }/integrations/google-tag-manager/containers`,
	} );
}

export async function selectGoogleTagManagerContainer( containerId ) {
	return apiFetch( {
		path: `/${ NS }/integrations/google-tag-manager/container`,
		method: 'POST',
		data: { container_id: containerId },
	} );
}

export async function getForms() {
	return apiFetch( { path: `/${ NS }/forms` } );
}

export async function getRemediationPlan( auditId = null, framework = 'gdpr' ) {
	return apiFetch( {
		path: `/${ NS }/remediation/plan`,
		method: 'POST',
		data: {
			audit_id: auditId,
			framework,
		},
	} );
}

export async function applyRemediation(
	actionIds,
	auditId = null,
	framework = 'gdpr'
) {
	return apiFetch( {
		path: `/${ NS }/remediation/apply`,
		method: 'POST',
		data: {
			action_ids: actionIds,
			audit_id: auditId,
			framework,
		},
	} );
}

export async function applyControlRemediation(
	controlId,
	auditId = null,
	framework = 'gdpr'
) {
	return apiFetch( {
		path: `/${ NS }/remediation/${ controlId }/apply`,
		method: 'POST',
		data: {
			audit_id: auditId,
			framework,
		},
	} );
}

export async function getFrameworks( includeInactive = false ) {
	const query = includeInactive ? '?include_inactive=1' : '';
	return apiFetch( { path: `/${ NS }/frameworks${ query }` } );
}

export async function activateFramework( frameworkId ) {
	return apiFetch( {
		path: `/${ NS }/frameworks/${ frameworkId }/activate`,
		method: 'POST',
	} );
}

export async function deactivateFramework( frameworkId ) {
	return apiFetch( {
		path: `/${ NS }/frameworks/${ frameworkId }/deactivate`,
		method: 'POST',
	} );
}

export async function updateControl(
	controlId,
	{ framework, enabled, comment }
) {
	return apiFetch( {
		path: `/${ NS }/controls/${ controlId }`,
		method: 'PATCH',
		data: {
			framework,
			enabled,
			comment,
		},
	} );
}

export async function updateControlApplicability(
	controlId,
	{ framework, state, reason }
) {
	return apiFetch( {
		path: `/${ NS }/controls/${ controlId }/applicability`,
		method: 'PUT',
		data: {
			framework,
			state,
			reason,
		},
	} );
}

export async function saveManualEvidence( {
	controlId,
	framework,
	status = 'PASS',
	note = '',
	reviewedAt = null,
	reviewIntervalDays = 365,
	attachmentId = null,
} ) {
	return apiFetch( {
		path: `/${ NS }/evidence/manual`,
		method: 'POST',
		data: {
			control_id: controlId,
			framework,
			status,
			note,
			reviewed_at: reviewedAt,
			review_interval_days: reviewIntervalDays,
			attachment_id: attachmentId,
		},
	} );
}

export async function previewFrameworkPack( pack, unlockKey ) {
	return apiFetch( {
		path: `/${ NS }/frameworks/preview`,
		method: 'POST',
		data: { pack, unlock_key: unlockKey },
	} );
}

export async function installFrameworkPack( pack, unlockKey ) {
	return apiFetch( {
		path: `/${ NS }/frameworks/install`,
		method: 'POST',
		data: { pack, unlock_key: unlockKey },
	} );
}

function buildQuery( params = {} ) {
	const search = new URLSearchParams();
	Object.entries( params ).forEach( ( [ key, value ] ) => {
		if ( value !== undefined && value !== null && value !== '' ) {
			search.set( key, String( value ) );
		}
	} );
	const query = search.toString();
	return query ? `?${ query }` : '';
}

export async function getEvidence( params = {} ) {
	return apiFetch( { path: `/${ NS }/evidence${ buildQuery( params ) }` } );
}

export async function getActivityLog( params = {} ) {
	return apiFetch( {
		path: `/${ NS }/activity-log${ buildQuery( params ) }`,
	} );
}

export async function exportEvidence( format = 'json', params = {} ) {
	return apiFetch( {
		path: `/${ NS }/evidence/export${ buildQuery( {
			...params,
			format,
		} ) }`,
	} );
}

export async function getSettings() {
	return apiFetch( { path: `/${ NS }/settings` } );
}

export async function updateSettings( data ) {
	return apiFetch( {
		path: `/${ NS }/settings`,
		method: 'PUT',
		data,
	} );
}

export async function getNotifications() {
	return apiFetch( { path: `/${ NS }/notifications` } );
}

export async function dismissNotification( id ) {
	return apiFetch( {
		path: `/${ NS }/notifications/${ encodeURIComponent( id ) }/dismiss`,
		method: 'POST',
	} );
}

export async function dismissAllNotifications() {
	return apiFetch( {
		path: `/${ NS }/notifications/dismiss-all`,
		method: 'POST',
	} );
}
