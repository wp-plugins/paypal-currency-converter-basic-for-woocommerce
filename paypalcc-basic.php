<?php
/* Plugin Name: PayPal Currency Converter BASIC(trial) for WooCommerce
 * Plugin URI: http://www.intelligent-it.asia
 * Description: Convert any currency to allowed PayPal currencies for PayPal's Payment Gateway within WooCommerce
 * Version: 1.4
 * Author: Intelligent-IT.asia
 * Author URI: http://www.intelligent-it.asia
 * @author Henry Krupp <henry.krupp@gmail.com> 
 * @copyright 2015 Intelligent IT 
 * @license GNU General Public License v2
 */
 
// Exit if accessed directly
//if ( ! defined( 'ABSPATH' ) ) exit;

// Check if WooCommerce is active and bail if it's not
if ( ! ppcc::is_woocommerce_active() )
	return;
$GLOBALS['ppcc'] = new ppcc();

// localization
    load_plugin_textdomain( 'PPAC', false, plugin_basename( dirname(__FILE__) ) . '/languages' );  

class ppcc {
	//define valid PayPal Currencies
	public $pp_currencies=array( 'AUD', 'BRL', 'CAD', 'MXN', 'NZD', 'HKD', 'SGD', 'USD', 'EUR', 'JPY', 'TRY', 'NOK', 'CZK', 'DKK', 'HUF', 'ILS', 'MYR', 'PHP', 'PLN', 'SEK', 'CHF', 'TWD', 'THB', 'GBP', 'CNY' );//RMB?

    protected $option_name = 'ppcc-options';
	
	//default settings
    protected $data = array(
        'target_currency' => 'USD',
        'conversion_rate' => '1.0',
		'auto_update' => 'on',
		'api_selection' => 'yahoo',
		'transactions' => 0,
		'turnover' => 0,
    );

    public function __construct() {

        add_action('init', array($this, 'init'));

        // Admin sub-menu
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_menu', array($this, 'add_page'));

        // Listen for the activate event
        register_activation_hook(__FILE__, array($this, 'activate'));

        // Deactivation plugin
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    public function activate() {
        //update_option($this->option_name, $this->data);
		global $woocommerce;
		$options = get_option('ppcc-options');
		$exrdata = get_exchangerate(get_woocommerce_currency(),$options['target_currency']);
		$options['conversion_rate'] = $exrdata;
		$options['retrieval_count'] = $options['retrieval_count'] + 1;
		$options['transactions'] = 0;
		$options['turnover'] = 0;
		update_option( 'ppcc-options', $options );
    }

    public function deactivate() {
        //delete_option($this->option_name);
    }
	
    public function init() {

		// add target Currency to Paypal and convert
		$options = get_option('ppcc-options');
		if ($options[turnover]<100 and $options['transactions']<20){
			add_filter( 'woocommerce_paypal_supported_currencies', 'add_new_paypal_valid_currency' );       
			function add_new_paypal_valid_currency( $currencies ) { 
				array_push ( $currencies , get_woocommerce_currency() );  
				return $currencies;    
			}
		}

		add_filter('woocommerce_paypal_args', 'convert_currency');  
			function convert_currency($paypal_args){ 
				global $woocommerce;
				$options = get_option('ppcc-options');

				if ( $paypal_args['currency_code'] == get_woocommerce_currency()){  
					$convert_rate = $options['conversion_rate']; //set the converting rate  
					$paypal_args['currency_code'] = $options['target_currency']; 
					$i = 1;  
					$nondecimalcurrencies=array('HUF','JPY','TWD');
					$decimals= (in_array($paypal_args['currency_code'], $nondecimalcurrencies))?0:2; //non decimal currencies
					while (isset($paypal_args['amount_' . $i])) {  
						$paypal_args['amount_' . $i] = round( $paypal_args['amount_' . $i] * $convert_rate, $decimals);  
						$turnover = $turnover + $paypal_args['amount_' . $i];
						++$i;  
					}  
					$discount = $paypal_args['discount_amount_cart'];
					$paypal_args['discount_amount_cart'] = round($discount * $convert_rate, $decimals);
					$paypal_args['tax_cart'] = 0;
				}
				$options = get_option('ppcc-options');
				$options['transactions'] = $options['transactions'] + 1;
				$options['turnover'] =  round(($options['turnover'] + $turnover),2);
				update_option( 'ppcc-options', $options );
				return $paypal_args;  
			}  
	}

	
		//calculate the converted total and tax amount for the payment-gateway description
	public function ppcc_converted_totals() {
		global $woocommerce;
		$options = get_option('ppcc-options');
		$cart_contents_total = number_format( $woocommerce->cart->cart_contents_total * $options['conversion_rate'], 2, '.', '' )." ".$options['target_currency'];
		$shipping_total = number_format( ($woocommerce->cart->shipping_total)*$options['conversion_rate'], 2, '.', '' )." ".$options['target_currency'];
		//$tax = number_format(array_sum($woocommerce->cart->taxes)*$options['conversion_rate'], 2, '.', '' )." ".$options['target_currency'];
		$cart_tax_total = number_format( ($woocommerce->cart->shipping_tax_total + array_sum($woocommerce->cart->taxes)) * $options['conversion_rate'], 2, '.', '' )." ".$options['target_currency'];
		$shipping_tax_total = number_format(array_sum($woocommerce->cart->shipping_taxes)*$options['conversion_rate'], 2, '.', '' )." ".$options['target_currency'];

		$order_total_exc_tax = number_format( ($woocommerce->cart->cart_contents_total + $woocommerce->cart->shipping_total)*$options['conversion_rate'], 2, '.', '' )." ".$options['target_currency'];
		$order_total_inc_tax = number_format( ($woocommerce->cart->cart_contents_total + $woocommerce->cart->shipping_total + array_sum($woocommerce->cart->taxes) + $woocommerce->cart->shipping_tax_total)*$options['conversion_rate'], 2, '.', '' )." ".$options['target_currency'];
		$tax_total = number_format( ( array_sum($woocommerce->cart->taxes) + $woocommerce->cart->shipping_tax_total)*$options['conversion_rate'], 2, '.', '' )." ".$options['target_currency'];
		$tax_total = number_format( ( array_sum($woocommerce->cart->taxes) + $woocommerce->cart->shipping_tax_total)*$options['conversion_rate'], 2, '.', '' )." ".$options['target_currency'];


		$cr = $options['conversion_rate']." ".$options['target_currency']."/".get_woocommerce_currency();

		//print_r($woocommerce->cart);
		print_r($woocommerce->order);
		//echo '<div id="ppcc" style="display: none;">
		echo '<div id="ppcc">
				<span id="cart_total">'.$cart_contents_total.'</span>
				<span id="cart_tax">'.$cart_tax_total.'</span>
				<span id="shipping_total">'.$shipping_total.'</span>
				<span id="shipping_tax_total">'.$shipping_tax_total.'</span>
				<span id="total_order_exc_tax">'.$order_total_exc_tax.'</span>
				<span id="tax_total">'.$tax_total.'</span>
				<span id="total_order_inc_tax">'.$order_total_inc_tax.'</span>
			</div>';
			
		wp_register_script( 'ppcc_checkout', plugins_url( '/assets/js/ppcc_checkout.js', __FILE__ ),'woocommerce.min.js', '1.0', true);//pass variables to javascript

		//echo $total.$tax.$cr."....".$_POST['payment_method'];

		$data = array(	
						'cart_total' => $cart_contents_total,
						'cart_tax' => $cart_tax_total,
						'shipping_total' => $shipping_total,
						'shipping_tax_total' => $shipping_tax_total,
						'total_order_exc_tax' => $order_total_exc_tax,
						'tax_total' => $order_total_exc_tax,
						'total_order_inc_tax' => $order_total_inc_tax,
						'cr'=>$cr,
						);
						
		wp_localize_script('ppcc_checkout', 'php_data', $data);
		wp_enqueue_script('ppcc_checkout');
			
	}
	
    // White list our options using the Settings API
    public function admin_init() {
        register_setting('ppcc_options', $this->option_name, array($this, 'validate'));
    }

    // Add entry in the WooCommerce settings menu
    public function add_page() {
		add_submenu_page( 'woocommerce', 'Exchange Rates',  'Exchange Rates' , 'manage_options', 'ppcc_options', array($this, 'options_do_page') );
    }

    // Print the menu page itself
    public function options_do_page() {
		if ( !current_user_can( 'manage_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		global $woocommerce;

        $options = get_option($this->option_name);

		$fromto = get_woocommerce_currency().$options['target_currency'];
		if ($_GET['page']=='ppcc_options'){
			$exrdata = get_exchangerate(get_woocommerce_currency(),$options['target_currency']);

			wp_register_script( 'ppcc_script', plugins_url( '/assets/js/ppcc_script.js', __FILE__ ),'woocommerce.min.js', '1.0', true);//pass variables to javascript
			wp_register_script( 'woocommerce_admin', $woocommerce->plugin_url() . '/assets/js/admin/woocommerce_admin.min.js', array( 'jquery', 'jquery-tiptip'), $woocommerce->version );
			wp_enqueue_style( 'woocommerce_admin_styles', $woocommerce->plugin_url() . '/assets/css/admin.css' );

			$data = array(	
							'source_currency' => get_woocommerce_currency(),
							'target_currency' => $options['target_currency'],
							'amount'=>$exrdata,
							);
							
			wp_localize_script('ppcc_script', 'php_data', $data);
			wp_enqueue_script('ppcc_script');
			wp_enqueue_script( 'woocommerce_admin' );
			
			update_paypal_description();
		}

	$currency_selector='<select id="target_cur" name="'.$this->option_name.'[target_currency]">';
		
		foreach($this->pp_currencies as $key => $value)
				{
					if ($options['target_currency']==$value){
						$currency_selector.= '<option value="'.$value.'" selected="selected">'.$value.'</option>';
						}else{
						$currency_selector.= '<option value="'.$value.'">'.$value.'</option>';
						}
				};
		$currency_selector.='</select>
		<label for="ppcc_target_cur"> (convert to currency)</label>';

        
        echo '<div class="wrap">
            <h2><div class="dashicons dashicons-admin-generic"></div>'. __('PayPal Currency Converter BASIC(trial) Settings','PPAC').'</h2>
            <form method="post" action="options.php">';
        settings_fields('ppcc_options');
		($options['api_selection']=="oer_api_id"?$oer_api_checked='checked="checked"': $oer_api_checked='');
		$yahoo_checked='checked="checked"';
		($options['api_selection']=="ecb"?$ecb_checked='checked="checked"': $ecb_checked='');

		if ($options[turnover]>=100 or $options['transactions']>=20){
			echo '<div class="error" ><p>You have been sending a turnover of '.$options['turnover'].$options['target_currency'].' within '.$options['transactions'].' transactions to PayPal... Sorry, trial is over.
			</br>About time to get <a href="http://codecanyon.net/item/paypal-currency-converter-pro-for-woocommerce/6343249" title="PAYPAL CURRENCY CONVERTER PRO FOR WOOCOMMERCE" >PAYPAL CURRENCY CONVERTER PRO FOR WOOCOMMERCE</a> plugin.</p></div>';
			die;
		}
		echo '<div class="error settings-error" visibility="hidden"><p>Please check your current Currency Exchange Rate setting!</p></div>';

		echo'   <table class="form-table">
 				<tbody>
                   <tr valign="top">
						<th class="titledesc" scope="row">
							<label >'.__('Source Currency','PPAC').': </label>
							<img class="help_tip" data-tip="'.__('Source Currency as settled in general settings.','PPAC').'" src="'.plugins_url().'/woocommerce/assets/images/help.png" height="16" width="16" />
						</th>
                        <td class="forminp"><input type="text" size="3" value="'.get_woocommerce_currency().'"  disabled/><label for="ppcc_source_cur"> (convert from currency, this is your WooCommerce Shop Currency)</label></td>
                    </tr>
                    <tr valign="top">
					<th class="titledesc" scope="row">
							<label >'.__('Target Currency','PPAC').': </label>
							<img class="help_tip" data-tip="'.__('Desired target currency, what you expect to be billed in PayPal.','PPAC').'" src="'.plugins_url().'/woocommerce/assets/images/help.png" height="16" width="16" />
						</th>
                        <td class="forminp">'. $currency_selector .'</td>
                    </tr>
                    <tr valign="top">
						<th class="titledesc" scope="row">
							<label >'. __('Shop Conversion Rate','PPAC').': </label>
							<img class="help_tip" data-tip="'. __('Accept suggested rate or set your own conversion rate.','PPAC').'" src="'.plugins_url().'/woocommerce/assets/images/help.png" height="16" width="16" />
						</th>
						<td class="forminp" ><input type="text" id="cr" size="7" name="'. $this->option_name.'[conversion_rate]" value="'.$options['conversion_rate'].'" /><img class="help_tip" data-tip="'. __('Input will be red when custom currency is not equal suggested currency.','PPAC').'" src="'.plugins_url().'/woocommerce/assets/images/help.png" height="16" width="16" />
							'. __('accept','PPAC').'&#9658;<input type="button" id="selected_currency" value="'.$exrdata.'"/>
						</td>
                    </tr>
				</tbody>
				</table>
				<h2><div class="dashicons dashicons-list-view"></div>'. __('Currency Exchange Rate Data Provider','PPAC').'</h2>
				NO guarantees are given whatsoever of accuracy, validity, availability, or fitness for any purpose - please use at your own risk.
				<table class="form-table">
				<tbody>					
				<tr valign="top">
						<th class="titledesc" scope="row">
							<label >'. __('Yahoo Finance','PPAC').': </label>
							<img class="help_tip" data-tip="'. __('This is the BASIC plugin version where  Yahoo Finance is your exchange rate provider.','PPAC').'" src="'.plugins_url().'/woocommerce/assets/images/help.png" height="16" width="16" />
						</th>
						<td class="forminp">
							<input type="radio" id="cur_api" name="'. $this->option_name.'[api_selection]" value="yahoo" '.$yahoo_checked.'/>Source.<a href="http://finance.yahoo.com/">Yahoo Finance</a>							
						</td>
                    </tr>
					</tbody>
				</table>
				<tbody>
					<tr valign="middle">
						<th scope="row" class="titledesc">
							<input type="submit" class="button-primary" value="'. __('Save Changes') .'" />
						</th>
					</tr>					
				</tbody>
				</table>
	         </form>
				<hr>		
				<table>
				<tbody>
					<tr valign="middle">
						<td align="right">
							<a href="http://codecanyon.net/item/paypal-currency-converter-pro-for-woocommerce/6343249" title="PayPal Currency Converter BASIC by intelligent-it.asia"><img src="'.plugins_url( 'assets/images/PPCC-PRO-icon-80x80.png',__file__).'"  alt="FACEBOOK STAR RATING BRONZE"/></a> 
						</td>
						<td class="forminp" align="right"> 
						PayPal Currency Converter BASIC made by</br>
							<a href="http://intelligent-it.asia" title="intelligent-it.asia"><img alt="'. __('PayPal Currency Converter BASIC plugin was brought to you by intelligent-it.asia.','PPCC-PRO').'" src="'.plugins_url('assets/images/intelligent-it-logo.png',__file__).'" /></a></br>
							The IT you deserve.
						</td>
						<td align="right">
						<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
							<input type="hidden" name="cmd" value="_s-xclick">
							<input type="hidden" name="hosted_button_id" value="9D95P85RYDN56">
							<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
							<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
						</form>
						</td>
					</tr>
				</tbody>
				</table>
			<h2>Buy <a href="http://codecanyon.net/item/paypal-currency-converter-pro-for-woocommerce/6343249" title="PAYPAL CURRENCY CONVERTER PRO FOR WOOCOMMERCE" >PAYPAL CURRENCY CONVERTER PRO FOR WOOCOMMERCE</a> plugin and benefit of its additional features such as:</h2>
			<ul style="list-style-type:circle;border-left-width: 13px;margin-left: 19px;">
				<li>Convert any given WooCommerce shop currency to allowed PayPal currencies for PayPal’s Payment Gateway within WooCommerce on checkout.</li>
				<li>Supports upcoming and customized version of <a href="http://www.woothemes.com/products/paypal-digital-goods-gateway/">PayPal Digital Goods gateway</a></li>
				<li>Supports customized version of <a href="https://wordpress.org/plugins/deals-engine/">Social Deals Engine</a></li>
				<li>Converts Shopping cart total.</li>
				<li>Converts Tax</li>
				<li>Converts shipping costs</li>
				<li>Custom Currency</li>
				<li>Show the total, the tax, and current conversion rate in PayPal related payment gateways descriptions</li>
				<li>Automatically update the currency exchange rate between your shop currency and the desired PayPal currency with WP-crontrol (or any other cron plugin), your hosting servers cron job, or a 3rd party cron job service.</li>
				<li>Actual Currency Exchange Rates will be retrieved from “Open Exchange Rates API”, YAHOO Finance, or European Central Bank.</li>
				<li>Google’s exchange rates history chart of the last 5 years.</li>
				<li>Have your virtual product orders automatically completed after checkout!</li>
				<li>Have your non virtual product orders automatically processed after checkout!</li>
				<li>Sends notification email to the admin’s email address when the exchange rate has been updated.</li>
				<li>Logs the actions into a log file.</li>
				<li>Tool-tip help on every item.</li>
				<li>Translation Ready</li>
				<li>Easy to Setup</li>
				<li>Works on Woocommerce 2.0.x+</li>
				<li>Detailed Documentation Included</li>
			</ul>
		</div>';
				
    }


	/**
	 * Checks if WooCommerce is active
	 * @return bool true if WooCommerce is active, false otherwise
	 */
	public static function is_woocommerce_active() {

		$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() )
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );

		return in_array( 'woocommerce/woocommerce.php', $active_plugins ) || array_key_exists( 'woocommerce/woocommerce.php', $active_plugins );
	}	

	public function validate($input) {

		$valid = array();
		$valid['target_currency'] = $input['target_currency'];
		$valid['conversion_rate'] = sanitize_text_field($input['conversion_rate']);
		$valid['oer_api_id'] = $input['oer_api_id'];
		$valid['cur_api_id'] = $input['cur_api_id'];
		$valid['api_selection'] = $input['api_selection'];
		$valid['transactions'] = $input['transactions'];
		$valid['turnover'] =  round($input['turnover'],2);
		return $valid;
	}


	public function logging($msg) {
			global $woocommerce;
			$this->log = $woocommerce->logger();
			$this->log->add( 'ppcc', $msg);
	}

}

//print the currency inside the description of PayPal payment Method using {...} enclosings*
function update_paypal_description(){
			global $woocommerce;
			$options = get_option('ppcc-options');
			
			$paypayl_options = get_option('woocommerce_paypal_settings');
			$ptn = "({.*})";
			preg_match($ptn, $paypayl_options['description'], $matches);
			if (count($matches)>0){
		
				$replace_string='{' .$options['conversion_rate'].$options['target_currency'].'/'.get_woocommerce_currency().'}';
				$paypayl_options['description'] = preg_replace($ptn, $replace_string, $paypayl_options['description']);
			}
			update_option( 'woocommerce_paypal_settings', $paypayl_options );
}

//retrieve EX data from the api
function get_exchangerate($from,$to) {
	$options = get_option('ppcc-options');
	
	if ($options['api_selection']=="oer_api_id" and !isset($options['oer_api_id'])){
			echo '<div class="error settings-error"><p>Please register an Open Exchange Rate API ID first!</p></div>';
			return 1;
			exit;
	}

	if ($options['api_selection']=="oer_api_id"){
		//$json = file_get_contents('http://rate-exchange.appspot.com/currency?from='.$from.'&to='.$to); 
		$url = 'http://openexchangerates.org/api/latest.json?app_id='.$options['oer_api_id']; 
		$json = file_get_contents($url); 
		$data = json_decode($json);
		if($data->error){
			echo '<div class="error settings-error"><p>openexchangerates.org says: '.$data->description.'<br/><a href"'.$url.'">'.$url.'</a></p></div>';
			return 1;
			exit;
		}
		return (string)(round($data->rates->$to/$data->rates->$from,5));
	}

		if ($options['api_selection']=="yahoo"){ //YAHOO http://finance.yahoo.com/d/quotes.csv?e=.csv&f=sl1d1t1&s=USDINR=X
		$requestUrl = "http://finance.yahoo.com/d/quotes.csv?e=.csv&f=sl1d1t1&s=".$from.$to."=X";
		$filesize=2000;
		$handle = fopen($requestUrl, "r");
		$raw = fread($handle, $filesize);
		fclose($handle);
		$quote = explode(",", $raw);
		if(!isset($quote[1])){
			echo '<div class="error settings-error"><p>Could not retrieve any data from Yahoo <br/><a href"'.$requestUrl.'">'.$requestUrl.'</a></p></div>';
			return 1;
			exit;
		}
	return (string)($quote[1]);		
	
	}
	
	
	if ($options['api_selection']=="ecb"){ //eurofx
		$XML=simplexml_load_file("http://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml");
		$efx_data =  array();      
		foreach($XML->Cube->Cube->Cube as $rate){
			$efx_data= $efx_data + array((string)$rate["currency"][0] => (string)$rate["rate"][0]);  
		}
		$efx_data= $efx_data + array("EUR" => 1);
		return (string)round($efx_data[$to]/$efx_data[$from],5);
	}
	
	else{
		echo '<div class="error settings-error"><p>Please select a EXR Source first</p></div>';
		return 1;
		exit;
	}
	
	
}
	
?>