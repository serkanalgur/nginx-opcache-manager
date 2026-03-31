/**
 * Nginx Opcache Manager Admin JavaScript
 */

(function ($) {
    'use strict';

    const NOM = {
        charts: {},
        chartInstances: {},

        /**
         * Initialize
         */
        init: function () {
            this.bindEvents();
            this.loadFlushLogs();
            this.initCharts();

            // Refresh flush logs every 30 seconds (reduced from 5s to prevent memory buildup)
            setInterval(function () {
                NOM.loadFlushLogs();
            }, 30000);
        },

        /**
         * Bind event handlers
         */
        bindEvents: function () {
            $(document)
                .on('click', '.nom-clear-nginx-btn', NOM.clearNginxCache)
                .on('click', '.nom-reset-opcache-btn', NOM.resetOpcache)
                .on('click', '.nom-clear-activity-logs-btn', NOM.clearActivityLogs)
                .on('click', '#nomClearDataBtn', NOM.clearAllData);
        },

        /**
         * Load flush logs via AJAX
         */
        loadFlushLogs: function () {
            $.ajax({
                url: nomData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'nom_get_flush_logs',
                    nonce: nomData.nonce
                },
                success: function (response) {
                    if (response.success) {
                        console.log('Cache flush logs received:', response.data.logs);
                        NOM.renderFlushLogs(response.data.logs);
                    }
                },
                error: function () {
                    $('#nomFlushLogsContainer').html('<p class="description">' +
                        'Cache flush logs not available' + '</p>');
                }
            });
        },

        /**
         * Render flush logs
         */
        renderFlushLogs: function (logs) {
            const container = $('#nomFlushLogsContainer');

            if (!logs || logs.length === 0) {
                container.html('<div class="nom-flush-logs-empty"><p>' +
                    'No cache flush activity yet' + '</p></div>');
                return;
            }

            let html = '';
            logs.forEach(function (log) {
                const time = new Date(log.timestamp).toLocaleTimeString();

                // Normalize action value - ensure it's clean
                const action = (log.action || '').trim().toLowerCase();

                // Get status icon and color based on action type
                let statusIcon = '⚠️';
                let statusText = 'UNKNOWN';
                let actionClass = 'unknown';

                // Cache deletion actions
                if (action === 'deleted') {
                    statusIcon = '✓';
                    statusText = 'CACHE DELETED';
                    actionClass = 'deleted';
                } else if (action === 'not_found') {
                    statusIcon = '⊘';
                    statusText = 'NOT FOUND';
                    actionClass = 'not_found';
                } else if (action === 'delete_failed') {
                    statusIcon = '✗';
                    statusText = 'DELETE FAILED';
                    actionClass = 'delete_failed';
                }
                // Post event actions
                else if (action === 'post_change') {
                    statusIcon = '📝';
                    statusText = 'POST MODIFIED';
                    actionClass = 'post_change';
                } else if (action === 'post_publish') {
                    statusIcon = '📤';
                    statusText = 'POST PUBLISHED';
                    actionClass = 'post_publish';
                }

                html += '<div class="nom-flush-log-item ' + actionClass + '">' +
                    '<div class="flush-log-status-icon">' + statusIcon + '</div>' +
                    '<div class="flush-log-details">' +
                    '<div class="flush-log-action">' + statusText + '</div>';

                // Show post name and ID for post events
                if (log.object_id && log.name) {
                    html += '<div class="flush-log-post-info">' +
                        'Post ID: ' + NOM.escapeHtml(log.object_id) + ' | ' +
                        'Title: ' + NOM.escapeHtml(log.name) + '</div>';
                }

                // Show URL (for cache operations)
                if (log.url) {
                    html += '<div class="flush-log-url">' +
                        $('<div/>').text(log.url).html() + '</div>';
                }

                // Show file path (for cache operations)
                if (log.file_path) {
                    html += '<div class="flush-log-path" title="' + $('<div/>').text(log.file_path).html() + '">' +
                        '<span class="path-label">Path:</span> ' +
                        $('<div/>').text(log.file_path).html() + '</div>';
                }

                html += '</div>' +
                    '<div class="flush-log-time">' + time + '</div>' +
                    '</div>';
            });

            container.html(html);
        },

        /**
         * Escape HTML for safe display
         */
        escapeHtml: function (text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        },

        /**
         * Clear Nginx cache via AJAX
         */
        clearNginxCache: function (e) {
            e.preventDefault();

            if (!confirm(nomLocalize.confirmClearCache || 'Are you sure you want to clear the Nginx cache?')) {
                return;
            }

            const $btn = $(this);
            $btn.prop('disabled', true).addClass('nom-loading');

            $.ajax({
                url: nomData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'nom_clear_cache',
                    nonce: nomData.nonce
                },
                success: function (response) {
                    if (response.success) {
                        NOM.showNotice('Nginx cache cleared successfully!', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        NOM.showNotice('Error: ' + response.data, 'error');
                    }
                },
                error: function () {
                    NOM.showNotice('Failed to clear cache. Please try again.', 'error');
                },
                complete: function () {
                    $btn.prop('disabled', false).removeClass('nom-loading');
                }
            });
        },

        /**
         * Reset Opcache via AJAX
         */
        resetOpcache: function (e) {
            e.preventDefault();

            if (!confirm(nomLocalize.confirmResetOpcache || 'Are you sure you want to reset Opcache?')) {
                return;
            }

            const $btn = $(this);
            $btn.prop('disabled', true).addClass('nom-loading');

            $.ajax({
                url: nomData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'nom_reset_opcache',
                    nonce: nomData.nonce
                },
                success: function (response) {
                    if (response.success) {
                        NOM.showNotice('Opcache reset successfully!', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        NOM.showNotice('Error: ' + response.data, 'error');
                    }
                },
                error: function () {
                    NOM.showNotice('Failed to reset opcache. Please try again.', 'error');
                },
                complete: function () {
                    $btn.prop('disabled', false).removeClass('nom-loading');
                }
            });
        },

        /**
         * Clear activity logs via AJAX
         */
        clearActivityLogs: function (e) {
            e.preventDefault();

            if (!confirm('Are you sure you want to clear all activity logs? This action cannot be undone.')) {
                return;
            }

            const $btn = $(this);
            $btn.prop('disabled', true).addClass('nom-loading');

            $.ajax({
                url: nomData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'nom_clear_activity_logs',
                    nonce: nomData.nonce
                },
                success: function (response) {
                    if (response.success) {
                        NOM.showNotice('Activity logs cleared successfully!', 'success');
                        NOM.loadFlushLogs();
                    } else {
                        NOM.showNotice('Error: ' + response.data, 'error');
                    }
                },
                error: function () {
                    NOM.showNotice('Failed to clear activity logs. Please try again.', 'error');
                },
                complete: function () {
                    $btn.prop('disabled', false).removeClass('nom-loading');
                }
            });
        },

        /**
         * Clear all statistics data
         */
        clearAllData: function (e) {
            if (!confirm('This action cannot be undone. Are you sure?')) {
                return false;
            }
            // Implementation for clearing data would go here
        },

        /**
         * Initialize charts
         */
        initCharts: function () {
            const chartDataElement = document.getElementById('nomChartData');

            if (!chartDataElement) {
                return;
            }

            try {
                const chartData = JSON.parse(chartDataElement.innerText);
                NOM.createCharts(chartData);
            } catch (e) {
                console.error('Failed to parse chart data:', e);
            }
        },

        /**
         * Create all charts
         */
        createCharts: function (data) {
            // Hit/Miss Chart
            if (data.opcache_hits && data.opcache_hits.length > 0) {
                NOM.createHitsMissesChart(data);
            }

            // Memory Trend Chart
            if (data.opcache_memory && data.opcache_memory.length > 0) {
                NOM.createMemoryTrendChart(data);
            }

            // Nginx Size Chart
            if (data.nginx_sizes && data.nginx_sizes.length > 0) {
                NOM.createNginxSizeChart(data);
            }

            // Cached Files Chart
            if (data.nginx_files && data.nginx_files.length > 0) {
                NOM.createCachedFilesChart(data);
            }

            // Hit Rate Chart (Dashboard) - Always draw if we have current data
            if (data.current && data.current.opcache) {
                NOM.createHitRateChart(data);
            }

            // Memory Chart (Dashboard) - Always draw if we have current data
            if (data.current && data.current.opcache) {
                NOM.createMemoryChart(data);
            }
        },

        /**
         * Create hits/misses chart
         */
        createHitsMissesChart: function (data) {
            const ctx = document.getElementById('nomHitsMissesChart');
            if (!ctx) return;

            const maxValue = Math.max(...data.opcache_hits, ...data.opcache_misses);

            const chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.timestamps,
                    datasets: [
                        {
                            label: 'Hits',
                            data: data.opcache_hits,
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            fill: false,
                            tension: 0.4,
                            borderWidth: 2,
                            pointRadius: 4,
                            pointBackgroundColor: '#28a745',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2
                        },
                        {
                            label: 'Misses',
                            data: data.opcache_misses,
                            borderColor: '#dc3545',
                            backgroundColor: 'rgba(220, 53, 69, 0.1)',
                            fill: false,
                            tension: 0.4,
                            borderWidth: 2,
                            pointRadius: 4,
                            pointBackgroundColor: '#dc3545',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            NOM.chartInstances.hitsMisses = chart;
        },

        /**
         * Create memory trend chart
         */
        createMemoryTrendChart: function (data) {
            const ctx = document.getElementById('nomMemoryTrendChart');
            if (!ctx) return;

            const chart = new Chart(ctx, {
                type: 'area',
                data: {
                    labels: data.timestamps,
                    datasets: [
                        {
                            label: 'Memory Usage %',
                            data: data.opcache_memory,
                            borderColor: '#667eea',
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            fill: true,
                            tension: 0.4,
                            borderWidth: 2,
                            pointRadius: 4,
                            pointBackgroundColor: '#667eea',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });

            NOM.chartInstances.memoryTrend = chart;
        },

        /**
         * Create nginx size chart
         */
        createNginxSizeChart: function (data) {
            const ctx = document.getElementById('nomNginxSizeChart');
            if (!ctx) return;

            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.timestamps,
                    datasets: [
                        {
                            label: 'Cache Size (MB)',
                            data: data.nginx_sizes,
                            backgroundColor: 'rgba(255, 193, 7, 0.6)',
                            borderColor: '#ffc107',
                            borderWidth: 2,
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            NOM.chartInstances.nginxSize = chart;
        },

        /**
         * Create cached files chart
         */
        createCachedFilesChart: function (data) {
            const ctx = document.getElementById('nomCachedFilesChart');
            if (!ctx) return;

            const chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.timestamps,
                    datasets: [
                        {
                            label: 'Cached Files',
                            data: data.nginx_files,
                            borderColor: '#17a2b8',
                            backgroundColor: 'rgba(23, 162, 184, 0.1)',
                            fill: true,
                            tension: 0.4,
                            borderWidth: 2,
                            pointRadius: 4,
                            pointBackgroundColor: '#17a2b8',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            NOM.chartInstances.cachedFiles = chart;
        },

        /**
         * Create hit rate chart for dashboard
         */
        createHitRateChart: function (data) {
            const ctx = document.getElementById('nomHitRateChart');
            if (!ctx) return;

            const hitRatePercentage = data.current && data.current.opcache
                ? data.current.opcache.hit_rate || 0
                : 0;

            $('#hitRateValue').text(Math.round(hitRatePercentage));

            const chart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Hit', 'Miss'],
                    datasets: [
                        {
                            data: [hitRatePercentage, 100 - hitRatePercentage],
                            backgroundColor: ['#28a745', '#e0e0e0'],
                            borderColor: ['#fff', '#fff'],
                            borderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    return context.label + ': ' + context.parsed + '%';
                                }
                            }
                        }
                    }
                }
            });

            NOM.chartInstances.hitRate = chart;
        },

        /**
         * Create memory chart for dashboard
         */
        createMemoryChart: function (data) {
            const ctx = document.getElementById('nomMemoryChart');
            if (!ctx) return;

            const memoryUsage = data.current && data.current.opcache
                ? data.current.opcache.memory_usage || 0
                : 0;

            $('#memoryValue').text(Math.round(memoryUsage));

            const memoryFree = 100 - memoryUsage;
            const color = memoryUsage < 50 ? '#28a745' : memoryUsage < 80 ? '#ffc107' : '#dc3545';

            const chart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Used', 'Free'],
                    datasets: [
                        {
                            data: [memoryUsage, memoryFree],
                            backgroundColor: [color, '#e0e0e0'],
                            borderColor: ['#fff', '#fff'],
                            borderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    return context.label + ': ' + context.parsed + '%';
                                }
                            }
                        }
                    }
                }
            });

            NOM.chartInstances.memory = chart;
        },

        /**
         * Show notification
         */
        showNotice: function (message, type = 'info') {
            const noticeClass = `nom-notice ${type}`;
            const $notice = $(`<div class="${noticeClass}"><p>${message}</p></div>`);

            $('.wrap').prepend($notice);

            setTimeout(() => {
                $notice.fadeOut(() => $notice.remove());
            }, 5000);
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        NOM.init();
    });

    // Expose NOM globally for external use
    window.NOM = NOM;

})(jQuery);
