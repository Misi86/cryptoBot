<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class buy_prices extends Model
{
    protected $table = 'buy_prices';
    protected $fillable = [
        'asset','amount'
    ];

}
