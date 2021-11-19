<?php
namespace App\Models;

class CourierGroupMemberModel extends GroupMemberLayer{
    protected $table="courier_group_member_list";
    protected $groupTable="courier_group_list";
    protected $allowedFields = [
        'group_id',
        'member_id'
        ];
}