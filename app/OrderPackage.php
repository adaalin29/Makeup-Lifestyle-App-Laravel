<?php

namespace App;

use Illuminate\Database\Eloquent\Model;


class OrderPackage extends Model
{
    public function orders(){
        return $this->belongsTo(Order::class,'order_id','id');
    }
    public function packages(){
        return $this->belongsTo(Package::class,'package_id','id');
    }
}
