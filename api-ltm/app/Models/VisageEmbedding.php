<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisageEmbedding extends Model
{
    use HasFactory;

    protected $table = 'visages_embeddings';

    protected $fillable = ['enseignant_id', 'device_id', 'embedding', 'enrolled_at', 'revoked_at'];

    protected $casts = [
        // Chiffré au repos (§7 — données biométriques sensibles)
        'embedding' => 'encrypted:array',
        'enrolled_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
