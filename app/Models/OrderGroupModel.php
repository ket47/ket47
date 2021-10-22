<?php
namespace App\Models;

class OrderGroupModel extends GroupLayer{
    protected $table      = 'order_group_list';
    protected $validationRules    = [
        'group_name'     => 'is_unique[order_group_list.group_name]'
    ];
    protected $validationMessages = [
        'group_name'        => [
            'is_unique' => 'Group name already exists'
        ]
    ];
}