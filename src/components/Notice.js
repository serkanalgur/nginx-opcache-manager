/**
 * Admin notice component.
 *
 * @package Nginx_Opcache_Manager
 */

import { Notice } from '@wordpress/components';

/**
 * Admin notice wrapper component.
 *
 * @param {Object}  props           - Component props.
 * @param {string}  props.status    - Notice status (success, error, warning, info).
 * @param {string}  props.message   - Notice message.
 * @param {boolean} props.isDismissible - Whether the notice is dismissible.
 * @param {Function} props.onDismiss   - Dismiss handler.
 * @return {JSX.Element} Notice component.
 */
export default function AdminNotice( {
	status = 'info',
	message,
	isDismissible = true,
	onDismiss,
} ) {
	if ( ! message ) {
		return null;
	}

	return (
		<Notice
			status={ status }
			isDismissible={ isDismissible }
			onDismiss={ onDismiss }
		>
			{ message }
		</Notice>
	);
}
