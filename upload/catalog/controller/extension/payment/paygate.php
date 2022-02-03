<?php
/*
 * Copyright (c) 2022 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

class ControllerExtensionPaymentPaygate extends Controller
{
    protected $testmode;

	const CHECKOUT_MODEL = "checkout/order";
	const INFORMATION_CONTACT = "information/contact";

	public function getPaymentMethods(){

		// Add enabled payment methods as checkout options
		$imgs       = 'catalog/view/theme/default/image/';
		$paymethods = [
			'creditcardmethod'   => [
				'title' => 'Card',
				'img'   => $imgs . 'mastercard-visa.svg',
			],
			'banktransfermethod' => [
				'title' => 'SiD Secure EFT',
				'img'   => $imgs . 'sid.svg',
			],
			'zappermethod'       => [
				'title' => 'Zapper',
				'img'   => $imgs . 'zapper.svg',
			],
			'snapscanmethod'       => [
				'title' => 'SnapScan',
				'img'   => $imgs . 'snapscan.svg',
			],
            'paypalmethod'       => [
                'title' => 'PayPal',
                'img'   => $imgs . 'paypal.svg',
            ],
			'mobicredmethod'     => [
				'title' => 'Mobicred',
				'img'   => $imgs . 'mobicred.svg',
			],
			'momopaymethod'      => [
				'title' => 'MoMoPay',
				'img'   => $imgs . 'momopay.svg',
			],
			'scantopaymethod'   => [
				'title' => 'ScanToPay',
				'img'   => $imgs . 'scan-to-pay.svg',
			],
		];
		$pms = [];
		foreach ( $paymethods as $key => $paymethod ) {
			$setting = 'payment_paygate_' . $key;
			if ( $this->config->get( $setting ) === 'yes' ) {
				$pms[] = ['method' => $key, 'title' => $paymethod['title'], 'img' => $paymethod['img']];
			}
		}

		return $pms;
	}

	public function getPayMethodDetails(){
		$data = array();
		$PAY_METHOD        = 'EW';
		switch ( $_POST['paygate_pay_method'] ) {
			case 'creditcardmethod';
				$PAY_METHOD        = 'CC';
				$PAY_METHOD_DETAIL = '';
				break;
			case 'banktransfermethod':
				$PAY_METHOD        = 'BT';
				$PAY_METHOD_DETAIL = 'SID';
				break;
			case 'zappermethod':
				$PAY_METHOD_DETAIL = 'Zapper';
				break;
			case 'snapscanmethod':
				$PAY_METHOD_DETAIL = 'SnapScan';
				break;
            case 'paypalmethod':
                $PAY_METHOD_DETAIL = 'PayPal';
                break;
			case 'mobicredmethod':
				$PAY_METHOD_DETAIL = 'Mobicred';
				break;
			case 'momopaymethod':
				$PAY_METHOD_DETAIL = 'Momopay';
				break;
			case 'scantopaymethod':
				$PAY_METHOD_DETAIL = 'ScanToPay';
				break;
			default:
				$PAY_METHOD_DETAIL = $_POST['paygate_pay_method'];
				break;

		}
		$data['PAY_METHOD'] = $PAY_METHOD;
		$data['PAY_METHOD_DETAIL'] = $PAY_METHOD_DETAIL;
		return $data;
	}

	public function getCurrency(){
		if ( $this->config->get( 'config_currency' ) != '' ) {
			$currency = filter_var( $this->config->get( 'config_currency' ), FILTER_SANITIZE_STRING );
		} else {
			$currency = filter_var( $this->currency->getCode(), FILTER_SANITIZE_STRING );
		}

		return $currency;
	}

	public function getNotifyUrl(){
		$notifyUrl = "";
		if ( $this->config->get( 'payment_paygate_notifyredirect' ) === 'notify' ) {
			$notifyUrl = filter_var(
				$this->url->link( 'extension/payment/paygate/notify_handler', '', true ),
				FILTER_SANITIZE_URL
			);
		}
		return $notifyUrl;
	}

	public function initiate_data($order_info,$pay_method_data){
		$doVault         = '';
		$vaultID         = '';
		$paygateID      = $this->getPaygateId();
		$encryption_key = $this->getEncryptionkey();

		if(isset($pay_method_data['PAY_METHOD'])){
			$PAY_METHOD = $pay_method_data['PAY_METHOD'];
			$PAY_METHOD_DETAIL = $pay_method_data['PAY_METHOD_DETAIL'];
		}

		/* getting order info ********/

		$preAmount = number_format( $order_info['total'], 2, '', '' );
		$reference = filter_var( $order_info['order_id'], FILTER_SANITIZE_STRING );
		$amount    = filter_var( $preAmount, FILTER_SANITIZE_NUMBER_INT );
		$currency = $this->getCurrency();

		$returnUrl = filter_var(
			$this->url->link( 'extension/payment/paygate/paygate_return', '', true ),
			FILTER_SANITIZE_URL
		);
		$returnUrl .= '/' . $reference;
		$transDate = filter_var( date( 'Y-m-d H:i:s' ), FILTER_SANITIZE_STRING );
		$locale    = filter_var( 'en', FILTER_SANITIZE_STRING );
		$country   = filter_var( $order_info['payment_iso_code_3'], FILTER_SANITIZE_STRING );
		$email     = filter_var( $order_info['email'], FILTER_SANITIZE_EMAIL );

		// Check if email empty due to some custom themes displaying this on the same page
		$email           = empty( $email ) ? $this->config->get( 'config_email' ) : $email;
		$payMethod       = isset( $PAY_METHOD ) ? $PAY_METHOD : '';
		$payMethodDetail = isset( $PAY_METHOD_DETAIL ) ? $PAY_METHOD_DETAIL : '';

		// Add notify if enabled
		$notifyUrl = $this->getNotifyUrl();
		$userField1      = $order_info['order_id'];
		$userField2      = $order_info['payment_firstname'] . ' ' . $order_info['payment_lastname'];
		$userField3      = 'opencart-v3.0.8';

		/* getting order info ********/

		$checksum_source = $paygateID . $reference . $amount . $currency . $returnUrl . $transDate;

		$checksum_source .= $locale;
		$checksum_source .= $country;
		$checksum_source .= $email;

		if ( $payMethod ) {
			$checksum_source .= $payMethod;
		}
		if ( $payMethodDetail ) {
			$checksum_source .= $payMethodDetail;
		}
		if ( $notifyUrl ) {
			$checksum_source .= $notifyUrl;
		}

		$checksum_source .= $userField1;
		$checksum_source .= $userField2;
		$checksum_source .= $userField3;

		if ( $doVault != '' ) {
			$checksum_source .= $doVault;
		}
		if ( $vaultID != '' ) {
			$checksum_source .= $vaultID;
		}

		$checksum_source .= $encryption_key;
		$checksum     = md5( $checksum_source );

		$initiateData = array(
			'PAYGATE_ID'        => $paygateID,
			'REFERENCE'         => $reference,
			'AMOUNT'            => $amount,
			'CURRENCY'          => $currency,
			'RETURN_URL'        => $returnUrl,
			'TRANSACTION_DATE'  => $transDate,
			'LOCALE'            => $locale,
			'COUNTRY'           => $country,
			'EMAIL'             => $email,
			'PAY_METHOD'        => $payMethod,
			'PAY_METHOD_DETAIL' => $payMethodDetail,
		);
		if ( $this->config->get( 'payment_paygate_notifyredirect' ) === 'notify' ) {
			$initiateData['NOTIFY_URL'] = $notifyUrl;
		}
		$initiateData['USER1']    = $userField1;
		$initiateData['USER2']    = $userField2;
		$initiateData['USER3']    = $userField3;
		$initiateData['VAULT']    = $doVault;
		$initiateData['VAULT_ID'] = $vaultID;
		$initiateData['CHECKSUM'] = $checksum;

		return $initiateData;
	}

	public function getPaygateId(){
		$this->testmode = $this->config->get( 'payment_paygate_testmode' ) === 'test';
		return $this->testmode ? '10011072130' : filter_var(
                $this->config->get( 'payment_paygate_merchant_id' ),
                FILTER_SANITIZE_STRING
            );
	}

	public function getEncryptionkey(){
		$this->testmode = $this->config->get( 'payment_paygate_testmode' ) === 'test';
		return $this->testmode ? 'secret' : $this->config->get( 'payment_paygate_merchant_key' );
	}

    public function index()
    {
        unset( $this->session->data['REFERENCE'] );

		$dateTime  = new DateTime();
		$time = $dateTime->format( 'YmdHis' );

        $data['text_loading']   = $this->language->get( 'text_loading' );
        $data['button_confirm'] = $this->language->get( 'button_confirm' );
        $data['continue']       = $this->language->get( 'payment_url' );

		$pay_method_data = array();

        $this->load->model( self::CHECKOUT_MODEL );

        $order_info = $this->model_checkout_order->getOrder( $this->session->data['order_id'] );

        if ( empty( $_POST ) && $order_info['payment_code'] === 'paygate' ) {
			/* Get Payment Methods list */
			$pms = $this->getPaymentMethods();

			if ( !empty( $pms ) ) {
				return $this->load->view(
					'extension/payment/paygate_payment_method',
					[
						'pay_methods' => $pms,
						'action'      => $this->url->link(
							'extension/payment/paygate/index',
							'',
							true
						),
					]
				);
			}
        } elseif ( isset( $_POST['paygate_pay_method'] ) ) {
            $pay_method_data = $this->getPayMethodDetails();
        }

        if ( $order_info ) {

			$initiateData = $this->initiate_data($order_info,$pay_method_data);

            $fields_string            = '';

            // Url-ify the data for the POST
            foreach ( $initiateData as $key => $value ) {
                $fields_string .= $key . '=' . $value . '&';
            }

            $fields_string = rtrim( $fields_string, '&' );

            // Open connection
            $ch = curl_init();

            // Set the url, number of POST vars, POST data
            curl_setopt( $ch, CURLOPT_POST, 1 );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 2 );
            curl_setopt( $ch, CURLOPT_URL, 'https://secure.paygate.co.za/payweb3/initiate.trans' );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $ch, CURLOPT_POST, count( $initiateData ) );
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $fields_string );

            // Execute post
            $r = curl_exec( $ch );

            // Close connection
            curl_close( $ch );

            $result = [];
            parse_str( $r, $result );

            if ( isset( $result['ERROR'] ) ) {
                print_r(
                    'Error trying to initiate a transaction, paygate error code: ' . $result['ERROR'] . '. Log support ticket to <a href="' . $this->url->link(
                        self::INFORMATION_CONTACT
                    ) . '">shop owner</a>'
                );

                die();
            }

            $data['CHECKSUM']       = $result['CHECKSUM'];
            $data['PAY_REQUEST_ID'] = $result['PAY_REQUEST_ID'];

            $this->session->data['REFERENCE'] = $time;
        } else {
            print_r(
                'Order could not be found, order_id: ' . $this->session->data['order_id'] . '. Log support ticket to <a href="' . $this->url->link(
                    self::INFORMATION_CONTACT
                ) . '">shop owner</a>'
            );
            die();
        }

        if ( $order_info['payment_code'] === 'paygate' ) {
            $this->cart->clear();
            echo <<<HTML
 <form name="form" id="pw3form" class="form-horizontal text-left"
               action="https://secure.paygate.co.za/payweb3/process.trans" method="post">
             <input type="hidden" name="PAY_REQUEST_ID" value="$data[PAY_REQUEST_ID]"/>
             <input type="hidden" name="CHECKSUM" value="$data[CHECKSUM]"/>
             <div class="buttons">
                 <div class="pull-right"><input type="hidden" value="Confirm" id="button-confirm"
                                                class="btn btn-primary"/>
                 </div>
             </div>
         </form>
         <p style="text-align:center;">Redirecting you to PayGate...</p>
         <script type="text/javascript">document.getElementById("pw3form").submit();</script>
         <script type="text/javascript">$("#button-confirm").hide();</script>
         <script type="text/javascript">$("#button-confirm").trigger('click');</script>
HTML;
        } else {
            return $this->load->view( 'extension/payment/paygate_redirect', $data );
        }
    }

	public function getOrderIdFromSession(){
		// Get order Id from query string as backup if session fails
        $m = [];
		$orderId = 0;
        preg_match( '/^.*\/(\d+)$/', $_GET['route'], $m );
        if ( count( $m ) > 1 ) {
            $orderId = (int) $m[1];
        } elseif ( isset( $this->session->data['order_id'] ) ) {
            $orderId = (int) $this->session->data['order_id'];
        }

		return $orderId;
	}

	public function setActivityData($order,$orderId){
		if ( $this->customer->isLogged() ) {
			$activity_data = array(
				'customer_id' => $this->customer->getId(),
				'name'        => $this->customer->getFirstName() . ' ' . $this->customer->getLastName(),
				'order_id'    => $orderId,
			);
			$this->model_account_activity->addActivity( 'order_account', $activity_data );
		} else {
			$activity_data = array(
				'name'     => $order['firstname'] . ' ' . $order['lastname'],
				'order_id' => $orderId,
			);
			$this->model_account_activity->addActivity( 'order_guest', $activity_data );
		}
	}

	public function mapPGData($result,$useRedirect,$pay_method_desc){
		$pgData = array();
		$orderStatusId = '7';
		$resultsComment = "";
        $status = '';

		if ( isset( $result['TRANSACTION_STATUS'] ) ) {
			$status = 'ok';

			if ( $result['TRANSACTION_STATUS'] == 0 ) {
				$orderStatusId = 1;
				$statusDesc    = 'pending';
				$resultsComment = "Transaction status verification failed. No transaction status. Please contact the shop owner to confirm transaction status.";
			} elseif ( $result['TRANSACTION_STATUS'] == 1 ) {
				$orderStatusId = $this->config->get( 'payment_paygate_success_order_status_id' );
				$statusDesc    = 'approved';
				$resultsComment = "Transaction Approved.";
			} elseif ( $result['TRANSACTION_STATUS'] == 2 ) {
				$orderStatusId = $this->config->get( 'payment_paygate_failed_order_status_id' );
				$statusDesc    = 'declined';
				$resultsComment = "Transaction Declined by PayWeb.";
			} elseif ( $result['TRANSACTION_STATUS'] == 4 ) {
				$orderStatusId = $this->config->get( 'payment_paygate_cancelled_order_status_id' );
				$statusDesc    = 'cancelled';
				$resultsComment = "Transaction Cancelled by User.";
			}
			if ( $useRedirect ) {
				$resultsComment = "Redirect response from PayGate with a status of " . $statusDesc . $pay_method_desc;
			}
		} else {
			$orderStatusId  = 1;
			$statusDesc     = 'pending';
			$resultsComment = 'Transaction status verification failed. No transaction status. Please contact the shop owner to confirm transaction status.';
		}

		$pgData['orderStatusId'] = $orderStatusId;
		$pgData['statusDesc'] = $statusDesc;
		$pgData['resultsComment'] = $resultsComment;
		$pgData['status'] = $status;

		return $pgData;
	}

    /**
     * Handles redirect response from PayGate
     * Is always received
     * Handle according to config setting for Notify/Redirect
     *
     * Must use part of this to get to correct checkout page in notify case,
     * but don't process the order
     */
    public function paygate_return()
    {
        $this->load->language( 'checkout/paygate' );
        $statusDesc = '';
        $status     = '';
        $result     = '';
        $r     = '';
        $error     = '';

        $useRedirect = $this->config->get( 'payment_paygate_notifyredirect' ) === 'redirect';

		$orderId = $this->getOrderIdFromSession();
		$sessionOrderId = $this->session->data['order_id'] ?? 'Session data not set';
        if ( $orderId !== 0 ) {
            // Add to activity log
            $this->load->model( 'account/activity' );
            $this->load->model( self::CHECKOUT_MODEL );
            $order = $this->model_checkout_order->getOrder( $orderId );
            $products = $this->model_checkout_order->getOrderProducts( $orderId );

            $this->setActivityData($order,$orderId);
			$pay_method_desc = '';
            $respData = $this->sendCurlRequest();
			$result = $respData['result'] ?? '';
			$r = $respData['r'] ?? '';
			$error = $respData['error'] ?? '';

            if ( isset( $result['PAY_METHOD_DETAIL'] ) && $result['PAY_METHOD_DETAIL'] != '' ) {
                $pay_method_desc = ', using a payment method of ' . $result['PAY_METHOD_DETAIL'];
            }

            // Mapping pg transactions status with open card statuses
            $pgData = $this->mapPGData($result,$useRedirect,$pay_method_desc);
			$orderStatusId = $pgData['orderStatusId'];
			$statusDesc = $pgData['statusDesc'];
			$resultsComment = $pgData['resultsComment'];
			$status = $pgData['status'];

			$this->restoreCart($products,$statusDesc,$orderId);

			$this->model_checkout_order->addOrderHistory(
				$orderId,
				$orderStatusId,
				$resultsComment,
				true
			);

           if ( $useRedirect ) {
				unset( $this->session->data['shipping_method'] );
				unset( $this->session->data['shipping_methods'] );
				unset( $this->session->data['payment_method'] );
				unset( $this->session->data['payment_methods'] );
				unset( $this->session->data['guest'] );
				unset( $this->session->data['comment'] );
				unset( $this->session->data['order_id'] );
				unset( $this->session->data['coupon'] );
				unset( $this->session->data['reward'] );
				unset( $this->session->data['voucher'] );
				unset( $this->session->data['vouchers'] );
				unset( $this->session->data['totals'] );
			}
		} else {
			$sessionOrderId = $this->session->data['order_id'] ?? 'Session data not set';
		}

        $this->setHeadingValues($result,$status,$error,$r,$sessionOrderId,$statusDesc);
    }

	public function restoreCart($products,$statusDesc,$orderId){

		if ( $statusDesc !== 'approved' && is_array($products)) {
			// Restore the cart which has already been cleared
			foreach ($products as $product){
				$options = $this->model_checkout_order->getOrderOptions($orderId, $product['order_product_id']);
				$option = [];
				if(is_array($options) && count($options) > 0){
					$option = $options;
				}
				$this->cart->add($product['product_id'], $product['quantity'], $option);
			}
		}
	}

	public function sendCurlRequest(){

        $paygateID      = $this->getPaygateId();
        $encryption_key = $this->getEncryptionkey();
		$useRedirect = $this->config->get( 'payment_paygate_notifyredirect' ) === 'redirect';
		$respData = array();
		$orderId = $this->getOrderIdFromSession();
		$r = "";
		$error = false;
		if ( $useRedirect ) {
			// Query to verify response data
			$pay_request_id = filter_var( $_POST['PAY_REQUEST_ID'] ?? '', FILTER_SANITIZE_STRING );
			$reference      = $orderId;
			$checksum       = md5( $paygateID . $pay_request_id . $reference . $encryption_key );
			$queryData      = array(
				'PAYGATE_ID'     => $paygateID,
				'PAY_REQUEST_ID' => $pay_request_id,
				'REFERENCE'      => $reference,
				'CHECKSUM'       => $checksum,
			);

			// Url-ify the data for the POST
			$fields_string = '';
			foreach ( $queryData as $key => $value ) {
				$fields_string .= $key . '=' . $value . '&';
			}

			$fields_string = rtrim( $fields_string, '&' );

			// Open connection
			$ch = curl_init();

			// Set the url, number of POST vars, POST data
			curl_setopt( $ch, CURLOPT_POST, 1 );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 2 );
			curl_setopt( $ch, CURLOPT_URL, 'https://secure.paygate.co.za/payweb3/query.trans' );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $fields_string );

			unset( $this->session->data['REFERENCE'] );

			// Execute post
			$r     = curl_exec( $ch );
			$error = curl_error( $ch );

			// Close connection
			curl_close( $ch );
			$result = [];
			if ( isset( $r ) && $r != '' ) {
				parse_str( $r, $result );
			}

		} else {
			// Use transaction status for redirecting in browser only
			$result = $_POST;
		}
		$respData['result'] = $result;
		$respData['r'] = $r;
		$respData['error'] = $error;
		return $respData;
	}

	public function setHeadingValues($result,$status,$error,$r,$sessionOrderId,$statusDesc){

		if ( $status == 'ok' ) {
            $data['heading_title'] = sprintf( $this->language->get( 'heading_title' ), $statusDesc );
            $this->document->setTitle( $data['heading_title'] );
        } else {
            $data['heading_title'] = sprintf(
                'Transaction status verification failed. Status not ok. Please contact the shop owner to confirm transaction status.'
            );
            $data['heading_title'] .= json_encode( $_POST );
            $data['heading_title'] .= json_encode( $result );
            $data['heading_title'] .= 'Curl error: ' . $error;
            $data['heading_title'] .= 'Curl response: ' . $r;
            $data['heading_title'] .= 'Session data: ' . $sessionOrderId;
            $this->document->setTitle( $data['heading_title'] );
        }

        $data['breadcrumbs']   = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get( 'text_home' ),
            'href' => $this->url->link( 'common/home' ),
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get( 'text_basket' ),
            'href' => $this->url->link( 'checkout/cart' ),
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get( 'text_checkout' ),
            'href' => $this->url->link( 'checkout/checkout', '', 'SSL' ),
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get( 'text_success' ),
            'href' => $this->url->link( 'checkout/success' ),
        );

        if ( $this->customer->isLogged() ) {
            $data['text_message'] = sprintf(
                $this->language->get( 'text_customer' ),
                $this->url->link( 'account/account', '', 'SSL' ),
                $this->url->link( 'account/order', '', 'SSL' ),
                $this->url->link( 'account/download', '', 'SSL' ),
                $this->url->link( self::INFORMATION_CONTACT )
            );
        } else {
            $data['text_message'] = sprintf(
                $this->language->get( 'text_guest' ),
                $this->url->link( self::INFORMATION_CONTACT )
            );
        }

        $data['button_continue'] = $this->language->get( 'button_continue' );
        $data['continue']        = $this->url->link( 'common/home' );
        $data['column_left']     = $this->load->controller( 'common/column_left' );
        $data['column_right']    = $this->load->controller( 'common/column_right' );
        $data['content_top']     = $this->load->controller( 'common/content_top' );
        $data['content_bottom']  = $this->load->controller( 'common/content_bottom' );
        $data['footer']          = $this->load->controller( 'common/footer' );
        $data['header']          = $this->load->controller( 'common/header' );

        $this->response->addHeader( 'Content-Type: text/html; charset=utf-8' );
        $this->response->setOutput( $this->load->view( 'common/paygate_success', $data ) );

	}

    /**
     * Handles notify response from PayGate
     * Controlled by Redirect/Notify setting in config
     */
    public function notify_handler()
    {
        // Shouldn't be able to get here in redirect as notify url is not set in redirect mode
        if ( $this->config->get( 'payment_paygate_notifyredirect' ) === 'notify' ) {
            // Notify PayGate that information has been received
            echo 'OK';

			$errors = isset($EERROR);

            if ( !$errors ) {
                $postData = $this->prepareCheckSumParams();
				$checkSumParams = $postData['checkSumParams'];
				$notify_checksum = $postData['notify_checksum'];
				$transaction_status = $postData['transaction_status'];
				$order_id = $postData['order_id'];
				$pay_method_desc = $postData['pay_method_desc'];

				if ( $checkSumParams != $notify_checksum ) {
					$errors = true;
				}

                if ( !$errors ) {
                    $txn_data = $this->getOrderStatusDesc($transaction_status);
					$orderStatusId = $txn_data['orderStatusId'];
					$statusDesc = $txn_data['statusDesc'];

                    $resultsComment = "Notify response from PayGate with a status of " . $statusDesc . $pay_method_desc;
                    $this->load->model( self::CHECKOUT_MODEL );
                    if ( $statusDesc == 'approved' ) {
                        $this->cart->clear();
                    }
                    $this->model_checkout_order->addOrderHistory( $order_id, $orderStatusId, $resultsComment, true );
                }
            }
        }
    }

	public function prepareCheckSumParams(){

		// Check for test / live modes
		$this->testmode = $this->config->get( 'payment_paygate_testmode' ) === 'test';
		$paygateID      = $this->getPaygateId();
		$encryption_key = $this->getEncryptionkey();

		$checkSumParams     = '';

		$postData = array();
		foreach ( $_POST as $key => $val ) {
			if ( $key == 'PAYGATE_ID' ) {
				$checkSumParams .= $paygateID;
			}

			if ( $key != 'CHECKSUM' && $key != 'PAYGATE_ID' ) {
				$checkSumParams .= $val;
			}

			if ( $key == 'CHECKSUM' ) {
				$notify_checksum = $val;
			}

			if ( $key == 'TRANSACTION_STATUS' ) {
				$transaction_status = $val;
			}

			if ( $key == 'USER1' ) {
				$order_id = $val;
			}

			if ( $key == 'PAY_METHOD_DETAIL' ) {
				$pay_method_desc = ', using a payment method of ' . $val;
			}
		}

		$checkSumParams .= $encryption_key;
		$checkSumParams = md5( $checkSumParams );

		$postData['checkSumParams'] = $checkSumParams;
		$postData['notify_checksum'] = $notify_checksum ?? '';
		$postData['transaction_status'] = $transaction_status ?? '';
		$postData['order_id'] = $order_id ?? '';
		$postData['pay_method_desc'] = $pay_method_desc ?? '';
		return $postData;
	}

	public function getOrderStatusDesc($transaction_status){
		$txn_data = array();
		if ( $transaction_status == 0 ) {
			$orderStatusId = 1;
			$statusDesc    = 'pending';
		} elseif ( $transaction_status == 1 ) {
			$orderStatusId = $this->config->get( 'payment_paygate_success_order_status_id' );
			$statusDesc    = 'approved';
		} elseif ( $transaction_status == 2 ) {
			$orderStatusId = $this->config->get( 'payment_paygate_failed_order_status_id' );
			$statusDesc    = 'declined';
		} elseif ( $transaction_status == 4 ) {
			$orderStatusId = $this->config->get( 'payment_paygate_cancelled_order_status_id' );
			$statusDesc    = 'cancelled';
		}

		$txn_data['orderStatusId'] = $orderStatusId;
		$txn_data['statusDesc'] = $statusDesc;
		return $txn_data;
	}

    public function confirm()
    {
        if ( $this->session->data['payment_method']['code'] == 'paygate' ) {
            $this->load->model( self::CHECKOUT_MODEL );
            $comment = 'Redirected to PayGate';
            $this->model_checkout_order->addOrderHistory(
                $this->session->data['order_id'],
                $this->config->get( 'payment_paygate_order_status_id' ),
                $comment,
                true
            );
        }
    }

    public function before_redirect()
    {
        $json = array();

        if ( $this->session->data['payment_method']['code'] == 'paygate' ) {
            $this->load->model( self::CHECKOUT_MODEL );
            /************** $comment = 'Before Redirect to PayGate'; ***********/
            $this->model_checkout_order->addOrderHistory( $this->session->data['order_id'], 1 );
            $json['answer'] = 'success';
        }

        $this->response->addHeader( 'Content-Type: application/json' );
        $this->response->setOutput( json_encode( $json ) );
    }
}
