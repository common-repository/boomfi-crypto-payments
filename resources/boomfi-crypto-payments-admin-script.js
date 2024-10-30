jQuery(document).ready(function ($) {
    let selector = $('#woocommerce_boomfi-crypto-payments_environment');
    function boomfi_crypto_payments_select_fields(){
        let selected = selector.val();
        if (selected === 'test') {
            $('#woocommerce_boomfi-crypto-payments_test_api_key').closest('tr').show();
            $('#woocommerce_boomfi-crypto-payments_test_payment_link').closest('tr').show();
            $('#woocommerce_boomfi-crypto-payments_live_api_key').closest('tr').hide();
            $('#woocommerce_boomfi-crypto-payments_live_payment_link').closest('tr').hide();
        } else {
            $('#woocommerce_boomfi-crypto-payments_test_api_key').closest('tr').hide();
            $('#woocommerce_boomfi-crypto-payments_test_payment_link').closest('tr').hide();
            $('#woocommerce_boomfi-crypto-payments_live_api_key').closest('tr').show();
            $('#woocommerce_boomfi-crypto-payments_live_payment_link').closest('tr').show();
        }
    }
    selector.change(function (e) {
        e.preventDefault();
        boomfi_crypto_payments_select_fields();
    });
    boomfi_crypto_payments_select_fields();
});