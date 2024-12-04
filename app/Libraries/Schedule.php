<?php
namespace App\Libraries;
class Schedule{
    private int $midnight;
    private int $dayfull=24*60*60;//duration of day in sec
    private array $timetable=[];

    function __construct(){
        $this->midnight=strtotime('midnight');
    }
    private function indexGet( int $time ):string{
        $days_from_today=floor( ($time-$this->midnight)/$this->dayfull );
        return "d$days_from_today";
    }

    /**
     * Converts day count from the midnight and hour to time 
     */
    public function timeGet( int $day, int $hour ){
        $midnight_correction=0;
        if($hour==24){//make it 23:59:59
            $midnight_correction=-1;
        }
        return $this->midnight+($day*24+$hour)*60*60+$midnight_correction;
    }
    public function begin( int $time, string $purge=null ){
        $index=$this->indexGet($time);
        $current=$this->timetable[$index]['begin']??null;
        $this->timetable[$index]['begin']=$current?max($time,$current):$time;
        if( $purge ){
            $this->purgeIndex( $index, $purge );
        }
    }
    public function beginHour( int $day, int $hour ){
        $this->begin($this->timeGet( $day, $hour ));
    }
    public function end( int $time, string $purge=null ){
        $index=$this->indexGet($time);
        $current=$this->timetable[$index]['end']??null;
        $this->timetable[$index]['end']=$current?min($time,$current):$time;
        if( $purge ){
            $this->purgeIndex( $index, $purge );
        }
    }
    public function endHour( int $day, int $hour ){
        $this->end($this->timeGet( $day, $hour ));
    }
    // public function purge( int $time, string $mode='before' ){
    //     $index=$this->indexGet($time);
    // }
    public function purgeIndex( string $index, string $mode ){
        foreach($this->timetable as $i=>$day){
            if( $mode=='before' && $i<$index || $mode=='after' && $i>$index || $mode=='same' && $i==$index ){
                unset($this->timetable[$i]);
            }
        }
    }
    public function offset( int $begin_offset=0, int $end_offset=0 ){
        foreach($this->timetable as $i=>$day){
            if( empty($this->timetable[$i]['begin']) ){
                continue;
            }
            $this->timetable[$i]['begin']+=$begin_offset;
        }
    }

    public function firstGet():?array{
        foreach($this->timetable as $day){
            if($day['begin']<$day['end']){
                return $day;
            }
        }
        return null;
    }
    public function lastGet():?array{
        foreach(array_reverse($this->timetable) as $day){
            if($day['begin']<$day['end']){
                return $day;
            }
        }
        return null;
    }
    public function tableGet( int $roundto=null ):array{
        $schedule=[];
        foreach($this->timetable as $day){
            foreach($day as $boundary=>$time){
                $time=$roundto?round($time/$roundto)*$roundto:$time;
                list($date,$hourmin)=explode(',',date("Y-m-d,H:i",$time));
                $schedule[$date][$boundary]=$hourmin;
            }
        }
        return $schedule;
    }

    public $swatchNearest=null;
    public function swatchGet( int $roundto=900 ){
        $schedule=[];
        foreach($this->timetable as $day){
            if( empty($day['begin']) || empty($day['end']) || $day['begin']>=$day['end']){//
                continue;
            }
            $begin=$roundto?round($day['begin']/$roundto)*$roundto:$day['begin'];
            list($date,$hour,$min)=explode(',',date("Y-m-d,H,i",$begin));
            $this->swatchNearest??="$date $hour:$min";
            while( $begin<$day['end'] ){
                while( $min<60 ){
                    $schedule[$date]["h_{$hour}"][]=(int)$min;
                    $min+=$roundto/60;
                    $begin+=$roundto;
                }
                $min=0;
                $hour++;
            }
        }
        return $schedule;
    }
    public function timetableGet(){
        foreach($this->timetable as $i=>$day){
            if( isset($day['begin']) && isset($day['end']) && $day['begin']>=$day['end']){
                unset($this->timetable[$i]);
            }
        }
        return $this->timetable;
    }
    public function timetableSet( array $tt ){
        $this->timetable=$tt;
    }

}