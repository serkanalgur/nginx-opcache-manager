<?php
/**
 * React Admin Dashboard View
 *
 * Shared by the Dashboard, Analytics and Settings screens. The requested
 * screen is passed to React via the root element's `data-tab` attribute;
 * without it TabPanel has no initialTab and every screen opens the Dashboard.
 *
 * The set of valid tab names is intentionally NOT duplicated here. This value
 * only ever comes from the hard-coded `$nom_tab` literals in
 * admin/class-admin.php, so `sanitize_key()` plus `esc_attr()` is all the
 * escaping it needs, and src/index.js owns the allow-list: it maps anything it
 * does not recognise to the Dashboard tab. A second PHP-side copy of the list
 * could only ever drift from the one that decides what actually renders.
 *
 * @package Nginx_Opcache_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$nom_tab = isset( $nom_tab ) ? sanitize_key( $nom_tab ) : 'dashboard';
?>
<div id="nom-react-root" data-tab="<?php echo esc_attr( $nom_tab ); ?>"></div>
