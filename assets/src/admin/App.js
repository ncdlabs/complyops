import Layout from './components/Layout';
import DashboardPage from './pages/DashboardPage';
import AuditPage from './pages/AuditPage';
import ControlsPage from './pages/ControlsPage';
import IntegrationsPage from './pages/IntegrationsPage';
import ConsentPage from './pages/ConsentPage';
import EvidencePage from './pages/EvidencePage';
import ReportsPage from './pages/ReportsPage';

function renderPage( page, config ) {
	switch ( page ) {
		case 'dashboard':
			return <DashboardPage config={ config } />;
		case 'audit':
			return <AuditPage config={ config } />;
		case 'controls':
			return <ControlsPage />;
		case 'integrations':
			return <IntegrationsPage config={ config } />;
		case 'consent':
			return <ConsentPage config={ config } />;
		case 'evidence':
			return <EvidencePage />;
		case 'reports':
			return <ReportsPage />;
		default:
			return <DashboardPage config={ config } />;
	}
}

export default function App( { config } ) {
	const page = config.page || 'dashboard';

	return <Layout>{ renderPage( page, config ) }</Layout>;
}
