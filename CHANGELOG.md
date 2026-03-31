# Changelog

All notable changes to the Nginx Opcache Manager plugin are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/), and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.1.0] - 2026-03-31

### Added
- **Cache Flush Activity Logging**: New database table to persistently track all cache-related activities
  - Logs cache deletion attempts with success/failure status
  - Displays cache file paths for debugging and audit trails
  - Shows post publication and modification events with post details
  - Accessible from main dashboard for easy monitoring
- **Post Activity Tracking**: Monitor when posts are published or modified
  - Display Post ID and title in activity log
  - Color-coded indicators for different event types
  - Automatic logging when posts change or are published
- **Customizable Cache Key Schema**: Allow users to define their own fastcgi_cache_key format
  - Uses tag-based templates: $scheme, $request_method, $host, $request_uri, $query_string
  - Default: "$scheme$request_method$host$request_uri"
  - Settings page for easy configuration
  - Support for various Nginx cache key configurations
- **Clear Activity Logs Button**: New button to clear old activity logs from database
  - Confirmation dialog to prevent accidental deletion
  - One-click clearing of all historical activity records
  - Helps manage database storage

### Fixed
- **Fastcgi Cache Path Calculation**: Fixed incorrect directory structure for cache path
  - Corrected path to: /[last_char]/[2_chars_before_last]/[full_md5] instead of /[first]/[second]/[rest]
  - Example: hash `beda56a8736ae8bc335cdd74983649f5` now correctly generates `/5/9f/beda56a8736ae8bc335cdd74983649f5`
  - Ensures proper cache file deletion at correct locations
- **JavaScript Type Safety**: Added proper string conversion in escapeHtml() function
  - Fixed "text.replace is not a function" error when escaping non-string values
  - Post IDs and other numeric values now safely processed

### Improved
- **Memory Optimization**: Reduced refresh interval from 5 seconds to 30 seconds
  - Prevents memory buildup from frequent AJAX requests
  - Keeps dashboard responsive while reducing server load
  - Database cleanup maintains only last 500 activity entries
- **Activity Log Display**: Enhanced UI for displaying cache and post activities
  - Color-coded status indicators for different activity types
  - Clear icons for different states (✓ deleted, ⊘ not found, ✗ failed, 📝 modified, 📤 published)
  - Structured display of relevant information for each activity type
- **Debug Logging**: Added comprehensive WP_DEBUG logging for troubleshooting
  - Logs cache activity insertion and retrieval
  - Helps trace issues with cache path calculations and activity logging

## [1.0.1] - 2026-03-31

### Fixed
- Fixed JavaScript localization error where `nomLocalize` object was undefined
  - Added second `wp_localize_script()` call to properly pass confirmation dialog strings
  - Confirmation dialogs for cache clearing and opcache reset now display correctly
- Fixed gauge charts not displaying in Live Performance Metrics section
  - Added explicit width and height attributes to canvas elements for proper Chart.js rendering
  - Removed conflicting CSS `max-height` property that interfered with chart sizing
  - Charts now display properly as doughnut/gauge visualizations
- Fixed chart initialization issue on dashboard
  - Added `nomChartData` JSON element with current opcache and nginx statistics
  - Updated chart creation conditions to render dashboard charts on first load without requiring history data
  - Doughnut charts for Hit Rate and Memory Usage now initialize immediately

## [1.0.0] - 2026-03-31

### Added
- Initial release of Nginx Opcache Manager plugin
- Nginx cache monitoring and management
  - View cache size and file count
  - Clear cache with one click
  - Configurable cache path
  - Real-time statistics
- PHP Opcache monitoring and management
  - Monitor hit rate and memory usage
  - Track cached scripts
  - Reset opcache functionality
  - Performance metrics
- Admin dashboard with:
  - Real-time cache statistics cards
  - Quick action buttons
  - Live performance charts
  - Status badges
- Analytics page with:
  - Historical performance data
  - Interactive charts using Chart.js
    - Hit/Miss ratio visualization
    - Memory usage trends
    - Cache size progression
    - Cached files count
  - Statistics table
  - Performance summary
- WordPress dashboard widget
  - Quick cache status overview
  - Jump-to-dashboard link
- Settings page with:
  - Nginx cache path configuration
  - Enable/disable monitoring options
  - Admin notification preferences
  - Server information display
- Administrative features:
  - AJAX endpoints for dynamic data
  - Hourly statistics recording
  - 24+ hours of historical data
  - Automatic data cleanup
- Security features:
  - Nonce verification
  - Capability checking (manage_options)
  - Input sanitization
  - Output escaping
  - XSS/CSRF protection
- Documentation:
  - README with feature overview
  - Installation guide
  - Architecture documentation
  - Inline code comments
  - Configuration templates

### Technical Details
- PHP 7.2+ support
- WordPress 4.7+ support
- Uses WordPress Options API for data storage
- Implements WordPress cron for background tasks
- Follows WordPress Coding Standards
- Responsive admin interface
- Modern UI with CSS Grid
- JavaScript with jQuery
- Chart.js for visualizations

### Security
- Proper nonce handling
- Capability verification
- Input validation and sanitization
- Output escaping
- SQL injection prevention
- File upload safety

### Performance
- Minimal database overhead
- Efficient file system operations
- Client-side chart rendering
- Lazy loading of statistics
- Automatic data retention management

## Planned Features for Future Releases

### Version 1.1.0
- Email notifications for cache issues
- REST API endpoints
- Multi-site support improvements
- Additional chart types
- Export statistics to CSV

### Version 1.2.0
- Custom caching backends (Redis, Memcached)
- Performance recommendations
- Web Vitals integration
- Advanced filtering options

### Version 2.0.0
- Custom database table for statistics
- Advanced analytics
- Machine learning-based optimization suggestions
- Multi-server monitoring

## Known Limitations

- Requires direct file system access to Nginx cache directory
- Opcache statistics available only when Opcache is enabled
- Statistics retained for ~24 hours (100 records)
- No automatic cache invalidation on content changes
- Single server monitoring only (no network cache support)

## Support Policy

- Version 1.0.0 will receive bug fixes and security updates
- PHP 7.2 is the minimum supported version
- WordPress 4.7 is the minimum supported version
- Compatibility tested with latest WordPress versions

## Migration Guide

No previous versions exist. This is the initial release.

## Upgrade Instructions

To upgrade when new versions are released:

1. Backup your WordPress database
2. Deactivate the plugin
3. Upload new plugin files
4. Activate the plugin
5. Settings and data are preserved during upgrades

Note: Major version upgrades (e.g., 1.x to 2.x) may require additional steps.

## Credits

**Development Team**: Serkan Algur Name  
**Contributors**: [List any contributors]  
**Translations**: [List translators when available]  

## Links

- [WordPress Plugin Repository](#) - Coming soon
- [GitHub Repository](#) - [Add your GitHub link]
- [Documentation](#) - Full documentation
- [Support Forum](#) - [Add support link]

---

For detailed information about each release, see the [GitHub Releases](https://github.com/example/nginx-opcache-manager/releases) page.
