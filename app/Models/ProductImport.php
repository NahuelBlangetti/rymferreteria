<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImport extends Model
{
    protected $fillable = [
        'user_id',
        'supplier_id',
        'filename',
        'file_path',
        'status',
        'products',
        'error_message',
        'product_count',
        'processed_at',
    ];

    protected $casts = [
        'products'     => 'array',
        'processed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }

    public function isError(): bool
    {
        return $this->status === 'error';
    }

    public function isValidated(): bool
    {
        return $this->status === 'validated';
    }
}
