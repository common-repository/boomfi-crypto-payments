<?php

if (!defined('ABSPATH')) {
    exit('No direct script access allowed');
}

use \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use \Automattic\WooCommerce\StoreApi\Payments\PaymentContext;
use \Automattic\WooCommerce\StoreApi\Payments\PaymentResult;

/**
 * BoomFi Crypto Payments Payment Block Support
 * @class BoomFi_Crypto_Payments_Payment_Gateway_Block_Support
 * @extends AbstractPaymentMethodType
 */
final class BoomFi_Crypto_Payments_Payment_Gateway_Block_Support extends AbstractPaymentMethodType
{

    private $gateway;
    protected $name;

    public function initialize(): void
    {
        $this->name = 'boomfi-crypto-payments';
        $this->settings = get_option("boomfi_crypto_payments_settings", array());
        $gateways = WC()->payment_gateways()->payment_gateways();
        $this->gateway = $gateways[$this->name];
        add_action('woocommerce_rest_checkout_process_payment_with_context', [$this, 'boomfi_crypto_payments_payment_gateway_block_support_failure_message'], 10, 2);
    }

    /**
     * Check if plugin is enabled
     * @return bool true when plugin is enabled
     */
    public function is_active(): bool
    {
        return $this->gateway->is_available();
    }

    /**
     * Add error message to payment result
     * @param PaymentContext $context payment context
     * @param PaymentResult $result payment
     * @return void
     */
    public function boomfi_crypto_payments_payment_gateway_block_support_failure_message(PaymentContext $context, PaymentResult &$result): void
    {
        if ($context->get_payment_method_instance()->id == 'boomfi-crypto-payments') {
            add_action('boomfi_crypto_payments_payment_error', function ($failed_notice) use (&$result) {
                $payment_details = $result->__get('payment_details');
                $payment_details['errorMessage'] = wp_strip_all_tags($failed_notice);
                $result->set_payment_details($payment_details);
            });
        }
    }

    /**
     * Returns payment method data
     * @return array payment method data
     */
    public function get_payment_method_data(): array
    {
        $base_url = BOOMFI_CRYPTO_PAYMENTS_PLUGIN_URL;

        return array(
            'title' => __('BoomFi Crypto Payments', 'boomfi-crypto-payments'),
            'description' => __('Pay with major cryptocurrencies. Quick & Secure.', 'boomfi-crypto-payments'),
            'icon' => "$base_url/assets/images/boomfi.svg",
        );
    }

    /**
     * Register custom payment method block
     * @return string[] custom payment method block handles
     */
    public function get_payment_method_script_handles(): array
    {
        $script_path = '/assets/js/frontend/blocks.js';
        $script_asset_path = BOOMFI_CRYPTO_PAYMENTS_PLUGIN_URL . 'assets/js/frontend/blocks.asset.php';
        $script_asset = file_exists($script_asset_path)
            ? require($script_asset_path)
            : array(
                'dependencies' => array(),
                'version' => '1.2.0'
            );
        $script_url = BOOMFI_CRYPTO_PAYMENTS_PLUGIN_URL . $script_path;

        wp_register_script(
            'boomfi-crypto-payments-payment-gateway-blocks',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('boomfi-crypto-payments-payment-gateway-blocks', 'boomfi-crypto-payments', BOOMFI_CRYPTO_PAYMENTS_PLUGIN_URL . 'languages/');
        }

        return ['boomfi-crypto-payments-payment-gateway-blocks'];
    }
}