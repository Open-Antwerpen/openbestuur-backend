<?php
/* doorzoekt alle titels van besluiten die nog niet geoCoded zijn op adresgegevens. Adresdata zet het script om in geolocatie (Point Of Interest POIs) via Google Maps API
straatNr bij vergunningen gefilterd
TODO: 
-check of real_escape_string($straat) werkt bij nieuwe set besluiten
-optimaliseren van straat en nr herkenning uit strings
bv. straatnaam en NEAR functie om straatNr filteren */

include_once('../config.php');

#initialisations
$log            = "Started script " . __FILE__;
$locality       = "Antwerpen";
$ch             = curl_init();
$POI_matched    = 0;


#doorloop stratenlijst Antwerpen voor alle nieuwe besluiten
$resultStraat = $mysqli->query("SELECT * FROM streets WHERE 1");

while ($rowStraat = $resultStraat->fetch_assoc()) {
     
     #zoek straat in nieuwe besluiten
     $straat = $rowStraat['street'];
     $straat = $mysqli->real_escape_string($straat); //voor speciale letters in adressen  
                                                
     if ($resultStraatBesluit = $mysqli->query("SELECT * FROM documents WHERE meetitem_title_pop LIKE '%". $straat . "%' AND is_geocoded = 0 ORDER BY meetitem_meetdate DESC")) {
         
         while ($rowBesluit = $resultStraatBesluit->fetch_assoc() ) {
           
            $onderwBesluit  = $rowBesluit['meetitem_title_pop']; // syntax bij vergunningen: 20151324 - district Wilrijk - Jules Moretuslei 150
            $idBesluit      = $rowBesluit['id'];
            
            #straatNr filteren
            $pieces                 = explode(' ', $onderwBesluit); 
            $last_word              = array_pop($pieces); //gets last item from array
            ( is_int($last_word) ? $adresNr = $last_word: $adresNr="");
           
            $needle = $straat . " " . $adresNr;
            trim($needle);
            
            $log .= "\n Extracted from title <" . $onderwBesluit . "> needle: " . $needle; 
            
            #get JSON geolocation from Google
            // make request
            $needle     = urlencode($needle);
            $requestUrl = "https://maps.googleapis.com/maps/api/geocode/json?address=" . $needle . "&components=locality:" . $locality . "&key=". GOOGLEAPIKEY;
            //echo $requestUrl . "<br>";
            curl_setopt($ch, CURLOPT_URL, $requestUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
            
            $output     = curl_exec($ch);  
            
            if (curl_errno($ch)) { 
                $log .= "curl_error: " . curl_error($ch);
            } 
            
            // convert response
            $geoloc = json_decode($output);
            var_dump($geoloc->results); //documentation https://developers.google.com/maps/documentation/geocoding/intro

            $statusCode     = $geoloc->status;
            $latitude       = $geoloc->results[0]->geometry->location->lat;
            $longitude      = $geoloc->results[0]->geometry->location->lng;
            
            #update dit besluit
            $updateBesluit = $mysqli->query("UPDATE documents SET poi_latitude = '" . $latitude . "', poi_longitude = '". $longitude . "'  WHERE id =" . $idBesluit);
            
            if (strpos($statusCode, 'OK') === false) { //catch error code from Google API
                $log .= "\n " . $needle . " gives problem with rec id ". $idBesluit . " gave error code from Google API: " .$statusCode . "\n";
           
            } else {
                $POI_matched = $POI_matched + 1;
                $log .= "\n Matched " . $needle . ", for rec id " . $idBesluit;
            }
                
        } //end while gevonden besluiten
     }
     
}

//flag all docs for today as GEOProcessed
$flagGEOProcessed = $mysqli->query("UPDATE documents SET is_geocoded = 1");

$log .= "\n" . $POI_matched . " POIs added to documents table";
writeLog($log);
echo $log;

mail(EMAIL_ADMIN,"logs",$log);
//var_dump(get_defined_vars());

#close connections
curl_close($ch);
mysqli_close($mysqli);
?>