<?
    error_reporting (0);

	$_SERVER['REQUEST_URI']='';
	$_SERVER['SCRIPT_NAME']='';
	$_SERVER['QUERY_STRING']='';
	define('_JEXEC', 1);
	define('DS', DIRECTORY_SEPARATOR);
	$option='com_virtuemart';
	$my_path = dirname(__FILE__);
	$my_path = explode(DS.'plugins',$my_path);	
	$my_path = $my_path[0];			
	if (file_exists($my_path . '/defines.php')) {
		include_once $my_path . '/defines.php';
		}
	if (!defined('_JDEFINES')) {
		define('JPATH_BASE', $my_path);
	require_once JPATH_BASE.'/includes/defines.php';
		}
	define('JPATH_COMPONENT',				JPATH_BASE . '/components/' . $option);
	define('JPATH_COMPONENT_SITE',			JPATH_SITE . '/components/' . $option);
	define('JPATH_COMPONENT_ADMINISTRATOR',	JPATH_ADMINISTRATOR . '/components/' . $option);	
	require_once JPATH_BASE.'/includes/framework.php';
	$app = JFactory::getApplication('site');
	$app->initialise();
	if (!class_exists( 'VmConfig' )) require(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart'.DS.'helpers'.DS.'config.php');
	VmConfig::loadConfig();
	if (!class_exists('VirtueMartModelOrders'))
		require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );			
	if (!class_exists('plgVmPaymentPayU'))
		require(dirname(__FILE__). DS . 'payu.php');					

		require(dirname(__FILE__). DS . 'PayU.cls.php');					

$order_id = $_POST['REFNOEXT'];
$order = new VirtueMartModelOrders();	

$method = new plgVmPaymentPayU();

$order_s_id = $order->getOrderIdByOrderNumber($order_id);
$orderitems = $order->getOrder($order_s_id);	
$methoditems = $method->__getVmPluginMethod($orderitems['details']['BT']->virtuemart_paymentmethod_id);

$option  = array(   'merchant' => $methoditems->PAYU_MERCHANT, 
                    'secretkey' =>  $methoditems->PAYU_SECRET_KEY, 
                    'debug' => $methoditems->PAYU_DEBUG );

$payansewer = PayU::getInst()->setOptions( $option )->IPN();
	echo $payansewer;


	
	$orderitems['order_status'] = $methoditems->status_success;
	$orderitems['customer_notified'] = 0;
	$orderitems['virtuemart_order_id'] = $order_s_id;
	$orderitems['comments'] = 'PayU ID: '.$order_id. " Ref ID : ". $_POST['REFNO'];
	$order->updateStatusForOneOrder($order_s_id, $orderitems, true);

?>