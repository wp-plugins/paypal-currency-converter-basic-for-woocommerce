<?php
/* Plugin Name: PayPal Currency Converter BASIC for WooCommerce
 * Plugin URI: http://www.intelligent-it.asia
 * Description: Convert any currency to allowed PayPal currencies for PayPal's Payment Gateway within WooCommerce
 * Version: 1.0
 * Author: Intelligent-IT.asia
 * Author URI: http://www.intelligent-it.asia
 * @author Henry Krupp <henry.krupp@gmail.com> 
 * @copyright 2013 Intelligent IT 
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
		'oer_api_id' => '', //https://openexchangerates.org/
		'api_selection' => 'yahoo',
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
        update_option($this->option_name, $this->data);
		global $woocommerce;
		$options = get_option('ppcc-options');
		$exrdata = get_exchangerate(get_woocommerce_currency(),$options['target_currency']);
		$options['conversion_rate'] = $exrdata;
		$options['time_stamp']= current_time( 'timestamp' );
		$options['retrieval_count'] = $options['retrieval_count'] + 1;
		update_option( 'ppcc-options', $options );
    }

    public function deactivate() {
        delete_option($this->option_name);
    }
	
    public function init() {

		// add target Currency to Paypal and convert

		add_filter( 'woocommerce_paypal_supported_currencies', 'add_new_paypal_valid_currency' );       
			function add_new_paypal_valid_currency( $currencies ) { 
				//global $woocommerce;
				//$options = get_option('ppcc-options');
				array_push ( $currencies , get_woocommerce_currency() );  
				return $currencies;    
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
						++$i;  
					}  
					$discount = $paypal_args['discount_amount_cart'];
					$paypal_args['discount_amount_cart'] = round($discount * $convert_rate, $decimals);
				}  
				return $paypal_args;  
			}  
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

			wp_register_script( 'ppcc_script', plugins_url().'/paypal-currency-converter-basic-for-woocommerce/assets/js/ppcc_script.js','woocommerce.min.js', '1.0', true);//pass variables to javascript
			wp_register_script( 'woocommerce_admin', $woocommerce->plugin_url() . '/assets/js/admin/woocommerce_admin' . $suffix . '.js', array( 'jquery', 'jquery-tiptip'), $woocommerce->version );
			wp_enqueue_style( 'woocommerce_admin_styles', $woocommerce->plugin_url() . '/assets/css/admin.css' );

			$data = array(	
			'plugin_url' => plugins_url(),
							'source_currency' => get_woocommerce_currency(),
							'target_currency' => $options['target_currency'],
							'amount'=>$exrdata,
							'requrl'=>'https://www.google.com/finance/converter?a=1&from='.get_woocommerce_currency().'&to='.$options['target_currency'],
							);
							
			wp_localize_script('ppcc_script', 'php_data', $data);
			wp_enqueue_script('ppcc_script');
			wp_enqueue_script( 'woocommerce_admin' );
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
			<div id="icon-options-general" class="icon32"></div>
            <h2>'. __('PayPal Currency Converter BASIC Settings','PPAC').'</h2>
            <form method="post" action="options.php">';
        settings_fields('ppcc_options');
		($options['api_selection']=="oer_api_id"?$oer_api_checked='checked="checked"': $oer_api_checked='');
		$yahoo_checked='checked="checked"';
		($options['api_selection']=="ecb"?$ecb_checked='checked="checked"': $ecb_checked='');

//		if ($exrdata->rate!=$options['conversion_rate']){
			echo '<div class="error settings-error" visibility="hidden"><p>Please check your current Currency Exchange Rate setting!</p></div>';
//		}

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
						<th class="titledesc" scope="row"><label >'. __('Google\'s conversion rate','PPAC').':</label >
							<img class="help_tip" data-tip="'.__('For informational purpose, only 4 digits accuracy (not used for calculations).','PPAC').'" src="'.plugins_url().'/woocommerce/assets/images/help.png" height="16" width="16" />
						</th>
						
                        <td class="forminp">
							<a href="https://www.google.com/finance?q='. $fromto .'" title="Google Finance"><div id="gc"><img src="' . plugins_url() . '/woocommerce/assets/images/ajax-loader.gif" alt="loading..." /></div></a>
							<img src="https://www.google.com/finance/chart?q=CURRENCY:'. $fromto .'&tkr=1&p=5Y&chst=vkc&chs=400x140"></img><br/>
						</td>
                    </tr>

                    <tr valign="top">
						<th class="titledesc" scope="row">'. __('The Money Converter Rate Ticker','PPAC').':</th>
                        <td class="forminp">
							<iframe id="tmc-ticker" style="height: 30px;width: 400px;" src="http://themoneyconverter.com/'.get_woocommerce_currency().'/RateTicker.aspx" scrolling="no" frameborder="0" marginwidth="0" marginheight="0"></iframe>
						</td>
                    </tr>
                    <tr valign="top">
						<th class="titledesc" scope="row">
							<label >'. __('Shop Conversion Rate','PPAC').': </label>
							<img class="help_tip" data-tip="'. __('Accept suggested rate or set your own conversion rate. (Will be overwritten if scheduled update is active.)','PPAC').'" src="'.plugins_url().'/woocommerce/assets/images/help.png" height="16" width="16" />
						</th>
						<td class="forminp" ><input type="text" id="cr" size="7" name="'. $this->option_name.'[conversion_rate]" value="'.$options['conversion_rate'].'" /><img class="help_tip" data-tip="'. __('Input will be red when custom currency is not equal suggested currency.','PPAC').'" src="'.plugins_url().'/woocommerce/assets/images/help.png" height="16" width="16" />
							'. __('accept','PPAC').'&#9658;<input type="button" id="selected_currency" value="'.$exrdata->rate.'"/>
						</td>
                    </tr>
				</tbody>
				</table>
				<hr>				
				<img class="icon32" src="'.plugins_url().'/paypal-currency-converter-basic-for-woocommerce/assets/images/exr-icon.png"  />
				<h2>'. __('Currency Exchange Rate Data Provider','PPAC').'</h2>
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
				<hr>
				<tbody>
					<tr valign="top">
						<th scope="row" class="titledesc">
							<input type="submit" class="button-primary" value="'. __('Save Changes') .'" />
						</th>
						<td class="forminp" align="right">
							<a href="http://intelligent-it.asia">
								<img class="help_tip" data-tip="'. __('PayPal Currency Converter PRO Plugin was brought to you by intelligent-it.asia.','PPAC').'" src="'.plugins_url().'/paypal-currency-converter-basic-for-woocommerce/assets/images/intelligent-it-logo.png" />
							</a>
						</td>
					</tr>					
				</tbody>
				</table>
            </form>
			<h2>Buy <a href="http://codecanyon.net/item/paypal-currency-converter-pro-for-woocommerce/6343249" title="PAYPAL CURRENCY CONVERTER PRO FOR WOOCOMMERCE" >PAYPAL CURRENCY CONVERTER PRO FOR WOOCOMMERCE</a> plugin and benefit of its additional features such as:</h2>
			<ul style="list-style-type:circle;border-left-width: 13px;margin-left: 19px;">
				<li>Convert any given WooCommerce shop currency to allowed PayPal currencies for PayPal\'s Payment Gateway within WooCommerce on checkout.</li>
				<li>Actual Currency Exchange Rates will be retrieved from "Open Exchange Rates API", YAHOO Finance, or European Central Bank.</li>
				<li>automatic currency exchange rate updates, choose among three different currency exchange rate provider,</li>
				<li>5 digits accuracy</li>				<li>Also integrated are Google\'s exchange rates history chart of the last 5 years and "The Money Converter Rate Ticker".</li>
				<li>Have your virtual product orders automatically completed after checkout!</li>
				<li>Show the current conversion rate in PayPal\'s payment gateway description</li>
				<li>Automatically update the currency exchange rate between your shop currency and the desired PayPal currency with WP-crontrol (or any other cron plugin), your hosting servers cron job, or a 3rd party cron job service.</li>
				<li>Sends notification email to the admin\'s email address when the exchange rate has been updated.</li>
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
		($_GET['settings-updated']=='true' || $_GET['ppcc_function'] == 'cexr_update')? $valid['time_stamp'] = current_time( 'timestamp' ):$valid['time_stamp'] = $input['time_stamp'];
		$valid['oer_api_id'] = $input['oer_api_id'];
		$valid['cur_api_id'] = $input['cur_api_id'];
		$valid['api_selection'] = $input['api_selection'];


		// Logs
		if ( 'on' == $valid['exrlog'] ){
			$this->logging($valid['target_currency'].$valid['conversion_rate']);
		}
		
		return $valid;
	}


	public function logging($msg) {
			global $woocommerce;
			$this->log = $woocommerce->logger();
			$this->log->add( 'ppcc', $msg);
	}

}


//retrieve EX data from the api
function get_exchangerate($from,$to) {
	//update the retrieval counter
	$options = get_option('ppcc-options');
	$options['retrieval_count'] = $options['retrieval_count'] + 1;
	update_option( 'ppcc-options', $options );
	
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