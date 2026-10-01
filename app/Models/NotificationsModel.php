<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationsModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_type',
        'url',
        'title',
        'body',
        'image',
        'broadcast_id',
        'payload',
        'is_readed',
        'is_sent'
    ];

    protected $appends = [
        'app_redirection',
        'redirect_to',
        'reference_id',
        'reference_type',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    protected $hidden = ['payload'];

    protected $table = 'notifications';

    /**
     * In-app inbox writes are paused. Call sites stay in place.
     * Set this to true to save notifications again.
     */
    public const IN_APP_NOTIFICATIONS_ENABLED = false;

    protected static function booted(): void
    {
        static::creating(function () {
            if (!self::IN_APP_NOTIFICATIONS_ENABLED) {
                return false;
            }
        });
    }

    public function getRedirectToAttribute()
    {
        return $this->payload['redirect_to'] ?? null;
    }

    public function getReferenceIdAttribute()
    {
        return $this->payload['reference_id'] ?? null;
    }

    public function getReferenceTypeAttribute()
    {
        return $this->payload['reference_type'] ?? null;
    }

    public function broadcast()
    {
        return $this->belongsTo(BroadcastNotificationModel::class, 'broadcast_id', 'id');
    }

    // protected static function boot()
    // {
    //     parent::boot();
    //     static::created(function ($notification) {
    //         FirebasePushNotificationSendJob::dispatch($notification->id);
    //     });
    // }

    public function getAppRedirectionAttribute(): string
    {
        $appRedirection = '';
        if ($this->payload) {
            $appRedirection = $this->payload['redirect_to'];
            if (isset($this->payload['reference_id'])) {
                $appRedirection .= '?id=' . $this->payload['reference_id'];
            }
        }
        return $appRedirection;
    }

    public function news()
    {
        return $this->belongsTo(
            CompanyNewsModel::class,
            'payload->reference_id',
            'id'
        );
    }
}
