<?php
/**
 * Created by PhpStorm.
 * User: Ben
 * Date: 3/5/14
 * Time: 6:32 PM
 */

//http://localhost/movie/php/test.php?latitude=40.703648&longitude=-89.40731199999999&origin=106+North+Main+Street%2C+Washington%2C+IL+61571%2C+USA&data=MEOW!&test=1
//movie.bennyjake.com/php/test.php?latitude=40.703648&longitude=-89.40731199999999&origin=106+North+Main+Street%2C+Washington%2C+IL+61571%2C+USA&data=MEOW!&test=1


//header('Content-Type: application/json');

session_start();

require_once('googlemovie/GoogleMovieShowtimes.php');

//return
if(isset($_GET['latitude']) && isset($_GET['longitude'])){


    $movie_search_db = get_movie_search_db($_GET);

    $_SESSION['test'] = json_encode($movie_search_db);


    if(!isset($_GET['test'])){

        echo $_SESSION['test'];
    }

}

function get_movie_search_db($data){

    //$dbh = new PDO('mysql:host=localhost;dbname=bennyjak_movie', 'root', '');
    $dbh = new PDO('mysql:host=76.74.220.80;port=3306;dbname=bennyjak_movie', 'bennyjak_quiet22', 'quietracket22');

    //THEATER

    $query_theater = "SELECT tid, lat, lng, name, address, city, state, phone,
                ( 3959 * acos( cos( radians(".$data['latitude'].") ) * cos( radians(lat) ) * cos( radians(lng) - radians(".$data['longitude'].") ) +
                    sin( radians(".$data['latitude'].") ) * sin( radians(lat) ) ) ) AS distance FROM theater HAVING distance < 30 ORDER BY distance LIMIT 0 , 20;";

    //Pull theater results from the database
    $theater_search_db = $dbh->query($query_theater)->fetchAll(PDO::FETCH_ASSOC);

    //THEATER-MOVIE UPDATE TIMES

    $query_theater_movie = "SELECT tid_mid, tid, mid, date, time FROM theater_movie WHERE tid = ?;";
    $prepare_theater_movie = $dbh->prepare($query_theater_movie);

    //for every theater brought up from our DB search
    foreach($theater_search_db as $theater_info){

        $prepare_theater_movie->execute(array($theater_info['tid']));
        $theater_movie_row = $prepare_theater_movie->fetchAll(PDO::FETCH_ASSOC);

        //if no results for a tid
        if(empty($theater_movie_row)){

            //do an api search on just that theater, pull theater info and movie times
            $movie_search_api = get_movie_search_api(NULL,NULL,$theater_info['tid']);

            //save this info for the app
            $theater_movie_row = $movie_search_api['theater'][$theater_info['tid']];

            //add
            $insert_theater_movie = "INSERT INTO theater_movie (tid_mid,tid, mid, date, time) VALUES (:tid_mid,:tid, :mid, :date, :time);";

            $prepare_theater_movie = $dbh->prepare($query_theater_movie);

            //$this->resp['theater'][$tid]['movies'][$mid]['time'][$k]
            foreach($theater_movie_row["movies"] as $mid => $movie_info){
                foreach($movie_info['time'] as $key => $single_time){
                    
					$prepare_theater_movie->bindParam(':tid_mid',$theater_info['tid'].'-'.$mid);
					$prepare_theater_movie->bindParam(':tid',$theater_info['tid']);
					$prepare_theater_movie->bindParam(':mid',$mid);
					$prepare_theater_movie->bindParam(':date',date('now'));
					$prepare_theater_movie->bindParam(':time',$single_time);					
                    $prepare_theater_movie->execute();
                }
            }

                //insert into $table (field, value) values (:name, :value) on duplicate key update value=:value2

        }
        //we got results based on the tid
        else{
			//foreach($theater_movie_row as 
			
            //need_update($theater_info['date'],24);

        }

        $theater_movie[] = $theater_movie_row;
    }

    echo "<hr/><pre>";
    var_dump($theater_movie);
    echo "</pre>";

    return $theater_search_db;
}

function get_movie_search_api($location = NULL, $mid = NULL, $tid = NULL){

    $movie_search_api_RAW = new GoogleMovieShowtimes($location, $mid, $tid);

    $movie_search_api = $movie_search_api_RAW->parse();

    return $movie_search_api;
}

function need_update($last_update,$diff){
    $most_recent_update = new DateTime($last_update);
    $current_time = new DateTime('now');
    $difference = $most_recent_update->diff($current_time);
    return $difference->h >= $diff;
}

function get_movie_search_final($api,$db){

    foreach($db as $theater_info){

        $api['theater'][$theater_info['tid']]['lat']
            = $theater_info['lat'];
        $api['theater'][$theater_info['tid']]['lng']
            = $theater_info['lng'];
    }

    return $api;
}

function erroneous($api,$db){

    if(isset($_GET['test'])){
        echo "<pre>";
        var_dump(json_encode($api));
        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                echo ' - No errors';
                break;
            case JSON_ERROR_DEPTH:
                echo ' - Maximum stack depth exceeded';
                break;
            case JSON_ERROR_STATE_MISMATCH:
                echo ' - Underflow or the modes mismatch';
                break;
            case JSON_ERROR_CTRL_CHAR:
                echo ' - Unexpected control character found';
                break;
            case JSON_ERROR_SYNTAX:
                echo ' - Syntax error, malformed JSON';
                break;
            case JSON_ERROR_UTF8:
                echo ' - Malformed UTF-8 characters, possibly incorrectly encoded';
                break;
            default:
                echo ' - Unknown error';
                break;
        }
        echo "<hr/>";
        var_dump(json_encode($db));
        echo "</pre>";
    }
}