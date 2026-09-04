import { useEffect, useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getAuditHistory } from '../api';
import { LoadingState, ErrorState, EmptyState } from '../components/ui';

export function HistoryPanel( { refreshKey = null } ) {
	const [ audits, setAudits ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	const load = useCallback( async () => {
		setLoading( true );
		setError( null );

		try {
			const data = await getAuditHistory();
			setAudits( data.audits || [] );
		} catch ( err ) {
			setError(
				err?.message ||
					__( 'Failed to load audit history.', 'complyops' )
			);
		} finally {
			setLoading( false );
		}
	}, [] );

	useEffect( () => {
		load();
	}, [ load, refreshKey ] );

	if ( loading ) {
		return (
			<LoadingState
				message={ __( 'Loading audit history…', 'complyops' ) }
			/>
		);
	}

	if ( error ) {
		return <ErrorState message={ error } onRetry={ load } />;
	}

	return (
		<div className="complyops-history-page">
			<p className="complyops-muted complyops-history-page__intro">
				{ __(
					'Compare point-in-time audit results and track technical readiness over time.',
					'complyops'
				) }
			</p>

			{ audits.length === 0 ? (
				<EmptyState
					title={ __( 'No audit history', 'complyops' ) }
					description={ __(
						'Completed audits will appear here.',
						'complyops'
					) }
				/>
			) : (
				<div className="complyops-panel complyops-panel--flush">
					<div className="complyops-table-wrap">
						<table className="widefat striped complyops-table">
							<thead>
								<tr>
									<th>{ __( 'Date', 'complyops' ) }</th>
									<th>{ __( 'Framework', 'complyops' ) }</th>
									<th>{ __( 'Score', 'complyops' ) }</th>
									<th>{ __( 'Passed', 'complyops' ) }</th>
									<th>{ __( 'Failed', 'complyops' ) }</th>
									<th>
										{ __( 'Manual Review', 'complyops' ) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ audits.map( ( audit ) => (
									<tr key={ audit.id }>
										<td>
											{ audit.completed_at ||
												audit.started_at }
										</td>
										<td>
											{ audit.framework?.toUpperCase() }
										</td>
										<td>{ audit.score ?? '—' }%</td>
										<td>{ audit.passed ?? 0 }</td>
										<td>{ audit.failed ?? 0 }</td>
										<td>{ audit.manual_review ?? 0 }</td>
									</tr>
								) ) }
							</tbody>
						</table>
					</div>
				</div>
			) }
		</div>
	);
}
