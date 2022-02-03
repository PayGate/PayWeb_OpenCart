<?php
/*
 * Copyright (c) 2022 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

class ModelExtensionPaymentPaygate extends Model
{

    public function getMethod( $address, $total )
    {
        $this->load->language( 'extension/payment/paygate' );

        $query = $this->db->query( "SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int) $this->config->get( 'payment_paygate_geo_zone_id' ) . "' AND country_id = '" . (int) $address['country_id'] . "' AND (zone_id = '" . (int) $address['zone_id'] . "' OR zone_id = '0')" );

        if ( $this->config->get( 'payment_paygate_total' ) > 0 && $this->config->get( 'payment_paygate_total' ) > $total ) {
            $status = false;
        } elseif ( !$this->config->get( 'payment_paygate_geo_zone_id' ) ) {
            $status = true;
        } elseif ( $query->num_rows ) {
            $status = true;
        } else {
            $status = false;
        }

        $method_data = array();

        if ( $status ) {
            $method_data = array(
                'code'       => 'paygate',
                'title'      => $this->language->get( 'text_paygate_checkout' ) . ' <img src="'.$this->config->get('config_ssl').'catalog/view/theme/default/image/paygate.png" alt="PayGate" title="PayGate" style="border: 0;" />',
                'terms'      => '',
                'sort_order' => $this->config->get( 'payment_paygate_sort_order' ),
            );
        }

        // Add enabled payment methods as checkout options
        $paymethods = [
            'creditcardmethod'   => 'Card',
            'banktransfermethod' => 'SiD Secure EFT',
            'zappermethod'       => 'Zapper',
            'snapscanmethod'     => 'SnapScan',
            'paypalmethod'       => 'PayPal',
            'mobicredmethod'     => 'Mobicred',
            'momopaymethod'      => 'MoMoPay',
            'scantopaymethod'   => 'ScanToPay',
        ];
        $pm = [];
        foreach ( $paymethods as $key => $paymethod ) {
            $setting = 'payment_paygate_' . $key;
            if ( $this->config->get( $setting ) === 'yes' ) {
                $pm[] = ['method' => $key, 'title' => $paymethod];
            }
        }
        $method_data['pay_methods'] = $pm;

        return $method_data;
    }
}
