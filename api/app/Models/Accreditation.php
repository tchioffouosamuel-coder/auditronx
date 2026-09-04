<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Accreditation extends Model
{
    use HasFactory;

    protected $fillable = ['label', 'groupe', 'niveau'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Accréditation à périmètre total (direction/admin). */
    public function estAccesTotal(): bool
    {
        return $this->groupe === '*';
    }
}
