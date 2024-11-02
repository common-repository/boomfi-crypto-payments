<?php

if (!defined('ABSPATH')) {
    exit('No direct script access allowed');
}

/**
 * BoomFi Crypto Payments Payment Gateway
 * @class BoomFi_Crypto_Payments_Payment_Gateway
 * @extends WC_Payment_Gateway
 */
class BoomFi_Crypto_Payments_Payment_Gateway extends WC_Payment_Gateway
{
    public bool $test_mode;
    private string $api_key;
    private string $payment_link;
    private string $payment_link_id;
    private string $merchant_api_base_url;
    private string $merchant_portal_base_url;
    private string $customer_portal_base_url;

    public function __construct()
    {
        $this->id = 'boomfi-crypto-payments';
        $this->title = __('BoomFi Crypto Payments', 'boomfi-crypto-payments');
        $this->description = __('Pay with major cryptocurrencies. Quick & Secure', 'boomfi-crypto-payments');
        $this->method_title = __('BoomFi Crypto Payments', 'boomfi-crypto-payments');
        $this->method_description = __("Accept crypto payments from your customers with BoomFi's crypto payment processor. Choose to settle in crypto stablecoin or fiat to your bank. This plugin requires WooCommerce to be installed and activated as well as a <a href=\"https://boomfi.xyz\">BoomFi merchant account</a>", 'boomfi-crypto-payments');
        $this->icon = apply_filters('boomfi_crypto_payments_icon', plugin_dir_url(__FILE__) . '../assets/images/boomfi.svg');
        $this->has_fields = true;
        $this->order_button_text = __('Proceed to BoomFi Crypto Payments', 'boomfi-crypto-payments');
        $this->supports = ['products'];
        $this->init_form_fields();
        $this->init_settings();

        // read options
        $this->enabled = $this->get_option('enabled') === 'yes';
        $this->test_mode = $this->get_option('environment') === 'test';
        $this->api_key = $this->test_mode ? $this->get_option('test_api_key') : $this->get_option('live_api_key');
        $this->payment_link = $this->test_mode ? $this->get_option('test_payment_link') : $this->get_option('live_payment_link');

        // extract conditional values
        $this->payment_link_id = $this->get_payment_link_id();
        $this->merchant_api_base_url = $this->get_merchant_api_base_url();
        $this->merchant_portal_base_url = $this->get_merchant_portal_base_url();
        $this->customer_portal_base_url = $this->get_customer_portal_base_url();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_thank_you_' . $this->id, [$this, 'thank_you_page']);
        add_action('woocommerce_available_payment_gateways', [$this, 'available_payment_gateways']);
        add_filter('woocommerce_thankyou_order_received_text', [$this, 'order_received_text'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'boomfi_crypto_payments_enqueue_admin_scripts']);
    }

    /**
     * Get payment link identifier from configured payment link
     * @return string payment link identifier
     */
    private function get_payment_link_id(): string
    {
        if (empty($this->payment_link)) {
            return '';
        }

        $path = rtrim(wp_parse_url($this->payment_link, PHP_URL_PATH), "/");
        return array_reverse(explode('/', $path))[0];
    }

    /**
     * Get merchant API base URL
     * @return string merchant API base URL
     */
    private function get_merchant_api_base_url(): string
    {
        return $this->test_mode ? 'https://mapi-test.boomfi.xyz' : 'https://mapi.boomfi.xyz';
    }

    /**
     * Get merchant portal base URL
     * @return string merchant portal base URL
     */
    private function get_merchant_portal_base_url(): string
    {
        return $this->test_mode ? 'https://test.boomfi.xyz' : 'https://app.boomfi.xyz';
    }

    /**
     * Get customer portal base URL
     * @return string customer portal base URL
     */
    private function get_customer_portal_base_url(): string
    {
        return $this->test_mode ? 'https://customer-test.boomfi.xyz' : 'https://customer.boomfi.xyz';
    }

    /**
     * Register admin helper script to show relevant entries based on selected environment
     * @return void
     */
    public function boomfi_crypto_payments_enqueue_admin_scripts(): void
    {
        wp_enqueue_script('boomfi_crypto_payments_admin_script', BOOMFI_CRYPTO_PAYMENTS_PLUGIN_URL . 'resources/boomfi-crypto-payments-admin-script.js', ['jquery'], BOOMFI_CRYPTO_PAYMENTS_PLUGIN_VERSION, true);
    }

    /**
     * Indicates whether plugin configuration has been completed
     * @return bool true when API key or payment link ID is invalid
     */
    public function needs_setup(): bool
    {
        if (empty($this->api_key) || empty($this->payment_link)) {
            return false;
        }

        // try API key
        if (!$this->check_api_key()) {
            return true;
        }

        // try payment link id
        if (!$this->check_payment_link()) {
            return true;
        }


        return false;
    }

    /**
     * Configures BoomFi plugin configuration fields
     * @return void
     */
    public function init_form_fields(): void
    {
        $this->form_fields = apply_filters('boomfi_crypto_payments_settings', [
            'enabled' => [
                'title' => __('Enable/Disable', 'boomfi-crypto-payments'),
                'type' => 'checkbox',
                'description' => __('Tick to enable BoomFi Crypto Payments Gateway', 'boomfi-crypto-payments'),
                'default' => 'no',
            ],
            'environment' => [
                'title' => __('Environment', 'boomfi-crypto-payments'),
                'type' => 'select',
                'options' => ['test' => 'Test', 'live' => 'Live',],
                'description' => __('Select BoomFi payment environment', 'boomfi-crypto-payments'),
                'default' => 'test',
            ],
            'test_api_key' => [
                'title' => __('API Key', 'boomfi-crypto-payments'),
                'type' => 'text',
                'description' => __('Create a BoomFi merchant API key in <a href="https://test.boomfi.xyz/dashboard/settings/api-keys">here</a>', 'boomfi-crypto-payments'),
                'placeholder' => 'TEST-API-KEY',
                'required' => true,
            ],
            'test_payment_link' => [
                'title' => __('Payment Link', 'boomfi-crypto-payments'),
                'type' => 'url',
                'description' => __('Create a one-time payment link <a href="https://test.boomfi.xyz/dashboard/pay-links/new">here</a>. Enable <strong>Allow repeat payments without confirmation</strong> to allow multiple payments from WooCommerce.', 'boomfi-crypto-payments'),
                'placeholder' => "https://pay-test.boomfi.xyz/2k2xd3ym0o3eRRgvDg6FmVIdh3m",
                'required' => true,
            ],
            'live_api_key' => [
                'title' => __('API Key', 'boomfi-crypto-payments'),
                'type' => 'text',
                'description' => __('Create a BoomFi merchant API key in <a href="https://app.boomfi.xyz/dashboard/settings/api-keys">here</a>', 'boomfi-crypto-payments'),
                'placeholder' => 'LIVE-API-KEY',
                'required' => true,
            ],
            'live_payment_link' => [
                'title' => __('Payment Link', 'boomfi-crypto-payments'),
                'type' => 'url',
                'description' => __('Create a one-time payment link <a href="https://app.boomfi.xyz/dashboard/pay-links/new">here</a>. Enable <strong>Allow repeat payments without confirmation</strong> to allow multiple payments from WooCommerce.', 'boomfi-crypto-payments'),
                'placeholder' => "https://pay.boomfi.xyz/2k2xd3ym0o3eRRgvDg6FmVIdh3m",
                'required' => true,
            ],
        ]);
    }

    /**
     * Process given order_id and create varying payment link via BoomFi merchant API
     * @param $order_id mixed order identifier
     * @return array payment processing result
     * @throws Exception When creating varying payment link failed
     */
    public function process_payment($order_id): array
    {
        $order = wc_get_order($order_id);
        $email = $order->get_billing_email();
        $currency = $order->get_currency();
        $amount = $order->get_total();
        $reference = wp_generate_uuid4();
        $customer_name = $order->get_billing_first_name(). ' '.$order->get_billing_last_name();

        $data = [
            'name' => $customer_name,
            'email' => $email,
            'amount' => $amount,
            'currency' => $currency,
            'redirect_to' => $this->get_return_url($order),
            'reference' => $reference,
        ];

        $response = $this->generate_variable_payment_link($data);
        if (wp_remote_retrieve_response_code($response) === 200) {
            $order->update_status('on-hold', __('Awaiting for BoomFi Crypto Payment processing completion', 'boomfi-crypto-payments'));
            $body = json_decode(wp_remote_retrieve_body($response), true);

            // plan type has to be OneTime
            if ($body['data']['plan']['type'] !== 'OneTime') {
                $order->update_status('failed', __('unexpected BoomFi Crypto Payments link recurrence type, expecting OneTime', 'boomfi-crypto-payments'));
                throw new Exception(esc_html__('unexpected BoomFi Crypto Payments link recurrence type, expecting OneTime', 'boomfi-crypto-payments'));
            }

            // omit_possible_duplicate_acknowledgement has to be set in plan or payment_link metadata
            if ($body['data']['plan']['metadata']['omit_possible_duplicate_acknowledgement'] !== true &&
                $body['data']['payment_link']['metadata']['omit_possible_duplicate_acknowledgement'] !== true) {
                $order->update_status('failed', __('unexpected BoomFi Crypto Payments plan metadata', 'boomfi-crypto-payments'));
                throw new Exception(esc_html__('unexpected BoomFi Crypto Payments Plan Metadata, expecting omit_possible_duplicate_acknowledgement to be true', 'boomfi-crypto-payments'));
            }

            // extract variable payment link and it's signature
            $payment_link_url = $body['data']['url'];
            if (empty($payment_link_url)) {
                $order->update_status('failed', __('Invalid BoomFi Crypto Payments payment link URL', 'boomfi-crypto-payments'));
                throw new Exception(esc_html__('Invalid BoomFi Crypto Payments payment link URL', 'boomfi-crypto-payments'));
            }

            // store signature in metadata
            $order->update_meta_data('boomfi_crypto_payments_metadata_invoice_reference', $reference);
            $order->save();

            WC()->cart->empty_cart();

            return [
                'result' => 'success',
                'redirect' => $payment_link_url,
            ];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $message = $body['error']['message'];
        if (!empty($message)) {
            $message = 'BoomFi API error : ' . $message;
            $order->update_status('failed', esc_html($message));
            throw new Exception(esc_html($message));
        }

        $order->update_status('failed', __('BoomFi Crypto Payments API connection error', 'boomfi-crypto-payments'));
        throw new Exception(esc_html__('BoomFi Crypto Payments API connection error', 'boomfi-crypto-payments'));
    }

    /**
     * Return BoomFi plugin icon
     * @return mixed|string|null
     */
    public function get_icon(): mixed
    {
        $base_url = BOOMFI_CRYPTO_PAYMENTS_PLUGIN_URL;
        $icon_html = "<img src=\"$base_url/assets/images/boomfi.svg\"  alt=\"boomfi-crypto-payments-icon\"/>";
        return apply_filters('boomfi-crypto-payments-icon', $icon_html, $this->id);
    }

    /**
     * Register BoomFi payment gateway
     * @param $available_payment_gateways array existing payment gateway array of objects
     * @return array updated array of existing gateways
     */
    public function available_payment_gateways(array $available_payment_gateways): array
    {
        if (!$this->is_available()) {
            unset($available_payment_gateways[$this->id]);
        } else {
            $available_payment_gateways[$this->id] = $this;
        }
        return $available_payment_gateways;
    }

    /**
     * Returns true if the plugin is enabled
     * @return bool true when plugin is enabled
     */
    public function is_available(): bool
    {
        return $this->enabled;
    }

    /**
     * Custom BoomFi order received text.
     *
     * @param string $text Default text.
     * @param WC_Order $order Order data.
     * @return string order completion message
     */
    public function order_received_text(string $text, WC_Order $order): string
    {
        if ($order && $this->id === $order->get_payment_method()) {

            // cross-check with BoomFi Crypt Payments server based on stored invoice reference
            if ($order->meta_exists('boomfi_crypto_payments_metadata_invoice_reference')) {
                $boomfi_invoice_reference = $order->get_meta('boomfi_crypto_payments_metadata_invoice_reference');
                $mapi_url = "$this->merchant_api_base_url/v1/invoices/$boomfi_invoice_reference";
                $response = wp_remote_get($mapi_url, [
                    'timeout' => 30,
                    'headers' => [
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                        'X-Api-Key' => $this->api_key,
                    ],
                ]);
                if (wp_remote_retrieve_response_code($response) === 200) {
                    $body = json_decode(wp_remote_retrieve_body($response), true);

                    $invoice_id = $body['data']['id'];
                    $customer_id = $body['data']['customer_id'];
                    $order->update_meta_data('boomfi_crypto_payments_metadata_invoice_id', "inv_$invoice_id");
                    $order->update_meta_data('boomfi_crypto_payments_metadata_customer_id', "cus_$customer_id");
                    $order->update_meta_data('boomfi_crypto_payments_metadata_customer_profile', "$this->merchant_portal_base_url/dashboard/customers/$customer_id");

                    // store extra details
                    $response_payments = $this->list_invoice_payments($invoice_id);
                    if (wp_remote_retrieve_response_code($response_payments) === 200) {
                        $body_payments = json_decode(wp_remote_retrieve_body($response_payments), true);
                        if (count($body_payments['data']['items'][0]) > 0) {
                            $payment_id = $body_payments['data']['items'][0]['id'];
                            $payment_method = $body_payments['data']['items'][0]['payment_method'];
                            $order->update_meta_data('boomfi_crypto_payments_metadata_payment_id', "pay_$payment_id");
                            $order->update_meta_data('boomfi_crypto_payments_metadata_payment_method', $payment_method);
                            $order->update_meta_data('boomfi_crypto_payments_metadata_payment_chain_id', $body_payments['data']['items'][0]['properties']['chain_id']);
                            $order->update_meta_data('boomfi_crypto_payments_metadata_payment_currency', $body_payments['data']['items'][0]['currency']);
                            $order->update_meta_data('boomfi_crypto_payments_metadata_payment_amount', $body_payments['data']['items'][0]['amount']);
                            $order->update_meta_data('boomfi_crypto_payments_metadata_customer_wallet_address', $body_payments['data']['items'][0]['customer']['wallet_address']);
                            if ($payment_method === 'MerchantContract') {
                                $order->update_meta_data('boomfi_crypto_payments_metadata_payment_token_address', $body_payments['data']['items'][0]['properties']['token_address']);
                            }
                        }
                    }

                    // mark as paid
                    if ($body['data']['payment_status'] === 'Succeeded') {
                        $order->payment_complete();
                    }
                }
            }

            // add link to customer portal
            return $this->test_mode ?
                __('Thank you for your payment using BoomFi Crypto Payments. Your transaction has been completed, and a receipt for your purchase has been emailed to you. Log into <a href="https://customer-test.boomfi.xyz/customer/login">BoomFi account</a> to view your transaction details.', 'boomfi-crypto-payments') :
                __('Thank you for your payment using BoomFi Crypto Payments. Your transaction has been completed, and a receipt for your purchase has been emailed to you. Log into <a href="https://customer.boomfi.xyz/customer/login">BoomFi account</a> to view your transaction details.', 'boomfi-crypto-payments');
        }

        return $text;
    }

    /**
     * Check configured API key before enabling BoomFi payment gateway
     * @return bool true when configured BoomFi API key is valid
     */
    private function check_api_key(): bool
    {
        $mapi_url = "$this->merchant_api_base_url/v1/orgs";
        $response = wp_remote_get($mapi_url, [
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Api-Key' => $this->api_key,
            ],
        ]);
        if (wp_remote_retrieve_response_code($response) === 200) {
            return true;
        }
        return false;
    }

    /**
     * Generate variable payment link based on given options
     * @param $data array associative array to be passed as query string to BoomFi merchant API
     * @return WP_Error|array return value of wp_remote_get
     */
    private function generate_variable_payment_link(array $data): WP_Error|array
    {
        $query = http_build_query($data, arg_separator: '&', encoding_type: PHP_QUERY_RFC3986);
        $mapi_url = "$this->merchant_api_base_url/v1/paylinks/generate-variant/$this->payment_link_id?$query";
        return wp_remote_get($mapi_url, [
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Api-Key' => $this->api_key,
            ],
        ]);
    }

    /**
     * List payments with given invoice_id
     * @param $invoice_id string invoice identifier
     * @return WP_Error|array return value of wp_remote_get
     */
    private function list_invoice_payments(string $invoice_id): WP_Error|array
    {
        $data = ['invoice_id' => $invoice_id];
        $query = http_build_query($data, arg_separator: '&', encoding_type: PHP_QUERY_RFC3986);
        $mapi_url = "$this->merchant_api_base_url/v1/payments?$query";
        return wp_remote_get($mapi_url, [
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Api-Key' => $this->api_key,
            ],
        ]);
    }

    /**
     * Check configured payment link before enabling BoomFi payment gateway
     * @return bool true when configured payment link is valid
     */
    private function check_payment_link(): bool
    {
        // this is not the actual payment amount, it's to test variable link creation
        $data = ['amount' => '2', 'currency' => 'USD',];
        $response = $this->generate_variable_payment_link($data);

        if (wp_remote_retrieve_response_code($response) === 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);

            // plan type has to be OneTime
            if ($body['data']['plan']['type'] !== 'OneTime') {
                return false;
            }

            // expecting omit_possible_duplicate_acknowledgement to be set
            if ($body['data']['plan']['metadata']['omit_possible_duplicate_acknowledgement'] !== true &&
                $body['data']['payment_link']['metadata']['omit_possible_duplicate_acknowledgement'] !== true) {
                return false;
            }

            // extract variable payment link and it's signature
            $payment_link_url = $body['data']['url'];
            if (empty($payment_link_url)) {
                return false;
            }

            return true;
        }

        return false;
    }
}
