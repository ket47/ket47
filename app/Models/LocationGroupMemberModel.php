<?php
namespace App\Models;

class LocationGroupMemberModel extends GroupMemberLayer{
    protected $table="location_group_member_list";
    protected $groupTable="location_group_list";
    protected $allowedFields = [
        'group_id',
        'member_id'
        ];
}