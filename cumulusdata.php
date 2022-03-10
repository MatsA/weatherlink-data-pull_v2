<?php

// Mats A 2017-01 metzallo@gmail.com
// For the weathertemplate https://vader.domsten.nu
// Source https://github.com/ktrue/CU-HWS
// This program uppdates the Cumulus realtime.txt after a call to Davis Weatherlink, v2 API, website with a JSON answer
// Documentation is available at https://pysselilivet.blogspot.com/2022/02/install-weather34-with-weatherlink-v2.html
/*
MIT License

Copyright (c) 2017 

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/ 

// File layout https://cumuluswiki.wxforum.net/a/Realtime.txt 
// 2017-01-29 To get better trend values for temp and pressure the csv files in chartswudata is used as history info. 
//            Now trend calculation is using the measures 2 hours ago ->sub(new DateInterval('PT2H')) 
// 2017-03-29 Changed filesuffix for files in folder "chartswudata" to .txt
//            added cumulus[34] wich is used in Barometer view
// 2017-04-25 Due to a change in W34 it's impossible to use the history files in chartswudata  as a base for trendata.
//            Extending the file realtime.txt so fields $cumulus[60-63] is used to store the history for press and temp
// 2017-05-04 Added fields min barometer => cumulus[36] and windrun => cumulus[17]. Onlinefil realtime.txt is created if missing
// 2017-05-20 Added fields uv-index => cumulus[43] and solar radiation => cumulus[45] wich will be updated if available, otherwise set to 0
//            Change in validation of data from Davis Weatherlink due to that they allways returns an answer even if wrong credentials
// 2017-05-26 Added fields temp high/low time => cumulus[27/29] and wind high/max time [31/33]
//            W34 changed use for cumulus[5/6/40] so updated app.
// 2017-06-21 Added an extra temperature sensor, Davis 6372, which in our case mesures water temperature in the sea.
//            cumulus[22], inside temp is used if $water_temp is true and value is valid
// 2018-03-19 Davis is updating Weatherlink to WL 2.0 with new host and user is Device ID
// 2018-10-15 Rewrite cause WL data is pulled via Curl and JSON as response
// 2018-11-05 Added field, yesterday's rainfall => $cumulus[21]
// 2018-11-30 Ten minutes windgust, available via JSON => $cumulus[40]
// 2018-12-03 Added calculation for rain last hour => $cumulus[47]
// 2019-03-31 Don't fetch data more often than every minute. Due to avoiding croon job.
// 2022-02-22 Updated for use with the latest v2 API https://weatherlink.github.io/v2-api/
// 2022-03-08 WL credentials stored in the file "wlauth.txt" and new handling of template file

$water_temp = FALSE;                        // If "temp_extra_1" have data and "$water_temp = TRUE" the inside temp, $cumulus[22], will be used as storage

ob_start();
error_reporting(0);
@ini_set('display_errors', 0);

chdir(dirname(__FILE__));

include('../settings1.php');                // The best schould have been to store the Weatherlink credentials here, but easyweathersetup.php cleans up

date_default_timezone_set($TZ);

$file_templ = "../add_on/realtime.templ";   // Template file
$file_realt = "../add_on/realtime.txt";     // Realtime/Online file 

$cumulus = array();                         // Current observation data 
$cumulus_l = array();                       // Last observation data 

if (file_exists($file_realt)) {             // Create the online file if don't exist
   
  if(time() - filemtime($file_realt)>=60){
       
    $getNewData = TRUE;                     // If file older than 60 seconds get new data
  }
  else {
        $getNewData = FALSE;
  }
}
else {
  copy($file_templ,$file_realt);
  $getNewData = TRUE;
}

if ($getNewData) {

    $contentAuth = file_get_contents("../add_on/wlauth.txt");   // echo $contentAuth."<br>";       
    $dataAuth = explode(",", $contentAuth);                     // var_dump ($dataAuth);

    $apiKey = $dataAuth[0];
    $apiSecret  = $dataAuth[1];
    $stationId = $dataAuth[2];

    $apiRequestTimestamp = time();

    $file_wrk = file_get_contents($file_realt); 
    
    $cumulus = explode(" ", $file_wrk);         // The last file is the base for creating the new file // var_dump ($cumulus);
    $cumulus_l = explode(" ", $file_wrk);       // Save the last data as referens for new calculated values
                                                                                
    // *******  Get the current "conditions" from Davis Weatherlink ******* // 
    $parametersToHash = array(                  // Parameters to hash for signature in URL
        "api-key" => $apiKey,
        "t" => $apiRequestTimestamp,
        "station-id" => $stationId
    );                                          // echo "<br>"; var_dump ($parametersToHash);
            
    $service_url = "https://api.weatherlink.com/v2/current/".$stationId."?api-key=".$apiKey."&t=".$apiRequestTimestamp."&api-signature=".calculateSignature($apiSecret, $parametersToHash);
            
    $curl_handle = curl_init($service_url);
    curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
    $curl_response = curl_exec($curl_handle);
    $wlJson = json_decode($curl_response);                                                      // echo "<br><br>"; var_dump ($wlJson); echo $wlJson->message;
                                                                                
    //  ******* If wrong response no file update  ******* //
    if (($wlJson == NULL) or ($wlJson->message != NULL)) {
        // *NOP
    } else {

        // Please note Field no in the Cumulus spec.  -1 => array no
        $cumulus[0] = date('d/m/y', $wlJson->sensors[0]->data[0]->ts);                          // Observation date from Weatherlink          
        $cumulus[1] = date('H:i:s', $wlJson->sensors[0]->data[0]->ts);                          // Observation time from Weatherlink
                                                                                                // echo "<br><br>"; echo ("$cumulus[1] $cumulus_l[1] \n");
        if ($cumulus[1] <> $cumulus_l[1])  {                                                    // Update file, not same time/observation as last

            // *******  Direct values ******* //
            $cumulus[2] = $wlJson->sensors[0]->data[0]->temp_out;
            $cumulus[3] = $wlJson->sensors[0]->data[0]->hum_out;
            $cumulus[4] = $wlJson->sensors[0]->data[0]->dew_point;                              // Dewpoint
            $cumulus[5] = $wlJson->sensors[0]->data[0]->wind_speed;                             // Used by W34 for trend calc. Wind this moment
            $cumulus[6] = $wlJson->sensors[0]->data[0]->wind_speed_10_min_avg;                  // Wind 10 min avg
            $cumulus[7] = $wlJson->sensors[0]->data[0]->wind_dir;                               // Wind direction
            $cumulus[8] = $wlJson->sensors[0]->data[0]->rain_rate_in;                           // Rain rate in/h
            $cumulus[9] = $wlJson->sensors[0]->data[0]->rain_day_in;                            // Rain in inches(in)
            $cumulus[10] = $wlJson->sensors[0]->data[0]->bar;                                   // Pressure inches
            $cumulus[13] = "mph";                                                               // Windspeed unit 
            $cumulus[14] = "F";                                                                 // Temp unit
            $cumulus[15] = "in";                                                                // Pressure unit
            $cumulus[16] = "in";                                                                // Rain unit
            $cumulus[19] = $wlJson->sensors[0]->data[0]->rain_month_in;                         // Rain acc. Month inches
            $cumulus[20] = $wlJson->sensors[0]->data[0]->rain_year_in;                          // Rain acc. Year  
            $cumulus[22] = $wlJson->sensors[0]->data[0]->temp_in;                               // Inside temp
            $cumulus[23] = $wlJson->sensors[0]->data[0]->hum_in;                                // Inside humidity
            $cumulus[24] = $wlJson->sensors[0]->data[0]->wind_chill;                            // Wind chill
            $cumulus[40] = $wlJson->sensors[0]->data[0]->wind_gust_10_min;                      // Windgust avg. 10 min

            // *******  Calculated values ******* //
            $hour = date_create($cumulus[1])->format('H');                                      // Create an save, in realtime.txt, values for trend caculation every hour 
            $hour_l = date_create($cumulus_l[1])->format('H');
            $obsTime = date('H:i', $wlJson->sensors[0]->data[0]->ts);                           // Observation time from Weatherlink
                                                                                                // echo "\n $hour $hour_l \n";
            if ($hour <> $hour_l) {                                                             // If new hour save data for trendvalues

                $cumulus[47] = round($cumulus_l[9] - $cumulus_l[59],4);                         // Rain last hour
                $cumulus[59] = $cumulus_l[9];                                                   // Save rain acc today

                $cumulus[61] = $cumulus_l[60];                                                  // Save the 1 hour old temp value 
                $cumulus[60] = $cumulus[2];                                                     // Save current temp F

                $cumulus[63] = $cumulus_l[62];                                                  // Save the 1 hour old press value 
                $cumulus[62] = $cumulus[10];                                                    // Save current press
            }

            $cumulus[18] = round((floatval($cumulus[10]) - $cumulus[63]),4);                    // Barometer trend, current - history
            $cumulus[25] = floatval($cumulus[2]) - $cumulus[61];                                // Temp trend // echo "$cumulus[25] $cumulus[18]  \n";

            if ($cumulus[0] == $cumulus_l[0]) {                                                 // Same date "d/m/y" ?Current and last
                                                                                                // Windrun calculation https://cumuluswiki.wxforum.net/a/Windrun
                $diff = date_diff(date_create($cumulus[1]), date_create($cumulus_l[1]));        // Observation time current - observation last
                $hours = ($diff->h) + ($diff->i)/60 + ($diff->s)/3600;                          // Diff, hours + minutes + seconds in hours. var_dump ($hours);
                $cumulus[17] = round($cumulus_l[17] + ($cumulus[6]*$hours),4);                                                                            

                                                                                                // Daily max temp calculation
                if ($cumulus[2] > $cumulus_l[26]) {                                             // Same date and if current temp > daily max avg => update daily max temp and time
                    $cumulus[26] = floatval($cumulus[2]);                                                  
                    $cumulus[27] = $obsTime;
                }
                                                                                                // Daily min temp calculation
                if ($cumulus[2] < $cumulus_l[28]) {                                             // Same date and if current temp < daily min => update daily temp and time
                    $cumulus[28] = floatval($cumulus[2]);                                                  
                    $cumulus[29] = $obsTime;
                }
                                                                                                // Daily max avg wind calculation
                if ($cumulus[6] > $cumulus_l[30]) {                                             // Same date and if current wind 10 min avg > daily max avg => update daily max wind avg. and time
                
                    $cumulus[30] = floatval($cumulus[6]);                                                  
                    $cumulus[31] = $obsTime;
                }
                                                                                                // Daily wind gust max calculation
                if ($cumulus[40] > $cumulus_l[32]) {                                            // Same date and if current gust wind 10 min avg > daily max avg => update daily max wind avg. and time
                
                    $cumulus[32] = floatval($cumulus[40]);                                                  
                    $cumulus[33] = $obsTime;
                }
                                                                                                // Daily pressure max calculation
                if ($cumulus[10] > $cumulus_l[34]) {                                            // Same date and if pressure > daily max => update daily max pressure and time
                
                    $cumulus[34] = floatval($cumulus[10]);                                                  
                    $cumulus[35] = $obsTime;
                }
                                                                                                // Daily pressure min calculation
                if ($cumulus[10] < $cumulus_l[36]) {                                            // Same date and if pressure < daily min => update daily min pressure and time
                
                    $cumulus[36] = floatval($cumulus[10]);                                                  
                    $cumulus[37] = $obsTime;
                }
            }
            else {                                                                              // New day
                                                                                                // Rain yesterday
                $cumulus[21] = $cumulus_l[9];                                                   // Update value for yesterdays rainfall
                                                                                                // Windrun calculation
                $hours = date_create($cumulus[1])->format('H') + 
                            date_create($cumulus[1])->format('i')/60;                           // Hours + minutes, since midnight in hours. var_dump ($cumulus[6]);
                $cumulus[17] = round($cumulus[6]*$hours,4);                                     // Ten min avg wind * hours. var_dump ($cumulus[17]);
                
                $cumulus[26] = floatval($cumulus[2]);                                           // Daily max temp calculation. Set daily max temp and time to current observation
                $cumulus[27] = $obsTime;                                

                $cumulus[28] = floatval($cumulus[2]);                                           // Daily min temp calculation. Set daily min temp and time to current observation
                $cumulus[29] = $obsTime;                                

                $cumulus[30] = floatval($cumulus[6]);                                           // Daily max wind calculation. Set daily max wind average and time to current observation
                $cumulus[31] = $obsTime;                                

                $cumulus[32] = floatval($cumulus[40]);                                          // Daily max wind gust calculation. Set daily max wind gust average and time to current observation
                $cumulus[33] = $obsTime;                                

                $cumulus[34] = floatval($cumulus[10]);                                          // Daily max pressure calculation. Set daily max pressure and time to current observation
                $cumulus[35] = $obsTime;                                
                
                $cumulus[36] = floatval($cumulus[10]);                                          // Daily min pressure calculation. Set daily max pressure and time to current observation
                $cumulus[37] = $obsTime;                                
            }

            if ($wlJson->sensors[0]->data[0]->uv != null) {                                     // uv-index
                
                $cumulus[43] = $wlJson->sensors[0]->data[0]->uv;                                // uv-index OK                                                        
            }
            else { 

                $cumulus[43] = 0;                                                               // If mesure not is available set to 0
            }

            if ($wlJson->sensors[0]->data[0]->solar_rad != null) {                              // solar radiation
                
                $cumulus[45] = $wlJson->sensors[0]->data[0]->solar_rad;                         // solar radiation OK
            }
            else {                                                                          

                $cumulus[45] = 0;                                                               // If mesure not is available set to 0
            }
        
            $cumulus[46] = round((($cumulus[7] + $cumulus_l[7])/2),0);                          // Wind direction average, no decimals

            if  ($water_temp) {                                                                 // Water temp. 
                if ($wlJson->sensors[0]->data[0]->temp_extra_1 != null){
                
                    $cumulus[22] = $wlJson->sensors[0]->data[0]->temp_extra_1;                  // Inside temp. is used
                }
            }

            // ******* Update the realtime.txt file ******* //
            $file_live = implode(" ",$cumulus);
            $handle = fopen($file_realt, "w");
            fwrite($handle, $file_live);
            fclose($handle);                                                                    // echo readfile("../add_on/realtime.txt");

        } // New date END

    } //  Wrong "conditions" data END
} // Get new data END

// Function *****************

function calculateSignature($apiSecret, $parametersToHash) {
    ksort($parametersToHash);
    $stringToHash = "";
    foreach ($parametersToHash as $parameterName => $parameterValue) {
        $stringToHash = $stringToHash . $parameterName . $parameterValue;
    }                                                                                           // echo "<br>"; echo $stringToHash;

    $apiSignature = hash_hmac("sha256", $stringToHash, $apiSecret);                             // echo "<br>"; echo $apiSignature;

    return $apiSignature;
}

?>
