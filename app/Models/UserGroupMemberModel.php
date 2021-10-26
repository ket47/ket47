<?php
namespace App\Models;

class UserGroupMemberModel extends GroupMemberLayer{
    protected $table="user_group_member_list";
    protected $groupTable="user_group_list";
}