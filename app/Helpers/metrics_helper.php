<?php

/**
 * Creates metric_act_list entry
 * @group page
 * @type action name. signin signout pay click
 * @result ok wrn error
 * @description human readable
 * @props may contain target, target_id, owner_ally_ids
 * @debounce in seconds
 */
function madd( string $group, string $type, string $result, $target_id=null, string $description=null, object $props=null, int $debounce=180 ){
    if( $debounce ){
        $clock_id="madd_clock".md5("$group $type $target_id $result $description");
        $clock_val=session()->get($clock_id)??0;
        if( $clock_val && $clock_val>time() ){
            return;
        }
        session()->set($clock_id,time()+$debounce);
    }

    $act=(object)[
        'act_group'=>$group,
        'act_type'=>$type,
        'act_result'=>$result,
        'act_description'=>$description,
        'act_target_id'=>$target_id,
        'append'=>$props->append??0
    ];

    if( $props->act_data??null ){
        $act->act_data=json_encode($props->act_data);
    }
    $MetricActModel=model('MetricActModel');
    $MetricActModel->itemCreate($act);
}