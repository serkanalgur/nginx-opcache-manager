=== Nginx Opcache Manager ===
Contributors: kaisercrazy
Donate link: https://example.com/donate
Tags: nginx, cache, opcache, performance, optimization, server management
Requires at least: 4.7
Requires PHP: 7.2
Tested up to: 6.9.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage and monitor Nginx cache and PHP Opcache directly from WordPress dashboard with real-time analytics and automatic cache invalidation.

== Description ==

Nginx Opcache Manager is a powerful WordPress plugin that provides complete control over your server's cache systems directly from the WordPress dashboard. Monitor cache performance, clear caches with one click, and automatically flush cache when content changes.

= Features =

* **Real-time Cache Monitoring**
  - View Nginx cache size and file count
  - Monitor PHP Opcache hit rate and memory usage
  - Live performance metrics with visual charts
  - Server configuration display

* **Cache Management**
  - Clear Nginx cache with one click
  - Reset PHP Opcache instantly
  - URL-specific cache invalidation
  - Confirmation dialogs for safety

* **Automatic Cache Flushing**
  - Automatically detect content changes
  - Flush related cache on post/page updates
  - Track category and tag modifications
  - Monitor comment activity
  - Customizable auto-flush behavior

* **Analytics Dashboard**
  - Interactive charts using Chart.js
  - Historical performance data tracking
  - 24+ hours of statistics
  - Memory usage trends
  - Hit/Miss ratio visualization
  - Cache flush activity log

* **WordPress Integration**
  - Dedicated admin menu with multiple pages
  - Dashboard widget for quick overview
  - Settings page for configuration
  - AJAX-powered updates
  - Proper WordPress hooks and actions

* **Security**
  - Nonce verification on all AJAX requests
  - Capability checking (manage_options)
  - Input sanitization and output escaping
  - XSS/CSRF protection

= Requirements =

* WordPress 4.7 or higher
* PHP 7.2 or higher
* Nginx web server (for cache features)
* PHP Opcache extension (optional but recommended)

= Use Cases =

* Website administrators managing server performance
* E-commerce sites with frequently updated inventory
* Blogs publishing multiple posts daily
* Multi-author sites needing cache management
* Performance-critical applications
* Sites using Nginx reverse proxy caching

= Author =

Developed by Serkan Algur

= Support =

For questions, issues, or feature requests, please visit the plugin support page or documentation.

== Installation ==

1. **Upload Method:**
   - Download the plugin ZIP file
   - Go to Plugins > Add New > Upload Plugin
   - Select nginx-opcache-manager.zip
   - Click Install Now
   - Activate the plugin

2. **Manual Method:**
   - Extract the plugin folder
   - Upload via FTP to `/wp-content/plugins/`
   - Go to Plugins > Installed Plugins
   - Find "Nginx Opcache Manager"
   - Click Activate

3. **Composer Installation:**
   ```
   composer require your-company/nginx-opcache-manager
   wp plugin activate nginx-opcache-manager
   ```

= Initial Setup =

1. After activation, go to **Cache Manager** in the WordPress admin menu
2. Click on **Settings** tab
3. Configure your Nginx cache path (typically `/var/run/nginx-cache`)
4. Enable Nginx cache monitoring if desired
5. Save changes

= Nginx Configuration =

To ensure proper cache monitoring, configure your Nginx server with a cache zone:

```nginx
# Define cache zone (in http block)
proxy_cache_path /var/run/nginx-cache 
    levels=1:2 
    keys_zone=wordpress:50m 
    max_size=500m 
    inactive=60m;

server {
    listen 80;
    server_name example.com;

    location ~* \.(jpg|jpeg|png|gif|css|js)$ {
        proxy_cache wordpress;
        proxy_cache_valid 200 30d;
    }

    location / {
        proxy_cache wordpress;
        proxy_cache_valid 200 1h;
        add_header X-Cache-Status $upstream_cache_status;
    }
}
```

Update the "Nginx Cache Path" setting in the plugin to match your configuration.

= PHP Opcache Configuration =

Ensure Opcache is enabled in your `php.ini`:

```ini
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
```

== Usage ==

= Dashboard =

The main dashboard displays:

* **Nginx Cache Card** - Cache size, file count, and quick clear button
* **PHP Opcache Card** - Hit rate, memory usage, and reset button
* **Live Performance Metrics** - Doughnut charts showing real-time metrics
* **Cache Flush Activity** - Recent cache invalidation events

= Analytics Page =

View detailed performance analytics:

* Current cache statistics summary
* Historical performance charts (up to 24 hours)
* Memory usage trends
* Cache size progression
* Cached files count
* Statistics history table with hourly records

= Settings Page =

Configure:

* Nginx cache path
* Enable/disable cache monitoring
* Enable/disable admin notifications
* Auto-flush cache on content changes
* View server information

= Auto-Flush Feature =

The plugin automatically flushes cache when:

* Blog posts are created or updated
* Pages are modified
* Posts are published
* Categories or tags are edited
* Comments are posted or approved
* Content is deleted

This ensures visitors always see the latest content without manual cache clearing.

= Cache Flush Activity Log =

Monitor cache flush events on your dashboard:

* See what triggered the cache flush
* View timestamps of each event
* Understand cache invalidation patterns
* Color-coded by event type

== Screenshots ==

1. **Main Dashboard** - Real-time cache statistics with performance charts
2. **Analytics Page** - Historical data and trend visualization
3. **Settings Page** - Configuration options and server information
4. **Cache Flush Activity** - Recent cache invalidation events
5. **Performance Metrics** - Hit rate and memory usage visualization
6. **Dashboard Widget** - Quick cache status overview

== Frequently Asked Questions ==

= Do I need Nginx to use this plugin? =

The Nginx cache features require Nginx as your web server. PHP Opcache monitoring works on any server with PHP Opcache extension. The plugin gracefully handles missing features.

= What if Opcache is not enabled? =

The plugin will still work and display Nginx cache statistics. The Opcache section will show a message that Opcache is not enabled. You can enable it in your php.ini configuration.

= How often are statistics recorded? =

Statistics are recorded hourly via WordPress cron jobs. The plugin maintains a rolling window of 100 records (approximately 24+ hours of data).

= Can I disable auto-cache flushing? =

Yes. Go to Settings and uncheck "Auto-Flush Cache on Content Changes". You can then manage cache manually.

= How much server impact does this plugin have? =

Minimal impact - less than 50ms per content change. Statistics recording runs hourly in the background. Cache operations are quick file system operations.

= Is this plugin secure? =

Yes. The plugin includes:
- Nonce verification on all AJAX requests
- User capability checking (manage_options required)
- Input sanitization
- Output escaping
- XSS/CSRF protection

= What cache paths does it support? =

The plugin supports any Nginx cache directory. Default is `/var/run/nginx-cache`. You can configure a custom path in settings.

= Can I export statistics? =

Currently statistics are not exported, but the data structure supports future export to CSV/PDF features.

= Does it work with WordPress multisite? =

Yes, the plugin works with multisite installations. Each site gets its own cache statistics.

= How do I clear cache before deploying? =

1. Go to Cache Manager > Dashboard
2. Click "Clear Cache" button in Nginx Cache section
3. Click "Reset Opcache" button in PHP Opcache section
4. Deploy your changes

= What happens when the plugin is deactivated? =

Cache statistics are preserved. Auto-flush hooks are removed. You can reactivate anytime without data loss.

= Can developers extend this plugin? =

Yes, the plugin includes hooks and filters for developers to create custom extensions and integrations.

== Changelog ==

= 1.1.0 - March 31, 2026 =

* **Added** Cache Flush Activity Logging with persistent database storage
* **Added** Post publication and modification tracking with post details
* **Added** Customizable fastcgi_cache_key_schema setting for custom cache key formats
* **Added** Clear Activity Logs button to manage database storage
* **Fixed** Fastcgi cache path calculation (corrected folder structure to use last 2 digits before final character)
* **Fixed** JavaScript type safety issue in escapeHtml() function
* **Improved** Memory optimization by reducing refresh interval from 5s to 30s
* **Improved** Activity log display with color-coded status indicators
* **Added** Debug logging for troubleshooting cache operations

= 1.0.1 - March 31, 2026 =

* **Fixed** JavaScript localization error where nomLocalize object was undefined
* **Fixed** Gauge charts not displaying in Live Performance Metrics section
* **Fixed** Canvas element sizing for proper Chart.js doughnut chart rendering
* **Fixed** Chart initialization issue requiring history data when none exists
* **Improved** Dashboard initialization to show charts immediately on first load
* **Enhanced** Confirmation dialogs for cache clearing and opcache reset operations

= 1.0.0 - March 31, 2026 =

* Initial release
* Real-time Nginx cache monitoring
* PHP Opcache statistics and management
* Interactive analytics dashboard
* Hourly statistics recording
* Dashboard widget
* Settings page with configuration options
* Automatic cache flushing on content changes
* Cache flush activity logging
* AJAX endpoints for dynamic updates
* Comprehensive security measures
* Full internationalization support
* Complete documentation

== Upgrade Notice ==

= 1.1.0 =

Major update with cache activity logging, customizable cache key schema, and improved memory optimization. 

= 1.0.0 =

Initial release - no upgrades needed.

== Support ==

For support with this plugin:

1. **Documentation** - Check the plugin's README.md file
2. **Installation Guide** - See INSTALLATION.md for detailed setup
3. **Quick Start** - Review QUICK_START.md for usage examples
4. **Technical Details** - See ARCHITECTURE.md for developer information

== License ==

This plugin is licensed under the GPLv2 or later. See the included LICENSE file for details.

== Privacy ==

This plugin:
* Does NOT collect any data about your website visitors
* Does NOT send data to external servers
* Only stores cache statistics locally in WordPress options
* Cache flush logs are stored temporarily (24 hours)
* All admin features require WordPress user authentication
* No private data is exposed

== Third-Party Services ==

This plugin uses Chart.js library (loaded from CDN) for chart visualization:
* https://www.chartjs.org/
* License: MIT
* Used for: Creating performance metric charts

== Disclaimer ==

This plugin provides cache management features for Nginx and PHP Opcache. Improper configuration could affect site performance. Test thoroughly before deploying to production. Always backup your WordPress database before making configuration changes.

== Credits ==

* Built with WordPress standards
* Uses Chart.js for visualizations
* Follows WordPress coding standards
* Inspired by server performance best practices

== Connect ==

* Website: https://example.com
* Documentation: https://example.com/docs
* Support: https://example.com/support
* GitHub: https://github.com/example/nginx-opcache-manager

== Thank You ==

Thank you for using Nginx Opcache Manager! We appreciate your feedback and support.
