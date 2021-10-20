<?php
require_once("st_includes/settings.php");
require_once(SITE_LIB_INCLUDE."smtp/sendmail.php");

/*$_REQUEST['pid'] = "voguepay";

$_REQUEST['prefid'] = "R18U-220118113649";//paystack

$_REQUEST['vrefid'] = "R18T-220118114640";//voguepay
$_REQUEST['transaction_id'] = "5a65c138f2d34";//voguepay
//www.orgds.org/pay?pid=voguepay&vrefid=R18T-220118114640&r=success&transaction_id=5a65c138f2d34

$_REQUEST['orderID'] = "";//remita*/

if (isset($_REQUEST['pid']) && $_REQUEST['pid']=='paystack' && isset($_REQUEST['prefid']) && !empty($_REQUEST['prefid'])) {
	
	include 'st_includes/paystack_constants.php';

	$prefid = $_REQUEST['prefid'];//R18U-220118113649

	$result = array();

	//The parameter after verify/ is the transaction reference to be verified
	$url = 'https://api.paystack.co/transaction/verify/'.$prefid;

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt(
	  $ch, CURLOPT_HTTPHEADER, [
	    'Authorization: Bearer '.LIVESECRETKEY]
	);
	$request = curl_exec($ch);
	curl_close($ch);

	if ($request) {
	  $result = json_decode($request, true);
	}

	/*foreach ($result as $key => $value) {
		echo $key." - ".$value;
	}*/

	if (array_key_exists('data', $result) && array_key_exists('status', $result['data']) && ($result['data']['status'] === 'success')) {
		//Perform necessary action
		$success = true;
		//update database and credit the user
		$callUserBack = $osmscon->query("SELECT * FROM ".TBL_PREFIX."transactions INNER JOIN ".TBL_PREFIX."users ON ".TBL_PREFIX."transactions.truserid=".TBL_PREFIX."users.userid WHERE ods_trans_ref_number='$prefid'");

		$countCalled = $callUserBack->num_rows;

		if ($countCalled) {
			$fetchCalled = $callUserBack->fetch_assoc();
			$theuser = $fetchCalled['userid'];
			$theunits = $fetchCalled['applied_sms_units'];
			$balance_before = $fetchCalled['sms_unit_balance'];
			$new_balance = $balance_before+$theunits;
			$current_status = $fetchCalled['trans_status'];
				
			if ($current_status == 0) {
				
				$transupdatestmt = $osmscon->prepare("UPDATE ".TBL_PREFIX."transactions SET trans_status = ?, reference_number = ? WHERE truserid = ? AND ods_trans_ref_number = ?");
				
				$transtatus = 1;
		        
		        $transbind = $transupdatestmt->bind_param("isis",$transtatus,$success_ref,$theuser,$prefid);

		        $transexec = $transupdatestmt->execute();


				$balanceupdatestmt = $osmscon->prepare("UPDATE ".TBL_PREFIX."users SET sms_unit_balance = sms_unit_balance + ? WHERE userid = ?");
		        
		        $balbind = $balanceupdatestmt->bind_param("di",$theunits,$theuser);

		        $balexec = $balanceupdatestmt->execute();
		    }
		    /*elseif($current_status == 0){
		    	$success = false;
				$errmsg = "Transaction authentication failed.<p class='tcenter'>Please feel free to call ".SITE_TEL." if you're facing any challenge.</p>";*/
		    elseif($current_status == 1){
		    	$success = false;
				$errmsg = "Transaction already processed and completed.<p class='tcenter'>Please check your balance or  call ".SITE_TEL." if you're facing any challenge.</p>";
		    }
		    else{
		    	$success = false;
				$errmsg = "<p class='tcenter'>An unexpected error occured!<br>Please feel free to call ".SITE_TEL." if you're facing any challenge.</p>";
		    }
		}
		else{
			//$success = false;
			$error = 1;
			$errmsg = "<p class='tcenter'>We encountered an unexpected error while processing: Unlinked transaction.<br>Please feel free to call ".SITE_TEL." if you're facing any challenge.</p>";
		}
	}else{
	  	$error = 1;
		$errmsg = "Transaction was not successful";
	}
}

//Voguepay verification
elseif (isset($_REQUEST['pid']) && $_REQUEST['pid']=='voguepay' && isset($_REQUEST['vrefid']) && !empty($_REQUEST['vrefid']) && isset($_REQUEST['transaction_id'])) {
	//Voguepay verification
	include 'st_includes/voguepay_constants.php';

	$merchant_id = VMERCHANTID;
	$vtransid = $_REQUEST['transaction_id'];
	$vrefid = $_REQUEST['vrefid'];
	$success_ref = $vtransid;

	//json verification
	//get the full transaction details as an json from voguepay
	$json = file_get_contents('https://voguepay.com/?v_transaction_id='.$vtransid.'&type=json');
	//create array to store our transaction details
	$transaction = json_decode($json, true);
	
	/*//now we have the following keys in our $transaction array
	$transaction['merchant_id'];
	$transaction['transaction_id'];
	$transaction['email'];
	$transaction['total'];
	$transaction['merchant_ref'];
	$transaction['memo'];
	$transaction['status'];
	$transaction['date'];
	$transaction['referrer'];
	$transaction['method'];
	$transaction['cur'];
	//and more
	*/

	if ($transaction['total'] > 0 && $transaction['status'] == 'Approved' && $transaction['merchant_id'] == $merchant_id) {
		//success now give value to user
		$success = true;
		//update database and credit the user
		$callUserBack = $osmscon->query("SELECT * FROM ".TBL_PREFIX."transactions INNER JOIN ".TBL_PREFIX."users ON ".TBL_PREFIX."transactions.truserid=".TBL_PREFIX."users.userid WHERE ods_trans_ref_number='$vrefid'");
		$countCalled = $callUserBack->num_rows;
		if ($countCalled) {
			$fetchCalled = $callUserBack->fetch_assoc();
			$theuser = $fetchCalled['userid'];
			$theunits = $fetchCalled['applied_sms_units'];
			$balance_before = $fetchCalled['sms_unit_balance'];
			$new_balance = $balance_before+$theunits;
			$current_status = $fetchCalled['trans_status'];
			
			if ($current_status == 0) {
				$transupdatestmt = $osmscon->prepare("UPDATE ".TBL_PREFIX."transactions SET trans_status = ?, reference_number = ? WHERE truserid = ? AND ods_trans_ref_number = ?");
				$transtatus = 1;
		        $transbind = $transupdatestmt->bind_param("isis",$transtatus,$servicerefno,$theuser,$vrefid);
		        $transexec = $transupdatestmt->execute();


				$balanceupdatestmt = $osmscon->prepare("UPDATE ".TBL_PREFIX."users SET sms_unit_balance = sms_unit_balance + ? WHERE userid = ?");
		        $balbind = $balanceupdatestmt->bind_param("di",$theunits,$theuser);
		        $balexec = $balanceupdatestmt->execute();
		    }
		    elseif($current_status == 1){
		    	$success = false;
				$errmsg = "<p class='tcenter'>Transaction with the ID has already been processed and completed.<br> Please check your balance or contact site administrator.</p>";
		    }
		    else{
		    	$success = false;
				$errmsg = "<p class='tcenter'>An unexpected error occured!<br>Please feel free to call ".SITE_TEL." if you're facing any challenge.</p>";
		    }
		}
		else{
			//$success = false;
			$error = 1;
			$errmsg = "<p class='tcenter'>We encountered an unexpected error while processing: Unlinked transaction.<p class='tcenter'>Please contact site administrator.</p>";
		}
		
		//$msg = "<strong>".$transaction['status']."</strong>: Transaction was successful<p>Your account has been updated!";
	}
	else{
		//transaction failed
		$error = 1;
		$errmsg = "<strong>".$transaction['status']."</strong>: Transaction was not successful";
	}
	//end json verification

	//xml verification
	//get the full transaction details as an xml from voguepay
	/*$xml = file_get_contents('https://voguepay.com/?v_transaction_id='.$vtransid.'&type=xml');
	//parse our new xml
	$xml_elements = new SimpleXMLElement($xml);
	//create new array to store our transaction details
	$transaction = array();
	//loop through the $xml_elements and populate our $transaction array
	foreach ($$xml_elements as $key => $value) {
		$transaction[$key] = $value;
	}
	/*now we have the following keys in our $transaction array
	$transaction['merchant_id'];
	$transaction['transaction_id'];
	$transaction['email'];
	$transaction['total'];
	$transaction['merchant_ref'];
	$transaction['memo'];
	$transaction['status'];
	$transaction['date'];
	$transaction['referrer'];
	$transaction['method'];
	$transaction['cur'];
	//and more
	

	if ($transaction['total'] > 0 && $transaction['status'] == 'Approved' && $transaction['merchant_id'] == $merchant_id) {
		//success now give value to user
		echo "Transaction was successful";
	}
	else{
		//transaction failed
		echo "Transaction was not successful";
	}*/
	//end of xml verification
}

//REMITA verification
elseif(isset( $_REQUEST['orderID']) && (isset($_REQUEST['pid']) && $_REQUEST['pid']=='remita')) {//remita uses GET to send RefBack
	//REMITA verification
	include 'st_includes/remita_constants.php';
	function remita_transaction_details($orderId){
		$mert =  MERCHANTID;
		$api_key =  APIKEY;
		$concatString = $orderId . $api_key . $mert;
		$hash = hash('sha512', $concatString);
		$url 	= CHECKSTATUSURL . '/' . $mert  . '/' . $orderId . '/' . $hash . '/' . 'orderstatus.reg';
		//  Initiate curl
		$ch = curl_init();
		// Disable SSL verification
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		// Will return the response, if false it print the response
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		// Set the url
		curl_setopt($ch, CURLOPT_URL,$url);
		// Execute
		$result=curl_exec($ch);
		// Closing
		curl_close($ch);
		$response = json_decode($result, true);
		return $response;
	}

	$response_code ="";
	$rrr = "";
	$response_message = "";
	$orderID = trim($_REQUEST["orderID"]);
	$scode = -1;//initial statuscode
	if (isset($_REQUEST['statuscode'])) {
		$scode = $_REQUEST['statuscode'];
	}

	if($orderID != null && $orderID !== 'null'/* && ($scode == -1 || $scode == '00' || $scode == '01')*/){//test only if success
		
		$response = remita_transaction_details($orderID);
		
		$response_code = $response['status'];

		if (isset($response['RRR']))
		{
			$rrr = $response['RRR'];
		}

		$response_message = $response['message'];

		/*if($response_code == '01' || $response_code == '00') {*/
		if($response_code!=null) {

			$success_ref = $rrr;
			//update database and credit the user
			$callUserBack = $osmscon->query("SELECT * FROM ".TBL_PREFIX."transactions INNER JOIN ".TBL_PREFIX."users ON ".TBL_PREFIX."transactions.truserid=".TBL_PREFIX."users.userid WHERE ods_trans_ref_number='$orderID'");
			$countCalled = $callUserBack->num_rows;
			if ($countCalled) {
				$fetchCalled = $callUserBack->fetch_assoc();
				$theuser = $fetchCalled['userid'];
				$theunits = $fetchCalled['applied_sms_units'];
				$balance_before = $fetchCalled['sms_unit_balance'];
				$new_balance = $balance_before+$theunits;
				$current_status = $fetchCalled['trans_status'];
				
				if ($current_status == 0) {
					$transupdatestmt = $osmscon->prepare("UPDATE ".TBL_PREFIX."transactions SET trans_status = ?, reference_number = ? WHERE truserid = ? AND ods_trans_ref_number = ?");
					if($response_code == '01' || $response_code == '00') {
						$transtatus = 1;
						$success = true;
					}
					else{
						$success = false;
						$transtatus = 0;
					}
			        $transbind = $transupdatestmt->bind_param("isis",$transtatus,$success_ref,$theuser,$orderID);
			        $transexec = $transupdatestmt->execute();

					if ($transtatus == 1) {
						$balanceupdatestmt = $osmscon->prepare("UPDATE ".TBL_PREFIX."users SET sms_unit_balance = sms_unit_balance + ? WHERE userid = ?");
				        $balbind = $balanceupdatestmt->bind_param("di",$theunits,$theuser);
				        $balexec = $balanceupdatestmt->execute();
					}
			    }
			    elseif($current_status == 1){
			    	//$success = false;
			    	$error = 1;
					$errmsg = "<p class='tcenter'>A transaction with the ID has already been processed and completed.<br> Please check your balance or contact site administrator.</p>";
			    }
			    else{
			    	//$success = false;
					$error = 1;
					$errmsg = "An unexpected error occured!<p class='tcenter'>Please feel free to call ".SITE_TEL." if you're facing any challenge.</p>";
			    }
			}
			else{
				//$success = false;
				$error = 1;
				$errmsg = "We encountered an unexpected error while processing: Unlinked transaction.<p class='tcenter'>Please feel free to call ".SITE_TEL." if you're facing any challenge.</p>";
			}
		}
		else{//failed transaction, get status code
			$statusdescription = RemitaStatusCode($response_code);
			$error = 1;
			$errmsg = $statusdescription."<p class='tcenter'>Please <a href='buy'>try your request again.</a></p>";
		}
	}else{
		if (isset($_REQUEST['statuscode']) && (isset($_REQUEST['pid']) && $_REQUEST['pid']=='remita')) {
			$response_code = $_REQUEST['statuscode'];
			$statusdescription = RemitaStatusCode($response_code);
			$error = 1;
			$errmsg = $statusdescription."<p class='tcenter'>Please <a href='buy'>try your request again.</a></p>";
		}else{
			$error = 1;
			$errmsg = "<p class='tcenter'>No transaction was processed.<br>Please <a href='buy'>try your request again.</a></p>";
		}
	}
}
else{
	//nothing happened
	$error = 1;
	$errmsg = "<p class='tcenter'>Your request is not understood!<br><a href='buy'>Click here to purchase SMS units</a></p>";
}
?>

<!DOCTYPE html>
<html>
<head>
	<title>Payment Confirmation</title>
	<meta name="robots" content="noindex,nofollow">
	<link rel="stylesheet" href="st_includes/css/w3.css">
	<link rel="stylesheet" href="st_includes/css/bootstrap.css">
	<style type="text/css">
		body{
		  background-color:#2D3047;
		  margin: 0px;
		  font-family: serif, 'Times New Romans';
		}

		h1,h2,h3,h4{
			margin: 0px;
		}

		a:link{
			color: white;
			text-decoration: none;
		}

		a:visited{
			color: #FFFF99;
		}

		a:hover{
			color: yellow;
		}

		.msgdivi{
		  background:#419D78;
		  color:#ffffff;/*#EFD0CA;*/
		  text-align:center;
		  margin-top: 0px;
		}
		.infodiv{
			text-align: center;
		}
		.infodiv p{
			margin: 0px 0px 0px;
		}

		.theinfo{
			color: #FFFF33;
		}

		.headerdiv{
			background: transparent;
			text-align: center;
			color: white;
		}
		.semitopinfo{
			background: indigo;
			text-align: center;
			text-transform: uppercase;
			color: white;
		}
	</style>
</head>
<body>
	<div class="w3-row">
		<div class="w3-col w3-padding-medium headerdiv">
			<h2><img src="st_content/graphics/orgds-logo.png" class="w3-col l2 m3 s4" style="float: none;" alt="OrgDS" /></h2>
		</div>
		<div class="w3-col l12 w3-padding-medium w3-xlarge semitopinfo">
			Payment Confirmation
		</div>
		<div class="w3-col l12 w3-padding-medium w3-large msgdivi">
			<?php if (isset($success) && $success == true) { ?>
				<div class="infodiv"><strong>Transaction was successful!</strong>
					<?php 
					if ($success_ref !=null){
						if (isset($orderID)) {
					?>
						<p class="tcenter"><b>Remita Retrieval Reference: </b><?= fc($success_ref); ?><p>
						<p class="tcenter"><b>Your Order ID: </b><?= fc($orderID); ?></p>
					<?php
						}
						else{
					?>
							<p class="tcenter" class='theinfo'><b>Ref No: </b><?= fc($success_ref); ?><p>
							<p class="tcenter">Your account has been updated!</p>

					<?php
						}
					}
					?>
					<?php if(isset($new_balance)){ ?>
						<p class="tcenter">Your new balance is <?= number_format($new_balance,2); ?></p>
					<?php } ?>
				</div>
			<?php 
					if(isset($_SESSION['transactRef'])){
						$_SESSION['transactRef'] = null;
						unset($_SESSION['transactRef']);
					}
					if(isset($_SESSION['transactStartTime'])){
						$_SESSION['transactStartTime'] = null;
						unset($_SESSION['transactStartTime']);
					}
				} elseif(isset($success) && $success != true){ ?>
				<div class="infodiv" style="">
					<strong>Processing failed!</strong>
					<?php
						if (isset($errmsg)) {
							echo "<p style='text-align:center;' class='theinfo'>".$errmsg."</p>";
						}
					?>
					
					<?php if(!empty($response_message)) { ?>
					  <p class="tcenter"><b>Reason: </b><?= $response_message; ?></p>
					<?php }
					?>

					<?php
					if ($success_ref !=null){

							if (isset($orderID)) {
					?>
							<p class="tcenter"><b>Remita Retrieval Reference: </b><?= fc($success_ref); ?><p>
							<p class="tcenter"><b>Your Order ID: </b><?= fc($orderID); ?></p>
					<?php
							}else{
								if (isset($errmsg)) {
									echo "<p class='tcenter' class='theinfo'>".$errmsg."</p>";
								}
					?>
								<p class="tcenter" style="text-align: center;"><b>Reference No: </b><?= fc($success_ref); ?></p>
					<?php
							}
					} ?>
				</div>
			<?php
					if(isset($_SESSION['transactRef'])){
						$_SESSION['transactRef'] = null;
						unset($_SESSION['transactRef']);
					}
					if(isset($_SESSION['transactStartTime'])){
						$_SESSION['transactStartTime'] = null;
						unset($_SESSION['transactStartTime']);
					}
				}
				/*else {
					//nothing
				}*/
			
				elseif (isset($error) && $error == 1) {
			?>
					<div class="infodiv w3-col" style="">
						<strong class="tcenter">Transaction Info</strong>
						<?php
							if (isset($errmsg)) {
								echo "<p style='text-align:center;' class='theinfo'>".$errmsg."</p>";
							}
						?>

						<?php if(!empty($response_message)) { ?>
						  <p class="tcenter"><b>More Info: </b><?= $response_message; ?></p>
						<?php } ?>
						<?php if ($success_ref !=null){
								if (isset($orderID)) {
						?>
								<p class="tcenter"><b>Remita Retrieval Reference: </b><?= fc($success_ref); ?><p>
								<p class="tcenter"><b>Your Order ID: </b><?= fc($orderID); ?></p>
						<?php
								}
							}
						?>
					</div>
			<?php
					if(isset($_SESSION['transactRef'])){
						$_SESSION['transactRef'] = null;
						unset($_SESSION['transactRef']);
					}
					if(isset($_SESSION['transactStartTime'])){
						$_SESSION['transactStartTime'] = null;
						unset($_SESSION['transactStartTime']);
					}
				}
			?>
		</div>
		<div class="w3-col w3-padding-medium w3-text-light-grey" style="text-align: center;">
			<p class="tcenter"><a href="<?= SITE_ROOT ?>">Home</a> | <a href="<?= SITE_ROOT ?>transactions">Transactions</a> | <a href="<?= SITE_ROOT ?>dashboard">Dashboard</a> | <a href="<?= SITE_ROOT ?>compose">Send SMS</a> | <a href="<?= SITE_ROOT ?>buy">Buy SMS</a></p>
		</div>
	</div>
</body>
</html>