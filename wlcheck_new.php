<?php

/* 
2022-02-19 Testing Weatherlink V2 API
Function "calculateSignature" idea picked from https://github.com/weatherlink/weatherlink-v2-api-sdk-php/blob/7f2cea5b05d468ded867f244de1932eb7d47d192/src/weatherlink_v2_api_sdk/signature/SignatureCalculator.php#L33

Retreiving the station id or current conditions
***********************************************

The file "wlauth.txt" shall contain "$apiKey", "$apiSecret" and "$stationId" separeted with commas(,)
Edit the file "wlauth.txt" and replace with the values, "$apiKey" and "$apiSecret", from your account at https://www.weatherlink.com

Then use the URL to get the "$stationId"
http://pws01/pws/add_on/wlchk.php?data=stations
which will show the retreived "$stationId" and write it to the file "wlauth.txt".

If something goes wrong you can copy and paste the generated URL in a WEB browser and check.

Then use the URL
http://pws01/pws/add_on/wlchk.php?data=current
Copy and paste the generated URL in a WEB browser and check out the weatherdata from your station

Test values from manual https://weatherlink.github.io/v2-api/tutorial
$apiKey = "987654321";
$apiSecret  = "ABC123";
$apiRequestTimestamp = "1558729481";
*/ 

$apiRequestTimestamp = time();
$data = $_GET['data'];

if (($data == "stations") or ($data == "current")){
    // NOP
}
else {                                                      // Catch all
        $data = "file wlauth.txt content";
}

echo "Selected to get ".$data."<br>";

$contentAuth = file_get_contents("../add_on/wlauth.txt");   // echo $contentAuth."<br>";
$dataAuth = explode(",", $contentAuth);                     // var_dump ($dataAuth);

$apiKey = $dataAuth[0];
$apiSecret  = $dataAuth[1];
$stationId = $dataAuth[2];

if (($data == "stations") or ($data == "current")){

    if ($data == "stations"){

        $parametersToHash = array(
            "api-key" => $apiKey,
            "t" => $apiRequestTimestamp
        );                                                  // echo "<br>"; var_dump ($parametersToHash);

        $service_url = "https://api.weatherlink.com/v2/stations?api-key=".$apiKey."&t=".$apiRequestTimestamp."&api-signature=".calculateSignature($apiSecret, $parametersToHash);

        $curl_handle = curl_init($service_url);
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
        $curl_response = curl_exec($curl_handle);
        $wlJson = json_decode($curl_response);              // echo "<br><br>"; var_dump ($wlJs echo $wlJson->message;                                            
                                                            // If wrong response no file update
        if (($wlJson == NULL) or ($wlJson->message != NULL)) {
            echo "<br> Something wrong !!!! Use URL below to check. <br>";
        }
        else {
                echo "<br> Got station id ".$wlJson->stations[0]->station_id."<br>";
                
                $dataAuth[2] = $wlJson->stations[0]->station_id;
                $file_live = implode(",",$dataAuth);
                $handle = fopen("../add_on/wlauth.txt", "w");
                fwrite($handle, $file_live);
                fclose($handle); 
        }
    }
    else {                                                  // "current" as input
                $parametersToHash = array(
                    "api-key" => $apiKey,
                    "t" => $apiRequestTimestamp,
                    "station-id" => $stationId
                );                                          // echo "<br>"; var_dump ($parametersToHash);      
            
                $service_url = "https://api.weatherlink.com/v2/current/".$stationId."?api-key=".$apiKey."&t=".$apiRequestTimestamp."&api-signature=".calculateSignature($apiSecret, $parametersToHash);
    }

    echo "<br>"."Copy URL to check data "."<br>";
    echo $service_url;
}

echo "<br><br>";
echo "File wlauth.txt content is => API key, API secret, Station ID => ".file_get_contents("../add_on/wlauth.txt")."<br>";

// *********** Function *********** //

function calculateSignature($apiSecret, $parametersToHash) {
    ksort($parametersToHash);
    $stringToHash = "";
    foreach ($parametersToHash as $parameterName => $parameterValue) {
        $stringToHash = $stringToHash . $parameterName . $parameterValue;
    }                                                       // echo "<br>"; echo $stringToHash;

    $apiSignature = hash_hmac("sha256", $stringToHash, $apiSecret);
                                                            // echo "<br>"; echo $apiSignature;
    return $apiSignature;
}
?>