<?php
namespace App\Models;

class AccountGroupModel extends GroupLayer{
    protected $table      = 'transaction_account_list';
    protected $validationRules    = [
        'group_name'     => 'is_unique[transaction_account_list.group_name]'
    ];
    protected $validationMessages = [
        'group_name'        => [
            'is_unique' => 'Group name already exists'
        ]
    ];
}