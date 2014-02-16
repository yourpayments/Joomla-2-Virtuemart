<?php
#ini_set("display_errors", true);
#error_reporting(E_ALL);

if (!defined('_VALID_MOS') && !defined('_JEXEC'))
    die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');

#####
# Dmitriy Dubinin, PayU
#####
if (!class_exists('vmPSPlugin'))
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');

class plgVmPaymentPayU extends vmPSPlugin {

    // instance of class
    public static $_this = false;
    


    function __construct(& $subject=null, $config=null) {
    
    if($subject&&$config) parent::__construct($subject, $config);

    $this->_psType = 'payment'; 
    $this->_configTable = '#__virtuemart_' . $this->_psType . 'methods';
    $this->_configTableFieldName = $this->_psType . '_params';
    $this->_configTableFileName = $this->_psType . 'methods'; 
    $this->_configTableClassName = 'Table' . ucfirst($this->_psType) . 'methods'; 
    
    
        $this->_loggable = true;
        $this->tableFields = array_keys($this->getTableSQLFields());
     
        $varsToPush = array( 'payment_logos' => array('', 'char'),
        'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\' ',
        'payment_info' => array('', 'string'),
        'PAYU_MERCHANT' => array('', 'string'),
        'PAYU_SECRET_KEY' => array('', 'string'),
        'PAYU_DEBUG' => array(0, 'int'),
        'status_pending' => array('', 'string'),
        'status_success' => array('', 'string'),
        'PAYU_SYSTEM_CURRENCY' => array('', 'string'),        
        'PAYU_COUNTRY' => array('', 'string'),
        'PAYU_BACK_REF' => array('', 'string'),     
        'PAYU_LANGUAGE' => array('', 'string'),
        'PAYU_VAT' => array('', 'string')
        );

        $res = $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);

    }

    /**
     * Create the table for this plugin if it does not yet exist.
     * @author Valérie Isaksen
     */
    protected function getVmPluginCreateTableSQL() {
    return $this->createTableSQL('Payment Standard Table');
    }
    /**
     * Fields to create the payment table
     * @return string SQL Fileds
     */
    function getTableSQLFields() {
    $SQLfields = array(
        'id' => 'tinyint(1) unsigned NOT NULL AUTO_INCREMENT',
        'virtuemart_order_id' => 'int(11) UNSIGNED DEFAULT NULL',
        'order_number' => 'char(32) DEFAULT NULL',
        'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED DEFAULT NULL',
        'payment_name' => 'char(255) NOT NULL DEFAULT \'\' ',
        'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\' ',
        'payment_currency' => 'char(3) ',
        'cost_per_transaction' => ' decimal(10,2) DEFAULT NULL ',
        'cost_percent_total' => ' decimal(10,2) DEFAULT NULL ',
        'tax_id' => 'smallint(11) DEFAULT NULL'
    );

    return $SQLfields;
    }

    
     function __getVmPluginMethod($method_id) {
    if (!($method = $this->getVmPluginMethod($method_id))) 
    return null; 
    else return $method;
    }
    
    /**
     *
     *
     * @author Valérie Isaksen
     */
    function plgVmConfirmedOrder($cart, $order) {
    if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
        return null; // Another method was selected, do nothing
    }
    if (!$this->selectedThisElement($method->payment_element)) {
        return false;
    }

    include_once( dirname(__FILE__). DS ."/PayU.cls.php" );
    if (!class_exists ('VirtueMartModelCurrency')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');
        }
       

    $lang = JFactory::getLanguage();
    $filename = 'com_virtuemart';
    $lang->load($filename, JPATH_ADMINISTRATOR);
    $vendorId = 0;

    $html = "";

    if (!class_exists('VirtueMartModelOrders'))
        require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
    
    $this->getPaymentCurrency($method);
     $currencyModel = new VirtueMartModelCurrency();
     $currencyObj = $currencyModel->getCurrency ($order['details']['BT']->order_currency);


$narr = $forsend = array();


$currency = "RUB";

if ( $method->PAYU_COUNTRY == "RU"  )
{
   $option['luUrl'] = "https://secure.payu.ru/order/lu.php";
   $currency = "RUB";
}

$currency = ( $method->PAYU_SYSTEM_CURRENCY == 1 ) ? $currencyObj->currency_code_3 : $currency;



$q = 'SELECT `virtuemart_currency_id` FROM `#__virtuemart_currencies` WHERE `currency_code_3` ="'.$currency.'" ';
$db = &JFactory::getDBO();
$db->setQuery($q);
$currency_id = $db->loadResult();

    




foreach ( $cart->products as $v)
{
    $paymentCurrency = CurrencyDisplay::getInstance($currencyObj->virtuemart_currency_id);
    $totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($currency_id, $v->product_price, false), 2);

    $forsend['ORDER_PNAME'][] = $v->product_name;
    $forsend['ORDER_PINFO'][] = $v->product_s_desc;
    $forsend['ORDER_PCODE'][] = $v->virtuemart_product_id;
    $forsend['ORDER_PRICE'][] = $totalInPaymentCurrency; #$v->product_price;
    $forsend['ORDER_QTY'][] = $v->quantity;
    $forsend['ORDER_VAT'][] = $method->PAYU_VAT;
}

$button = "<div><img src='http://www.demo.payu.org.ua/img/loader.gif' width='50px' style='margin:20px 20px;'></div>".
          "<script>
            setTimeout( subform, 100 );
            function subform(){ document.getElementById('PayUForm').submit(); }
          </script>";

$option  = array(   'merchant' => $method->PAYU_MERCHANT, 
                    'secretkey' =>  $method->PAYU_SECRET_KEY, 
                    'debug' => $method->PAYU_DEBUG,
                    'button' => $button );

$user = &$cart->BT;

# Create form for request
$narr = array(
                'ORDER_REF' => $cart->order_number, 
                'ORDER_SHIPPING' => $cart->pricesUnformatted['salesPriceShipment'], 
                'PRICES_CURRENCY' => $currency, 
                'LANGUAGE' => $method->PAYU_LANGUAGE,
                'BILL_FNAME' => $user['first_name'],
                'BILL_LNAME' => $user['last_name'],
                'BILL_EMAIL' => $user['email'],
                'BILL_PHONE' => @$user['phone_1'],
                'BILL_FAX' => @$user['fax'],
                'BILL_ADDRESS' => @$user['address_1'],
                'BILL_ADDRESS2' => @$user['address_2'],
                'BILL_ZIPCODE' => @$user['zip'],
                'BILL_CITY' => @$user['city'],
                'BILL_COUNTRYCODE' => @$user['virtuemart_country_id'],

            );

if ( $method->PAYU_BACK_REF != "" ) $narr['BACK_REF'] = $method->PAYU_BACK_REF;

$forsend  = array_merge( $narr, $forsend );
$html = PayU::getInst()->setOptions( $option )->setData( $forsend )->LU();

$cart->emptyCart();

    JRequest::setVar('html', $html);
    return true;  // empty cart, send order
    }



    /**
     * Check if the payment conditions are fulfilled for this payment method
     * @author: Valerie Isaksen
     *
     * @param $cart_prices: cart prices
     * @param $payment
     * @return true: if the conditions are fulfilled, false otherwise
     *
     */
    protected function checkConditions($cart, $method, $cart_prices) {
        return true;
    }

    /*
     * We must reimplement this triggers for joomla 1.7
     */

    /**
     * Create the table for this plugin if it does not yet exist.
     * This functions checks if the called plugin is active one.
     * When yes it is calling the standard method to create the tables
     * @author Valérie Isaksen
     *
     */
    function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) {
    return $this->onStoreInstallPluginTable($jplugin_id);
    }

    /**
     * This event is fired after the payment method has been selected. It can be used to store
     * additional payment info in the cart.
     *
     * @author Max Milbers
     * @author Valérie isaksen
     *
     * @param VirtueMartCart $cart: the actual cart
     * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
     *
     */
    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart) {
    return $this->OnSelectCheck($cart);
    }

    /**
     * plgVmDisplayListFEPayment
     * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
     *
     * @param object $cart Cart object
     * @param integer $selected ID of the method selected
     * @return boolean True on succes, false on failures, null when this plugin was not selected.
     * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
     *
     * @author Valerie Isaksen
     * @author Max Milbers
     */
    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn) {
    return $this->displayListFE($cart, $selected, $htmlIn);
    }

    /*
     * plgVmonSelectedCalculatePricePayment
     * Calculate the price (value, tax_id) of the selected method
     * It is called by the calculator
     * This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
     * @author Valerie Isaksen
     * @cart: VirtueMartCart the current cart
     * @cart_prices: array the new cart prices
     * @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
     *
     *
     */

    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {
    return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId) {

    if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
        return null; // Another method was selected, do nothing
    }
    if (!$this->selectedThisElement($method->payment_element)) {
        return false;
    }
     $this->getPaymentCurrency($method);

    $paymentCurrencyId = $method->payment_currency;
    }

    /**
     * plgVmOnCheckAutomaticSelectedPayment
     * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
     * The plugin must check first if it is the correct type
     * @author Valerie Isaksen
     * @param VirtueMartCart cart: the cart object
     * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
     *
     */
    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array()) {
    return $this->onCheckAutomaticSelected($cart, $cart_prices);
    }

    /**
     * This method is fired when showing the order details in the frontend.
     * It displays the method-specific data.
     *
     * @param integer $order_id The order ID
     * @return mixed Null for methods that aren't active, text (HTML) otherwise
     * @author Max Milbers
     * @author Valerie Isaksen
     */
    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {
    $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    
    /**
     * This method is fired when showing when priting an Order
     * It displays the the payment method-specific data.
     *
     * @param integer $_virtuemart_order_id The order ID
     * @param integer $method_id  method used for this order
     * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
     * @author Valerie Isaksen
     */
    function plgVmonShowOrderPrintPayment($order_number, $method_id) {
    return $this->onShowOrderPrint($order_number, $method_id);
    }

    function plgVmDeclarePluginParamsPayment($name, $id, &$data) {
    return $this->declarePluginParams('payment', $name, $id, $data);
    }

    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) {
    return $this->setOnTablePluginParams($name, $id, $table);
    }
     
}

// No closing tag
