<?php

namespace App\Models;

use App\Enums\TableStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Table extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'tables';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'number',
        'section',
        'capacity',
        'status',
        'qr_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TableStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Table $table): void {
            if (! $table->qr_token) {
                $table->qr_token = Str::random(32);
            }
        });
    }
}
