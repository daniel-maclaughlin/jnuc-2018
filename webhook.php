<?php
//Written for rextracting data fron a SnipeIT Asset DB


//DB details note these can be put into a different file and you can use an include statement
// Add DB config
//include 'dbConfig.php';

//hard code DB details
$dbHost = '192.168.100.50';
$dbUsername = 'jnuc';
$dbPassword = 'jnuc2018';
$dbName = 'snipeit';

//Create connection and select DB
$db = new mysqli($dbHost, $dbUsername, $dbPassword, $dbName);

if ($db->connect_error) {
	die("Unable to connect database: " . $db->connect_error);
}
//server url as injectected by webhook address 
$jamfurl = $_GET["jamfurl"];

//webhook auth can be used as variables entered into the jamf server
$webhookuser = $_SERVER['PHP_AUTH_USER'];
$webhookpass = $_SERVER['PHP_AUTH_PW'];

//WebHook details in JSON format
$json = file_get_contents('php://input');

//Format the JSON for data to be extracted from it
$obj = json_decode($json, TRUE);

//get device type from JSON
$deviceType = $obj["webhook"]["webhookEvent"];

//get serial number, id and name from JSON
$deviceSN   = $obj["event"]["serialNumber"];
$deviceID   = $obj["event"]["jssID"];
$deviceName = $obj["event"]["deviceName"];

//query the Database to find the information in array format
$result = mysqli_query($db,"SELECT * FROM assets WHERE serial = '".$deviceSN."'");

//check to see if the device is there if not then exits out
if ($result->num_rows === 0){ 
        echo 'No results';
    }
else {

//cycle through the rows fields assigning variables from the array
while ($row = mysqli_fetch_array($result)){
$asset_tag = $row['asset_tag'];
$po_date = $row['purchase_date'];
$status = $row['status_id'];
$warranty = $row['warranty_months'];

}

//calculate warranty expiration and format into YYYY-MM-DD
$tmpdate = new DateTime($po_date);
$warranty_date= $tmpdate ->add(new DateInterval('P'.$warranty.'M'));
$warranty_date = $warranty_date->format('Y-m-d');

//Set the status id to be human readable
if ($status == 1) {
    $status = "Pending";
} elseif ($status == 2) {
     $status = "Ready to Deploy";
} elseif ($status == 3) {
     $status = "Archived";
} elseif ($status == 4) {
     $status = "Undeployable";
} elseif ($status == 5) {
     $status = "Lost";
} elseif ($status == 6) {
     $status = "Pending Diagnostics";
}


//Check if the device type is Mobile or Computer
if( strpos( $deviceType, 'Mobile' ) !== false) {	
		$xml="<mobile_device>
  			<general>
    				<asset_tag>$asset_tag</asset_tag>
  			</general>
  			<purchasing>
    				<is_purchased>true</is_purchased>
    				<po_date>$po_date</po_date>
    				<warranty_expires>$warranty_date</warranty_expires>
  			</purchasing>
  			<extension_attributes>
    				<extension_attribute>
      				<id>1</id>
      				<name>Status</name>
      				<type>String</type>
      				<value>$status</value>
    				</extension_attribute>
  			</extension_attributes>
		</mobile_device>";
		
		//append to jamf url
		$url = $jamfurl . "/JSSResource/mobiledevices/id/$deviceID";

	} else {
		$xml="<computer>
  			<general>
    				<asset_tag>$asset_tag</asset_tag>
  			</general>
  			<purchasing>
    				<is_purchased>true</is_purchased>
    				<po_date>$po_date</po_date>
    				<warranty_expires>$warranty_date</warranty_expires>
  			</purchasing>
  			<extension_attributes>
    				<extension_attribute>
      				<id>1</id>
      				<name>Status</name>
     				<type>String</type>
      				<value>$status</value>
    				</extension_attribute>
  			</extension_attributes>
		</computer>";
		
		//append to jamf url
		$url = $jamfurl . "/JSSResource/computers/id/$deviceID";

			
	}



	// Setup and run CURL to call jss api to update content from Asset DB
	//Thanks to Oliver Lindsey for showing PHP CURL options
	$ch = curl_init();
  	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY ) ;		// eauthentification method to use  CURLAUTH_BASIC
	curl_setopt($ch, CURLOPT_USERPWD, "$webhookuser:$webhookpass"); // Username and password of the admin JSS accountl
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);					// Return as string
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');					// REST Method 
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);				// For testing only... use a real cert or install CURLOPT_CAINFO file for php. 
	curl_setopt($ch, CURLOPT_VERBOSE, 1); 						// turn verbose on 
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/xml'));
	curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
	$output = curl_exec($ch);


	//Tmp log to troubleshoot the curl command submitting to the API
	// Open the file to get existing content
	$file = "/tmp/curl.log";
	// Write the contents back to the file for logging
	file_put_contents($file, $output);
}

?>
