/**
 * Activity log component.
 *
 * @package Nginx_Opcache_Manager
 */

import { Card, CardHeader, CardBody, Button } from '@wordpress/components';
import { formatDate } from '../utils/helpers';

/**
 * Action badge color mapping.
 */
const actionColors = {
	clear: 'nom-badge-danger',
	reset: 'nom-badge-warning',
	flush: 'nom-badge-info',
	invalidate: 'nom-badge-info',
	delete: 'nom-badge-danger',
	deleted: 'nom-badge-danger',
	schedule: 'nom-badge-warning',
};

/**
 * Get badge class for action type.
 *
 * @param {string} action - Action type.
 * @return {string} CSS class.
 */
function getActionBadge( action ) {
	if ( ! action ) {
		return 'nom-badge-default';
	}
	const lower = action.toLowerCase();
	for ( const [ key, cls ] of Object.entries( actionColors ) ) {
		if ( lower.includes( key ) ) {
			return cls;
		}
	}
	return 'nom-badge-default';
}

/**
 * Activity log list component.
 *
 * @param {Object}   props            - Component props.
 * @param {Array}    props.logs       - Array of log entries.
 * @param {Function} props.onClear    - Clear logs handler.
 * @param {boolean}  props.isClearing - Whether clearing is in progress.
 * @return {JSX.Element} Activity log component.
 */
export default function ActivityLog( { logs = [], onClear, isClearing = false } ) {
	return (
		<Card className="nom-activity-log">
			<CardHeader className="nom-activity-log-header">
				<h3>
					<span className="dashicons dashicons-list-view"></span>
					{ 'Aktivite Gunlugu' }
				</h3>
				{ onClear && (
					<Button
						variant="secondary"
						isSmall
						onClick={ onClear }
						disabled={ isClearing || logs.length === 0 }
						isBusy={ isClearing }
						icon="trash"
					>
						{ 'Temizle' }
					</Button>
				) }
			</CardHeader>
			<CardBody>
				{ logs.length === 0 ? (
					<div className="nom-empty-state">
						<span className="dashicons dashicons-info-outline"></span>
						<p>{ 'Henuz aktivite kaydi yok.' }</p>
					</div>
				) : (
					<div className="nom-log-list">
						{ logs.map( ( log, index ) => (
							<div key={ index } className="nom-log-item">
								<div className="nom-log-badge">
									<span className={ `nom-badge ${ getActionBadge( log.action ) }` }>
										{ log.action || 'unknown' }
									</span>
								</div>
								<div className="nom-log-details">
									<span className="nom-log-url">
										{ log.url || log.file_path || 'N/A' }
									</span>
									{ log.method && (
										<span className="nom-log-method">
											{ log.method }
										</span>
									) }
								</div>
								<div className="nom-log-time">
									{ formatDate( log.timestamp ) }
								</div>
							</div>
						) ) }
					</div>
				) }
			</CardBody>
		</Card>
	);
}
