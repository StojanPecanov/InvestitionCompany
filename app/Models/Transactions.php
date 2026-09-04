<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Client;

class Transactions extends Model
{
    protected $fillable = [
        'client_id',
        'type',
        'amount',
        'instrument',
        'quantity',
        'price',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
