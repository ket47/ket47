<?php
namespace App\Models;

class LocationGroupModel extends GroupLayer{
    protected $table      = 'location_group_list';
    protected $validationRules    = [
        'group_name'     => 'is_unique[location_group_list.group_name]'
    ];
    protected $validationMessages = [
        'group_name'        => [
            'is_unique' => 'Location type name already exists'
        ]
    ];
    protected $useTimestamps=false;
}