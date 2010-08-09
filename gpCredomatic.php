<?php
/*
 * Credomatic Payment Gateway for WP-e-Commerce
 * Copyright, 2010 - Sebastian Oliva
 * http://sebastianoliva.com
 *
 * Made at the request of http://royalestudios.com/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License version 3 
 * as published by the Free Software Foundation.
 *
 * This software is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 */
$nzshpcrt_gateways[$num]['name'] = 'Pago Credomatic';
$nzshpcrt_gateways[$num]['admin_name'] = 'Pago Credomatic / GatewayCredomatic';
$nzshpcrt_gateways[$num]['internalname'] = 'pagoCredomatic';
$nzshpcrt_gateways[$num]['function'] = 'gateway_pagoCredomatic';
$nzshpcrt_gateways[$num]['form'] = "form_pagoCredomatic";
$nzshpcrt_gateways[$num]['submit_function'] = "submit_pagoCredomatic";

if(in_array('pagoCredomatic',(array)get_option('custom_gateway_options'))) {
    //Generating the possible expire years for the card
	$curryear = date('y');
	for($i=0; $i < 10; $i++){
		$years .= "<option value='".$curryear."'>20".$curryear."</option>\r\n";
		$curryear++;
	}

	$gateway_checkout_form_fields[$nzshpcrt_gateways[$num]['internalname']] = "

	<tr id='wpsc_gpc_cc_number'>
		<td class='wpsc_gpc_cc_number1'>Numero de Tarjeta: *</td>
		<td class='wpsc_gpc_cc_number2'>
			<input type='text' value='' name='card_number' />
		</td>
	</tr>
	<tr id='wpsc_gpc_cc_expiry'>
		<td class='wpsc_gpc_cc_expiry1'>Fecha de Expiracion: *</td>
		<td class='wpsc_gpc_cc_expiry2'>
			<select class='wpsc_ccBox' name='expiry[month]'>
              <option value='01'>01</option>
              <option value='02'>02</option>
              <option value='03'>03</option>
              <option value='04'>04</option>
              <option value='05'>05</option>
              <option value='06'>06</option>
              <option value='07'>07</option>
              <option value='08'>08</option>
              <option value='09'>09</option>
              <option value='10'>10</option>
              <option value='11'>11</option>
              <option value='12'>12</option>
			</select>
			<select class='wpsc_ccBox' name='expiry[year]'>
              ".$years."
			</select>
		</td>
	</tr>
    <tr id='wpsc_gpc_cc_cvv'>
		<td class='wpsc_gpc_cc_cvv1'>Numero de Verificaci&oacute;n: *</td>
		<td class='wpsc_gpc_cc_cvv2'>
			<input type='text' value='' name='cvv' />
		</td>
	</tr>
";
}

function gateway_pagoCredomatic($seperator, $sessionid){

  //Adding extra protection against SQL Injection
  if( !is_numeric($_POST['card_number'])    ||
      !is_numeric($_POST['expiry']['year']) ||
      !is_numeric($_POST['expiry']['month'])||
      !is_numeric($_POST['cvv']))
  {
    $transact_url = get_option('checkout_url');
    $_SESSION['wpsc_checkout_misc_error_messages'][] = __('La operacion fue fallida, entrada Invalida ');
    $_SESSION['gpc'] = 'fail';
    header("Location: ".get_option('transact_url').$seperator."sessionid=".$sessionid);
    exit();
  }

  global $wpdb, $wpsc_cart;
  $purchase_log = $wpdb->get_row("SELECT * FROM `".WPSC_TABLE_PURCHASE_LOGS."` WHERE `sessionid`= ".$sessionid." LIMIT 1",ARRAY_A) ;
  $usersql = "SELECT `".WPSC_TABLE_SUBMITED_FORM_DATA."`.value, `".WPSC_TABLE_CHECKOUT_FORMS."`.`name`, `".WPSC_TABLE_CHECKOUT_FORMS."`.`unique_name` FROM `".WPSC_TABLE_CHECKOUT_FORMS."` LEFT JOIN `".WPSC_TABLE_SUBMITED_FORM_DATA."` ON `".WPSC_TABLE_CHECKOUT_FORMS."`.id = `".WPSC_TABLE_SUBMITED_FORM_DATA."`.`form_id` WHERE  `".WPSC_TABLE_SUBMITED_FORM_DATA."`.`log_id`=".$purchase_log['id']." ORDER BY `".WPSC_TABLE_CHECKOUT_FORMS."`.`order`";
  //exit($usersql);
  $userinfo = $wpdb->get_results($usersql, ARRAY_A);
  //exit('<pre>'.print_r($userinfo, true).'</pre>');
  //


  $POSTURL = "https://credomatic.compassmerchantsolutions.com/api/transact.php";
  //$POSTURL = "http://localhost:4567/api/transact.php"; //Testing Gateway included as dummyServerTesting.rb

  //Preparing the data to be sent

  $GC_purchaseData = array();

  $mT_keyID = get_option("mT_keyID");             //the key ID of the merchant
  $mT_key   = stripslashes(get_option('mT_key')); //key of the merchant
  $t_time = time();

  
  foreach((array)$userinfo as $key => $value){

    if(($value['unique_name']=='billingfirstname') && $value['value'] != ''){
      $GC_purchaseData["firstname"] = $value['value'];
    }
    if(($value['unique_name']=='billinglastname')  && $value['value'] != ''){
      $GC_purchaseData['lastname']	= $value['value'];
    }
    if(($value['unique_name']=='billingemail')     && $value['value'] != ''){
      $GC_purchaseData["email"]     = $value['value'];
	}
  }

 
  $t_item     = "";
  $t_amount   = 0;

  //calculating the total to be charged
  foreach($wpsc_cart->cart_items as $i => $Item) {
    $t_item   .= $Item->product_name." ";
    $t_amount = $t_amount + number_format($Item->unit_price,2) * $Item->quantity;
  }

  $t_ccNumber = $_POST['card_number'];
  $t_ccExp    = $_POST['expiry']['month'].$_POST['expiry']['year'];
  $t_ccCvv     =$_POST['cvv'];

  $t_hash = md5("$t_item|$t_amount|$t_time|$mT_key");

  //Filling the arguments array

  $GC_purchaseData["type"]        = "sale";
  $GC_purchaseData["key_id"]      = $mT_keyID;
  $GC_purchaseData["time"]        = $t_time;
  $GC_purchaseData["redirect"]    = "http://localhost"; //get_option('mT_REDIRECTURL');
  $GC_purchaseData["orderid"]     = $t_item;
  $GC_purchaseData["amount"]      = $t_amount;

  //TODO: add more fields

  $GC_purchaseData["ccnumber"]    = $t_ccNumber;
  $GC_purchaseData["ccexp"]       = $t_ccExp;
    $GC_purchaseData["cvv"]       = $t_ccCvv;

  $GC_purchaseData["hash"]        = $t_hash;

  //Creating the query string with the data
  $t_constructedArgs = http_build_query($GC_purchaseData); 

  //print_r($t_constructedArgs);

  $params = array('http' => array(
              'method' => 'POST',
              'content' => $t_constructedArgs
            ));

  $context = stream_context_create($params);
  $stream = @fopen($POSTURL, 'rb', false, $context); //making the actual request

  if ($stream != false){ //added protection in case of an invalid request

    $resultMetaData = stream_get_meta_data($stream);

    fclose($stream);

    $results = $resultMetaData["wrapper_data"];

    $GC_resultVal = ""; //here we store the results


    foreach ($results as $element){
      $splitArrays = explode (": ", $element); //we separate the HTTP Header in sections
      //if the selected header is Location we store the results
      if ($splitArrays[0] == "Location"){
        $GC_resultVal = $splitArrays[1];
        break;
      }
    }


    parse_str($GC_resultVal, $GC_ResultData);


    $serverResponse = $GC_ResultData["http://localhost?response"]; //$GC_ResultData[get_option('mT_REDIRECTURL')."?response"]; //didn't actually work

  }

  if($serverResponse == 1 && $GC_resultVal != ""){
      //redirect to  transaction page and store in DB as a order with accepted payment
      $sql = "UPDATE `".WPSC_TABLE_PURCHASE_LOGS."` SET `processed`= '2' WHERE `sessionid`=".$sessionid;
      $wpdb->query($sql);
      $transact_url = get_option('transact_url');
      unset($_SESSION['WpscGatewayErrorMessage']);
      $_SESSION['gpc'] = 'success';
      header("Location: ".get_option('transact_url').$seperator."sessionid=".$sessionid);
      exit();
  }
  else {
    //redirect back to checkout page with errors
    $sql = "UPDATE `".WPSC_TABLE_PURCHASE_LOGS."` SET `processed`= '5' WHERE `sessionid`=".$sessionid;
    $wpdb->query($sql);
    $transact_url = get_option('checkout_url');
    
    //if ($GC_ResultData["responsecode"] > 199 && $GC_ResultData["responsecode"] < 300)
      $_SESSION['wpsc_checkout_misc_error_messages'][] = __('La operacion fue fallida, ningun cargo ha sido efectuado a su tarjeta.   ') . $GC_ResultData["responsetext"];
    
    $_SESSION['gpc'] = 'fail';
  }
}

//Validating the POST info data from form_pagoCredomatic
function submit_pagoCredomatic(){
 //exit('<pre>'.print_r($_POST, true).'</pre>');
 if($_POST['gpc']['kID'] != null) {
    update_option('mT_keyID',       $_POST['gpc']['kID']);
 }
 if($_POST['gpc']['key'] != null) {
    update_option('mT_key',         $_POST['gpc']['key']);
 }
  return true;
}

function form_pagoCredomatic(){
$output = '
<tr>
	<td>
		<label for="mT_keyID">'.__('ID de la llave:').'</label>
	</td>
	<td>
		<input type="text" name="gpc[kID]" id="mT_keyID" value="'.get_option("mT_keyID").'" size="10" />
	</td>
</tr>
<tr>
	<td>
		<label for="mT_key">'.__('Llave del Mercante:').'</label>
	</td>
	<td>
		<input type="text" name="gpc[key]" id="mT_key" value="'.stripslashes(get_option('mT_key')).'" size="30" />
	</td>
</tr>';
return $output;
}
?>
