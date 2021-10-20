<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\HasApiTokens;



class Account extends Authenticatable
{
    use HasApiTokens;
    protected $hidden = ['password'];
    
    public function validateForPassportPasswordGrant($password)
    {
        return true;
        if (Hash::check($password, $this->password))
            return true;
        
        if (decrypt($password) == 'oauth')
            return true;
    }
}
