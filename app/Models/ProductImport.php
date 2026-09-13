<?php

namespace App\Models;

use App\Filament\Pages\ValidarImport;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImport extends Model
{
    protected $fillable = [
        'user_id',
        'supplier_id',
        'filename',
        'file_path',
        'file_hash',
        'status',
        'products',
        'error_message',
        'product_count',
        'processed_at',
    ];

    protected $casts = [
        'products' => 'array',
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

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /** Elimina del panel las notificaciones de "Revisar" de esta importación. */
    public function dismissReviewNotifications(): void
    {
        $user = $this->user;

        if (! $user) {
            return;
        }

        $url = ValidarImport::getUrl(['id' => $this->id]);

        $user->notifications()
            ->whereJsonContains('data->actions', ['url' => $url])
            ->delete();
    }

    /**
     * Descarta una importación lista para validar: marca cancelled,
     * borra el archivo temporal si quedó y limpia notificaciones.
     */
    public function cancel(): void
    {
        if ($this->status !== 'done') {
            return;
        }

        if ($this->file_path && Storage::disk('local')->exists($this->file_path)) {
            Storage::disk('local')->delete($this->file_path);
        }

        $this->update(['status' => 'cancelled']);
        $this->dismissReviewNotifications();
    }

    public static function staleAfterMinutes(): int
    {
        return max(1, (int) config('services.import.stale_after_minutes', 15));
    }

    /**
     * Marca como error las importaciones pending/processing que superaron el timeout.
     * Cubre cola caída, worker muerto y jobs que nunca llegan a failed().
     */
    public static function expireStale(?int $minutes = null): int
    {
        $minutes ??= static::staleAfterMinutes();
        $cutoff = now()->subMinutes($minutes);

        $stale = static::query()
            ->with('user')
            ->whereIn('status', ['pending', 'processing'])
            ->where('updated_at', '<=', $cutoff)
            ->get();

        $message = 'El análisis no terminó a tiempo. El archivo puede ser demasiado grande, o el procesador no estaba disponible. Volvé a subirlo.';

        foreach ($stale as $import) {
            $import->markAsStuck($message);
        }

        return $stale->count();
    }

    public function markAsStuck(string $message, string $status = 'error'): void
    {
        if (! in_array($this->status, ['pending', 'processing'], true)) {
            return;
        }

        if ($this->file_path && Storage::disk('local')->exists($this->file_path)) {
            Storage::disk('local')->delete($this->file_path);
        }

        $this->update([
            'status' => $status,
            'error_message' => $message,
        ]);

        if ($status === 'error' && $this->user) {
            Notification::make()
                ->title('No se pudo procesar el archivo')
                ->body("\"{$this->filename}\" quedó trabado y se canceló automáticamente. Volvé a subirlo.")
                ->danger()
                ->sendToDatabase($this->user);
        }
    }
}
