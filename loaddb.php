<?php
/**
 * Created by PhpStorm.
 * User: Ben
 * Date: 12/1/13
 * Time: 4:13 PM
 */

ini_set('max_execution_time', 7200);//2 hours

$cap = 50;
$state = "CA";

require_once('php/googlemovie/GoogleMovieShowtimes.php');

$dbh = new PDO('mysql:dbname=quiet22_movie;host=50.87.149.42;port=3306', 'quiet22_movie', 'quiet22');

$finished_update = $dbh->prepare("UPDATE zipcodes SET Finished = 'T' WHERE ZipCode = ?;");

function microtime_float()
{
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}

$time_start = microtime_float();

$counter = 0;

foreach($dbh->query("SELECT ZipCode, State FROM zipcodes WHERE Finished <> 'T' AND STATE = '".$state."';") as $zipcode_row){

    if(runAddress($zipcode_row['ZipCode'],$zipcode_row['State'],$dbh)){
    sleep(5);

    $time_end = microtime_float();
    $time = $time_end - $time_start;

    echo "<br/>Execution time for <strong>".$zipcode_row['ZipCode']."</strong>: $time seconds<hr/><hr/>";

    $finished_update->execute(array($zipcode_row['ZipCode']));

    $counter++;
    }
    else{
        break;
    }

    if($counter >= $cap){
        break;
    }
}

$time_end = microtime_float();
$time = $time_end - $time_start;


echo "<hr/><br/><strong>Final</strong> Execution time: $time seconds\n";



function runAddress($zip, $proper_state, $dbh){

    echo "Zip: ".$zip;

    $test = new GoogleMovieShowtimes($zip);
    //var_dump() the response which was parsed into a giant organized associative array.
    $movie_array = $test->parse();

    if(sizeof($movie_array)>0){
        foreach($movie_array["theater"] as $index => $theater){

                $location = explode(', ',$movie_array['theater'][$index]['info']);
                $movie_array['theater'][$index]['address'] = $location[0];
                $movie_array['theater'][$index]['city'] = $location[1];

                $state_and_phone = explode(' - ',$location[2]);
                $movie_array['theater'][$index]['state'] = $state_and_phone[0];
                if(isset($state_and_phone[1])){
                    $movie_array['theater'][$index]['phone'] = $state_and_phone[1];
                }
                else{
                    $movie_array['theater'][$index]['phone'] = '';
                }

            try {
            $select = $dbh->query("SELECT count(*) FROM theater WHERE address = '".$movie_array['theater'][$index]['address']."'
                AND "." city = '".$movie_array['theater'][$index]['city']."'
                AND state = '".$movie_array['theater'][$index]['state']."';")->fetchColumn();
            } catch (PDOException $e) {
            print "Error!: " . $e->getMessage() . "<br/>";
            return false;
        }

            //if it isn't in the db already
            if(intval($select) > 0){
                echo "<hr/>";
                echo "Insert result: <strong>ALREADY IN DB</strong><br/>";
                echo $movie_array['theater'][$index]['name'].", "
                    .$movie_array['theater'][$index]['address'].", ".$movie_array['theater'][$index]['city'].", "
                    .$movie_array['theater'][$index]['state'].", ".$movie_array['theater'][$index]['phone'];
                echo "<hr/>";
            }
            elseif($movie_array['theater'][$index]['state']!=$proper_state){
                echo "<hr/>";
                echo "Insert result: <strong>STATE NOT MATCH</strong><br/>";
                echo $movie_array['theater'][$index]['name'].", "
                    .$movie_array['theater'][$index]['address'].", ".$movie_array['theater'][$index]['city'].", "
                    .$movie_array['theater'][$index]['state'].", ".$movie_array['theater'][$index]['phone'];
                echo "<hr/>";
            }else{

                $latlng = lookup($movie_array['theater'][$index]['address'].', '.
                    $movie_array['theater'][$index]['city'].', '.
                    $movie_array['theater'][$index]['state']);

                    try {
                    $insert = $dbh->query("INSERT INTO theater(tid, lat, lng, name, address, city, state, phone) VALUES
                    ('".$movie_array['theater'][$index]['tid']."','".$latlng['lat']."','".$latlng['lng']."','".$movie_array['theater'][$index]['name']."','"
                         .$movie_array['theater'][$index]['address']."','".$movie_array['theater'][$index]['city']."','"
                        .$movie_array['theater'][$index]['state']."','".$movie_array['theater'][$index]['phone']."');");


                        if($insert){
                        echo 'Added '.$movie_array['theater'][$index]['name'].'<br/>';
                        }

                    } catch (PDOException $e) {
                    print "Error!: " . $e->getMessage() . "<br/>";
                    return false;
                    }
                }
            }

        }

    return true;

}
function lookup($string){

    $string = str_replace (" ", "+", urlencode($string));
    $details_url = "http://maps.googleapis.com/maps/api/geocode/json?address=".$string."&sensor=false";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $details_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = json_decode(curl_exec($ch), true);

    // If Status Code is ZERO_RESULTS, OVER_QUERY_LIMIT, REQUEST_DENIED or INVALID_REQUEST
    if ($response['status'] != 'OK') {
        return null;
    }

    $geometry = $response['results'][0]['geometry'];

    $array = array(
        'lat' => $geometry['location']['lat'],
        'lng' => $geometry['location']['lng'],
        'location_type' => $geometry['location_type'],
    );

    return $array;

}