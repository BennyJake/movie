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
	else{
	echo "<hr/><pre>";
    var_dump($movie_search_db);
    echo "</pre>";
	}

}

function get_movie_search_db($data){

    //$dbh = new PDO('mysql:host=localhost;dbname=bennyjak_movie', 'root', '');
    //$dbh = new PDO('mysql:host=76.74.220.80;port=3306;dbname=bennyjak_movie', 'bennyjak_quiet22', 'quietracket22');
    $dbh = new PDO('mysql:host=69.172.211.222;port=3306;dbname=bennyjak_movie', 'bennyjak_quiet22', 'quietracket22');

    $now = date('Y-m-d', strtotime('now'));

    //THEATER

    $query_theater = "SELECT tid, lat, lng, name, address, city, state, phone,
                ( 3959 * acos( cos( radians(".$data['latitude'].") ) * cos( radians(lat) ) * cos( radians(lng) - radians(".$data['longitude'].") ) +
                    sin( radians(".$data['latitude'].") ) * sin( radians(lat) ) ) ) AS distance FROM theater HAVING distance < 30 ORDER BY distance LIMIT 0 , 20;";

    //Pull theater results from the database
    $theater_search_db = $dbh->query($query_theater)->fetchAll(PDO::FETCH_ASSOC);

    //THEATER-MOVIE

    //select theater-movie
    $query_theater_movie = "SELECT tid, mid, date, time FROM theater_movie WHERE tid = :var_tid AND date >= :date_today;";
    $prepare_select_theater_movie = $dbh->prepare($query_theater_movie);

    //insert theater-movie
    $insert_theater_movie = "INSERT INTO theater_movie (tid, mid, date, time) VALUES (:tid,:mid,:now,:play_time);";
    $prepare_insert_theater_movie = $dbh->prepare($insert_theater_movie);

    //for every theater brought up from our DB search
    foreach($theater_search_db as $theater_info){

        $prepare_select_theater_movie->execute(array(':var_tid'=>$theater_info['tid'],':date_today'=>$now));
        $theater_movie_row = $prepare_select_theater_movie->fetchAll(PDO::FETCH_ASSOC);

        //nab theater id's for movie lookups...
        $theater_id[] = $theater_info['tid'];

        //if no results for a tid
        if(empty($theater_movie_row)){

            //do an api search on just that theater, pull theater info and movie times
            $theater_search_api = get_movie_search_api(NULL,NULL,$theater_info['tid']);

            $theater_movie_row = $theater_search_api['theater'][$theater_info['tid']];

            foreach($theater_movie_row["movie"] as $mid => $movie_info){

                    foreach($movie_info['time'] as $key => $single_time){

                        $prepare_insert_theater_movie->bindParam(':tid',$theater_info['tid']);
                        $prepare_insert_theater_movie->bindParam(':mid',$mid);
                        $prepare_insert_theater_movie->bindParam(':now',$now);
                        $prepare_insert_theater_movie->bindParam(':play_time',$single_time);

                        $prepare_insert_theater_movie->execute();
                    }
            }

            $theater_movie_db['theater'][$theater_info['tid']] = $theater_movie_row;

        }
        //we have results based on the theater - the data will need to be massaged before we send it back
        else{

            $theater_movie_db['theater'][$theater_info['tid']] = $theater_info;

            foreach($theater_movie_row as $theater_movie_time){
                $theater_movie_db['theater'][$theater_info['tid']]['movie'][$theater_movie_time['mid']]['time'][] = array('time'=>$theater_movie_time['time'],'date'=>$theater_movie_time['date']);
            }

        }


    }




    //MOVIE

    //Only the movies playing in theaters around us right now (using id's pulled from the last group of searches)
    $query_movie_prep = "SELECT DISTINCT mid FROM theater_movie WHERE tid IN ('".implode('\',\'',$theater_id)."')";

    $prepare_select_movie_prep = $dbh->query($query_movie_prep)->fetchAll(PDO::FETCH_ASSOC);

    $query_movie = "SELECT mid, name, image, length, rating, genre, director, actors, synopsis FROM movie WHERE mid = :mid";
    $prepare_select_movie = $dbh->prepare($query_movie);

    //insert theater-movie
    $insert_movie = "INSERT INTO movie (mid, name, image, length, rating, genre, director, actors, synopsis) VALUES
    (:mid,:movie_name, :image, :length, :rating, :genre, :director, :actors, :synopsis);";
    $prepare_insert_movie = $dbh->prepare($insert_movie);

    //for every movie id brought up from our DB search
    foreach($prepare_select_movie_prep as $movie_info){

        //get the info on movies based on the movie id
        $prepare_select_movie->execute(array(':mid'=>$movie_info['mid']));
        $movie_row = $prepare_select_movie->fetchAll(PDO::FETCH_ASSOC);

        //if no results for a movie
        if(empty($movie_row)){

            //do an api search on just that theater, pull theater info and movie times
            $movie_search_api = get_movie_search_api(NULL,$movie_info['mid'],NULL);

            if(!empty($movie_search_api)){

                $movie_row = $movie_search_api['movie'][$movie_info['mid']];

                $prepare_insert_movie->bindParam(':mid',$movie_info['mid']);
                $prepare_insert_movie->bindParam(':movie_name',$movie_search_api['movie'][$movie_info['mid']]['name']);
                $prepare_insert_movie->bindParam(':image',$movie_search_api['movie'][$movie_info['mid']]['image']);
                $prepare_insert_movie->bindParam(':length',$movie_search_api['movie'][$movie_info['mid']]['length']);
                $prepare_insert_movie->bindParam(':rating',$movie_search_api['movie'][$movie_info['mid']]['rating']);
                $prepare_insert_movie->bindParam(':genre',$movie_search_api['movie'][$movie_info['mid']]['genre']);
                $prepare_insert_movie->bindParam(':director',$movie_search_api['movie'][$movie_info['mid']]['director']);
                $prepare_insert_movie->bindParam(':actors',$movie_search_api['movie'][$movie_info['mid']]['actors']);
                $prepare_insert_movie->bindParam(':synopsis',$movie_search_api['movie'][$movie_info['mid']]['synopsis']);

                $prepare_insert_movie->execute();

                $theater_movie_db['movie'][$movie_info['mid']] = $movie_row;
            }
            else{
                //look into why api returns nothing on a movie lookup...
            }
        }
        //we get results back, just need to format the data before sending it back
        else{
            $theater_movie_db['movies'][$movie_info['mid']] = $movie_row[0];
        }
    }
    
    $dbh = null;

    return $theater_movie_db;
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