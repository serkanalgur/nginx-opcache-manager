/**
 * Admin notice component.
 *
 * @package Nginx_Opcache_Manager
 */

import React from 'react';
import { Notice } from '@wordpress/components';

/**
 * Admin notice wrapper component.
 *
 * The prop is named `type` to match the `notice.type` value every call site in
 * src/index.js passes. It previously read `status`, which no call site supplied,
 * so every notice — including failures — silently defaulted to 'info' and
 * rendered as an informational banner.
 *
 * @param {Object}   props               - Component props.
 * @param {string}   props.type          - Notice type (success, error, warning, info).
 * @param {string}   props.message       - Notice message.
 * @param {boolean}  props.isDismissible - Whether the notice is dismissible.
 * @param {Function} props.onDismiss     - Dismiss handler.
 * @return {JSX.Element} Notice component.
 */
export default function AdminNotice( {
	type = 'info',
	message,
	isDismissible = true,
	onDismiss,
} ) {
	if ( ! message ) {
		return null;
	}

	return (
		<Notice
			status={ type }
			isDismissible={ isDismissible }
			onDismiss={ onDismiss }
		>
			{ message }
		</Notice>
	);
}
