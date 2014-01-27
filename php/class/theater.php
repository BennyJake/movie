<?php
/**
 * Created by PhpStorm.
 * User: Ben
 * Date: 1/13/14
 * Time: 9:30 PM
 */

namespace movietheater;


class theater {

        private $tid;

        private $movie_array;

        private $var_array;

    public function __init($tid=NULL){

        if($tid){
            $this->setTid($tid);
        }

        $this->movie_array = array();

        $this->var_array = array();
    }

    public function addMovie($mid,movie $movie){
        $this->movie_array[$mid] = $movie;
    }

    public function getMovie($mid=NULL){
        if($mid){
            return $this->movie_array[$mid];
        }
        else{
            return $this->movie_array;
        }
    }

    /**
     * @param mixed $tid
     */
    public function setVarVal($var,$val)
    {
        $this->var_array[$var] = $val;
    }

    public function setVarValList(array $array)
    {
        foreach($array as $var=>$val){
            setVarVal($var,$val);
        }
    }

    /**
     * @param mixed $tid
     */
    public function getVarVal($var)
    {
        if(isset($this->var_array[$var])){

            return $this->var_array[$var];
        }
        else{
            return NULL;
        }
    }

    /**
     * @param mixed $tid
     */
    public function setTid($tid)
    {
        $this->tid = $tid;
    }

    /**
     * @return mixed
     */
    public function getTid()
    {
        return $this->tid;
    }


} 