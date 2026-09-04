import { NotificationProvider } from './Notifications';
import ProductTutorialModal from './ProductTutorialModal';
import SetupWizardModal from './SetupWizardModal';

export default function Layout( { children } ) {
	return (
		<NotificationProvider>
			<div className="complyops-admin">
				<main className="complyops-admin__main">{ children }</main>
			</div>
			<SetupWizardModal />
			<ProductTutorialModal />
		</NotificationProvider>
	);
}
