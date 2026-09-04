import { __ } from '@wordpress/i18n';
import { PageHeader, Tabs } from '../components/ui';
import { useUrlTab } from '../url-state';
import { AuditOverviewPanel, useAuditRunner } from './audit-overview';
import { FindingsPanel } from './FindingsPage';
import { HistoryPanel } from './HistoryPage';

const AUDIT_TABS = [
	{ value: 'overview', label: __( 'Overview', 'complyops' ) },
	{ value: 'findings', label: __( 'Findings', 'complyops' ) },
	{ value: 'history', label: __( 'History', 'complyops' ) },
];

const AUDIT_TAB_VALUES = AUDIT_TABS.map( ( tab ) => tab.value );

export default function AuditPage( { config } ) {
	const [ activeTab, setActiveTab ] = useUrlTab(
		'tab',
		AUDIT_TAB_VALUES,
		'overview'
	);
	const { running, result, handleRun } = useAuditRunner( config, () => {
		setActiveTab( 'findings' );
	} );

	return (
		<div className="complyops-audit-page">
			<PageHeader
				eyebrow={ __( 'Assure', 'complyops' ) }
				title={ __( 'Audits', 'complyops' ) }
				description={ __(
					'Evaluate technical controls, review findings, and compare audit results over time.',
					'complyops'
				) }
				actions={
					config.canRunAudit && (
						<button
							type="button"
							className="button button-primary"
							data-complyops-tour="run-audit"
							onClick={ handleRun }
							disabled={ running }
						>
							{ running
								? __( 'Running audit…', 'complyops' )
								: __( 'Run Audit', 'complyops' ) }
						</button>
					)
				}
			/>

			<Tabs
				active={ activeTab }
				onChange={ setActiveTab }
				items={ AUDIT_TABS }
			/>

			{ activeTab === 'overview' && (
				<AuditOverviewPanel
					config={ config }
					running={ running }
					refreshKey={ result?.id }
					isActive={ activeTab === 'overview' }
					onRun={ handleRun }
				/>
			) }
			{ activeTab === 'findings' && (
				<FindingsPanel config={ config } refreshKey={ result?.id } />
			) }
			{ activeTab === 'history' && (
				<HistoryPanel refreshKey={ result?.id } />
			) }
		</div>
	);
}
