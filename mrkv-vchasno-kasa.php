<?php
/**
 * Plugin Name: MORKVA Vchasno Kasa Integration
 * Plugin URI: https://kasa.vchasno.com.ua/
 * Description: Інтеграція WooCommerce з пРРО Вчасно.Каса
 * Version: 1.1.6
 * Tested up to: 6.9
 * Requires at least: 5.2
 * Requires PHP: 7.1
 * Author: MORKVA
 * Author URI: https://morkva.co.ua
 * Text Domain: mrkv-vchasno-kasa
 * WC requires at least: 5.4.0
 * WC tested up to: 9.6.0
 * Domain Path: /i18n/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

/**
 * 1. Activation code (Check first register)
 * 2. Setup all part of plugin
 * */

// -----------------------------------------------------------------------//
// -------------------------------1.ACTIVATION----------------------------//
// -----------------------------------------------------------------------//

# This prevents a public user from directly accessing your .php files
if (! defined('ABSPATH')) {
    exit;
}

add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

function mrkv_vchasno_kasa_admin_notice() 
{
    if (get_user_meta(get_current_user_id(), 'mrkv_vchasno_kasa_notice_dismissed', true)) {
        return;
    }

    $settings_url = esc_url( admin_url( 'admin.php?page=vchasno_kasa_settings' ) );
    $message = sprintf(
       /* translators: %s is the URL to the settings page */
       __( '<strong>MORKVA Vchasno Kasa Integration</strong> Увага! Перевірте <strong><a href="%s">в налаштуваннях</a></strong> чи для всіх способів оплати вказані label.', 'mrkv-vchasno-kasa' ),
       $settings_url
    );

    ?>
    <div class="notice notice-error is-dismissible mrkv-vchasnokasa-notice">
        <br>
        <p><?php echo wp_kses_post( $message ); ?></p>
        <br>
    </div>
    <script>
        jQuery(document).ready(function($) {
            jQuery(document).on('click', '.mrkv-vchasnokasa-notice .notice-dismiss', function() {
                jQuery.post(ajaxurl, {
                    action: 'mrkv_vchasno_kasa_dismiss_notice',
                    nonce: '<?php echo esc_js( wp_create_nonce("mrkv_vchasno_kasa_notice_nonce") ); ?>'
                });
            });
        });
    </script>
    <?php
}
add_action('admin_notices', 'mrkv_vchasno_kasa_admin_notice');

function mrkv_vchasno_kasa_dismiss_notice() 
{
    check_ajax_referer('mrkv_vchasno_kasa_notice_nonce', 'nonce');
    update_user_meta(get_current_user_id(), 'mrkv_vchasno_kasa_notice_dismissed', true);
    wp_die();
}
add_action('wp_ajax_mrkv_vchasno_kasa_dismiss_notice', 'mrkv_vchasno_kasa_dismiss_notice');

# Include all needed classes for activate
require_once 'classes/mrkv-activate.php'; 

new MRKV_ACTIVATION(__FILE__);

# Setup all before woo init
add_action( 'before_woocommerce_init', function() {
    // -----------------------------------------------------------------------//
    // -------------------------------2.SETUP---------------------------------//
    // -----------------------------------------------------------------------//

    # Include all needed classes for setup
    require_once 'classes/mrkv-setup.php'; 

    # Setup Plugin
    new MRKV_SETUP(__FILE__);
});

?>