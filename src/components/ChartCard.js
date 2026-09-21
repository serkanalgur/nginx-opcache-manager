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
		},
		tooltip: {
			mode: 'index',
			intersect: false,
		},
	},
	scales: {
		x: {
			display: true,
			grid: {
				display: false,
			},
		},
		y: {
			display: true,
			beginAtZero: true,
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
 * @return {JSX.Element} Chart card component.
 */
export default function ChartCard( {
	title,
	data,
	type = 'line',
	options = {},
	height = 300,
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
				<CardHeader>
					<h3>{ title }</h3>
				</CardHeader>
			) }
			<CardBody>
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
