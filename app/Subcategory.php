<?php

namespace App;

use Illuminate\Database\Eloquent\Model;


class Subcategory extends Model
{
    public function courses(){
      return $this->hasMany('\App\Tutorial', 'subcategory_id');
    }
}
