/**
 * Smoke test for the React admin panel.
 *
 * Mounts the real App against a mocked @wordpress/api-fetch transport, so the
 * production REST client (src/utils/api.js) still runs: request paths, the
 * nonce middleware registration, and the WP REST error-unwrapping path are all
 * exercised. This is the regression net for the nonce defect that made every
 * nom/v1 request fail with rest_cookie_invalid_nonce (403).
 *
 * @package Nginx_Opcache_Manager
 */

import { render, screen } from '@testing-library/react';

// Real apiFetch exposes `.use()` for middleware registration; the mock needs it
// too or src/utils/api.js throws on import.
const mockApiFetch = Object.assign( jest.fn(), { use: jest.fn() } );
const mockCreateNonceMiddleware = jest.fn( () => jest.fn() );

jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: mockApiFetch,
	createNonceMiddleware: ( ...args ) => mockCreateNonceMiddleware( ...args ),
} ) );

// Chart.js needs a real canvas; jsdom has none.
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

const STATS_RESPONSE = {
	nginx: { cache_size: 1048576, cached_files: 42 },
	opcache: { hit_rate: 87.5, used_memory: 52428800, cached_scripts: 1200 },
};

const LOGS_RESPONSE = {
	logs: [
		{
			timestamp: '2026-10-03 10:00:00',
			action: 'purge',
			url: '/',
			file_path: '-',
			method: 'GET',
		},
	],
};

const NONCE = 'test-rest-nonce';

// src/utils/api.js reads this at import time, so it must be set before the
// module under test is required. Requiring once (no resetModules) also keeps a
// single React copy shared with @testing-library/react.
window.nomData = { nonce: NONCE };

const { App } = require( '../index' );

describe( 'admin panel REST contract', () => {
	beforeEach( () => {
		mockApiFetch.mockReset();

		mockApiFetch.mockImplementation( ( { path } ) => {
			if ( path === '/nom/v1/stats' ) {
				return Promise.resolve( STATS_RESPONSE );
			}
			if ( path === '/nom/v1/logs' ) {
				return Promise.resolve( LOGS_RESPONSE );
			}
			return Promise.resolve( {} );
		} );
	} );

	it( 'registers the REST nonce middleware from the localized nomData', () => {
		expect( mockCreateNonceMiddleware ).toHaveBeenCalledWith( NONCE );
	} );

	it( 'requests the nom/v1 endpoints the PHP routes expose', async () => {
		render( <App /> );

		await screen.findByText( 'Cache Manager Dashboard' );

		const paths = mockApiFetch.mock.calls.map(
			( [ options ] ) => options.path
		);
		expect( paths ).toContain( '/nom/v1/stats' );
		expect( paths ).toContain( '/nom/v1/logs' );
	} );

	it( 'renders values returned by the stats endpoint', async () => {
		render( <App /> );

		await screen.findByText( 'Cache Manager Dashboard' );

		// 87.5% hit rate and 42 cached files come straight from the fixture.
		expect( screen.getByText( '87.5%' ) ).toBeTruthy();
		expect( screen.getByText( '42' ) ).toBeTruthy();
	} );

	it( 'surfaces the server message when the REST call fails', async () => {
		mockApiFetch.mockImplementation( ( { path } ) => {
			if ( path === '/nom/v1/stats' ) {
				return Promise.reject( {
					status: 403,
					data: {
						code: 'rest_cookie_invalid_nonce',
						message: 'Cookie check failed',
					},
				} );
			}
			return Promise.resolve( LOGS_RESPONSE );
		} );

		render( <App /> );

		// The unwrapped WP REST message, not apiFetch's generic 403 text.
		await screen.findByText( 'Cookie check failed' );
		// NoticeComponent renders the message in more than one node.
		expect(
			screen.getAllByText( 'Cookie check failed' ).length
		).toBeGreaterThan( 0 );
	} );
} );
