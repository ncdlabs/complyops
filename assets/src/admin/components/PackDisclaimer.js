import { __ } from '@wordpress/i18n';

export function PackDisclaimer( { text } ) {
	if ( ! text ) {
		return null;
	}

	return (
		<p className="complyops-pack-disclaimer" role="note">
			{ text }
		</p>
	);
}

export function NonCertificationNotice() {
	return (
		<p className="complyops-pack-disclaimer complyops-pack-disclaimer--global" role="note">
			{ __(
				'Scores and findings describe technical readiness only. They are not legal certification or compliance guarantees.',
				'complyops'
			) }
		</p>
	);
}
