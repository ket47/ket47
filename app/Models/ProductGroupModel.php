<?php
namespace App\Models;

class ProductGroupModel extends GroupLayer{
    protected $table      = 'product_group_list';
    protected $allowedFields = [
        'group_parent_id',
        'group_name',
        'group_type',
        'group_path_id',
        'group_description',
        ];
    protected $validationRules    = [
        'group_name'     => 'is_unique[product_group_list.group_name]'
    ];
    protected $validationMessages = [
        'group_name'        => [
            'is_unique' => 'Group name already exists'
        ]
    ];
}