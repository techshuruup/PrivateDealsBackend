<?php

namespace App\Models;

use App\Helpers\UtillsHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class PartnerModel extends Authenticatable
{
    use HasFactory, HasApiTokens;

    function investorCounter(): HasMany
    {
        return $this->hasMany(InvestorModel::class, 'partner_id');
    }

    function investor(): HasMany
    {
        return $this->hasMany(InvestorModel::class, 'partner_id');
    }

    function childPartner(): HasMany
    {
        return $this->hasMany(PartnerModel::class, 'parent_id');
    }

    public function getAllSubPartnerIds()
    {
        return $this->childPartner()->pluck('id')->toArray();
    }

    // protected static function booted()
    // {
    //     static::addGlobalScope('hide_partners', function (Builder $builder) {

    //         $hiddenPartnerIds = UtillsHelper::hiddenPartnerIds();

    //         if (!empty($hiddenPartnerIds)) {
    //             $builder->whereNotIn('id', $hiddenPartnerIds);
    //         }
    //     });
    // }

    protected $fillable = [
        'uuid',
        'type',
        'parent_type',
        'parent_id',
        'name',
        'mobile_country_code',
        'mobile_number',
        'email',
        'address',
        'commission',
        'password',
        'city_id',
        'state_id',
        'country_id',
        'pincode',
        'gender',
        'profile_photo',
        'is_verified_mobile',
        'is_verified_email',
        'ask_password_change',
        'is_blocked',
        'is_deleted',
        'is_demo',
        'created_by',
        'updated_by',
        'is_primary_access',
        'is_secondary_access',
        'is_preipo_access'
    ];

    protected $table = 'partner';


    protected $hidden = [
        'password'
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($query) {
            $query->uuid = (string) Str::uuid();
        });
    }

    function city(): BelongsTo
    {
        return $this->belongsTo(MasterCityModel::class, 'city_id', 'id');
    }

    function country(): BelongsTo
    {
        return $this->belongsTo(MasterCountryModel::class, 'country_id', 'id');
    }

    function state(): BelongsTo
    {
        return $this->belongsTo(MasterStateModel::class, 'state_id', 'id');
    }
}
