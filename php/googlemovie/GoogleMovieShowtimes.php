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

    private $resp;
    
	function __construct($location = NULL, $mid = NULL, $tid = NULL) {

        $this->resp = array();

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

        foreach ($this->html->find('#movie_results .header') as $div) {
            $mid = $this->params['mid'];
            $this->resp['movie'][$mid]['mid'] = $mid;
            $this->resp['movie'][$mid]['name'] = iconv("utf-8", "utf-8//ignore",strip_tags($div->find('h2', 0)->innertext));
            $this->resp['movie'][$mid]['info links'] = strip_tags($div->find('.info, .links', 0)->innertext);
            $this->resp['movie'][$mid]['image'] = $div->find('img',0)->getAttribute('src');
            //$this->resp['movie'][$mid]['info'] = strip_tags($div->find('.info', 1)->innertext);

            $actors = $div->find('.info span');
            $j = 0;
            foreach($actors as $actor) {
                $this->resp['movie'][$mid]['actors'][$j] = iconv("utf-8", "utf-8//ignore",strip_tags($actor->innertext));
                $j++;
            }

            //$this->resp['movie'][[$mid]]['stars'] = strip_tags($div->find('nobr', 1)->innertext);
        }

        return $this->resp;
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
				    $this->resp['theater'][$tid]['tid'] = $tid;
				}

			$this->resp['theater'][$tid]['name'] = iconv("utf-8", "utf-8//ignore",strip_tags($div->find('h2 a', 0)->innertext));

			}
			else{
            //assume its a page dedicated to the theater, and a tid was passed in the url
                $tid = $this->params['tid'];
			}

            $parsed_info = $this->parse_theater_address(strip_tags($div->find('.info', 0)->innertext));

            foreach($parsed_info as $key => $data){

                $this->resp['theater'][$tid][$key] = $data;
            }

			$movies = $div->find('.movie');

            foreach($movies as $movie) {

                $url = parse_url("http://www.google.com".$movie->find('a', 0)->getAttribute('href'));
                parse_str(html_entity_decode($url['query']),$url_query);
                $mid = $url_query['mid'];
                
                $this->resp['theater'][$tid]['movies'][$mid]['mid'] = $mid;

                if(!isset($this->resp['movies'][$mid])){

                    $movie_RAW = new GoogleMovieShowtimes(NULL,$mid,NULL);

                    $single_movie = $movie_RAW->parse();

                    $this->resp['movies'][$mid] = $single_movie['movie'][$mid];
                }

                $k = 0;

                foreach ($movie->find('.times span') as $time) {
                    //$skim_time = htmlentities(strip_tags($time->innertext));
                    $time = trim(str_replace(array('8206','nbsp'),'',preg_replace("/[^a-zA-Z0-9]+/", "", strip_tags($time->innertext))));
                    if($time!=''){
                        $this->resp['theater'][$tid]['movies'][$mid]['time'][$k] = $time;
                        $k++;
                    }
                }

			}

		}

		return $this->resp;
	    }

    function parse_theater_address($data){

        $location = array();

        $temp = explode(', ',$data);
        $location['address'] = $temp[0];
        $location['city'] = $temp[1];

        $temp = explode(' - ',$temp[2]);
        $location['state'] = $temp[0];
        if(isset($temp[1])){
            $location['phone'] = $temp[1];
        }
        else{
            $location['phone'] = '';
        }

        return $location;
    }
}

