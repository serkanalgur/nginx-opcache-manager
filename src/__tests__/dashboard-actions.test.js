/**
 * Regression tests for the destructive dashboard actions and the activity-log
 * Clear button.
 *
 * FIX 1 — confirmAction(): the helper used to `return false` when
 * `window.wp.confirm` was missing (wp-util not actually loaded), which silently
 * aborted BOTH destructive buttons with no prompt, no notice and no console
 * output — indistinguishable from the buttons being dead. It now falls back to
 * the native `window.confirm()`.
 *
 * FIX 2 — `<ActivityLog onClear={loadLogs} />`: the Clear button re-fetched the
 * log instead of clearing it, so POST /nom/v1/logs/clear was never issued. The
 * handler now issues the delete — and, because it is an irreversible wipe of
 * the audit trail, behind the same confirmation the other two destructive
 * actions use.
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

const { App } = require( '../index' );

const STATS_RESPONSE = {
	nginx: { cache_size: 1048576, cached_files: 42 },
	opcache: { hit_rate: 87.5, memory_usage: 40.2, cached_scripts: 1200 },
};

// At least one entry: ActivityLog disables its Clear button when logs is empty.
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

const FALLBACK_WARNING =
	'NOM: wp-util/wp.confirm unavailable; falling back to native confirm().';

/**
 * The rendered app subtree.
 *
 * The a11y package's "speak region" mirror nodes are appended straight to
 * document.body and they survive RTL cleanup, so every notice message also
 * exists in a stale node outside the container. Scoping queries to the app
 * root keeps assertions about what the user can actually see.
 *
 * @return {HTMLElement} App root element.
 */
function app() {
	return document.querySelector( '.nom-app' );
}

/**
 * Requests made so far for one path, optionally filtered by HTTP method.
 *
 * @param {string} path     REST path.
 * @param {string} [method] HTTP method.
 * @return {Array<Object>} Matching apiFetch options.
 */
function requestsFor( path, method ) {
	return mockApiFetch.mock.calls
		.map( ( [ options ] ) => options )
		.filter(
			( options ) =>
				options.path === path &&
				( ! method || options.method === method )
		);
}

/**
 * A promise whose settlement the test controls, for asserting in-flight UI.
 *
 * @return {Object} Deferred promise plus resolve/reject handles.
 */
function deferred() {
	let resolve;
	let reject;
	const promise = new Promise( ( res, rej ) => {
		resolve = res;
		reject = rej;
	} );
	return { promise, resolve, reject };
}

describe( 'destructive dashboard actions without wp.confirm (FIX 1)', () => {
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

		// wp-util did not load: window.wp is absent entirely.
		delete window.wp;
		window.confirm = jest.fn( () => true );
	} );

	afterEach( () => {
		delete window.wp;
		delete window.confirm;
	} );

	it( 'clears the Nginx cache when wp.confirm is missing and the user accepts the native dialog', async () => {
		render( <App /> );
		await screen.findByText( 'Cache Manager Dashboard' );

		fireEvent.click(
			screen.getByRole( 'button', { name: /Clear Nginx Cache/ } )
		);

		// Pre-fix: confirmAction() returned false and this POST never fired.
		await waitFor( () =>
			expect( requestsFor( '/nom/v1/nginx/clear', 'POST' ) ).toHaveLength(
				1
			)
		);
		expect( window.confirm ).toHaveBeenCalledWith(
			expect.stringContaining( 'clear the Nginx cache' )
		);
		await within( app() ).findByText( 'Cache cleared.' );
		expect( console ).toHaveWarnedWith( FALLBACK_WARNING );
	} );

	it( 'resets Opcache when wp.confirm is missing and the user accepts the native dialog', async () => {
		render( <App /> );
		await screen.findByText( 'Cache Manager Dashboard' );

		fireEvent.click(
			screen.getByRole( 'button', { name: /Reset Opcache/ } )
		);

		// Pre-fix: also silently aborted here.
		await waitFor( () =>
			expect(
				requestsFor( '/nom/v1/opcache/reset', 'POST' )
			).toHaveLength( 1 )
		);
		expect( window.confirm ).toHaveBeenCalledWith(
			expect.stringContaining( 'reset Opcache' )
		);
		expect( console ).toHaveWarnedWith( FALLBACK_WARNING );
	} );

	it( 'warns that wp.confirm was unavailable, naming the fallback', async () => {
		render( <App /> );
		await screen.findByText( 'Cache Manager Dashboard' );

		fireEvent.click(
			screen.getByRole( 'button', { name: /Clear Nginx Cache/ } )
		);

		// The warning is the only signal an admin gets that wp-util did not load.
		await waitFor( () =>
			expect( console ).toHaveWarnedWith( FALLBACK_WARNING )
		);
	} );

	it( 'aborts clearing the Nginx cache when the user cancels the native dialog', async () => {
		window.confirm = jest.fn( () => false );

		render( <App /> );
		await screen.findByText( 'Cache Manager Dashboard' );

		fireEvent.click(
			screen.getByRole( 'button', { name: /Clear Nginx Cache/ } )
		);

		// Flush the handler's microtasks, then assert nothing was sent.
		await waitFor( () => expect( window.confirm ).toHaveBeenCalled() );
		expect( requestsFor( '/nom/v1/nginx/clear' ) ).toHaveLength( 0 );
		expect( console ).toHaveWarnedWith( FALLBACK_WARNING );
	} );

	it( 'aborts resetting Opcache when the user cancels the native dialog', async () => {
		window.confirm = jest.fn( () => false );

		render( <App /> );
		await screen.findByText( 'Cache Manager Dashboard' );

		fireEvent.click(
			screen.getByRole( 'button', { name: /Reset Opcache/ } )
		);

		await waitFor( () => expect( window.confirm ).toHaveBeenCalled() );
		expect( requestsFor( '/nom/v1/opcache/reset' ) ).toHaveLength( 0 );
		expect( console ).toHaveWarnedWith( FALLBACK_WARNING );
	} );

	it( 'still prefers wp.confirm over the native dialog when wp-util is present', async () => {
		window.wp = { confirm: jest.fn( () => true ) };

		render( <App /> );
		await screen.findByText( 'Cache Manager Dashboard' );

		fireEvent.click(
			screen.getByRole( 'button', { name: /Clear Nginx Cache/ } )
		);

		await waitFor( () =>
			expect( requestsFor( '/nom/v1/nginx/clear', 'POST' ) ).toHaveLength(
				1
			)
		);
		expect( window.wp.confirm ).toHaveBeenCalledTimes( 1 );
		expect( window.confirm ).not.toHaveBeenCalled();
		// No fallback warning when the preferred implementation exists.
		expect( console ).not.toHaveWarned();
	} );
} );

describe( 'activity log Clear button (FIX 2)', () => {
	let clearLogsRequest;

	beforeEach( () => {
		clearLogsRequest = deferred();
		mockApiFetch.mockReset();
		mockApiFetch.mockImplementation( ( { path } ) => {
			if ( path === '/nom/v1/stats' ) {
				return Promise.resolve( STATS_RESPONSE );
			}
			if ( path === '/nom/v1/logs' ) {
				return Promise.resolve( LOGS_RESPONSE );
			}
			if ( path === '/nom/v1/logs/clear' ) {
				return clearLogsRequest.promise;
			}
			return Promise.resolve( { message: 'done' } );
		} );

		// wp-util is loaded here. These tests are about the Clear button, not the
		// confirm fallback, and leaving window.wp absent would make every one of
		// them trip @wordpress/jest-console's "unexpected console.warn" failure
		// for a message they are not asserting.
		window.wp = { confirm: jest.fn( () => true ) };
		window.confirm = jest.fn( () => true );
	} );

	afterEach( () => {
		delete window.wp;
		delete window.confirm;
	} );

	it( 'issues POST /nom/v1/logs/clear when the Clear button is clicked', async () => {
		render( <App /> );
		await screen.findByText( 'Cache Manager Dashboard' );

		clearLogsRequest.resolve( { message: 'cleared' } );
		fireEvent.click( screen.getByRole( 'button', { name: /Temizle/ } ) );

		await within( app() ).findByText( 'Activity log cleared.' );

		// Pre-fix the button was wired to loadLogs(), so this POST never fired
		// and only a redundant GET /nom/v1/logs was sent.
		expect( requestsFor( '/nom/v1/logs/clear', 'POST' ) ).toHaveLength( 1 );
		// Initial load plus the reload the clear handler performs.
		expect( requestsFor( '/nom/v1/logs', 'GET' ) ).toHaveLength( 2 );
	} );

	it( 'confirms before clearing, naming the activity log', async () => {
		render( <App /> );
		await screen.findByText( 'Cache Manager Dashboard' );

		fireEvent.click( screen.getByRole( 'button', { name: /Temizle/ } ) );

		// Clearing the log is an irreversible delete of the audit trail, so it is
		// gated the same way Clear Nginx Cache and Reset Opcache are. The prompt
		// has to name what is being destroyed, not just say "are you sure".
		expect( window.wp.confirm ).toHaveBeenCalledWith(
			expect.stringContaining( 'activity log' )
		);
	} );

	it( 'issues no request at all when the confirmation is declined', async () => {
		window.wp = { confirm: jest.fn( () => false ) };

		render( <App /> );
		await screen.findByText( 'Cache Manager Dashboard' );

		fireEvent.click( screen.getByRole( 'button', { name: /Temizle/ } ) );

		// Flush the handler, then assert the destructive request never left the
		// browser. Without the confirmation the audit log would be gone on the
		// first misclick, irreversibly.
		await waitFor( () => expect( window.wp.confirm ).toHaveBeenCalled() );
		await Promise.resolve();

		expect( requestsFor( '/nom/v1/logs/clear' ) ).toHaveLength( 0 );
		// Nor a silent GET standing in for the delete.
		expect( requestsFor( '/nom/v1/logs' ) ).toHaveLength( 1 );
	} );

	it( 'disables the Clear button while the clear request is in flight, then re-enables it', async () => {
		render( <App /> );
		await screen.findByText( 'Cache Manager Dashboard' );

		const clearButton = screen.getByRole( 'button', {
			name: /Temizle/,
		} );
		expect( clearButton.disabled ).toBe( false );

		fireEvent.click( clearButton );

		// setClearingLogs(true) runs before the await, so the button reports
		// itself pending (and busy) immediately after the click.
		expect( clearButton.disabled ).toBe( true );
		expect( clearButton.className ).toContain( 'is-busy' );

		clearLogsRequest.resolve( { message: 'cleared' } );

		await waitFor( () =>
			expect(
				screen.getByRole( 'button', { name: /Temizle/ } ).disabled
			).toBe( false )
		);
	} );

	it( 'shows an error notice when clearing the activity log fails', async () => {
		render( <App /> );
		await screen.findByText( 'Cache Manager Dashboard' );

		clearLogsRequest.reject( {
			status: 500,
			data: {
				code: 'nom_log_clear_failed',
				message: 'Could not clear the activity log',
			},
		} );

		fireEvent.click( screen.getByRole( 'button', { name: /Temizle/ } ) );

		await within( app() ).findByText( 'Could not clear the activity log' );

		// The failure must be styled as an error, not as information.
		expect(
			app().querySelector( '.components-notice.is-error' )
		).not.toBeNull();

		// The failure clears the pending state so the button is usable again.
		await waitFor( () =>
			expect(
				screen.getByRole( 'button', { name: /Temizle/ } ).disabled
			).toBe( false )
		);
	} );
} );
