<?php
namespace App\Models;

class CourierGroupModel extends GroupLayer{
    protected $table      = 'courier_group_list';
    protected $validationRules    = [
        'group_name'     => 'is_unique[courier_group_list.group_name]'
    ];
    protected $validationMessages = [
        'group_name'        => [
            'is_unique' => 'Status name already exists'
        ]
    ];
    protected $useTimestamps=false;
}