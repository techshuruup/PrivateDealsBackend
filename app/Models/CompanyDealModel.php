<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CompanyDealModel extends Model
{
    protected $table = 'company_deals';

    protected $fillable = [
        'uuid',
        'company_id',
        'created_by_seller_id',
        'created_by_partner_id',
        'available_quantity',
        'share_price',
        'base_price',
        'minimum_qty',
        'processing_fee_percentage',
        'status',
        'deal_type',
        'expired_at',
        'is_hot_deal',
        'is_deleted',
    ];

    protected $hidden = [
        'id',
        'company_id',
        'created_by_seller_id',
        'created_by_partner_id',
        'is_deleted',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'available_quantity' => 'integer',
        'share_price' => 'float',
        'base_price' => 'float',
        'minimum_qty' => 'integer',
        'processing_fee_percentage' => 'float',
        'expired_at' => 'datetime',
        'is_hot_deal' => 'boolean',
        'is_deleted' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($query) {
            $query->uuid = (string) Str::uuid();
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(CompanyModel::class, 'company_id');
    }

    public function createdBySeller(): BelongsTo
    {
        return $this->belongsTo(SellerMasterModel::class, 'created_by_seller_id');
    }

    public function createdByPartner(): BelongsTo
    {
        return $this->belongsTo(PartnerModel::class, 'created_by_partner_id');
    }

    public function scopeNotDeleted($query)
    {
        return $query->where('is_deleted', false);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available')->where('is_deleted', false);
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expired_at')
                ->orWhere('expired_at', '>', now());
        });
    }

    public function scopeHot($query)
    {
        return $query->where('is_hot_deal', true);
    }

    public function scopeBuy($query)
    {
        return $query->where('deal_type', 'buy');
    }

    public function scopeSell($query)
    {
        return $query->where('deal_type', 'sell');
    }

    public function isExpired(): bool
    {
        return $this->expired_at !== null && $this->expired_at->lte(now());
    }
}
