<?php
/**
 * Plugin Name: مجلهٔ پارمیس
 * Description: صفحهٔ مجله با مقاله‌های زندهٔ وردپرس، جست‌وجو، مسیر مطالعه و چک‌لیست حسابداری. شورت‌کد: [parmis_mag]
 * Version: 1.0.5
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Parmis
 * License: GPL-2.0-or-later
 * Text Domain: parmis-mag
 */

defined( 'ABSPATH' ) || exit;

define( 'PRM_MAG_VERSION', '1.0.5' );
define( 'PRM_MAG_FILE', __FILE__ );
define( 'PRM_MAG_DIR', plugin_dir_path( __FILE__ ) );
define( 'PRM_MAG_URL', plugin_dir_url( __FILE__ ) );

require_once PRM_MAG_DIR . 'includes/class-prm-magazine.php';
PRM_Magazine::init();

if ( is_admin() ) {
    require_once PRM_MAG_DIR . 'includes/class-prm-admin.php';
    PRM_Magazine_Admin::init();
}
