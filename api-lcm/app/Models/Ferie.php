<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ferie extends Model
{
    use HasFactory;

    protected $fillable = ['date', 'libelle', 'description'];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];
}
