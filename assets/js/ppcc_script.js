jQuery(document).ready(function() {
	jQuery( ".settings-error" ).hide();
	get_ajax("#gc");
	var googlerate=jQuery('#currency_converter_result span.bld').text().replace(/[^\d\.]/g, '');
	googlerate = Math.round(googlerate * 1000) / 1000000;
	//jQuery("#gc").html(currency()+' ***'+php_data.target_currency+'/'+php_data.source_currency);
	//jQuery("#gc").html(googlerate + ' ***' + php_data.target_currency + '/' + php_data.source_currency);

	jQuery('#selected_currency').val(currency() + ' ' + php_data.target_currency + '/' + php_data.source_currency);
	if (jQuery('#cr').val()==currency()){
		jQuery('#cr').css('color', 'green');
	}else{
		jQuery('#cr').css('color', 'red');
		jQuery( ".settings-error" ).show();
		//alert ('please check your current currency setting!');
	}
});
jQuery(function(){
	// Tooltips
	jQuery(".tips, .help_tip").tipTip({
    	'attribute' : 'data-tip',
    	'fadeIn' : 50,
    	'fadeOut' : 50,
    	'delay' : 200
    });
});

//Change target Currency
	jQuery('#target_cur').change(function() {
		jQuery(this).parents('form').submit();
	});
//Accept Currency
	jQuery('#selected_currency').click(function() {
	jQuery('#cr').val(currency());
	jQuery('#cr').css('color', 'green');
	});		

function currency(){
	//var currate=jQuery('#currency_converter_result span.bld').text().replace(/[^\d\.]/g, '');
	var currate=php_data.amount;
	//currate = Math.round(currate * 1000) / 1000000;
	//jQuery('#cr').val(currate);
	return currate;
}

function oc_currency(){
	//var currate=jQuery('#currency_converter_result span.bld').text().replace(/[^\d\.]/g, '');
	var currate=php_data.amount;
	//currate = Math.round(currate * 1000) / 1000000;
	//jQuery('#cr').val(crate);
	return currate;
}

function get_ajax(my_target){
		var answer = jQuery.get(php_data.plugin_url+"/paypal-currency-converter-basic-for-woocommerce/proxy.php",{ requrl: php_data.requrl},
			function(data) {
				jQuery(my_target).html(data);
				jQuery('#selected_currency').val(currency()+' '+php_data.target_currency+'/'+php_data.source_currency);
				if (jQuery('#cr').val()==currency()){
					jQuery('#cr').css('color', 'green');
				}else{
					jQuery('#cr').css('color', 'red');
					jQuery( ".settings-error" ).show();
					//alert ('please check your current currency setting!');

				}
			}
		);
	}