<?php
/**
 * Created by PhpStorm.
 * User: Ben
 * Date: 3/5/14
 * Time: 6:32 PM
 */

//header('Content-Type: application/json');

session_start();


//return
if(isset($_GET['latitude']) && isset($_GET['longitude'])){


    $movie_search_db = get_movie_search_db($_GET);

    $movie_search_api = get_movie_search_api($_GET);

    if(isset($_GET['test'])){
    echo "<pre>";
    var_dump(json_encode($movie_search_api));
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
    var_dump(json_encode($movie_search_db));
    echo "</pre>";
    }

    $_SESSION['test'] = json_encode($movie_search_api);
    //$_SESSION['test'] = json_encode($movie_search_db);


    if(!isset($_GET['test'])){

        echo $_SESSION['test'];
    }

}

function get_movie_search_db($data){

    $dbh = new PDO('mysql:host=localhost;dbname=movie', 'root', '');
    //$dbh = new PDO('dbname=quiet22_movie;mysql:host=50.87.149.42;port=3306', 'quiet22_movie', 'quiet22');

    //THEATER

    $query = "SELECT tid, last_update, lat, lng, name, address, city, state, phone,
                ( 3959 * acos( cos( radians(".$data['latitude'].") ) * cos( radians(lat) ) * cos( radians(lng) - radians(".$data['longitude'].") ) +
                    sin( radians(".$data['latitude'].") ) * sin( radians(lat) ) ) ) AS distance FROM theater HAVING distance < 30 ORDER BY distance LIMIT 0 , 20;";


    //Pull theater results from the database
    $results = $dbh->query($query)->fetchAll(PDO::FETCH_ASSOC);

    foreach($results as $key => $data){

        if(isset($results['tid']) && check_update($results['last_update'])){
        $movie_search_db['theater'][$results['tid']] = $results;
        $movie_search_db['theater'][$results['tid']]['valid'] = true;
        }
        else{

        }
    }

    //THEATER_MOVIE

    $query = "SELECT tid, last_update, lat, lng, name, address, city, state, phone,
                ( 3959 * acos( cos( radians(".$data['latitude'].") ) * cos( radians(lat) ) * cos( radians(lng) - radians(".$data['longitude'].") ) +
                    sin( radians(".$data['latitude'].") ) * sin( radians(lat) ) ) ) AS distance FROM theater HAVING distance < 30 ORDER BY distance LIMIT 0 , 20;";


    //Pull theater results from the database
    $results = $dbh->query($query)->fetchAll(PDO::FETCH_ASSOC);

    return $movie_search_db;
}

function get_movie_search_api($data){

    require_once('googlemovie/GoogleMovieShowtimes.php');

    $movie_search_api_RAW = new GoogleMovieShowtimes($data['origin']);

    $movie_search_api = $movie_search_api_RAW->parse();

    return $movie_search_api;
}

function check_update($last_update){
    $most_recent_update = new DateTime($last_update);
    $current_time = new DateTime('now');
    $difference = $most_recent_update->diff($current_time);
    return $difference->d < 3;
}