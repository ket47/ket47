<?php

namespace App\Models;

use CodeIgniter\Model;

class UserConfirmationModel extends Model
{
    protected $table      = 'user_confirmation_list';
    protected $primaryKey = 'user_confirmation_id';

    protected $returnType     = 'array';
    
    
}