/**
 * Nginx Opcache Manager - React Admin Panel
 *
 * @package Nginx_Opcache_Manager
 */

import { useState, useEffect, createRoot } from '@wordpress/element';
import {
	TabPanel,
	Panel,
	PanelBody,
	PanelRow,
	Button,
	Spinner,
	Notice,
	ToggleControl,
	SelectControl,
	TextControl,
	__experimentalText as Text,
	Card,
	CardBody,
	CardHeader,
	Flex,
	FlexItem,
	FlexBlock,
	Modal,
} from '@wordpress/components';
import { fetchStats, clearNginxCache, resetOpcache, fetchSettings, updateSettings, fetchLogs, clearLogs, fetchAnalytics } from './utils/api';
import { formatBytes, formatDate, formatNumber } from './utils/helpers';
import ChartCard from './components/ChartCard';
import ActivityLog from './components/ActivityLog';
import LoadingSpinner from './components/LoadingSpinner';
import StatCard from './components/StatCard';
import NoticeComponent from './components/Notice';
import './style.css';

/**
 * Dashboard Page Component
 */
function DashboardPage() {
	const [stats, setStats] = useState(null);
	const [loading, setLoading] = useState(true);
	const [clearing, setClearing] = useState(false);
	const [resetting, setResetting] = useState(false);
	const [notice, setNotice] = useState(null);
	const [logs, setLogs] = useState([]);

	useEffect(() => {
		loadStats();
		loadLogs();
		const interval = setInterval(loadLogs, 30000);
		return () => clearInterval(interval);
	}, []);

	const loadStats = async () => {
		try {
			setLoading(true);
			const data = await fetchStats();
			setStats(data);
		} catch (error) {
			setNotice({ type: 'error', message: error.message });
		} finally {
			setLoading(false);
		}
	};

	const loadLogs = async () => {
		try {
			const data = await fetchLogs();
			setLogs(data.logs || []);
		} catch (error) {
			console.error('Failed to load logs:', error);
		}
	};

	const handleClearCache = async () => {
		if (!confirm('Are you sure you want to clear the Nginx cache?')) {
			return;
		}
		try {
			setClearing(true);
			const result = await clearNginxCache();
			setNotice({ type: 'success', message: result.message });
			await loadStats();
			await loadLogs();
		} catch (error) {
			setNotice({ type: 'error', message: error.message });
		} finally {
			setClearing(false);
		}
	};

	const handleResetOpcache = async () => {
		if (!confirm('Are you sure you want to reset Opcache?')) {
			return;
		}
		try {
			setResetting(true);
			const result = await resetOpcache();
			setNotice({ type: 'success', message: result.message });
			await loadStats();
		} catch (error) {
			setNotice({ type: 'error', message: error.message });
		} finally {
			setResetting(false);
		}
	};

	if (loading) {
		return <LoadingSpinner />;
	}

	const nginx = stats?.nginx || {};
	const opcache = stats?.opcache || {};

	return (
		<div className="nom-dashboard">
			<div className="nom-header">
				<h1>{'Cache Manager Dashboard'}</h1>
				<p className="nom-subtitle">{'Monitor and manage your Nginx cache and PHP Opcache'}</p>
			</div>

			{notice && (
				<NoticeComponent
					type={notice.type}
					message={notice.message}
					onDismiss={() => setNotice(null)}
				/>
			)}

			<Flex className="nom-actions-bar" justify="flex-end">
				<FlexItem>
					<Button
						variant="secondary"
						onClick={handleClearCache}
						isBusy={clearing}
						disabled={clearing}
						icon="trash"
					>
						{'Clear Nginx Cache'}
					</Button>
				</FlexItem>
				<FlexItem>
					<Button
						variant="secondary"
						onClick={handleResetOpcache}
						isBusy={resetting}
						disabled={resetting}
						icon="update"
					>
						{'Reset Opcache'}
					</Button>
				</FlexItem>
			</Flex>

			<div className="nom-stat-cards">
				<StatCard
					title={'Fastcgi / Nginx Cache'}
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
					title={'PHP Opcache'}
					icon="performance"
					color="#9C27B0"
					primary={ {
						value: `${ ( opcache.hit_rate || 0 ).toFixed( 1 ) }%`,
						label: 'Hit Rate',
					} }
					secondary={ {
						value: `${ ( opcache.memory_usage || 0 ).toFixed( 1 ) }%`,
						label: 'Bellek Kullanimi',
					} }
				/>
			</div>

			<div className="nom-charts-grid">
				<ChartCard
					title={'Hit Rate'}
					icon="chart-bar"
					type="doughnut"
					data={{
						labels: ['Hits', 'Misses'],
						datasets: [{
							data: [opcache.hit_rate || 0, 100 - (opcache.hit_rate || 0)],
							backgroundColor: ['#4CAF50', '#FF5722'],
							borderWidth: 0,
						}],
					}}
					options={{
						cutout: '70%',
						plugins: { legend: { display: false } },
						responsive: true,
						maintainAspectRatio: false,
					}}
				/>
				<ChartCard
					title={'Memory Usage'}
					icon="performance"
					type="doughnut"
					data={{
						labels: ['Used', 'Free'],
						datasets: [{
							data: [opcache.memory_usage || 0, 100 - (opcache.memory_usage || 0)],
							backgroundColor: ['#9C27B0', '#E1BEE7'],
							borderWidth: 0,
						}],
					}}
					options={{
						cutout: '70%',
						plugins: { legend: { display: false } },
						responsive: true,
						maintainAspectRatio: false,
					}}
				/>
			</div>

			<ActivityLog logs={logs} onClear={loadLogs} />
		</div>
	);
}

/**
 * Analytics Page Component
 */
function AnalyticsPage() {
	const [data, setData] = useState(null);
	const [loading, setLoading] = useState(true);
	const [notice, setNotice] = useState(null);

	useEffect(() => {
		loadAnalytics();
	}, []);

	const loadAnalytics = async () => {
		try {
			setLoading(true);
			const result = await fetchAnalytics();
			setData(result);
		} catch (error) {
			setNotice({ type: 'error', message: error.message });
		} finally {
			setLoading(false);
		}
	};

	if (loading) {
		return <LoadingSpinner />;
	}

	const charts = data?.charts || {};

	return (
		<div className="nom-analytics">
			<div className="nom-header">
				<h1>{'Analytics'}</h1>
				<p className="nom-subtitle">{'Performance metrics and historical data'}</p>
			</div>

			{notice && (
				<NoticeComponent
					type={notice.type}
					message={notice.message}
					onDismiss={() => setNotice(null)}
				/>
			)}

			<div className="nom-charts-grid">
				<ChartCard
					title={'Cache Hits vs Misses'}
					type="line"
					data={{
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
					}}
					options={{
						responsive: true,
						maintainAspectRatio: false,
						scales: {
							y: { beginAtZero: true },
						},
					}}
				/>
				<ChartCard
					title={'Memory Usage Trend'}
					type="line"
					data={{
						labels: charts.labels || [],
						datasets: [{
							label: 'Memory Usage %',
							data: charts.memory || [],
							borderColor: '#9C27B0',
							backgroundColor: 'rgba(156, 39, 176, 0.1)',
							fill: true,
							tension: 0.4,
						}],
					}}
					options={{
						responsive: true,
						maintainAspectRatio: false,
						scales: {
							y: {
								beginAtZero: true,
								max: 100,
							},
						},
					}}
				/>
				<ChartCard
					title={'Nginx Cache Size'}
					type="bar"
					data={{
						labels: charts.labels || [],
						datasets: [{
							label: 'Size (MB)',
							data: charts.cache_size || [],
							backgroundColor: '#2196F3',
							borderRadius: 4,
						}],
					}}
					options={{
						responsive: true,
						maintainAspectRatio: false,
						scales: {
							y: { beginAtZero: true },
						},
					}}
				/>
				<ChartCard
					title={'Cached Files'}
					type="line"
					data={{
						labels: charts.labels || [],
						datasets: [{
							label: 'Files',
							data: charts.files || [],
							borderColor: '#FF9800',
							backgroundColor: 'rgba(255, 152, 0, 0.1)',
							fill: true,
							tension: 0.4,
						}],
					}}
					options={{
						responsive: true,
						maintainAspectRatio: false,
						scales: {
							y: { beginAtZero: true },
						},
					}}
				/>
			</div>
		</div>
	);
}

/**
 * Settings Page Component
 */
function SettingsPage() {
	const [settings, setSettings] = useState(null);
	const [loading, setLoading] = useState(true);
	const [saving, setSaving] = useState(false);
	const [notice, setNotice] = useState(null);
	const [formData, setFormData] = useState({});

	useEffect(() => {
		loadSettings();
	}, []);

	const loadSettings = async () => {
		try {
			setLoading(true);
			const data = await fetchSettings();
			setSettings(data);
			setFormData(data);
		} catch (error) {
			setNotice({ type: 'error', message: error.message });
		} finally {
			setLoading(false);
		}
	};

	const handleSave = async () => {
		try {
			setSaving(true);
			const result = await updateSettings(formData);
			setNotice({ type: 'success', message: result.message });
		} catch (error) {
			setNotice({ type: 'error', message: error.message });
		} finally {
			setSaving(false);
		}
	};

	const updateField = (key, value) => {
		setFormData(prev => ({ ...prev, [key]: value }));
	};

	if (loading) {
		return <LoadingSpinner />;
	}

	const intervals = settings?.available_intervals || {};
	const targets = settings?.available_targets || {};

	return (
		<div className="nom-settings">
			<div className="nom-header">
				<h1>{'Settings'}</h1>
				<p className="nom-subtitle">{'Configure Nginx Opcache Manager'}</p>
			</div>

			{notice && (
				<NoticeComponent
					type={notice.type}
					message={notice.message}
					onDismiss={() => setNotice(null)}
				/>
			)}

			<Card>
				<CardHeader>
					<h2>{'Nginx Cache Settings'}</h2>
				</CardHeader>
				<CardBody>
					<PanelRow>
						<ToggleControl
							label={'Enable Nginx Cache Monitoring'}
							checked={formData.nginx_cache_enabled || false}
							onChange={(val) => updateField('nginx_cache_enabled', val)}
						/>
					</PanelRow>
					<PanelRow>
						<TextControl
							label={'Nginx Cache Path'}
							value={formData.nginx_cache_path || ''}
							onChange={(val) => updateField('nginx_cache_path', val)}
							placeholder="/var/run/nginx-cache"
						/>
					</PanelRow>
					<PanelRow>
						<TextControl
							label={'Fastcgi Cache Key Schema'}
							value={formData.fastcgi_cache_key_schema || ''}
							onChange={(val) => updateField('fastcgi_cache_key_schema', val)}
							placeholder="$scheme$request_method$host$request_uri"
						/>
					</PanelRow>
					<PanelRow>
						<ToggleControl
							label={'Auto-Flush Cache on Content Changes'}
							checked={formData.enable_post_cache_flush || false}
							onChange={(val) => updateField('enable_post_cache_flush', val)}
						/>
					</PanelRow>
					<PanelRow>
						<ToggleControl
							label={'Auto-Flush Product Cache (WooCommerce)'}
							checked={formData.enable_woocommerce_flush || false}
							onChange={(val) => updateField('enable_woocommerce_flush', val)}
						/>
					</PanelRow>
					<PanelRow>
						<ToggleControl
							label={'Enable Notifications'}
							checked={formData.enable_notifications || false}
							onChange={(val) => updateField('enable_notifications', val)}
						/>
					</PanelRow>
				</CardBody>
			</Card>

			<Card>
				<CardHeader>
					<h2>{'Scheduled Cache Purge'}</h2>
				</CardHeader>
				<CardBody>
					<PanelRow>
						<ToggleControl
							label={'Enable Scheduled Purge'}
							checked={formData.schedule_enabled || false}
							onChange={(val) => updateField('schedule_enabled', val)}
						/>
					</PanelRow>
					{formData.schedule_enabled && (
						<>
							<PanelRow>
								<SelectControl
									label={'Purge Interval'}
									value={formData.schedule_interval || 'six_hours'}
									options={Object.entries(intervals).map(([slug, data]) => ({
										label: data.label,
										value: slug,
									}))}
									onChange={(val) => updateField('schedule_interval', val)}
								/>
							</PanelRow>
							<PanelRow>
								<SelectControl
									label={'Purge Targets'}
									value={formData.schedule_targets || 'both'}
									options={Object.entries(targets).map(([slug, label]) => ({
										label,
										value: slug,
									}))}
									onChange={(val) => updateField('schedule_targets', val)}
								/>
							</PanelRow>
						</>
					)}
				</CardBody>
			</Card>

			<Flex className="nom-settings-actions" justify="flex-end">
				<FlexItem>
					<Button
						variant="primary"
						onClick={handleSave}
						isBusy={saving}
						disabled={saving}
					>
						{'Save Settings'}
					</Button>
				</FlexItem>
			</Flex>
		</div>
	);
}

/**
 * Main App Component
 */
function App() {
	const [activeTab, setActiveTab] = useState('dashboard');

	return (
		<div className="nom-app">
			<TabPanel
				className="nom-tab-panel"
				activeClass="nom-tab-active"
				onSelect={setActiveTab}
				tabs={[
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
				]}
			>
				{(tab) => {
					switch (tab.name) {
						case 'dashboard':
							return <DashboardPage />;
						case 'analytics':
							return <AnalyticsPage />;
						case 'settings':
							return <SettingsPage />;
						default:
							return <DashboardPage />;
					}
				}}
			</TabPanel>
		</div>
	);
}

/**
 * Initialize React app when DOM is ready
 */
document.addEventListener('DOMContentLoaded', () => {
	const rootElement = document.getElementById('nom-react-root');
	if (rootElement) {
		const root = createRoot(rootElement);
		root.render(<App />);
	}
});
