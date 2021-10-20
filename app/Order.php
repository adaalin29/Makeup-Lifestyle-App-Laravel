<?php

namespace App;

use Illuminate\Database\Eloquent\Model;


class Order extends Model
{
    public function packages () {
        return $this->belongsToMany(Package::class, 'order_packages','order_id','package_id');
    }
}
