<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un abonné a cliqué « ✓ Fait » sous une pratique. */
class PratiqueFaite extends Model
{
    protected $table = 'pratiques_faites';

    protected $fillable = ['pratique_telegram_id', 'telegram_user_id', 'inscription_id', 'contact_id', 'fait_le'];

    protected function casts(): array
    {
        return ['fait_le' => 'datetime'];
    }

    public function pratique(): BelongsTo
    {
        return $this->belongsTo(PratiqueTelegram::class, 'pratique_telegram_id');
    }
}
