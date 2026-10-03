/**
 * Loading spinner component.
 *
 * @package Nginx_Opcache_Manager
 */

import React from 'react';
import { Spinner } from '@wordpress/components';

/**
 * Full-page loading spinner.
 *
 * @return {JSX.Element} Loading spinner component.
 */
export default function LoadingSpinner() {
	return (
		<div className="nom-loading">
			<Spinner />
			<p>Yukleniyor...</p>
		</div>
	);
}
