<?php
namespace App\Models;

class OrderGroupMemberModel extends GroupMemberLayer{
    protected $table="order_group_member_list";
    protected $groupTable="order_group_list";
    protected $allowedFields = [
        'group_id',
        'member_id',
        'created_by'
        ];
}