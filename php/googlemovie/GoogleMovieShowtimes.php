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
            //$this->resp['movie'][$mid]['info links'] = strip_tags($div->find('.info, .links', 0)->innertext);
            $this->resp['movie'][$mid]['image'] = $div->find('img',0)->getAttribute('src');
            //$this->resp['movie'][$mid]['info'] = strip_tags($div->find('.info', 1)->innertext);

            $info_string = str_replace('&#8206;','',$div->find('.info',-1)->innertext);
            $info = explode(' - ',substr($info_string,0,strpos($info_string,'<br>')));

            $this->resp['movie'][$mid]['length'] = isset($info[0]) ? $info[0] : "";

            $this->resp['movie'][$mid]['rating'] = isset($info[1]) ? $info[1] : "";

            $this->resp['movie'][$mid]['genre'] = isset($info[2]) ? $info[2] : "";

            $this->resp['movie'][$mid]['director'] = htmlspecialchars(strip_tags($div->find('.info span[itemprop=director]',0)),ENT_QUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED | ENT_HTML5);

            $actors = $div->find('.info span[itemprop=actors]');

            $actor_array = array();
            foreach($actors as $actor) {
                //$this->resp['movie'][$mid]['actors'][$j] = iconv("utf-8", "utf-8//ignore",strip_tags($actor->innertext));
                $actor_array[] = htmlspecialchars(strip_tags($actor->innertext),ENT_QUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED | ENT_HTML5);
            }

            $this->resp['movie'][$mid]['actors'] = implode(', ',$actor_array);

            $this->resp['movie'][$mid]['synopsis'] = str_replace(array('&laquo; less','more &raquo;'),'',strip_tags($div->find('.syn',0)->innertext));
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
                
                $this->resp['theater'][$tid]['movie'][$mid]['mid'] = $mid;

                /*if(!isset($this->resp['movie'][$mid])){

                    $movie_RAW = new GoogleMovieShowtimes(NULL,$mid,NULL);

                    $single_movie = $movie_RAW->parse();

                    $this->resp['movie'][$mid] = $single_movie['movie'][$mid];
                }*/

				$time_array = array();
                foreach ($movie->find('.times span') as $time) {
                    
					$time = trim(str_replace(array('&',';','#','8206','nbsp'),'',strip_tags($time->innertext)));
                    
					if($time!=''){
                        $time_array[] = $time;
                    }
                }
				
				$period = '';
					
				foreach(array_reverse($time_array) as $key => $time){
						if(substr($time,-2)=='pm'){
							$period = 'pm';
							$time_array[$key] = $this->time_pm(substr($time,0,-2));
						}
						elseif(substr($time,-2)=='am'){
							$period = 'am';
							$time_array[$key] = $this->time_am(substr($time,0,-2));							
						}
						else{							
							if($period=='pm'){
								$time_array[$key] = $this->time_pm($time);
							}
							elseif($period=='am'){
								$time_array[$key] = $this->time_am($time);
							}
						}
				}
                    
				$this->resp['theater'][$tid]['movie'][$mid]['time'] = array_reverse($time_array);

			}

		}

		return $this->resp;
	    }
		
	function time_pm($time){
		$time_sep = explode(':',$time);
		return $time_sep[0]!='12' ? ($time_sep[0]+12).':'.$time_sep[1] : $time_sep[0].':'.$time_sep[1];	
	}
	
	function time_am($time){
		
		$time_sep = explode(':',$time);
		//if the hour is less than 10, add a leading 0
		return $time_sep[0]<10 ? '0'.$time_sep[0].':'.$time_sep[1]:$time_sep[0].':'.$time_sep[1];
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

