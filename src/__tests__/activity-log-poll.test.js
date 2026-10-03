/**
 * Regression tests for the activity-log poll and the notices around it.
 *
 * Three things are covered here, all of which the poll's notice guard governs:
 *
 * R4 — the guard's precedence rules. `loadLogs()` runs on a 30s interval, so an
 * unconditional `setNotice` would rewrite the same banner every 30s forever.
 * Suppressing whenever *any* notice is already on screen is just as wrong: a
 * success banner left up by an action would mask an endpoint that has been
 * failing indefinitely. Suppression is therefore conditional: an identical
 * failure is not re-announced, and a success notice wins only while it is still
 * the fresh outcome of an action (within one poll window).
 *
 * R5 — a failed reload *after* a successful action. The action's own success
 * banner is genuine and must keep the server's wording, but the failed reload
 * still has to be reported, otherwise the user reads a green banner over a
 * list that never refreshed.
 *
 * R7 — the poll timer itself: it fires at the interval and is torn down on
 * unmount.
 *
 * @package Nginx_Opcache_Manager
 */

import React from 'react';
import {
	render,
	screen,
	fireEvent,
	act,
	waitFor,
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

/** Mirrors LOG_POLL_INTERVAL in src/index.js. */
const POLL_INTERVAL = 30000;

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

const LOGS_FAILURE = {
	status: 500,
	data: {
		code: 'nom_logs_failed',
		message: 'Log read blew up',
	},
};

const REFRESH_FAILURE_MESSAGE =
	'The activity log below could not be refreshed and may be out of date.';

/**
 * The rendered app subtree.
 *
 * The a11y package's "speak region" mirror nodes are appended to
 * document.body and survive RTL cleanup, so scoping to the app root keeps
 * assertions about what the user can actually see.
 *
 * @return {HTMLElement} App root element.
 */
function app() {
	return document.querySelector( '.nom-app' );
}

/**
 * How many apiFetch calls have been made for the activity-log endpoint.
 *
 * @return {number} Call count.
 */
function logRequests() {
	return mockApiFetch.mock.calls
		.map( ( [ options ] ) => options.path )
		.filter( ( value ) => value === '/nom/v1/logs' ).length;
}

/**
 * A clock the tests move by hand.
 *
 * The recency half of the notice guard is defined in terms of `Date.now()`.
 * Jest's fake timers advance `Date` in lockstep with the timer queue, so
 * advancing 30s to fire the poll would *also* push the success notice to
 * exactly the freshness boundary at the same moment. `Date` is therefore left
 * unfaked and stubbed separately, which makes "fresh" and "stale" exact and
 * independent of the poll's cadence.
 */
let now;

/**
 * Advance the fake timer queue, letting React re-render afterwards.
 *
 * @param {number} ms Milliseconds to advance by.
 * @return {Promise<void>} Resolves once React has settled.
 */
async function advance( ms ) {
	await act( async () => {
		jest.advanceTimersByTime( ms );
	} );
}

/**
 * Mount the dashboard and wait for the first stats + log reads to settle.
 *
 * @param {Function} [api] apiFetch implementation to install before mounting.
 * @return {Object} RTL render result.
 */
async function mountDashboard( api ) {
	if ( api ) {
		mockApiFetch.mockImplementation( api );
	}

	const renderResult = render( <App /> );
	await flush();
	return renderResult;
}

/**
 * Let pending promises settle and React re-render.
 *
 * Deliberately not RTL's `waitFor`/`findBy*`: those poll on a timer and, under
 * fake timers, advance the clock themselves. Any test that asserts on the poll
 * interval needs the clock to have moved by exactly what it moved, and a
 * timer-based wait would quietly move it by something else first.
 *
 * @return {Promise<void>} Resolves once React has settled.
 */
async function flush() {
	await act( async () => {
		await Promise.resolve();
		await Promise.resolve();
	} );
}

/**
 * Resolve stats, resolve or reject logs, and settle every other POST.
 *
 * @param {boolean} logsFail Whether the activity-log read should fail.
 * @return {Function} apiFetch implementation.
 */
function apiWhere( logsFail ) {
	return ( { path } ) => {
		if ( path === '/nom/v1/stats' ) {
			return Promise.resolve( STATS_RESPONSE );
		}
		if ( path === '/nom/v1/logs' ) {
			return logsFail
				? Promise.reject( LOGS_FAILURE )
				: Promise.resolve( LOGS_RESPONSE );
		}
		return Promise.resolve( { message: 'Cache cleared.' } );
	};
}

beforeEach( () => {
	now = Date.now();
	jest.spyOn( Date, 'now' ).mockImplementation( () => now );

	// Installed BEFORE any render so the component's setInterval is a fake one.
	// Switching to fake timers after mount would leave the real interval
	// running and every timing assertion below would pass vacuously.
	jest.useFakeTimers( { doNotFake: [ 'Date' ] } );

	mockApiFetch.mockReset();
	mockApiFetch.mockImplementation( apiWhere( false ) );

	// wp-util present: these tests are about notice precedence, and leaving
	// window.wp absent would trip @wordpress/jest-console's unexpected
	// console.warn failure for a message these tests do not assert.
	window.wp = { confirm: jest.fn( () => true ) };
} );

afterEach( () => {
	jest.useRealTimers();
	jest.restoreAllMocks();
	delete window.wp;
} );

describe( 'activity-log poll timer (R7)', () => {
	it( 're-reads the activity log after one poll interval', async () => {
		await mountDashboard();
		expect( logRequests() ).toBe( 1 );

		await advance( POLL_INTERVAL );

		expect( logRequests() ).toBe( 2 );
	} );

	it( 'does not re-read again before the interval has elapsed', async () => {
		await mountDashboard();

		await advance( POLL_INTERVAL - 1 );

		expect( logRequests() ).toBe( 1 );
	} );

	it( 'keeps polling on the same cadence over several intervals', async () => {
		await mountDashboard();

		await advance( POLL_INTERVAL );
		await advance( POLL_INTERVAL );
		await advance( POLL_INTERVAL );

		expect( logRequests() ).toBe( 4 );
	} );

	it( 'stops polling once the component unmounts', async () => {
		const { unmount } = await mountDashboard();

		unmount();
		await advance( POLL_INTERVAL * 3 );

		// Without the clearInterval in the effect cleanup this is 4: a leaked
		// timer keeps hitting the REST API from a tree that is gone.
		expect( logRequests() ).toBe( 1 );
	} );
} );

describe( 'poll failures against a success notice (R4, R5)', () => {
	it( 'keeps a fresh success banner and separately warns that the reload failed', async () => {
		await mountDashboard();

		// The clear succeeds; the follow-up log read does not.
		mockApiFetch.mockImplementation( apiWhere( true ) );

		fireEvent.click(
			screen.getByRole( 'button', { name: /Clear Nginx Cache/ } )
		);

		// R5: the success banner keeps the server's own wording...
		await waitFor( () =>
			expect( app().textContent ).toContain( 'Cache cleared.' )
		);
		// ...and the failed reload is reported rather than silently swallowed.
		await waitFor( () =>
			expect( app().textContent ).toContain( REFRESH_FAILURE_MESSAGE )
		);
		// The reload's failure must not masquerade as the action's own.
		expect( app().textContent ).not.toContain( 'Log read blew up' );
	} );

	it( 'lets a stale success banner yield to a poll failure', async () => {
		await mountDashboard();

		mockApiFetch.mockImplementation( apiWhere( true ) );

		fireEvent.click(
			screen.getByRole( 'button', { name: /Clear Nginx Cache/ } )
		);
		await waitFor( () =>
			expect( app().textContent ).toContain( 'Cache cleared.' )
		);

		// Push the clock past the one-poll-window freshness bound, then let the
		// poll fire. Suppression keyed on "some notice exists" would keep the
		// success banner here and hide a permanently broken endpoint.
		now += POLL_INTERVAL * 2;
		await advance( POLL_INTERVAL );

		await waitFor( () =>
			expect( app().textContent ).toContain( 'Log read blew up' )
		);
	} );

	it( 'keeps a fresh success banner across the very next poll failure', async () => {
		await mountDashboard();

		let logsFail = false;
		mockApiFetch.mockImplementation( apiWhere( logsFail ) );

		logsFail = true;
		fireEvent.click(
			screen.getByRole( 'button', { name: /Clear Nginx Cache/ } )
		);
		await waitFor( () =>
			expect( app().textContent ).toContain( 'Cache cleared.' )
		);

		// Clock is still inside the freshness window: the banner is the
		// authoritative outcome of what the user just did, so it wins.
		await advance( POLL_INTERVAL );

		expect( logRequests() ).toBe( 3 ); // mount, action reload, poll
		expect( app().textContent ).toContain( 'Cache cleared.' );
		expect( app().textContent ).not.toContain( 'Log read blew up' );
	} );
} );

describe( 'repeat poll failures (R4)', () => {
	it( 'reports a poll failure and keeps exactly one error banner on screen', async () => {
		await mountDashboard( apiWhere( true ) );

		await waitFor( () =>
			expect( app().textContent ).toContain( 'Log read blew up' )
		);

		await advance( POLL_INTERVAL );
		await advance( POLL_INTERVAL );

		// The identical failure is not re-announced, so the banner count cannot
		// creep upward every 30s for as long as the endpoint stays broken.
		expect(
			app().querySelectorAll( '.components-notice.is-error' )
		).toHaveLength( 1 );
	} );

	it( 'lets a later poll failure report itself again once the banner is dismissed', async () => {
		await mountDashboard( apiWhere( true ) );

		await waitFor( () =>
			expect( app().textContent ).toContain( 'Log read blew up' )
		);

		fireEvent.click( dismissButton( app() ) );
		await waitFor( () =>
			expect( app().textContent ).not.toContain( 'Log read blew up' )
		);

		await advance( POLL_INTERVAL );

		// Dismissal is not a permanent mute: a read that is still failing has to
		// be able to speak up again.
		await waitFor( () =>
			expect( app().textContent ).toContain( 'Log read blew up' )
		);
	} );
} );

/**
 * The dismiss control of the first notice inside the app subtree.
 *
 * @param {HTMLElement} root App root element.
 * @return {HTMLElement} Dismiss button.
 */
function dismissButton( root ) {
	return root.querySelector( '.components-notice__dismiss' );
}
