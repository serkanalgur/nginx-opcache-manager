/**
 * Nginx Opcache Manager - React Admin Panel
 *
 * @package Nginx_Opcache_Manager
 */

import React from 'react';
import { useState, useEffect, useRef, createRoot } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	TabPanel,
	PanelRow,
	Button,
	ToggleControl,
	SelectControl,
	TextControl,
	Card,
	CardBody,
	CardHeader,
	Flex,
	FlexItem,
} from '@wordpress/components';
import {
	fetchStats,
	clearNginxCache,
	resetOpcache,
	fetchSettings,
	updateSettings,
	fetchLogs,
	clearLogs,
	fetchAnalytics,
} from './utils/api';
import { formatBytes, formatNumber } from './utils/helpers';
import ChartCard from './components/ChartCard';
import ActivityLog from './components/ActivityLog';
import LoadingSpinner from './components/LoadingSpinner';
import StatCard from './components/StatCard';
import NoticeComponent from './components/Notice';
import './style.css';

/**
 * How often the activity log is re-read, in milliseconds.
 *
 * Shared by the poll timer and the notice-suppression window so the two can
 * never drift apart.
 *
 * @type {number}
 */
const LOG_POLL_INTERVAL = 30000;

/**
 * Ask for confirmation before a destructive action.
 *
 * Prefers the `wp.confirm` implementation WordPress core ships in wp-util
 * (there is no npm `@wordpress/confirm` package); admin/class-admin.php
 * declares `wp-util` as a script dependency of this screen.
 *
 * If `wp.confirm` is nonetheless missing — another script registered it as a
 * dependency it never actually provides, or it failed to load — fall back to
 * the native dialog. Returning `false` here would abort every destructive
 * action with no prompt, no notice and no console output, which is
 * indistinguishable from the buttons being dead.
 *
 * @param {string} message Confirmation question.
 * @return {boolean} True when the user confirms.
 */
function confirmAction( message ) {
	if ( window.wp && typeof window.wp.confirm === 'function' ) {
		return Boolean( window.wp.confirm( message ) );
	}

	/*
	 * The console warning and the native dialog below are both intentional.
	 * The warning is the only signal that wp-util did not load, and suppressing
	 * the dialog would reintroduce the silent no-op this helper guards.
	 */
	// eslint-disable-next-line no-console
	console.warn(
		'NOM: wp-util/wp.confirm unavailable; falling back to native confirm().'
	);

	// eslint-disable-next-line no-alert
	return window.confirm( message );
}

/**
 * Dashboard Page Component
 */
function DashboardPage() {
	const [ stats, setStats ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ clearing, setClearing ] = useState( false );
	const [ resetting, setResetting ] = useState( false );
	const [ clearingLogs, setClearingLogs ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ refreshError, setRefreshError ] = useState( null );
	const [ logs, setLogs ] = useState( [] );

	// Timestamp of the last notice an action handler set. The activity-log
	// poll uses it to decide whether a success banner it finds on screen is
	// still the fresh outcome of the action the user just performed.
	const lastActionAt = useRef( 0 );

	useEffect( () => {
		loadStats();
		loadLogs();
		const interval = setInterval( loadLogs, LOG_POLL_INTERVAL );
		return () => clearInterval( interval );
	}, [] );

	const loadStats = async () => {
		try {
			setLoading( true );
			const data = await fetchStats();
			setStats( data );
		} catch ( error ) {
			setNotice( { type: 'error', message: error.message } );
		} finally {
			setLoading( false );
		}
	};

	/**
	 * Read the activity log.
	 *
	 * @param {Object}  [options]              - Load options.
	 * @param {boolean} [options.reportErrors] - Whether a failure should be
	 *                                         reported through the notice
	 *                                         area. Defaults to true, which
	 *                                         is what the poll wants. Action
	 *                                         handlers pass false because
	 *                                         they need to describe the
	 *                                         failure themselves.
	 * @return {Promise<boolean>} True when the log was read successfully.
	 */
	const loadLogs = async ( { reportErrors = true } = {} ) => {
		try {
			const data = await fetchLogs();
			setLogs( data.logs || [] );
			return true;
		} catch ( error ) {
			if ( ! reportErrors ) {
				return false;
			}

			/*
			 * This runs on a poll, so an unconditional setNotice would re-fire
			 * every 30s for as long as the endpoint stays broken. But the
			 * opposite extreme — suppressing whenever *any* notice is already
			 * on screen — is worse: a success banner left up by an action would
			 * hide an indefinitely failing endpoint until the user dismissed
			 * it, which is the same silent no-op this release exists to fix.
			 *
			 * Suppress only these two cases:
			 *  1. the identical failure is already on screen (re-firing would
			 *     just rewrite an identical banner);
			 *  2. a success banner that a *recent* action set, i.e. one that
			 *     arrived within one poll window of that action. It is the
			 *     authoritative outcome of what the user just did, so it wins
			 *     over a background read. Past that window it is stale history
			 *     and a live failure takes over.
			 */
			const failure = {
				type: 'error',
				message:
					error.message ||
					__(
						'Failed to load activity logs.',
						'nginx-opcache-manager'
					),
			};

			setNotice( ( current ) => {
				if (
					current &&
					current.type === failure.type &&
					current.message === failure.message
				) {
					return current;
				}

				const successIsFresh =
					current &&
					current.type === 'success' &&
					Date.now() - lastActionAt.current < LOG_POLL_INTERVAL;

				return successIsFresh ? current : failure;
			} );

			return false;
		}
	};

	const handleClearCache = async () => {
		if (
			! confirmAction( 'Are you sure you want to clear the Nginx cache?' )
		) {
			return;
		}
		try {
			setClearing( true );
			setRefreshError( null );
			const result = await clearNginxCache();
			lastActionAt.current = Date.now();
			setNotice( { type: 'success', message: result.message } );
			await loadStats();
			/*
			 * The action itself succeeded, so its success notice is genuine
			 * and must stay exactly as the server worded it. But the reload
			 * still has to be reported: without this the user reads a green
			 * banner over a list that never refreshed.
			 */
			if ( ! ( await loadLogs( { reportErrors: false } ) ) ) {
				setRefreshError(
					__(
						'The activity log below could not be refreshed and may be out of date.',
						'nginx-opcache-manager'
					)
				);
			}
		} catch ( error ) {
			setNotice( { type: 'error', message: error.message } );
		} finally {
			setClearing( false );
		}
	};

	const handleResetOpcache = async () => {
		if ( ! confirmAction( 'Are you sure you want to reset Opcache?' ) ) {
			return;
		}
		try {
			setResetting( true );
			const result = await resetOpcache();
			lastActionAt.current = Date.now();
			setNotice( { type: 'success', message: result.message } );
			await loadStats();
		} catch ( error ) {
			setNotice( { type: 'error', message: error.message } );
		} finally {
			setResetting( false );
		}
	};

	const handleClearLogs = async () => {
		// Clearing the log is an irreversible delete of the audit trail, so it
		// is gated the same way the other two destructive actions are.
		if (
			! confirmAction(
				__(
					'Are you sure you want to permanently clear the activity log?',
					'nginx-opcache-manager'
				)
			)
		) {
			return;
		}
		try {
			setClearingLogs( true );
			setRefreshError( null );
			await clearLogs();
			lastActionAt.current = Date.now();

			/*
			 * The delete already happened, so the outcome is never a plain
			 * "failed". If the follow-up read fails the list on screen is the
			 * pre-delete one, so say both things rather than showing a bare
			 * success banner over stale rows.
			 */
			if ( await loadLogs( { reportErrors: false } ) ) {
				setNotice( {
					type: 'success',
					message: __(
						'Activity log cleared.',
						'nginx-opcache-manager'
					),
				} );
			} else {
				setNotice( {
					type: 'warning',
					message: __(
						'Activity log cleared, but the refreshed list could not be loaded.',
						'nginx-opcache-manager'
					),
				} );
			}
		} catch ( error ) {
			setNotice( { type: 'error', message: error.message } );
		} finally {
			setClearingLogs( false );
		}
	};

	if ( loading ) {
		return <LoadingSpinner />;
	}

	const nginx = stats?.nginx || {};
	const opcache = stats?.opcache || {};

	return (
		<div className="nom-dashboard">
			<div className="nom-header">
				<h1>{ 'Cache Manager Dashboard' }</h1>
				<p className="nom-subtitle">
					{ 'Monitor and manage your Nginx cache and PHP Opcache' }
				</p>
			</div>

			{ notice && (
				<NoticeComponent
					type={ notice.type }
					message={ notice.message }
					onDismiss={ () => setNotice( null ) }
				/>
			) }

			{ refreshError && (
				<NoticeComponent
					type="warning"
					message={ refreshError }
					onDismiss={ () => setRefreshError( null ) }
				/>
			) }

			<Flex className="nom-actions-bar" justify="flex-end">
				<FlexItem>
					<Button
						variant="secondary"
						onClick={ handleClearCache }
						isBusy={ clearing }
						disabled={ clearing }
						icon="trash"
					>
						{ 'Clear Nginx Cache' }
					</Button>
				</FlexItem>
				<FlexItem>
					<Button
						variant="secondary"
						onClick={ handleResetOpcache }
						isBusy={ resetting }
						disabled={ resetting }
						icon="update"
					>
						{ 'Reset Opcache' }
					</Button>
				</FlexItem>
			</Flex>

			<div className="nom-stat-cards">
				<StatCard
					title={ 'Fastcgi / Nginx Cache' }
					icon="database"
					color="#2196F3"
					primary={ {
						value: formatBytes( nginx.cache_size || 0 ),
						label: 'Cache Boyutu',
					} }
					secondary={ {
						value: formatNumber( nginx.cached_files || 0 ),
						label: 'Dosya Sayisi',
					} }
				/>
				<StatCard
					title={ 'PHP Opcache' }
					icon="performance"
					color="#9C27B0"
					primary={ {
						value: `${ ( opcache.hit_rate || 0 ).toFixed( 1 ) }%`,
						label: 'Hit Rate',
					} }
					secondary={ {
						value: `${ ( opcache.memory_usage || 0 ).toFixed(
							1
						) }%`,
						label: 'Bellek Kullanimi',
					} }
				/>
			</div>

			<div className="nom-charts-grid">
				<ChartCard
					title={ 'Hit Rate' }
					icon="chart-bar"
					type="doughnut"
					data={ {
						labels: [ 'Hits', 'Misses' ],
						datasets: [
							{
								data: [
									opcache.hit_rate || 0,
									100 - ( opcache.hit_rate || 0 ),
								],
								backgroundColor: [ '#4CAF50', '#FF5722' ],
								borderWidth: 0,
							},
						],
					} }
					options={ {
						cutout: '70%',
						plugins: { legend: { display: false } },
						responsive: true,
						maintainAspectRatio: false,
					} }
				/>
				<ChartCard
					title={ 'Memory Usage' }
					icon="performance"
					type="doughnut"
					data={ {
						labels: [ 'Used', 'Free' ],
						datasets: [
							{
								data: [
									opcache.memory_usage || 0,
									100 - ( opcache.memory_usage || 0 ),
								],
								backgroundColor: [ '#9C27B0', '#E1BEE7' ],
								borderWidth: 0,
							},
						],
					} }
					options={ {
						cutout: '70%',
						plugins: { legend: { display: false } },
						responsive: true,
						maintainAspectRatio: false,
					} }
				/>
			</div>

			<ActivityLog
				logs={ logs }
				onClear={ handleClearLogs }
				isClearing={ clearingLogs }
			/>
		</div>
	);
}

/**
 * Analytics Page Component
 */
function AnalyticsPage() {
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		loadAnalytics();
	}, [] );

	const loadAnalytics = async () => {
		try {
			setLoading( true );
			const result = await fetchAnalytics();
			setData( result );
		} catch ( error ) {
			setNotice( { type: 'error', message: error.message } );
		} finally {
			setLoading( false );
		}
	};

	if ( loading ) {
		return <LoadingSpinner />;
	}

	const charts = data?.charts || {};

	return (
		<div className="nom-analytics">
			<div className="nom-header">
				<h1>{ 'Analytics' }</h1>
				<p className="nom-subtitle">
					{ 'Performance metrics and historical data' }
				</p>
			</div>

			{ notice && (
				<NoticeComponent
					type={ notice.type }
					message={ notice.message }
					onDismiss={ () => setNotice( null ) }
				/>
			) }

			<div className="nom-charts-grid">
				<ChartCard
					title={ 'Cache Hits vs Misses' }
					type="line"
					data={ {
						labels: charts.labels || [],
						datasets: [
							{
								label: 'Hits',
								data: charts.hits || [],
								borderColor: '#4CAF50',
								backgroundColor: 'rgba(76, 175, 80, 0.1)',
								fill: true,
								tension: 0.4,
							},
							{
								label: 'Misses',
								data: charts.misses || [],
								borderColor: '#FF5722',
								backgroundColor: 'rgba(255, 87, 34, 0.1)',
								fill: true,
								tension: 0.4,
							},
						],
					} }
					options={ {
						responsive: true,
						maintainAspectRatio: false,
						scales: {
							y: { beginAtZero: true },
						},
					} }
				/>
				<ChartCard
					title={ 'Memory Usage Trend' }
					type="line"
					data={ {
						labels: charts.labels || [],
						datasets: [
							{
								label: 'Memory Usage %',
								data: charts.memory || [],
								borderColor: '#9C27B0',
								backgroundColor: 'rgba(156, 39, 176, 0.1)',
								fill: true,
								tension: 0.4,
							},
						],
					} }
					options={ {
						responsive: true,
						maintainAspectRatio: false,
						scales: {
							y: {
								beginAtZero: true,
								max: 100,
							},
						},
					} }
				/>
				<ChartCard
					title={ 'Nginx Cache Size' }
					type="bar"
					data={ {
						labels: charts.labels || [],
						datasets: [
							{
								label: 'Size (MB)',
								data: charts.cache_size || [],
								backgroundColor: '#2196F3',
								borderRadius: 4,
							},
						],
					} }
					options={ {
						responsive: true,
						maintainAspectRatio: false,
						scales: {
							y: { beginAtZero: true },
						},
					} }
				/>
				<ChartCard
					title={ 'Cached Files' }
					type="line"
					data={ {
						labels: charts.labels || [],
						datasets: [
							{
								label: 'Files',
								data: charts.files || [],
								borderColor: '#FF9800',
								backgroundColor: 'rgba(255, 152, 0, 0.1)',
								fill: true,
								tension: 0.4,
							},
						],
					} }
					options={ {
						responsive: true,
						maintainAspectRatio: false,
						scales: {
							y: { beginAtZero: true },
						},
					} }
				/>
			</div>
		</div>
	);
}

/**
 * Settings Page Component
 */
function SettingsPage() {
	const [ settings, setSettings ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ formData, setFormData ] = useState( {} );

	useEffect( () => {
		loadSettings();
	}, [] );

	const loadSettings = async () => {
		try {
			setLoading( true );
			const data = await fetchSettings();
			setSettings( data );
			setFormData( data );
		} catch ( error ) {
			setNotice( { type: 'error', message: error.message } );
		} finally {
			setLoading( false );
		}
	};

	const handleSave = async () => {
		try {
			setSaving( true );
			const result = await updateSettings( formData );
			setNotice( { type: 'success', message: result.message } );
		} catch ( error ) {
			setNotice( { type: 'error', message: error.message } );
		} finally {
			setSaving( false );
		}
	};

	const updateField = ( key, value ) => {
		setFormData( ( prev ) => ( { ...prev, [ key ]: value } ) );
	};

	if ( loading ) {
		return <LoadingSpinner />;
	}

	const intervals = settings?.available_intervals || {};
	const targets = settings?.available_targets || {};

	return (
		<div className="nom-settings">
			<div className="nom-header">
				<h1>{ 'Settings' }</h1>
				<p className="nom-subtitle">
					{ 'Configure Nginx Opcache Manager' }
				</p>
			</div>

			{ notice && (
				<NoticeComponent
					type={ notice.type }
					message={ notice.message }
					onDismiss={ () => setNotice( null ) }
				/>
			) }

			<Card>
				<CardHeader>
					<h2>{ 'Nginx Cache Settings' }</h2>
				</CardHeader>
				<CardBody>
					{ /*
					 * __nextHasNoMarginBottom / __next40pxDefaultSize opt into
					 * the styles WordPress makes default in 7.0 / 7.1. Both are
					 * no-ops on the older wp-components builds this plugin still
					 * supports (an unrecognised prop is ignored), so passing them
					 * now is safe and keeps the console clean.
					 */ }
					<PanelRow>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ 'Enable Nginx Cache Monitoring' }
							checked={ formData.nginx_cache_enabled || false }
							onChange={ ( val ) =>
								updateField( 'nginx_cache_enabled', val )
							}
						/>
					</PanelRow>
					<PanelRow>
						<TextControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ 'Nginx Cache Path' }
							value={ formData.nginx_cache_path || '' }
							onChange={ ( val ) =>
								updateField( 'nginx_cache_path', val )
							}
							placeholder="/var/run/nginx-cache"
						/>
					</PanelRow>
					<PanelRow>
						<TextControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ 'Fastcgi Cache Key Schema' }
							value={ formData.fastcgi_cache_key_schema || '' }
							onChange={ ( val ) =>
								updateField( 'fastcgi_cache_key_schema', val )
							}
							placeholder="$scheme$request_method$host$request_uri"
						/>
					</PanelRow>
					<PanelRow>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ 'Auto-Flush Cache on Content Changes' }
							checked={
								formData.enable_post_cache_flush || false
							}
							onChange={ ( val ) =>
								updateField( 'enable_post_cache_flush', val )
							}
						/>
					</PanelRow>
					<PanelRow>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ 'Auto-Flush Product Cache (WooCommerce)' }
							checked={
								formData.enable_woocommerce_flush || false
							}
							onChange={ ( val ) =>
								updateField( 'enable_woocommerce_flush', val )
							}
						/>
					</PanelRow>
					<PanelRow>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ 'Enable Notifications' }
							checked={ formData.enable_notifications || false }
							onChange={ ( val ) =>
								updateField( 'enable_notifications', val )
							}
						/>
					</PanelRow>
				</CardBody>
			</Card>

			<Card>
				<CardHeader>
					<h2>{ 'Scheduled Cache Purge' }</h2>
				</CardHeader>
				<CardBody>
					<PanelRow>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ 'Enable Scheduled Purge' }
							checked={ formData.schedule_enabled || false }
							onChange={ ( val ) =>
								updateField( 'schedule_enabled', val )
							}
						/>
					</PanelRow>
					{ formData.schedule_enabled && (
						<>
							<PanelRow>
								<SelectControl
									label={ 'Purge Interval' }
									value={
										formData.schedule_interval ||
										'six_hours'
									}
									options={ Object.entries( intervals ).map(
										( [ slug, data ] ) => ( {
											label: data.label,
											value: slug,
										} )
									) }
									onChange={ ( val ) =>
										updateField( 'schedule_interval', val )
									}
								/>
							</PanelRow>
							<PanelRow>
								<SelectControl
									label={ 'Purge Targets' }
									value={
										formData.schedule_targets || 'both'
									}
									options={ Object.entries( targets ).map(
										( [ slug, label ] ) => ( {
											label,
											value: slug,
										} )
									) }
									onChange={ ( val ) =>
										updateField( 'schedule_targets', val )
									}
								/>
							</PanelRow>
						</>
					) }
				</CardBody>
			</Card>

			<Flex className="nom-settings-actions" justify="flex-end">
				<FlexItem>
					<Button
						variant="primary"
						onClick={ handleSave }
						isBusy={ saving }
						disabled={ saving }
					>
						{ 'Save Settings' }
					</Button>
				</FlexItem>
			</Flex>
		</div>
	);
}

/**
 * The tabs this App offers, matching the `data-tab` values written by
 * admin/views/react-dashboard.php.
 *
 * Declared once. `TAB_NAMES` is derived from it below so the allow-list used
 * by `resolveTab()` cannot drift away from the tabs that are actually
 * rendered — a tab added here is valid immediately, and a tab removed here
 * stops resolving in the same commit.
 *
 * @type {Array<Object>}
 */
const TABS = [
	{
		name: 'dashboard',
		title: 'Dashboard',
		className: 'nom-tab-dashboard',
	},
	{
		name: 'analytics',
		title: 'Analytics',
		className: 'nom-tab-analytics',
	},
	{
		name: 'settings',
		title: 'Settings',
		className: 'nom-tab-settings',
	},
];

/**
 * Tab names the admin screens can open on.
 *
 * @type {Array<string>}
 */
const TAB_NAMES = TABS.map( ( tab ) => tab.name );

/**
 * Narrow an arbitrary value to one of the tabs this App knows about.
 *
 * TabPanel renders *nothing at all* when it is handed an `initialTabName`
 * that matches no tab (it waits for the named tab to be declared), so an
 * unrecognised value has to be rejected here rather than passed through.
 *
 * @param {string|undefined} name Candidate tab name.
 * @return {string} A valid tab name.
 */
function resolveTab( name ) {
	return TAB_NAMES.includes( name ) ? name : 'dashboard';
}

/**
 * Resolve the tab to open on from the root element's `data-tab` attribute.
 *
 * The three admin screens all include the same view, so without this every
 * screen opened the Dashboard — Settings had no "Save Settings" button on
 * screen at all. Anything absent or unrecognised falls back to the Dashboard.
 *
 * @param {HTMLElement|null} rootElement Root mount element.
 * @return {string} A valid tab name.
 */
export function getInitialTab( rootElement ) {
	return resolveTab( rootElement?.dataset?.tab );
}

/**
 * Main App Component
 *
 * @param {Object} props            - Component props.
 * @param {string} props.initialTab - Tab to open on.
 * @return {JSX.Element} App component.
 */
export function App( { initialTab = 'dashboard' } ) {
	const activeTabName = resolveTab( initialTab );

	return (
		<div className="nom-app">
			<TabPanel
				className="nom-tab-panel"
				activeClass="nom-tab-active"
				/*
				 * `initialTabName` is the prop @wordpress/components reads from
				 * WordPress 6.6 onwards; `initialTab` was its name before that and
				 * was removed outright (no alias shim) from the 28.x build.
				 * `wp-components` is a runtime global supplied by the host and
				 * this plugin supports WordPress 6.2+, so the only way to cover
				 * both is to pass both. The extra prop is inert on either version:
				 * TabPanel destructures its known props explicitly and never
				 * spreads the rest onto a DOM node, so an unrecognised prop is
				 * silently dropped rather than warned about or leaked to the DOM.
				 *
				 * Passing only `initialTabName` would silently regress every
				 * install below 6.6 back to the Dashboard tab — exactly the bug
				 * this prop plumbing exists to fix.
				 */
				initialTabName={ activeTabName }
				initialTab={ activeTabName }
				tabs={ TABS }
			>
				{ ( tab ) => {
					switch ( tab.name ) {
						case 'dashboard':
							return <DashboardPage />;
						case 'analytics':
							return <AnalyticsPage />;
						case 'settings':
							return <SettingsPage />;
						default:
							return <DashboardPage />;
					}
				} }
			</TabPanel>
		</div>
	);
}

/**
 * Initialize React app when DOM is ready
 */
document.addEventListener( 'DOMContentLoaded', () => {
	const rootElement = document.getElementById( 'nom-react-root' );
	if ( rootElement ) {
		const root = createRoot( rootElement );
		root.render( <App initialTab={ getInitialTab( rootElement ) } /> );
	}
} );
