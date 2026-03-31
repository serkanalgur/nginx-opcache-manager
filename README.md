# Nginx Opcache Manager

A powerful WordPress plugin to monitor and manage Nginx cache and PHP Opcache directly from the WordPress Dashboard.

## Features

- **Real-time Monitoring**: Display live statistics for both Nginx cache and PHP Opcache
- **Nginx Cache Management**: 
  - View cache size and number of cached files
  - Clear cache with one click
  - Configure cache path
- **PHP Opcache Management**:
  - Monitor hit rate, memory usage, and cached scripts
  - Reset opcache with one click
  - Track miss rate and performance metrics
- **Analytics Dashboard**:
  - Beautiful charts showing cache performance over time
  - Historical data logging
  - Memory usage trends
  - Hit/Miss ratio visualization
- **WordPress Dashboard Widget**: Quick cache status overview on WordPress dashboard
- **Settings Page**: Easy configuration of plugin options
- **Performance Metrics**: Get insights into cache effectiveness

## Requirements

- WordPress 4.7 or higher
- PHP 7.2 or higher
- Nginx web server (for cache management features)
- PHP Opcache extension (for opcache features)

## Installation

1. Upload the `nginx-opcache-manager` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to 'Cache Manager' in the admin menu
4. Configure the plugin settings if needed

## Usage

### Dashboard

The main dashboard shows:
- **Nginx Cache Card**: Current cache size, number of cached files, and cache path
- **PHP Opcache Card**: Hit rate, memory usage, and number of cached scripts
- **Quick Actions**: Clear cache and reset opcache buttons

### Analytics Page

View detailed performance analytics:
- Cache size trends over time
- Opcache hit/miss ratio
- Memory usage visualization
- Historical data table with hourly records

### Settings Page

Configure:
- Enable/disable Nginx cache monitoring
- Set Nginx cache path
- Enable/disable admin notifications
- View server information

## AJAX Actions

The plugin provides several AJAX endpoints:

### `nom_get_stats`
Get current cache statistics.

**Request:**
```
POST /wp-admin/admin-ajax.php
action: nom_get_stats
nonce: {nom_nonce}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "nginx": {
      "enabled": true,
      "cache_size": 1048576,
      "cached_files": 42,
      "cache_path": "/var/run/nginx-cache"
    },
    "opcache": {
      "enabled": true,
      "hit_rate": 98.5,
      "used_memory": 67108864,
      "cached_scripts": 120
    }
  }
}
```

### `nom_clear_cache`
Clear Nginx cache.

**Request:**
```
POST /wp-admin/admin-ajax.php
action: nom_clear_cache
nonce: {nom_nonce}
```

### `nom_reset_opcache`
Reset PHP Opcache.

**Request:**
```
POST /wp-admin/admin-ajax.php
action: nom_reset_opcache
nonce: {nom_nonce}
```

## Directory Structure

```
nginx-opcache-manager/
├── nginx-opcache-manager.php          # Main plugin file
├── admin/
│   ├── class-admin.php                # Admin menu and settings
│   ├── class-analytics.php            # Analytics functionality
│   └── views/
│       ├── dashboard.php              # Dashboard page template
│       ├── analytics.php              # Analytics page template
│       ├── settings.php               # Settings page template
│       └── dashboard-widget.php       # Dashboard widget template
├── includes/
│   ├── class-nginx-cache-manager.php # Nginx cache management
│   ├── class-opcache-manager.php     # PHP Opcache management
│   └── class-cache-stats.php         # Statistics tracking
├── assets/
│   ├── css/
│   │   └── admin.css                 # Admin styles
│   └── js/
│       └── admin.js                  # Admin JavaScript
└── README.md

```

## Classes Reference

### `Nginx_Opcache_Manager_Cache`

Manages Nginx cache operations.

**Methods:**
- `is_enabled()`: Check if nginx cache is enabled
- `get_cache_size()`: Get total cache size in bytes
- `get_cached_files_count()`: Get number of cached files
- `clear_cache()`: Clear all nginx cache
- `clear_url_cache($url)`: Clear cache for specific URL
- `get_cache_stats()`: Get cache statistics
- `format_bytes($bytes)`: Format bytes to human readable string

### `Nginx_Opcache_Manager_Opcache`

Manages PHP Opcache operations.

**Methods:**
- `is_enabled()`: Check if opcache is enabled
- `get_status()`: Get opcache status
- `get_config()`: Get opcache configuration
- `get_opcache_stats()`: Get comprehensive opcache statistics
- `reset_opcache()`: Reset opcache
- `invalidate_file($file)`: Invalidate specific file cache
- `get_cached_files()`: Get list of cached files
- `get_memory_percentage()`: Get memory usage percentage
- `format_bytes($bytes)`: Format bytes to human readable string

### `Nginx_Opcache_Manager_Stats`

Tracks and manages cache statistics.

**Methods:**
- `initialize()`: Initialize statistics storage
- `get_nginx_stats()`: Get current nginx statistics
- `get_opcache_stats()`: Get current opcache statistics
- `record_stats()`: Record statistics to history
- `get_history($limit)`: Get statistics history
- `get_current_stats()`: Get current statistics
- `get_dashboard_data()`: Get data formatted for dashboard
- `clear_history()`: Clear all statistics history

## Filter Hooks

None currently, but can be extended.

## Action Hooks

- `nom_record_stats`: Scheduled action to record statistics hourly
- `wp_dashboard_setup`: Used to register dashboard widget

## Nginx Configuration

To ensure proper cache statistics collection, configure your Nginx server with a cache zone:

```nginx
# Cache zone definition
proxy_cache_path /var/run/nginx-cache levels=1:2 keys_zone=my_cache:10m max_size=1g inactive=60m;

# Use in server block
server {
    listen 80;
    server_name example.com;

    location ~ \.php$ {
        # PHP configuration
        proxy_cache my_cache;
        proxy_cache_valid 200 60m;
    }
}
```

Update the "Nginx Cache Path" setting in the plugin to match your configuration.

## Performance Considerations

- Statistics are recorded hourly to avoid excessive database usage
- Only the last 100 records are retained (approximately 24+ hours of data)
- Use read-only operations when monitoring to minimize server load

## Troubleshooting

### Nginx Cache Not Showing
1. Verify the nginx cache path is correct in settings
2. Check file permissions on the cache directory
3. Ensure the PHP user has read access to the cache directory

### Opcache Not Detected
1. Verify opcache is installed: `php -m | grep opcache`
2. Check opcache is enabled in php.ini: `opcache.enable = 1`
3. Verify opcache.enable_cli is not disabling it

### No Statistics Data
1. Clear the WordPress cache
2. Wait for the hourly cron job to run
3. Check WordPress error log for issues

## Security

The plugin includes security measures:
- Nonce verification on all AJAX requests
- Capability checking (manage_options required)
- Sanitized and escaped output
- No privilege escalation

## License

GPL v2 or later

## Support

For issues and feature requests, please visit the plugin's support page.

## Changelog

### Version 1.1.0
- **Added** Cache Flush Activity Logging with persistent database storage
  - Track all cache deletion operations with success/failure status
  - Add post publication and modification tracking
  - Display file paths for debugging and audit trails
- **Added** Customizable fastcgi_cache_key_schema setting
  - Support for custom cache key formats
  - Tag-based template system: $scheme, $request_method, $host, $request_uri, $query_string
- **Added** Clear Activity Logs button to manage database storage
- **Fixed** Fastcgi cache path calculation
  - Corrected directory structure to use last 2 digits before final character
  - Example: `/5/9f/beda56a8736ae8bc335cdd74983649f5` (was `/ed/a5/...`)
- **Fixed** JavaScript type safety issue in escapeHtml() function
- **Improved** Memory optimization by reducing refresh interval from 5s to 30s
- **Improved** Activity log display with color-coded status indicators
- **Added** Debug logging for troubleshooting cache operations

### Version 1.0.1
- **Fixed** JavaScript localization error where nomLocalize object was undefined
- **Fixed** Gauge charts not displaying in Live Performance Metrics section
- **Fixed** Canvas element sizing for proper Chart.js doughnut chart rendering
- **Fixed** Chart initialization requiring history data when none exists
- **Improved** Dashboard initialization to show charts immediately on first load
- **Enhanced** Confirmation dialogs for cache clearing and opcache reset operations

### Version 1.0.0
- Initial release
- Nginx cache management
- PHP Opcache monitoring
- Analytics dashboard
- Statistics tracking
