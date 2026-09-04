import { __ } from '@wordpress/i18n';

export const BANNER_DIFF_FIELDS = [
	{ key: 'banner_headline', label: __( 'Title', 'complyops' ) },
	{ key: 'banner_description', label: __( 'Description', 'complyops' ) },
	{
		key: 'show_site_logo',
		label: __( 'Site Logo', 'complyops' ),
		type: 'boolean',
	},
	{
		key: 'show_reopen_button',
		label: __( 'Privacy Settings Button', 'complyops' ),
		type: 'boolean',
	},
];

export function snapshotFromSettings( settings ) {
	return {
		banner_headline: settings.banner_headline || '',
		banner_description: settings.banner_description || '',
		show_site_logo: !! settings.show_site_logo,
		show_reopen_button: !! settings.show_reopen_button,
	};
}

export function snapshotFromRevision( revision ) {
	return {
		banner_headline: revision.banner_headline || '',
		banner_description: revision.banner_description || '',
		show_site_logo: !! revision.show_site_logo,
		show_reopen_button: !! revision.show_reopen_button,
	};
}

export function fieldChanged( leftValue, rightValue, field ) {
	if ( field.type === 'boolean' ) {
		return leftValue !== rightValue;
	}

	return (
		String( leftValue || '' ).trim() !== String( rightValue || '' ).trim()
	);
}

export function formatBooleanValue( value ) {
	return value ? __( 'Yes', 'complyops' ) : __( 'No', 'complyops' );
}

export function formatRevisionDate( savedAt ) {
	if ( ! savedAt ) {
		return __( 'Unknown date', 'complyops' );
	}

	const date = new Date( savedAt );

	if ( Number.isNaN( date.getTime() ) ) {
		return savedAt;
	}

	return date.toLocaleString();
}
