<?php
/*
 * Plugin Name: Virtual Pay Payment Gateway
 * Plugin URI: https://www.virtual-pay.io/
 * Description: Receive payments via credit card and Mobile Money Payments - Virtual Pay Payment Gateway Plugin
 * Version: 1.0
 * Author: Virtual Pay
 * Author URI: https://www.virtual-pay.io/
 *Licence: GPL2 
 *WC requires at least: 2.2
 *WC tested up to: 4.9.7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action('plugins_loaded', 'virtualpayio_payment_gateway_init');


 function virtualpayio_adds_to_the_head() {
   wp_enqueue_script('VPJQuery', plugin_dir_url(__FILE__) . 'assets/js/jquery-3.5.1.min.js', array('jquery'));
   wp_enqueue_script('VPProcessing', plugin_dir_url(__FILE__) . 'assets/js/virtual3d.js', array('jquery'));
   wp_enqueue_style( 'VPResponses', plugin_dir_url(__FILE__) . 'assets/css/virtual.css',false,'1.1','all');
}
//Add the css and js files to the header.
add_action( 'wp_enqueue_scripts', 'virtualpayio_adds_to_the_head');

//Request payment function start//
add_action( 'init', function() {
    /** Add a custom path and set a custom query argument. */
    add_rewrite_rule( '^/vp_payment/?([^/]*)/?', 'index.php?vp_payment_action=1', 'top' );
} );

add_filter( 'query_vars', function( $query_vars ) {

    /** Make sure WordPress knows about this custom action. */

    $query_vars []= 'vp_payment_action';

    return $query_vars;

} );

add_action( 'wp', function() {
    /** This is an call for our custom action. */
    if ( get_query_var( 'vp_payment_action' ) ) {
        // your code here
		virtualpayio_request_payment();
    }
} );

add_action( 'init', function() {
    add_rewrite_rule( '^/3dprocess/?([^/]*)/?', 'index.php?3dprocess_action=1', 'top' );
} );


add_filter( 'query_vars', function( $query_vars ) {
    $query_vars []= '3dprocess_action';
    return $query_vars;
} );

//Escaped Function


function virtualpayio_payment_gateway_init() {


    if( !class_exists( 'WC_Payment_Gateway' )) return;

    class WC_VirtualPay_Gateway extends WC_Payment_Gateway {

      /**
         * Define variables
         */
        private $VP_SUCCESS_CALLBACK_URL = "payment_success";
        private $VP_FAILURE_CALLBACK_URL = "payment_failure";
        private $VP_SUCCESS_REDIRECT_URL = "/checkout/order-received/";
        private $VP_FAILURE_REDIRECT_URL = "/checkout/order-received/";
        private $VP_API_HOST = " ";
        private $VP_API_SESSION_CREATE_ENDPOINT = "/checkout/v1/session/create";

        //Constructor
        public function __construct(){		
            $this->id = 'virtualpayio'; // payment gateway plugin ID
            $this->icon =plugin_dir_url( dirname( __FILE__ ) ) . 'Virtual-Pay/assets/images/VirtualPay-Logo-240x40.png';// URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = true; // in case you need a custom credit card form
            $this->method_title = 'Virtual Pay';
            $this->method_description = 'Simple. Seamless. Secure end-to-end payment solutions from https://www.virtual-pay.io'; // will be displayed on the options page
         
            // gateways can support subscriptions, refunds, saved payment methods,
            // but in this tutorial we begin with simple payments
            $this->supports = array(
                'products'
            );
         
            // Method with all the options fields
            $this->init_form_fields();
         
            // Load the settings.
            $this->init_settings();
            $this->title = 'Virtual Pay'; //$this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->enabled = $this->get_option( 'enabled' );
            $this->testmode = 'yes' === $this->get_option( 'testmode' );

            /**
             * Virtual Pay Creds
             */
            $this->merchant_username = $this->testmode ? $this->get_option( 'virtual_payio_test_merchant_username' ) : $this->get_option( 'virtual_payio_merchant_username' );
            $this->merchant_password = $this->testmode ? $this->get_option( 'virtual_payio_test_merchant_password' ) : $this->get_option( 'virtual_payio_merchant_password' );
            

            $_SESSION['virtual_payio_test_merchant_username'] = $this->get_option( 'virtual_payio_test_merchant_username' ); 

            $_SESSION['virtual_payio_test_merchant_password'] = $this->get_option( 'virtual_payio_test_merchant_password' ); 

            $_SESSION['virtual_payio_merchant_password']= $this->get_option( 'virtual_payio_merchant_password'); 

            $_SESSION['virtual_payio_merchant_password']= $this->get_option( 'virtual_payio_merchant_password' ); 

           



            // This action hook saves the settings
           	//Save the admin options
			if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
				add_action( 'woocommerce_update_options_payment_gateways_'.$this->id, array( $this, 'process_admin_options' ) );
			} else {
				add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
			}

            // We need custom JavaScript to obtain a token
            //add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
            add_action( 'woocommerce_receipt_virtualpayio', array( $this, 'virtualpayio_receipt_page' ));
            //webhook
            add_action( 'woocommerce_api_virtualpayio', array( $this, 'webhook' ) );

            /**
             * Site URL
             */
            $this->siteUrl = get_site_url(); 

            /**
             * API Callbacks
             */
            // add_action('woocommerce_thankyou_virtualpay', array($this, 'thankyou_page'));
            add_action( 'woocommerce_api_'. $this->VP_SUCCESS_CALLBACK_URL, array( $this, 'payment_success'));
            add_action( 'woocommerce_api_' . $this->VP_FAILURE_CALLBACK_URL, array( $this, 'payment_failure'));
            
            //add_action('woocommerce_payment_successful_result', 'so_27024470_paypal_redirect', 10, 2 );
            //add_action( 'woocommerce_thankyou', 'virtualPay_redir_based_on_payment_method' );
            add_action( 'woocommerce_api_virtualpayio', array( $this, 'check_ipn_response' ) );




         

        }

        /**
         * Init Form Fields
         */
        public function init_form_fields(){
 
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable Virtual Pay Gateway',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default'     => 'Virtual Pay',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default'     => 'Pay with your credit card or mobile money wallet.',
                ),
                'testmode' => array(
                    'title'       => 'Test mode',
                    'label'       => 'Enable Test Mode',
                    'type'        => 'checkbox',
                    'description' => 'Place the payment gateway in test mode using test API keys.',
                    'default'     => 'yes',
                    'desc_tip'    => true,
                ),
               
                'virtual_payio_test_merchant_username' => array(
                    'title'       => 'Test Merchant Username',
                    'type'        => 'text',
                    'description' => 'Use Virtual Pay Test Merchant Username'
                    
                ),
               
                'virtual_payio_test_merchant_password' => array(
                    'title'       => 'Test Merchant Password',
                    'type'        => 'password',
                    'description' => 'Use Virtual Pay Test Merchant Password'
                ),

               
                'virtual_payio_merchant_username' => array(
                    'title'       => 'Live Merchant Username',
                    'type'        => 'text',
                    'description' => 'Use Virtual Pay Live Merchant Username'
                ),
               
                'virtual_payio_merchant_password' => array(
                    'title'       => 'Live Merchant Password',
                    'type'        => 'password',
                    'description' => 'Use Virtual Pay Live Merchant Password'
                )

            );
 
	 	}
 

         /**
        * Generates the HTML for admin settings page
        */

        public function admin_options(){

            echo '<h3>' . 'Virtual Pay Payment Gateway' . '</h3>';
            echo '<p>' . 'Simple. Seamless. Secure.' . '</p>';
            echo '<table class="form-table">';
            $this->generate_settings_html( );
            echo '</table>';

        }

        /**
         * Receipt Page
         */
        public function virtualpayio_receipt_page( $order_id ) {

            echo $this->virtualayio_generate_iframe( $order_id );
        
        }

        /**
         * Generate IFrame
         */
        public function virtualayio_generate_iframe( $order_id ) {

            global $woocommerce;
            $order = new WC_Order ( $order_id );
            $_SESSION['total'] = (int)$order->order_total;
        
            $tel = $order->billing_phone;

            $nonce = substr(str_shuffle(MD5(microtime())), 0, 12);
            wc_add_order_item_meta($order_id,'ipn_nonce',$nonce);

            $merchantCustomerId = $order->get_user_id();

            $merchantOrderId = $order->get_order_number();

            $orderIdString = '?orderId=' . $order_id;

            $callbackOnSuccessUrl=$this->siteUrl . "//wc-api/" . $this->VP_SUCCESS_CALLBACK_URL . $orderIdString;
            $callbackOnFailureUrl=$this->siteUrl . "//wc-api/" . $this->VP_FAILURE_CALLBACK_URL . $orderIdString;

            $redirectOnSuccessUrl=$this->siteUrl . $this->VP_SUCCESS_REDIRECT_URL . $orderIdString;
            $redirectOnFailureUrl=$this->siteUrl . $this->VP_FAILURE_REDIRECT_URL . $orderIdString;

        if(!$this->testmode){
              $username= $this->get_option('virtual_payio_merchant_username');
              $password= $this->get_option( 'virtual_payio_merchant_password');
              $url='https://evirtualpay.com:5443/api/authenticate';
               $urlmw='https://evirtualpay.com:5443/api/mobileCheckout';
          }else{
              $username= $this->get_option( 'virtual_payio_test_merchant_username' );;
              $password=$this->get_option( 'virtual_payio_test_merchant_password' );
              $url='https://evirtualpay.com:65443/api/authenticate';
              $urlmw='https://evirtualpay.com:65443/api/mobileCheckout';
          }


           // $callback_url = add_query_arg('key', $order->get_order_key(), $order->get_checkout_order_received_url());

          $options="";
          $displayThis="";
          if( get_woocommerce_currency()=="KES"){
              
              $options='<select name="network" id="network">
               <option value=""> Select Network</option>
              <option value="MPESA_KE"> Safaricom</option>
               <option value="AIRTEL_KE">Airtel Kenya</option>
              </select>';
              $displayThis=1;
              
          }
          else if( get_woocommerce_currency()=="TZS"){
               $options='<select name="network" id="network">
               <option value=""> Select Network</option>
              <option value="MPESA_TZ"> Vodacom</option>
               <option value="HALOPESA_TZ">Halopesa</option>
                <option value="AIRTEL_TZ">Airtel Tanzania</option>
              </select>';
               $displayThis=1;
          }
           else if( get_woocommerce_currency()=="UGX"){
               $options='<select name="network" id="network">
               <option value=""> Select Network</option>
              <option value="MTN_UG"> MTN Uganda</option>
               <option value="AIRTEL_UG">Airtel Uganda</option>
              </select>';
               $displayThis=1;
          }
            else if( get_woocommerce_currency()=="ZMW"){
               $options='<select name="network" id="network">
               <option value="ZANTEL_ZM">Zantel</option>
              </select>';
               $displayThis=1;
          }
          else {
               $options="<div class='alert alert-info'>Virtual Pay Mobile Wallet not available in your country</div>";
               
                $displayThis="<div class='alert alert-info'>Virtual Pay Mobile Wallet is not available in this store</div>";
          }
          
          
 
    
    if ($_GET['transactionType']=='checkout') {
                
            echo "<p style='font-size:20px; font-weight:600'>Select Payment Method</p>";
           ?>

            <div id="vpay"></div>
            
            <div class="vp-tab">
                <button class="vp-tablinks active" onclick="openVpTab(event, 'vpByCard')">Pay by Card</button>
                <button class="vp-tablinks" onclick="openVpTab(event, 'vpByWallet')">Pay by Mobile Wallet</button>
            </div>

           
            <form id = "payment_form" method="post" name = "payment_form">
            <div class="vp-card-body card-body">
                
       

            <div class="container vp-container">
            <div id="vpByCard" class="vp-tabcontent" style="display:block">
               
          
  
    <div class="row">
      <div class="col-100">
        <label for="vpCard">Card Number</label>
      </div>
      <div class="col-100">
        <input type="text" id="vpCard" name="vpCard" placeholder="Card Number">
      </div>
    </div>
    <div class="row col-100" style="margin-top:20px">
      <div class="col-50">
        <label for="cardMM">Expiry date</label>
      </div>
      <div class="col-15  mob-15">
        <input type="text" id="cardMM" name="cardMM" placeholder="MM" minlength="2" maxlength="2">
      </div>
      <div class="col-3 mob-sepa">
        &nbsp;/&nbsp;
      </div>
      <div class="col-15 mob-15 mob-15-2">
        <input type="text" id="cardYY" name="cardYY" placeholder="YYYY" minlength="4" maxlength="4">
      </div>

    
   
    </div>

    <div class="row col-100">
      <div class="col-50">
        <label for="cardMM">CVV</label>
      </div>
      <div class="col-15 mob-cvv">
        <input type="text" id="cardCVV" name="cardCVV" placeholder="CVV" minlength="3" maxlength="3">
      </div>
      
   
    
   
    </div>
    
   
    <div class="row vp-button">
                <button type="button" id="pay_button_card" onClick="return payVCard()">Make Payment</button>
            </div>

</div>
        </div> <!--end card section-->
           

<!--mobile wallet-->
<div class="container vp-container">


            <div id="vpByWallet" class="vp-tabcontent" style="display:none">
     <?php if( $displayThis==1){?>      
   <div class="row">
    <div class="col-100">
      <label for="mobilenetwork">Select Mobile Network</label>
      
    </div>
    <div class="col-100">
      
     <?php echo $options;?>
      </select>
    </div>
  </div>
  <div class="row">
    <div class="col-100">
      <label for="vpCard">Enter Mobile Number with country code</label>
    </div>
    <div class="col-100">
      <input type="text" id="vpMobileNo" name="vpMobileNo" placeholder="Mobile Number i.e 254729123456">
    </div>
  </div>
  
 
 
  <div class="row vp-button">
                <button type="button" id="pay_button_wallet" onClick="payWallet()">Make Payment</button>
            </div>
<?php } else {  echo $displayThis;}?>
            </div>
            

        <!--end mobile wallet-->

          

        </div>

        <!--modal-->

<div id="vp_modal" class="vp_modal">

<!-- Modal content -->
<div class="vp_modal-content">
  <div class="vp_loader"></div>
  <p>Please wait...</p>
  <img style="max-width:100px" src="<?php echo plugin_dir_url( dirname( __FILE__ ) ) . 'Virtual-Pay/assets/images/VirtualPay-Logo-240x40.png'; ?>">

</div>

</div>

<!--modal msape-->
<div id="mob_modal" class="vp_modal">

<!-- Modal content -->
<div class="vp_modal-content">
  <div class="vp_loader"></div>
  <p>Please check your device for a USSD popup to authorize this payment. Thank you</p>
 

</div>

</div>
<!--end modal msape-->
        </form>

        <div class="vp3DForm" id="vp3DForm"></div>
        
        <?php
        $nonce = substr(str_shuffle(MD5(microtime())), 0, 12);
        $merchantkey=$username;
        $merchantpass=$password;
        $customerName=$order->get_billing_first_name()." ".$order->get_billing_last_name();
        $merchantID= $merchantkey;
        $requestID=$order_id.'_'.$nonce;
        $orderDate=date('Y-m-d', strtotime(get_post($order->get_id())->post_date));
        $requestTime=date('Y-m-d H:i:s', strtotime(get_post($order->get_id())->post_date));
        $customerPhoneNo=$order->get_billing_phone();
        $amount=wc_format_decimal($order->get_total());
        $currency = $order->get_currency();
        $country=$order->get_billing_country();
        $city=$order->get_billing_city();
        $postalCode=$order->get_billing_postcode();
        $stateCode=$order->get_billing_state();
        $email=$order->get_billing_email();
        $cardUrl= $url; //Virtual Pay Card Processing URL; 
        $mobileUrl=$urlmw; //Virtual Pay Mobile Processing URL
        wc_add_order_item_meta($order_id,'ipn_nonce',$nonce);
        $returnUrl=str_replace( 'https:', 'https:', esc_url(add_query_arg( array(
             'wc-api'=>'virtualpayio'
            ), home_url( '/') ) ));
            
       /* $mobileCallback=   $returnUrl=str_replace( 'https:', 'https:', esc_url(add_query_arg( array(
             'wc-api'=>'virtualpayio',
             ''
            ), home_url( '/') ) ));*/
        ?>
        
    
        <script type="text/javascript">
        
        function payVCard() {
            
             loadModal("block");
         
         var merchantID,requestID, orderDate,requestTime,customerName,customerPhoneNo,cardNumber,expiry,amount,currency,country,city,cvv,postalCode,stateCode,email,request_xml;
             merchantID = '<?php echo  $merchantID;?>';
              requestID='<?php echo $requestID;?>';
              orderDate = '<?php echo  $orderDate;?>';
              requestTime = '<?php echo $requestTime;?>';
              customerName ='<?php echo  $customerName;?>';
              customerPhoneNo = <?php echo $customerPhoneNo;?>;
              cardNumber = jQuery("#vpCard").val();
              expiry = jQuery("#cardMM").val() + jQuery("#cardYY").val();
              amount = <?php echo $amount*100; ?>;
              currency = '<?php echo $currency;?>';
              country = '<?php echo $country;?>';
              city = '<?php echo $city;?>';
              cvv = jQuery("#cardCVV").val();
              postalCode = '<?php echo $postalCode;?>';
              stateCode = '<?php echo  $stateCode;?>';
              email = '<?php echo $email;?>';
              var returnUrl='<?php echo $returnUrl;?>;'
              
    request_xml ="<message>" +"<merchantID>" +merchantID +
    "</merchantID>" +
    "<requestID>" +
    requestID +
    "</requestID>" +
    "<date>" +
    orderDate +
    "</date>" +
    "<requestTime>" +
    requestTime +
    "</requestTime>" +
    "<customerName>" +
    customerName +
    "</customerName>" +
    "<customerPhoneNumber>" +
    customerPhoneNo +
    "</customerPhoneNumber>" +
    "<cardNumber>" +
    cardNumber +
    "</cardNumber>" +
    "<expiry>" +
    expiry +
    "</expiry>" +
    "<amount>" +
    amount +
    "</amount>" +
    "<redirectUrl>"+returnUrl+"</redirectUrl>" +
    "<timeoutUrl>"+returnUrl+"</timeoutUrl>" +
    "<currency>" +
    currency +
    "</currency>" +
    "<country>" +
    country +
    "</country>" +
    "<city>" +
    city +
    "</city>" +
    "<cvv>" +
    cvv +
    "</cvv>" +
    "<postalCode>" +
    postalCode +
    "</postalCode>" +
    "<stateCode>" +
    stateCode +
    "</stateCode>" +
    "<email>" +
    email +
    "</email>" +
    "<description>Virtual Pay Payment for order number " +
    requestID +
    "</description>" +
    "</message>";
  
    
    jQuery.ajax({
                type: "POST",
                url: '<?php echo $cardUrl;?>',
                headers: {
                  "Content-Type": "text/plain",
                  Username:'<?php echo $username;?>',
                  Password: '<?php echo $password?>',
                },
            
                data: request_xml,
                success: function (response) {
                 
                  var response3D = parseXmlToJson(response);
                 
                  //process 3d
                  let payload = {
                    PaReq: response3D["Payload"],
                    MD: response3D["requestID"],
                    TermUrl: response3D["ValidateUrl"],
                  };
            
                  ProcessCard3DRequest(response3D["ACSUrl"], payload, "post");
                  loadModal("none");
                
                },
                error: function (xhr, status, error) {
                  var err = eval("(" + xhr.responseText + ")");
                  
                },
              }); //End of Ajax

                return false;
              
            
            
        }
        
        function parseXmlToJson(xml) {
              const json = {};
              for (const res of xml.matchAll(
                /(?:<(\w*)(?:\s[^>]*)*>)((?:(?!<\1).)*)(?:<\/\1>)|<(\w*)(?:\s*)*\/>/gm
              )) {
                const key = res[1] || res[3];
                const value = res[2] && parseXmlToJson(res[2]);
                json[key] = (value && Object.keys(value).length ? value : res[2]) || null;
              }
              return json;
            }
            /**
             * Process 3D Request
             */
            function ProcessCard3DRequest(path, params, method) {
              const vp3DForm = document.createElement("form");
              vp3DForm.method = "POST";
              vp3DForm.action = path;
              vp3DForm.target = "_parent";
            
              for (const key in params) {
                if (params.hasOwnProperty(key)) {
                  const hiddenField = document.createElement("input");
                  hiddenField.type = "hidden";
                  hiddenField.name = key;
                  hiddenField.value = params[key];
                  vp3DForm.appendChild(hiddenField);
                }
              }
              document.body.appendChild(vp3DForm);
              vp3DForm.submit();
            }
            
    //wallet
    function payWallet() {

              var merchantID = '<?php echo  $merchantID;?>';
              var requestID='<?php echo $requestID;?>';
              var orderDate = '<?php echo  $orderDate;?>'; //
              var requestTime = '<?php echo $requestTime;?>';//
              var customerName ='<?php echo  $customerName;?>';
              var customerPhoneNo = jQuery('#vpMobileNo').val();
              var amount = <?php echo $amount*100; ?>;
              var currency = '<?php echo $currency;?>';
              var country = '<?php echo $country;?>'; //
              var returnUrl='<?php echo $returnUrl;?>';
              
          var walletRequest = {
            date: orderDate,
            requestTime: requestTime,
            country: country ,
            amount:amount,
            redirectUrl: returnUrl,
            merchantID: merchantID,
            requestID: requestID,
            description: "VirtualPay",
            currency: currency,
            customerPhoneNumber:customerPhoneNo,
            customerName:customerName,
            network:jQuery("#network").val(),
          };
          loadModal("block");
          jQuery.ajax({
            url:'<?php echo $mobileUrl;?>',
            headers: {
              Username: '<?php echo $username;?>',
              Password: '<?php echo $password?>',
              "Content-Type": "application/json",
            },
            method: "POST",
            dataType: "json",
            data: JSON.stringify(walletRequest),
            success: function (response) {
                console.log("Mobile Wallet Response is ",response);
              var obj = response;
             // console.log(response['ResponseCode']);
              if(response['ResponseCode']==0){
                  console.log('Processing');
                  loadModalMpesa('block');
                  
                  setTimeout(function(){
                       loadModalMpesa('none');
                       window.location='<?php echo $redirectOnSuccessUrl;?>';
                      }, 
                      1000
                      );
              }
              
              loadModal("none");
               window.location='<?php echo $redirectOnFailureUrl;?>';
            },
            error: function (request, status, error) {
                alert(request.responseText);
                 loadModal("none");
            }
          });
        
          return false;
        }
//end
            
            
        function loadModal(display) {
          var modal = document.getElementById("vp_modal");
          var btn = document.getElementById("myBtn");
          var span = document.getElementsByClassName("vp_close")[0];
          modal.style.display = display;
        }
            function loadModalMpesa(display) {
          var modal = document.getElementById("mob_modal");
          var btn = document.getElementById("myMobBtn");
          var span = document.getElementsByClassName("vp_close")[0];
          modal.style.display = display;
        }
        </script>
            <?php	
            
            
        }
        }

        /**
         * Process Payment and redirect to checkout
         */
        public function process_payment( $order_id ) {

                
            $order = new WC_Order( $order_id );
      
            $_SESSION["orderID"] = $order->id;      		
           // Redirect to checkout/pay page
            $checkout_url = $order->get_checkout_payment_url(true);
            $checkout_edited_url =$checkout_url."&transactionType=checkout";
        
             return array(
                'result' => 'success',
                'redirect' => add_query_arg('order', $order->id,
                    add_query_arg('key', $order->order_key, $checkout_edited_url))
                );
                
                
    
    
    }


//Error Log


   public function write_log($log) {
        if (true === WP_DEBUG) {
            if (is_array($log) || is_object($log)) {
                error_log(print_r($log, true));
            } else {
                error_log($log);
            }
        }
    }


//End Error Log
        /**
         * Process webhook
         */
        public function webhook(){
           
        }

        public function payment_success($order_id) {
            
         
       
         }
         
      
        public function thankyou_page($order_id)
            {
                
              
                
            }
            
        public function check_ipn_response(){
            
             header( 'HTTP/1.1 200 OK' );
             
             global $woocommerce;
             
             $order_id="";
             
             $nonce="";
             
             $log="Duncan was here";
             
             $this->write_log($log);
             
             if(isset($_GET['tid']) && isset($_GET['result'])){
                 
                 $tid=explode('_',sanitize_text_field($_GET['tid']));
                 
                 $order_id=$tid[0];
                 $order_nonce=$tid[1];
                 
                  //if (wc_get_order_item_meta($order_id,'ipn_nonce')!=$nonce) ; // return;
             
                  $order=new WC_Order($order_id);
                  
                  if($_GET['result']==0){
                    
                      $order->add_order_note( __('IPN payment completed', 'woocommerce') );

                      //Update Payment
                      $order->payment_complete();
                      //Reduce Stock
                      wc_reduce_stock_levels($order_id);
                      //Empty cart
                      $woocommerce->cart->empty_cart();
                      wp_redirect($this->get_return_url( $order ));
                  }
                  else {
                      

                         $error_response = sanitize_text_field($_GET['responsedescription']);
                         
                        if ($_GET['responsedescription']) {
                            $error_code = $error_response;
                            $order->add_order_note(__('Payment Failed - ' . $error_code, 'woocommerce'));
                            $order->update_status('failed');
                         
                            
                            wp_redirect($this->get_return_url( $order ));

                        }
                     
                  }
                 //die(); 
             }
             
            
         }

    }
}

function virtualpayio_add_gateway_class( $methods ) {
    $methods[] = 'WC_VirtualPay_Gateway';
    return $methods;
}
if(!add_filter( 'woocommerce_payment_gateways', 'virtualpayio_add_gateway_class' )){
    die;
}


?>