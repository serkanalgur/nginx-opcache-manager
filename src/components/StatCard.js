/**
 * Stat card component for dashboard metrics.
 *
 * @package Nginx_Opcache_Manager
 */

import { Card, CardBody } from '@wordpress/components';

/**
 * Displays a single or dual metric in a card format.
 *
 * @param {Object}   props             - Component props.
 * @param {string}   props.title       - Card title.
 * @param {string}   props.icon        - Dashicons class name.
 * @param {string}   props.color       - Accent color.
 * @param {Object}   props.primary     - Primary metric: { value, label }.
 * @param {Object}   props.secondary   - Secondary metric: { value, label }.
 * @return {JSX.Element} Stat card component.
 */
export default function StatCard( { title, icon, color = '#2271b1', primary, secondary } ) {
	return (
		<Card className="nom-stat-card">
			<CardBody>
				<div className="nom-stat-card__top">
					<div
						className="nom-stat-card__icon"
						style={ { background: 'linear-gradient(135deg, ' + color + ', ' + color + 'dd)' } }
					>
						{ icon && <span className={ `dashicons ${ icon }` }></span> }
					</div>
					<div className="nom-stat-card__title-wrap">
						<span className="nom-stat-card__label">{ title }</span>
					</div>
				</div>

				<div className="nom-stat-card__metrics">
					{ primary && (
						<div className="nom-stat-card__metric">
							<span className="nom-stat-card__value" style={ { color } }>
								{ primary.value }
							</span>
							<span className="nom-stat-card__metric-label">{ primary.label }</span>
						</div>
					) }
					{ secondary && (
						<div className="nom-stat-card__metric">
							<span className="nom-stat-card__value nom-stat-card__value--sm">
								{ secondary.value }
							</span>
							<span className="nom-stat-card__metric-label">{ secondary.label }</span>
						</div>
					) }
				</div>
			</CardBody>
		</Card>
	);
}
