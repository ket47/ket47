<?php

namespace App\Models;

use CodeIgniter\Model;

class UserVerificationModel extends Model
{
    protected $table      = 'user_confirmation_list';
    protected $primaryKey = 'user_confirmation_id';

    
    protected $allowedFields = [
        'user_id',
        'user_type',
        'user_value'
        ];
    
}