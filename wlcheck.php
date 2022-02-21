<?php

/* 
2022-02-19 Testing Weatherlink V2 API
Code idea picked from https://github.com/weatherlink/weatherlink-v2-api-sdk-php/blob/7f2cea5b05d468ded867f244de1932eb7d47d192/src/weatherlink_v2_api_sdk/signature/SignatureCalculator.php#L33

Retreiving the station id or current conditions
***********************************************

Replace the values "$apiKey" and "$apiSecret" from your account at https://www.weatherlink.com and get the "$stationId"
Execute this code and copy and paste the generated URL in a WEB browser and check out the response

Change "$data" to "current" and set the "$stationId" and get the current weather data
Execute this code and copy and paste the generated URL in a WEB browser and check out the response

Test values from manual https://weatherlink.github.io/v2-api/tutorial
$apiKey = "987654321";
$apiSecret  = "ABC123";
$apiRequestTimestamp = "1558729481";
*/ 

// Select "stations" or "current"
// $data = "stations"; 
$data = "current";

// Replace with your own values !!!! 
$apiKey = "xxx";
$apiSecret  = "yyy";
$stationId = 0000;
$apiRequestTimestamp = time();

if ($data == "stations"){

    $parametersToHash = array(
        "api-key" => $apiKey,
        "t" => $apiRequestTimestamp
    );                                                                      // echo "<br>"; var_dump ($parametersToHash);

    $service_url = "https://api.weatherlink.com/v2/stations?api-key=".$apiKey."&t=".$apiRequestTimestamp."&api-signature=".calculateSignature($apiSecret, $parametersToHash);
}

if ($data == "current"){
    
    $parametersToHash = array(
        "api-key" => $apiKey,
        "t" => $apiRequestTimestamp,
        "station-id" => $stationId
    );                                                                      // echo "<br>"; var_dump ($parametersToHash);

    $service_url = "https://api.weatherlink.com/v2/current/".$stationId."?api-key=".$apiKey."&t=".$apiRequestTimestamp."&api-signature=".calculateSignature($apiSecret, $parametersToHash);
}

echo "<br>"; echo $service_url;

function calculateSignature($apiSecret, $parametersToHash) {
    ksort($parametersToHash);
    $stringToHash = "";
    foreach ($parametersToHash as $parameterName => $parameterValue) {
        $stringToHash = $stringToHash . $parameterName . $parameterValue;
    }                                                                   // echo "<br>"; echo $stringToHash;

    $apiSignature = hash_hmac("sha256", $stringToHash, $apiSecret);     // echo "<br>"; echo $apiSignature;

    return $apiSignature;
}
?>