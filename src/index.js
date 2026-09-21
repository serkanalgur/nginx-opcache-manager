/**
 * Nginx Opcache Manager - React Admin Panel
 *
 * @package Nginx_Opcache_Manager
 */

import { __ } from '@wordpress/i18n';
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
		if (!confirm(__('Are you sure you want to clear the Nginx cache?', 'nginx-opcache-manager'))) {
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
		if (!confirm(__('Are you sure you want to reset Opcache?', 'nginx-opcache-manager'))) {
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
				<h1>{__('Cache Manager Dashboard', 'nginx-opcache-manager')}</h1>
				<p className="nom-subtitle">{__('Monitor and manage your Nginx cache and PHP Opcache', 'nginx-opcache-manager')}</p>
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
						{__('Clear Nginx Cache', 'nginx-opcache-manager')}
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
						{__('Reset Opcache', 'nginx-opcache-manager')}
					</Button>
				</FlexItem>
			</Flex>

			<div className="nom-stat-cards">
				<StatCard
					title={__('Nginx Cache', 'nginx-opcache-manager')}
					value={formatBytes(nginx.cache_size || 0)}
					subtitle={__('Cache Size', 'nginx-opcache-manager')}
					icon="database"
					color="#2196F3"
				/>
				<StatCard
					title={__('Cached Files', 'nginx-opcache-manager')}
					value={formatNumber(nginx.cached_files || 0)}
					subtitle={__('Files', 'nginx-opcache-manager')}
					icon="media-default"
					color="#4CAF50"
				/>
				<StatCard
					title={__('Hit Rate', 'nginx-opcache-manager')}
					value={`${(opcache.hit_rate || 0).toFixed(1)}%`}
					subtitle={__('Opcache', 'nginx-opcache-manager')}
					icon="chart-bar"
					color="#FF9800"
				/>
				<StatCard
					title={__('Memory Usage', 'nginx-opcache-manager')}
					value={`${(opcache.memory_usage || 0).toFixed(1)}%`}
					subtitle={__('Used', 'nginx-opcache-manager')}
					icon="memory"
					color="#9C27B0"
				/>
			</div>

			<div className="nom-charts-grid">
				<ChartCard
					title={__('Hit Rate', 'nginx-opcache-manager')}
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
					title={__('Memory Usage', 'nginx-opcache-manager')}
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
				<h1>{__('Analytics', 'nginx-opcache-manager')}</h1>
				<p className="nom-subtitle">{__('Performance metrics and historical data', 'nginx-opcache-manager')}</p>
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
					title={__('Cache Hits vs Misses', 'nginx-opcache-manager')}
					type="line"
					data={{
						labels: charts.labels || [],
						datasets: [
							{
								label: __('Hits', 'nginx-opcache-manager'),
								data: charts.hits || [],
								borderColor: '#4CAF50',
								backgroundColor: 'rgba(76, 175, 80, 0.1)',
								fill: true,
								tension: 0.4,
							},
							{
								label: __('Misses', 'nginx-opcache-manager'),
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
					title={__('Memory Usage Trend', 'nginx-opcache-manager')}
					type="line"
					data={{
						labels: charts.labels || [],
						datasets: [{
							label: __('Memory Usage %', 'nginx-opcache-manager'),
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
					title={__('Nginx Cache Size', 'nginx-opcache-manager')}
					type="bar"
					data={{
						labels: charts.labels || [],
						datasets: [{
							label: __('Size (MB)', 'nginx-opcache-manager'),
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
					title={__('Cached Files', 'nginx-opcache-manager')}
					type="line"
					data={{
						labels: charts.labels || [],
						datasets: [{
							label: __('Files', 'nginx-opcache-manager'),
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
				<h1>{__('Settings', 'nginx-opcache-manager')}</h1>
				<p className="nom-subtitle">{__('Configure Nginx Opcache Manager', 'nginx-opcache-manager')}</p>
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
					<h2>{__('Nginx Cache Settings', 'nginx-opcache-manager')}</h2>
				</CardHeader>
				<CardBody>
					<PanelRow>
						<ToggleControl
							label={__('Enable Nginx Cache Monitoring', 'nginx-opcache-manager')}
							checked={formData.nginx_cache_enabled || false}
							onChange={(val) => updateField('nginx_cache_enabled', val)}
						/>
					</PanelRow>
					<PanelRow>
						<TextControl
							label={__('Nginx Cache Path', 'nginx-opcache-manager')}
							value={formData.nginx_cache_path || ''}
							onChange={(val) => updateField('nginx_cache_path', val)}
							placeholder="/var/run/nginx-cache"
						/>
					</PanelRow>
					<PanelRow>
						<TextControl
							label={__('Fastcgi Cache Key Schema', 'nginx-opcache-manager')}
							value={formData.fastcgi_cache_key_schema || ''}
							onChange={(val) => updateField('fastcgi_cache_key_schema', val)}
							placeholder="$scheme$request_method$host$request_uri"
						/>
					</PanelRow>
					<PanelRow>
						<ToggleControl
							label={__('Auto-Flush Cache on Content Changes', 'nginx-opcache-manager')}
							checked={formData.enable_post_cache_flush || false}
							onChange={(val) => updateField('enable_post_cache_flush', val)}
						/>
					</PanelRow>
					<PanelRow>
						<ToggleControl
							label={__('Auto-Flush Product Cache (WooCommerce)', 'nginx-opcache-manager')}
							checked={formData.enable_woocommerce_flush || false}
							onChange={(val) => updateField('enable_woocommerce_flush', val)}
						/>
					</PanelRow>
					<PanelRow>
						<ToggleControl
							label={__('Enable Notifications', 'nginx-opcache-manager')}
							checked={formData.enable_notifications || false}
							onChange={(val) => updateField('enable_notifications', val)}
						/>
					</PanelRow>
				</CardBody>
			</Card>

			<Card>
				<CardHeader>
					<h2>{__('Scheduled Cache Purge', 'nginx-opcache-manager')}</h2>
				</CardHeader>
				<CardBody>
					<PanelRow>
						<ToggleControl
							label={__('Enable Scheduled Purge', 'nginx-opcache-manager')}
							checked={formData.schedule_enabled || false}
							onChange={(val) => updateField('schedule_enabled', val)}
						/>
					</PanelRow>
					{formData.schedule_enabled && (
						<>
							<PanelRow>
								<SelectControl
									label={__('Purge Interval', 'nginx-opcache-manager')}
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
									label={__('Purge Targets', 'nginx-opcache-manager')}
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
						{__('Save Settings', 'nginx-opcache-manager')}
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
						title: __('Dashboard', 'nginx-opcache-manager'),
						className: 'nom-tab-dashboard',
					},
					{
						name: 'analytics',
						title: __('Analytics', 'nginx-opcache-manager'),
						className: 'nom-tab-analytics',
					},
					{
						name: 'settings',
						title: __('Settings', 'nginx-opcache-manager'),
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
