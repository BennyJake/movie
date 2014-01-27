<?php
/**
 * Created by PhpStorm.
 * User: Ben
 * Date: 11/25/13
 * Time: 10:34 PM
 */

//header('Content-Type: application/json');

require_once('googlemovie/GoogleMovieShowtimes.php');

//$dbh = new PDO('mysql:host=localhost;dbname=movie', 'root', '');
$dbh = new PDO('dbname=quiet22_movie;mysql:host=50.87.149.42;port=3306', 'quiet22_movie', 'quiet22');
//error_reporting(0);

if(isset($_POST['action']) && !empty($_POST['action'])) {
    $action = $_POST['action'];
    $location = explode('/',$_POST['location']);
    $param = $_POST['param'];
    switch($action) {
        case 'load-for-area' :

            //SELECT tid, lat, lng, name, address, city, state, phone, ( 3959 * acos( cos( radians(37) ) * cos( radians( lat ) ) * cos( radians( lng ) - radians(-122) ) + sin( radians(37) ) * sin( radians( lat ) ) ) ) AS distance FROM markers HAVING distance < 25 ORDER BY distance LIMIT 0 , 20;
            $query = "SELECT tid, lat, lng, name, address, city, state, phone, 
                ( 3959 * acos( cos( radians(".$location[0].") ) * cos( radians(lat) ) * cos( radians(lng) - radians(".$location[1].") ) + 
                    sin( radians(".$location[0].") ) * sin( radians(lat) ) ) ) AS distance FROM theater HAVING distance < ".$param." ORDER BY distance LIMIT 0 , 20;";


            $movie_search_db = $dbh->query($query)->fetchAll(PDO::FETCH_ASSOC);

            $movie_search_api = new GoogleMovieShowtimes($location[2]); 
            
            $movie_search_api = $movie_search_api->parse();
            
            $found = array();

            foreach($movie_search_db as $pointer=>$movie_db){

                $found[$pointer] = false;

                //use address, city, and state to search single-api-call on theater/movies
                //if we have a tid saved in the db
                
                //if we haven't found the theater yet, try finding it in the single api call
                if(!$found[$pointer]){
                    foreach($movie_search_api['theater'] as $key=>$movie_api){
                        
                        if($movie_api['info'] == $movie_db['address'].', '.$movie_db['city'].', '.$movie_db['state'].' - '.$movie_db['phone']){

                            $movie_search_db[$pointer]['movies'] = $movie_search_api['theater'][$key]['movies'];

                            $found[$pointer] = true;

                            break;
                        }
                    }
                }

                //use tids to search on multi-api-call theater/movies
                //if we have a tid saved in the db
                if(!$found[$pointer]){
                    //if we still haven't found the movie, search via api directly for it
                    if(isset($movie['tid']) && $movie_db['tid'] != ''){

                        $movie_google = new GoogleMovieShowtimes(NULL,NULL,$movie_db['tid']);

                        $movie_google = $movie_google->parse();

                        if($movie_google['theater'][0]['name']!=$movie_db['name']){
                            //updated the db
                        }

                        $movie_search_db[$pointer]['movies'] = $movie_google['theater'][0]['movies'];

                        $found[$pointer] = true;
                    }
                    //else go off of the address, city, and state
                    else if(isset($movie_db['address']) && $movie_db['address'] != '' &&
                            isset($movie_db['city']) && $movie_db['city'] != '' &&
                            isset($movie_db['state']) && $movie_db['state'] != ''){
                      
                      $movie_google = new GoogleMovieShowtimes($movie_db['address'].', '.$movie_db['city'].', '.$movie_db['state']);

                      $movie_google = $movie_google->parse();

                      if($movie_google['theater'][0]['name'] == $movie_db['name']){

                        $movie_search_db[$pointer]['movies'] = $movie_google['theater'][0]['movies'];
                      
                        $found[$pointer] = true;
                      }
                      else{
                        //Not for sure?
                        $movie_search_db[$pointer]['movies'] = "FAIL!";
                      }
                    }
                }
            }

            echo json_encode($movie_search_db);

            //echo $movie_array['theater'][0]['info'];

            break;
        // ...etc...

        case 'load-to-db' :

            $test = new GoogleMovieShowtimes($data);
//var_dump() the response which was parsed into a giant organized associative array.
            $movie_array = $test->parse();

            foreach($movie_array["theater"] as $index => $theater){
                $info = explode(' - ',$theater["info"]);
                $movie_array['theater'][$index]['info'] = $info[0];
                $movie_array['theater'][$index]['phone'] = $info[1];
            }

            try {
                $dbh = new PDO('mysql:host=localhost;dbname=test', 'root', '');
                foreach($dbh->query('SELECT * from FOO') as $row) {
                    print_r($row);
                }
                $dbh = null;
            } catch (PDOException $e) {
                print "Error!: " . $e->getMessage() . "<br/>";
                die();
            }

            break;
    }
}