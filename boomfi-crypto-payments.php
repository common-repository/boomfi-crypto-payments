<?php

/**
 * Plugin Name: BoomFi Crypto Payments for WooCommerce
 * Author: BoomFi LLC FZ
 * Author URI: https://boomfi.xyz
 * Description: The BoomFi Crypto Payments Plugin enables e-commerce stores to effortlessly accept cryptocurrency payments through WooCommerce.
 * Version: 1.2.1
 * License: GPL-v3
 * text-domain: boomfi-crypto-payments
 * Requires Plugins: woocommerce
 * WC requires at least: 7.6
 * WC tested up to: 8.9.3
 * Requires at least: 6.0
 * Requires PHP: 7.3
 * Version: 7.9.2
 */

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

if (!defined('ABSPATH')) {
    exit('No direct script access allowed');
}

define('BOOMFI_CRYPTO_PAYMENTS_PLUGIN_VERSION', '1.2.1');
define('BOOMFI_CRYPTO_PAYMENTS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BOOMFI_CRYPTO_PAYMENTS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('BOOMFI_CRYPTO_PAYMENTS_PLUGIN_BASENAME', plugin_basename(__FILE__));

add_action('plugins_loaded', 'boomfi_crypto_payments_plugin_loaded');
add_filter('plugin_action_links_' . BOOMFI_CRYPTO_PAYMENTS_PLUGIN_BASENAME, 'boomfi_crypto_payments_settings_link');
add_filter('woocommerce_payment_gateways', 'boomfi_crypto_payments_register_payment_gateway');
add_action('woocommerce_blocks_loaded', 'boomfi_crypto_payments_payment_gateway_block_support');
add_action('before_woocommerce_init', 'boomfi_crypto_payments_register_compatibility');

function boomfi_crypto_payments_settings_link($links): array
{
    $settings = __('Settings', 'boomfi-crypto-payments');
    $links[] = sprintf('<a href="/wp-admin/admin.php?page=wc-settings&tab=checkout&section=boomfi-crypto-payments">%s</a>', $settings);
    return $links;
}

function boomfi_crypto_payments_register_payment_gateway($gateways): array
{
    $gateways[] = 'BoomFi_Crypto_Payments_Payment_Gateway';
    return $gateways;
}

function boomfi_crypto_payments_payment_gateway_block_support(): void
{
    if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        include_once BOOMFI_CRYPTO_PAYMENTS_PLUGIN_PATH . 'includes/blocks/class-boomfi-crypto-payments-payment-gateway-block-support.php';
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                $payment_method_registry->register(new BoomFi_Crypto_Payments_Payment_Gateway_Block_Support());
            }
        );
    }
}

function boomfi_crypto_payments_plugin_loaded(): void
{
    if (!class_exists('BoomFi_Crypto_Payments_Payment_Gateway')) {
        include_once BOOMFI_CRYPTO_PAYMENTS_PLUGIN_PATH . '/includes/class-boomfi-crypto-payments-payment-gateway.php';
    }
}

function boomfi_crypto_payments_register_compatibility(): void
{
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
}