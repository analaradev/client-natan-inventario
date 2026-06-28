<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

trait Auditable
{
    /**
     * Boot the Auditable trait for a model.
     */
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            static::logActivity($model, 'created', null, $model->getAttributes());
        });

        static::updated(function (Model $model) {
            $changes = $model->getChanges();
            
            // Exclude common timestamps from audit comparison
            unset($changes['updated_at']);
            
            if (empty($changes)) {
                return;
            }

            $old = [];
            $new = [];
            foreach ($changes as $key => $newValue) {
                $old[$key] = $model->getOriginal($key);
                $new[$key] = $newValue;
            }

            static::logActivity($model, 'updated', $old, $new);
        });

        static::deleted(function (Model $model) {
            static::logActivity($model, 'deleted', $model->getAttributes(), null);
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model) {
                static::logActivity($model, 'restored', null, $model->getAttributes());
            });
        }
    }

    /**
     * Write record to activity_logs table.
     */
    protected static function logActivity(Model $model, string $action, ?array $old, ?array $new): void
    {
        try {
            // Determine active user or fall back to system
            $user = session('username') ?? auth()->user()->name ?? 'Sistema';

            ActivityLog::create([
                'user' => $user,
                'action' => $action,
                'auditable_type' => get_class($model),
                'auditable_id' => $model->getKey(),
                'old_values' => $old,
                'new_values' => $new,
            ]);
        } catch (\Exception $e) {
            // Log failure to Laravel log without throwing exception to keep main business flow uninterrupted
            Log::error("Failed to write audit log for " . get_class($model) . " ID " . $model->getKey() . ": " . $e->getMessage());
        }
    }
}
