/**
 * Regression tests for the notice type plumbing (FIX 3).
 *
 * src/components/Notice.js destructured a prop named `status` while every call
 * site in src/index.js passes `type`. `status` therefore always took its
 * 'info' default and EVERY notice — including REST failures — rendered as a
 * blue informational banner. The prop is now named `type`.
 *
 * The second half of the fix is in DashboardPage's activity-log loader: it must
 * not replace a success notice an action handler has just set, while a
 * dismissed notice lets a later poll failure report itself again.
 *
 * @package Nginx_Opcache_Manager
 */

import React from 'react';
import {
	render,
	screen,
	fireEvent,
	waitFor,
	within,
} from '@testing-library/react';

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

const NoticeComponent = require( '../components/Notice' ).default;
const { App } = require( '../index' );

const STATS_RESPONSE = {
	nginx: { cache_size: 1048576, cached_files: 42 },
	opcache: { hit_rate: 87.5, memory_usage: 40.2 },
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

/**
 * The rendered app subtree; a11y speak mirrors outside it are never cleaned up.
 *
 * @return {HTMLElement} App root element.
 */
function app() {
	return document.querySelector( '.nom-app' );
}

describe( 'notice component type prop (FIX 3)', () => {
	it( 'renders an error notice with error styling', () => {
		const { container } = render(
			<NoticeComponent type="error" message="Purge failed" />
		);

		// Pre-fix `type` was ignored, status defaulted to 'info', and this
		// element carried .is-info instead.
		const notice = container.querySelector( '.components-notice' );
		expect( notice ).not.toBeNull();
		expect( notice.className ).toContain( 'is-error' );
		expect( notice.className ).not.toContain( 'is-info' );
	} );

	it( 'renders a success notice with success styling', () => {
		const { container } = render(
			<NoticeComponent type="success" message="Cache cleared" />
		);

		expect(
			container.querySelector( '.components-notice' ).className
		).toContain( 'is-success' );
	} );

	it( 'renders an info notice without error styling', () => {
		const { container } = render(
			<NoticeComponent message="Nothing to report" />
		);

		const notice = container.querySelector( '.components-notice' );
		expect( notice.className ).toContain( 'is-info' );
		expect( notice.className ).not.toContain( 'is-error' );
	} );

	it( 'renders nothing without a message', () => {
		const { container } = render( <NoticeComponent type="error" /> );

		expect( container.querySelector( '.components-notice' ) ).toBeNull();
	} );
} );

describe( 'DashboardPage notice routing (FIX 3)', () => {
	beforeEach( () => {
		mockApiFetch.mockReset();
		mockApiFetch.mockImplementation( ( { path } ) => {
			if ( path === '/nom/v1/stats' ) {
				return Promise.resolve( STATS_RESPONSE );
			}
			if ( path === '/nom/v1/logs' ) {
				return Promise.resolve( LOGS_RESPONSE );
			}
			return Promise.resolve( { message: 'Cache cleared.' } );
		} );

		delete window.wp;
		window.confirm = jest.fn( () => true );
	} );

	afterEach( () => {
		delete window.wp;
		delete window.confirm;
	} );

	it( 'styles a REST failure as an error, not as information', async () => {
		mockApiFetch.mockImplementation( ( { path } ) => {
			if ( path === '/nom/v1/stats' ) {
				return Promise.reject( {
					status: 500,
					data: {
						code: 'nom_stats_failed',
						message: 'Stats blew up',
					},
				} );
			}
			return Promise.resolve( LOGS_RESPONSE );
		} );

		render( <App /> );

		await within( app() ).findByText( 'Stats blew up' );

		expect(
			app().querySelector( '.components-notice.is-error' )
		).not.toBeNull();
	} );

	it( 'keeps a success notice when the activity-log reload afterwards fails', async () => {
		let logsFail = false;

		mockApiFetch.mockImplementation( ( { path } ) => {
			if ( path === '/nom/v1/stats' ) {
				return Promise.resolve( STATS_RESPONSE );
			}
			if ( path === '/nom/v1/logs' ) {
				return logsFail
					? Promise.reject( {
							status: 500,
							data: {
								code: 'nom_logs_failed',
								message: 'Log read blew up',
							},
					  } )
					: Promise.resolve( LOGS_RESPONSE );
			}
			return Promise.resolve( { message: 'Cache cleared.' } );
		} );

		render( <App /> );
		await screen.findByText( 'Cache Manager Dashboard' );

		// wp-util is present here, so no fallback warning is involved; this test
		// is about notice precedence only.
		window.wp = { confirm: jest.fn( () => true ) };

		// The action succeeds and then re-reads both stats and logs.
		logsFail = true;
		fireEvent.click(
			screen.getByRole( 'button', { name: /Clear Nginx Cache/ } )
		);

		await within( app() ).findByText( 'Cache cleared.' );

		// The failed reload must not replace the user's success notice: the
		// poll guard keeps whatever notice is already on screen.
		await waitFor( () =>
			expect( requestsFor( '/nom/v1/logs' ) ).toBe( 2 )
		);
		expect( app().textContent ).toContain( 'Cache cleared.' );
		expect( app().textContent ).not.toContain( 'Log read blew up' );
	} );
} );

/**
 * Number of apiFetch calls for a path.
 *
 * @param {string} path REST path.
 * @return {number} Call count.
 */
function requestsFor( path ) {
	return mockApiFetch.mock.calls
		.map( ( [ options ] ) => options.path )
		.filter( ( value ) => value === path ).length;
}
