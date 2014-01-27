<?php
/**
 * Created by PhpStorm.
 * User: Ben
 * Date: 1/13/14
 * Time: 9:31 PM
 */

namespace movietheater;


class movie {

    private $tid;

    private $time_array;

    private $var_array;

    public function __init($mid=NULL){

        if($mid){
            $this->setMid($mid);
        }

        $this->time_array = array();

        $this->var_array = array();
    }

    public function addTime(time $time){
        $this->time_array[] = $time;
    }

    public function getTime($mid=NULL){
        if($mid){
            return $this->time_array[$mid];
        }
        else{
            return $this->time_array;
        }
    }

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
    public function setMid($mid)
    {
        $this->mid = $mid;
    }

    /**
     * @return mixed
     */
    public function getMid()
    {
        return $this->mid;
    }

} 