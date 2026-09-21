/**
 * Stat card component for dashboard metrics.
 *
 * @package Nginx_Opcache_Manager
 */

import { Card, CardBody } from '@wordpress/components';

/**
 * Displays a single statistic in a card format.
 *
 * @param {Object} props           - Component props.
 * @param {string} props.title     - Card title.
 * @param {string|number} props.value - Statistic value.
 * @param {string} props.subtitle  - Subtitle text.
 * @param {string} props.icon      - Dashicons class name.
 * @param {string} props.status    - Status color class.
 * @return {JSX.Element} Stat card component.
 */
export default function StatCard( { title, value, subtitle, icon, status = '' } ) {
	return (
		<Card className={ `nom-stat-card ${ status }` }>
			<CardBody>
				<div className="nom-stat-card-inner">
					{ icon && (
						<div className="nom-stat-icon">
							<span className={ `dashicons ${ icon }` }></span>
						</div>
					) }
					<div className="nom-stat-content">
						<h3 className="nom-stat-value">{ value }</h3>
						<p className="nom-stat-title">{ title }</p>
						{ subtitle && (
							<p className="nom-stat-subtitle">{ subtitle }</p>
						) }
					</div>
				</div>
			</CardBody>
		</Card>
	);
}
