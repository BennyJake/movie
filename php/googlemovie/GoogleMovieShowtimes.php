<?php
/**
 * Google Movie Showtimes Parser
 * 
 * This script provides a PHP class that can be used to parse Google Movie
 * Showtimes (www.google.com/movies) pages into sensible associative arrays.
 *
 * This script makes use of PHP Simple HTML DOM Parser:
 * http://simplehtmldom.sourceforge.net/
 * At the time when this script was being written, version 1.5 of PHP Simple
 * HTML DOM Parser was used. Therefore, the same version has been included
 * in the project repository just in case this script does not work well
 * with your latest copy of PHP Simple HTML DOM Parser.
 *
 * You should get your latest copy of PHP Simple HTML DOM Parser from
 * (http://sourceforge.net/projects/simplehtmldom/files/) and unzip into  
 * PROJECT_ROOT/simple_html_dom/. Having said that, this script should be in
 * PROJECT_ROOT/
 *
 * 
 * @author Vaidik Kapoor <kapoor.vaidik@gmail.com>
 * @version 0.1
 */

require_once 'simple_html_dom/simple_html_dom.php';

define("BASE_PATH", "http://www.google.com/movies");

class GoogleMovieShowtimes {
	function __construct($location = NULL, $mid = NULL, $tid = NULL) {
		$this->params = array(
			'near' => $location,
			'mid' => $mid,
			'tid' => $tid,
		);

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, BASE_PATH . '?' . http_build_query($this->params));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($curl, CURLINFO_HEADER_OUT, 1);

		$this->response = array();
		$this->response['body'] = curl_exec($curl);
		$this->response['code'] = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$this->response['headers'] = curl_getinfo($curl, CURLINFO_HEADER_OUT);

		curl_close($curl);

		if ($this->response['code'] == 200) {
			$this->html = str_get_html($this->response['body']);
		}
	}

	function check() {
		if ($this->response['code'] == 200) {
			return TRUE;
		}

		return FALSE;
	}

    function parse() {
        if ($this->params['mid']) {

            $return = $this->parse_movie();
        }
        else{

            $return = $this->parse_theater();
        }
            return $return;

    }

    function parse_movie(){

        $resp = array();

        foreach ($this->html->find('#movie_results .movie') as $div) {
            $mid = $this->params['mid'];
            $resp['movie'][$mid]['mid'] = $mid;
            $resp['movie'][$mid]['name'] = iconv("utf-8", "utf-8//ignore",strip_tags($div->find('h2', 0)->innertext));
            $resp['movie'][$mid]['info links'] = strip_tags($div->find('.info, .links', 0)->innertext);
            //$resp['movie'][$mid]['info'] = strip_tags($div->find('.info', 1)->innertext);

            $actors = $div->find('.info span');
            $j = 0;
            foreach($actors as $actor) {
                $resp['movie'][$mid]['actors'][$j] = iconv("utf-8", "utf-8//ignore",strip_tags($actor->innertext));
                $j++;
            }

            //$resp['movie'][[$mid]]['stars'] = strip_tags($div->find('nobr', 1)->innertext);
        }

        return $resp;
    }

    function parse_theater(){
		foreach ($this->html->find('#movie_results .theater') as $div) {

			$h2_tag = $div->find('h2',0);

			if($h2_tag->find('a',0)){

				$a_tag = $h2_tag->find('a',0);
				
				$temp_var = explode('?',strip_tags($a_tag->getAttribute('href')));

				parse_str(html_entity_decode($temp_var[1]),$link_array);
                
				if(isset($link_array['tid'])){
                
                    $tid = $link_array['tid'];
				    $resp['theater'][$tid]['tid'] = $tid;
				}
                else{
                    //TODO what if theater doesn't have an id?
                }
				

			$resp['theater'][$tid]['name'] = iconv("utf-8", "utf-8//ignore",strip_tags($div->find('h2 a', 0)->innertext));

			}
			else{
            //TODO what if theater name doesn't have a link?
			}

			$resp['theater'][$tid]['info'] = strip_tags($div->find('.info', 0)->innertext);

			$movies = $div->find('.movie');

            foreach($movies as $movie) {

                $url = parse_url("http://www.google.com".$movie->find('a', 0)->getAttribute('href'));
                parse_str(html_entity_decode($url['query']),$url_query);
                $mid = $url_query['mid'];
                
                $resp['theater'][$tid]['movies'][$mid]['mid'] = $mid;

                if(!isset($resp['movies'][$mid])){

                    $movie_RAW = new GoogleMovieShowtimes(NULL,$mid,NULL);

                    $single_movie = $movie_RAW->parse();

                    $resp['movies'][$mid] = $single_movie['movie'][$mid];
                }

                $k = 0;

                foreach ($movie->find('.times span') as $time) {
                    $resp['theater'][$tid]['movies'][$mid]['time'][$k] = strip_tags($time->innertext);
                    $k++;
                }

			}

		}

		return $resp;
	    }
}

