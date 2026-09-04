import { __ } from '@wordpress/i18n';
import { chartDomain } from '../audit-trends';

const WIDTH = 640;
const HEIGHT = 220;
const PAD = { top: 16, right: 16, bottom: 36, left: 40 };

function shortDate( value ) {
	if ( ! value ) {
		return '';
	}

	const date = new Date( String( value ).replace( ' ', 'T' ) );

	if ( Number.isNaN( date.getTime() ) ) {
		return String( value );
	}

	return new Intl.DateTimeFormat( undefined, {
		month: 'short',
		day: 'numeric',
	} ).format( date );
}

function scaleX( index, count, innerWidth ) {
	if ( count <= 1 ) {
		return PAD.left + innerWidth / 2;
	}

	return PAD.left + ( innerWidth * index ) / ( count - 1 );
}

function scaleY( value, min, max, innerHeight ) {
	if ( max === min ) {
		return PAD.top + innerHeight / 2;
	}

	return (
		PAD.top +
		innerHeight -
		( ( value - min ) / ( max - min ) ) * innerHeight
	);
}

export function AuditTrendChart( { points } ) {
	if ( ! points?.length ) {
		return (
			<p className="complyops-muted complyops-audit-trend-chart__empty">
				{ __(
					'Run additional audits to chart readiness over time.',
					'complyops'
				) }
			</p>
		);
	}

	const innerWidth = WIDTH - PAD.left - PAD.right;
	const innerHeight = HEIGHT - PAD.top - PAD.bottom;
	const domain = chartDomain( points );
	const gridValues = [
		domain.min,
		domain.min + ( domain.max - domain.min ) / 2,
		domain.max,
	];
	const linePath = points
		.map( ( point, index ) => {
			const x = scaleX( index, points.length, innerWidth );
			const y = scaleY(
				point.score,
				domain.min,
				domain.max,
				innerHeight
			);
			return `${ index === 0 ? 'M' : 'L' } ${ x } ${ y }`;
		} )
		.join( ' ' );
	const latest = points[ points.length - 1 ];
	const summary =
		points.length === 1
			? __( 'Single audit recorded.', 'complyops' )
			: `${ __( 'Readiness trend across', 'complyops' ) } ${
					points.length
			  } ${ __( 'audits', 'complyops' ) }. ${ __(
					'Latest score',
					'complyops'
			  ) }: ${ latest.score }%.`;

	return (
		<div className="complyops-audit-trend-chart">
			<svg
				className="complyops-audit-trend-chart__svg"
				viewBox={ `0 0 ${ WIDTH } ${ HEIGHT }` }
				role="img"
				aria-label={ summary }
			>
				<title>{ summary }</title>
				{ gridValues.map( ( value ) => {
					const y = scaleY(
						value,
						domain.min,
						domain.max,
						innerHeight
					);
					return (
						<g key={ value }>
							<line
								className="complyops-audit-trend-chart__grid"
								x1={ PAD.left }
								y1={ y }
								x2={ WIDTH - PAD.right }
								y2={ y }
							/>
							<text
								className="complyops-audit-trend-chart__axis-label"
								x={ PAD.left - 8 }
								y={ y + 4 }
								textAnchor="end"
							>
								{ Math.round( value ) }
							</text>
						</g>
					);
				} ) }
				<path
					className="complyops-audit-trend-chart__line"
					d={ linePath }
				/>
				{ points.map( ( point, index ) => {
					const x = scaleX( index, points.length, innerWidth );
					const y = scaleY(
						point.score,
						domain.min,
						domain.max,
						innerHeight
					);
					return (
						<g key={ point.id || index }>
							<circle
								className="complyops-audit-trend-chart__point"
								cx={ x }
								cy={ y }
								r="4.5"
							/>
							<text
								className="complyops-audit-trend-chart__x-label"
								x={ x }
								y={ HEIGHT - 10 }
								textAnchor="middle"
							>
								{ shortDate( point.at ) }
							</text>
						</g>
					);
				} ) }
			</svg>
			<p className="complyops-audit-trend-chart__legend">
				<span className="complyops-audit-trend-chart__legend-item complyops-audit-trend-chart__legend-item--score">
					{ __( 'Readiness score', 'complyops' ) }
				</span>
			</p>
		</div>
	);
}
