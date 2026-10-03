/**
 * Regression tests for the initial-tab plumbing (FIX 4).
 *
 * admin/views/react-dashboard.php is included by the Dashboard, Analytics AND
 * Settings admin screens, and it now emits `data-tab` on the mount point.
 * src/index.js reads that attribute and passes it to TabPanel. Before the fix
 * every one of those screens opened the Dashboard tab, so the Settings screen
 * never rendered "Save Settings" and the Analytics screen never rendered its
 * charts — the menu item looked broken.
 *
 * Two entry points are covered:
 *  - the real bootstrap, by dispatching DOMContentLoaded over a mount point that
 *    carries `data-tab`, exactly as admin/class-admin.php enqueues it;
 *  - the App component's `initialTab` prop directly.
 *
 * @package Nginx_Opcache_Manager
 */

import React from 'react';
import { render, screen, waitFor, act } from '@testing-library/react';

const mockApiFetch = Object.assign( jest.fn(), { use: jest.fn() } );
const mockCreateNonceMiddleware = jest.fn( () => jest.fn() );

jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: mockApiFetch,
	createNonceMiddleware: ( ...args ) => mockCreateNonceMiddleware( ...args ),
} ) );

jest.mock( 'react-chartjs-2', () => ( {
	__esModule: true,
	Line: () => null,
	Bar: () => null,
	Doughnut: () => null,
} ) );

jest.mock( 'chart.js', () => {
	const scale = { id: 'scale' };
	return {
		Chart: { register: jest.fn() },
		CategoryScale: scale,
		LinearScale: scale,
		PointElement: scale,
		LineElement: scale,
		BarElement: scale,
		ArcElement: scale,
		Title: scale,
		Tooltip: scale,
		Legend: scale,
		Filler: scale,
	};
} );

window.nomData = { nonce: 'test-rest-nonce' };

const { App, getInitialTab } = require( '../index' );

const STATS_RESPONSE = {
	nginx: { cache_size: 1048576, cached_files: 42 },
	opcache: { hit_rate: 87.5, memory_usage: 40.2 },
};

const LOGS_RESPONSE = { logs: [] };

const SETTINGS_RESPONSE = {
	nginx_cache_enabled: true,
	nginx_cache_path: '/var/run/nginx-cache',
	schedule_enabled: false,
	available_intervals: {},
	available_targets: {},
};

const ANALYTICS_RESPONSE = {
	charts: {
		labels: [],
		hits: [],
		misses: [],
		memory: [],
		cache_size: [],
		files: [],
	},
};

beforeEach( () => {
	mockApiFetch.mockReset();
	mockApiFetch.mockImplementation( ( { path } ) => {
		switch ( path ) {
			case '/nom/v1/stats':
				return Promise.resolve( STATS_RESPONSE );
			case '/nom/v1/logs':
				return Promise.resolve( LOGS_RESPONSE );
			case '/nom/v1/settings':
				return Promise.resolve( SETTINGS_RESPONSE );
			case '/nom/v1/analytics':
				return Promise.resolve( ANALYTICS_RESPONSE );
			default:
				return Promise.resolve( {} );
		}
	} );
} );

afterEach( () => {
	const root = document.getElementById( 'nom-react-root' );
	if ( root ) {
		root.remove();
	}
} );

/**
 * Mount the app the way src/index.js does on DOMContentLoaded.
 *
 * @param {string} [tab] Value for the mount point's data-tab attribute.
 * @return {Promise<void>} Resolves once React has rendered.
 */
async function bootstrapWithTab( tab ) {
	const root = document.createElement( 'div' );
	root.id = 'nom-react-root';
	if ( tab !== undefined ) {
		root.setAttribute( 'data-tab', tab );
	}
	document.body.appendChild( root );

	// The bootstrap calls createRoot().render(), which is outside RTL's act()
	// scope; wrapping the event keeps the concurrent render inside act.
	await act( async () => {
		document.dispatchEvent( new Event( 'DOMContentLoaded' ) );
	} );
}

describe( 'bootstrap reads data-tab from the mount point (FIX 4)', () => {
	it( 'opens the Settings screen with the Save Settings control', async () => {
		await bootstrapWithTab( 'settings' );

		// Pre-fix the Settings screen rendered the Dashboard instead, so this
		// control was never on screen and settings could not be saved at all.
		await waitFor( () =>
			expect(
				screen.getByRole( 'button', { name: 'Save Settings' } )
			).toBeTruthy()
		);
		expect( screen.queryByText( 'Cache Manager Dashboard' ) ).toBeNull();
	} );

	it( 'opens the Analytics screen', async () => {
		await bootstrapWithTab( 'analytics' );

		// The Analytics *page*, not the always-present Analytics tab button.
		await waitFor( () =>
			expect(
				screen.getByRole( 'heading', { name: 'Analytics' } )
			).toBeTruthy()
		);
		expect( screen.queryByText( 'Cache Manager Dashboard' ) ).toBeNull();
		expect(
			mockApiFetch.mock.calls
				.map( ( [ options ] ) => options.path )
				.filter( ( path ) => path === '/nom/v1/analytics' )
		).toHaveLength( 1 );
	} );

	it( 'opens the Dashboard screen when data-tab is absent', async () => {
		await bootstrapWithTab();

		await waitFor( () =>
			expect( screen.getByText( 'Cache Manager Dashboard' ) ).toBeTruthy()
		);
	} );

	it( 'falls back to the Dashboard screen for an unrecognised data-tab', async () => {
		await bootstrapWithTab( 'not-a-tab' );

		await waitFor( () =>
			expect( screen.getByText( 'Cache Manager Dashboard' ) ).toBeTruthy()
		);
	} );
} );

describe( 'getInitialTab resolution (FIX 4)', () => {
	/**
	 * Build a mount point carrying the given data-tab value.
	 *
	 * @param {string|undefined} tab Value for data-tab, or omit the attribute.
	 * @return {HTMLElement} The mount point.
	 */
	function mountPoint( tab ) {
		const element = document.createElement( 'div' );
		if ( tab !== undefined ) {
			element.setAttribute( 'data-tab', tab );
		}
		return element;
	}

	it.each( [ 'dashboard', 'analytics', 'settings' ] )(
		'returns %s for a matching data-tab',
		( tab ) => {
			expect( getInitialTab( mountPoint( tab ) ) ).toBe( tab );
		}
	);

	it( 'returns dashboard when data-tab is absent', () => {
		expect( getInitialTab( mountPoint() ) ).toBe( 'dashboard' );
	} );

	it( 'returns dashboard for an unrecognised data-tab', () => {
		expect( getInitialTab( mountPoint( 'not-a-tab' ) ) ).toBe(
			'dashboard'
		);
	} );

	it( 'returns dashboard for a case-mismatched data-tab', () => {
		expect( getInitialTab( mountPoint( 'Settings' ) ) ).toBe( 'dashboard' );
	} );

	it( 'returns dashboard when the root element is null', () => {
		expect( getInitialTab( null ) ).toBe( 'dashboard' );
	} );
} );

describe( 'App initialTab prop (FIX 4)', () => {
	it( 'renders the Settings page for initialTab="settings"', async () => {
		render( <App initialTab="settings" /> );

		await waitFor( () =>
			expect(
				screen.getByRole( 'button', { name: 'Save Settings' } )
			).toBeTruthy()
		);
		expect( screen.queryByText( 'Cache Manager Dashboard' ) ).toBeNull();
	} );

	it( 'renders the Analytics page for initialTab="analytics"', async () => {
		render( <App initialTab="analytics" /> );

		await waitFor( () =>
			expect(
				screen.getByRole( 'heading', { name: 'Analytics' } )
			).toBeTruthy()
		);
		expect( screen.queryByText( 'Cache Manager Dashboard' ) ).toBeNull();
	} );

	it( 'renders the Dashboard page when initialTab is not supplied', async () => {
		render( <App /> );

		await waitFor( () =>
			expect( screen.getByText( 'Cache Manager Dashboard' ) ).toBeTruthy()
		);
	} );

	it( 'renders the Dashboard page for an unrecognised initialTab', async () => {
		render( <App initialTab="nope" /> );

		await waitFor( () =>
			expect( screen.getByText( 'Cache Manager Dashboard' ) ).toBeTruthy()
		);
	} );
} );
