<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;


class Package extends Model
{

    use Searchable;

    public $asYouType = false;

    public function tutorials () {
        return $this->belongsToMany(Tutorial::class, 'package_tutorials','package_id','tutorial_id');
    }

      public function toSearchableArray()
      {
          return $this->only([
            'id',
            'name',
            'description',
          ]);

      }
}
