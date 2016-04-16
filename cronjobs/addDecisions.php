<?php
/* created by FF on 26-03-2016

voegt dagelijks alle nieuwe besluiten van e-besluit Gent toe aan OA-DB. 

validity: 
if groupsSourceId conflicts between different sources, we must create internal ids

dependencies:
make sure developer mode is off (config.php) in production!
moet op einde van de dag draaien rond halftwaalf s'avonds

TODO: 
- */

include_once('../config.php');
include_once('../httpful.phar'); //nice library making http requests easy

#inis
$log               = "Started script " . __FILE__;
$i                 = 0;
$recInserted       = 0;
$sourceName        = "ebesluit antwerpen";
$sourceUrl         = "http://ebesluit.antwerpen.be/"; //Gent: http://qbesluit.gent.be/  
$recordStart       = 0;
$recordLength      = 1000;

#zoek besluiten, return json object
$updateDate             = date("d-m-Y",strtotime("-1 days")); //cron job runs at 00:15 am, so get yesterday's decisions
//$updateDate             = "12-04-2016";

$uri = $sourceUrl . "do/search/ajax?searchText=&yearNumber=&organId=&title=&meetingDate=" . $updateDate . "&draw=1&columns[0][data]=function&columns[0][name]=&columns[0][searchable]=false&columns[0][orderable]=false&columns[0][search][value]=&columns[0][search][regex]=false&columns[1][data]=function&columns[1][name]=&columns[1][searchable]=false&columns[1][orderable]=false&columns[1][search][value]=&columns[1][search][regex]=false&columns[2][data]=function&columns[2][name]=&columns[2][searchable]=true&columns[2][orderable]=false&columns[2][search][value]=&columns[2][search][regex]=false&columns[3][data]=function&columns[3][name]=&columns[3][searchable]=true&columns[3][orderable]=false&columns[3][search][value]=&columns[3][search][regex]=false&columns[4][data]=function&columns[4][name]=&columns[4][searchable]=true&columns[4][orderable]=false&columns[4][search][value]=&columns[4][search][regex]=false&columns[5][data]=function&columns[5][name]=&columns[5][searchable]=true&columns[5][orderable]=false&columns[5][search][value]=&columns[5][search][regex]=false&start=" . $recordStart . "&length=" . $recordLength . "&search[value]=&search[regex]=false&_=";

$response = \Httpful\Request::get($uri)
    ->addHeader('Access-Control-Allow-Origin', '*') 
    ->addHeader('Content-Type','application/json')
    ->expectsJson()
    ->send();

//var_dump($response);

$recordsFound       = $response->body->recordsTotal;
$recordsFiltered    = $response->body->recordsTotal;
$statusCode         = $response->body->error;
$log .= "API status from (" . $sourceUrl . "): " . $statusCode . "\n";

if ($statusCode == '') { //zero means were OK

   /* create a prepared statement */
   $stmtDocuments     = $mysqli->prepare("INSERT INTO documents (src_id, content_pub, meetitem_src_id, meetitem_title_off,meetitem_title_pop, meetitem_meetdate, group_id, src_name, url, summary) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?,?)");
   
   $stmtExistGroup    = $mysqli->prepare("SELECT id FROM groups WHERE src_id=?");
   $stmtNewGroup      = $mysqli->prepare("INSERT INTO groups (src_id, name, short_name) VALUES (?, ? , ?)");
   
   /* bind parameters for markers */
   $stmtDocuments->bind_param('sissssisss', $sourceId, $contentPublished, $meetingItemSourceId, $meetingItemTitleOff, $meetingItemTitlePop, $meetingItemMeetingDate, $groupId, $sourceName, $url, $summary);   
   
   /* bind parameters for markers */
    $stmtExistGroup->bind_param("i", $groupSourceId);
    $stmtNewGroup->bind_param('iss', $groupSourceId, $groupName, $groupShortName); 
  
    /* set parameters and execute */
   
    while ($i < $recordsFound) {
        if (isset($response->body->data[$i]->meeting->id)) { //docu needed -> what is this?
            $log .= "record id " . $response->body->data[$i]->id . " seems invalid and was not inserted \n";
        } 
        else {
            $sourceId                           = $response->body->data[$i]->id;
            $contentPublished                   = $response->body->data[$i]->contentPublished;
            $meetingItemSourceId                = $response->body->data[$i]->meetingItem->id;
            $meetingItemTitleOff                = $response->body->data[$i]->meetingItem->title;
            $meetingItemMeetingDate             = $response->body->data[$i]->meetingItem->meeting->date;
            $groupSourceId                      = $response->body->data[$i]->meetingItem->meeting->group->id;
            $groupName                          = $response->body->data[$i]->meetingItem->meeting->group->name;
            $groupShortName                     = $response->body->data[$i]->meetingItem->meeting->group->shortName;
            $sourceName                         = $sourceName ;//init at beginning of script
            $url                                = $sourceUrl . "do/publication/" . $sourceId . "/inline"; 
            $summary                            = "Geen samenvatting beschikbaar";
            
            # condition some values for db 
            ( $contentPublished == true ? $contentPublished = 1: $contentPublished = 0 );
            //cleanup the official title, split off first part (e.g. 2016_MV_00157 - Mondelinge vraag van raa...)
            $pieces = explode(" - ", $meetingItemTitleOff);  
            //escape speciale letters in titel 
            $meetingItemTitlePop = str_replace( $pieces[0] . " - " , "" , $meetingItemTitleOff ); 
            $meetingItemTitlePop = $mysqli->real_escape_string($meetingItemTitlePop); 
            $meetingItemTitleOff = $mysqli->real_escape_string($meetingItemTitleOff);
            //format epoch time (1458579600000) to date 
            $epoch =    $meetingItemMeetingDate / 1000;      
            $dt = new DateTime("@$epoch");  // convert UNIX timestamp to PHP DateTime
            $meetingItemMeetingDate = $dt->format('Y-m-d H:i:s'); // output = 2012-08-15 00:00:00 
            
            //var_dump(get_defined_vars());
            ( $stmtDocuments->execute() ? $recInserted++: $log .= "record id " . $response->body->data[$i]->id . " could not be inserted \n") ;
            
            //if this group exists, get its id from db , else make new group
            $stmtExistGroup->execute();
            $stmtExistGroup->store_result(); //if we want to do ->num_rows we need to store results first 
            
            if ( !$stmtExistGroup->num_rows ==0 ) {
                $stmtExistGroup->bind_result($existingGroupId);
                if ( $stmtExistGroup->fetch() ) {
                    $groupId = $existingGroupId;
                }
            } else {
                $stmtNewGroup->execute();
                $groupId = $stmtNewGroup->insert_id;
                $log .= "Created new group " . $groupName . "(id: " . $groupId . ") \n";
            }
   
        }
        
        $i = $i +1 ;
   }
}
                
$log .= " -Script retrieved records for " . $updateDate . "\n -Found " . $recordsFound . " records and filtered " . $recordsFiltered . "\n -Inserted " . $recInserted . " records successfully" ;

writeLog($log);
echo $log;

mail(EMAIL_ADMIN,"logs",$log);

#close connections
$stmtDocuments->close();
$stmtNewGroup->close();
//mysqli_close($mysqli);  
?>