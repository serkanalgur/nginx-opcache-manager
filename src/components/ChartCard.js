/**
 * Chart card component using Chart.js via react-chartjs-2.
 *
 * @package Nginx_Opcache_Manager
 */

import { Card, CardHeader, CardBody } from '@wordpress/components';
import {
	Chart as ChartJS,
	CategoryScale,
	LinearScale,
	PointElement,
	LineElement,
	BarElement,
	Title,
	Tooltip,
	Legend,
	ArcElement,
	Filler,
} from 'chart.js';
import { Line, Bar, Doughnut } from 'react-chartjs-2';

// Register Chart.js components
ChartJS.register(
	CategoryScale,
	LinearScale,
	PointElement,
	LineElement,
	BarElement,
	Title,
	Tooltip,
	Legend,
	ArcElement,
	Filler
);

/**
 * Default chart options.
 */
const defaultOptions = {
	responsive: true,
	maintainAspectRatio: false,
	plugins: {
		legend: {
			display: true,
			position: 'top',
			labels: {
				usePointStyle: true,
				pointStyle: 'circle',
				padding: 16,
				font: { size: 12, weight: '500' },
			},
		},
		tooltip: {
			mode: 'index',
			intersect: false,
			backgroundColor: '#1d2327',
			titleFont: { size: 13 },
			bodyFont: { size: 12 },
			padding: 12,
			cornerRadius: 8,
		},
	},
	scales: {
		x: {
			display: true,
			grid: { display: false },
			ticks: { font: { size: 11 }, color: '#8c8f94' },
		},
		y: {
			display: true,
			beginAtZero: true,
			grid: { color: '#f0f0f1' },
			ticks: { font: { size: 11 }, color: '#8c8f94' },
		},
	},
};

/**
 * Chart card component.
 *
 * @param {Object} props           - Component props.
 * @param {string} props.title     - Card title.
 * @param {Object} props.data      - Chart.js data object.
 * @param {string} props.type      - Chart type (line, bar, doughnut).
 * @param {Object} props.options   - Chart.js options override.
 * @param {number} props.height    - Chart height in pixels.
 * @param {string} props.icon      - Dashicons class.
 * @return {JSX.Element} Chart card component.
 */
export default function ChartCard( {
	title,
	data,
	type = 'line',
	options = {},
	height = 280,
	icon,
} ) {
	const mergedOptions = {
		...defaultOptions,
		...options,
	};

	const renderChart = () => {
		const chartProps = {
			data,
			options: mergedOptions,
		};

		switch ( type ) {
			case 'bar':
				return <Bar { ...chartProps } />;
			case 'doughnut':
				return <Doughnut { ...chartProps } />;
			case 'line':
			default:
				return <Line { ...chartProps } />;
		}
	};

	return (
		<Card className="nom-chart-card">
			{ title && (
				<CardHeader className="nom-chart-card__header">
					<div className="nom-chart-card__title-wrap">
						{ icon && (
							<span className={ `dashicons nom-chart-card__icon ${ icon }` }></span>
						) }
						<h3 className="nom-chart-card__title">{ title }</h3>
					</div>
				</CardHeader>
			) }
			<CardBody className="nom-chart-card__body">
				<div
					className="nom-chart-container"
					style={ { height: `${ height }px` } }
				>
					{ renderChart() }
				</div>
			</CardBody>
		</Card>
	);
}
