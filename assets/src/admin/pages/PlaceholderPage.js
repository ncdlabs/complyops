import { __ } from '@wordpress/i18n';
import { PageHeader } from '../components/ui';

export default function PlaceholderPage( { title, phase, description } ) {
	return (
		<div className="complyops-placeholder-page">
			<PageHeader title={ title } description={ description } />
			<div className="complyops-panel complyops-panel--placeholder">
				<p>
					{ __( 'This section is planned for', 'complyops' ) }{ ' ' }
					<strong>{ phase }</strong>.
				</p>
			</div>
		</div>
	);
}
