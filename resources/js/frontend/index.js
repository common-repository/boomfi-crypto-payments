
import { sprintf, __ } from '@wordpress/i18n';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';

const settings = getSetting( 'boomfi_crypto_payments_data', {} );

const defaultLabel = __(
    'BoomFi Crypto Payments',
    'woo-gutenberg-products-block'
);

const label = decodeEntities( settings.title ) || defaultLabel;
/**
 * Content component
 */
const Content = () => {
    return decodeEntities( settings.description || '' );
};

const Icon = () => {
    return settings.icon
        ? <img src={settings.icon} style={{ float: 'right', marginRight: '20px' }}  alt={'boomfi'}/>
        : ''
}

const Label = (  ) => {
    return (
        <span style={{width: '100%'}}>
            {label}
            <Icon/>
        </span>
    )
};


/**
 * BoomFi Crypto Payments payment method config object.
 */
const BoomFiCryptoPaymentsPaymentMethod = {
    name: "boomfi-crypto-payments",
    label: <Label />,
    content: <Content />,
    edit: <Content />,
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports,
    },
};

registerPaymentMethod( BoomFiCryptoPaymentsPaymentMethod );