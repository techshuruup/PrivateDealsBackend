<?php

namespace App\Models;

use App\Enums\GenderEnum;
use App\Helpers\UtillsHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class InvestorModel extends Authenticatable
{
    use HasFactory, HasApiTokens;



    protected $fillable = [
        'uuid',
        'referral_code',
        'referal_deep_link',
        'partner_id',
        'referred_by_investor_id',
        'referral_used_at',
        'parent_investor_id',
        'family_relation_id',
        'investor_type',
        'name',
        'mobile_country_code',
        'mobile_number',
        'email',
        'address',
        'city_id',
        'state_id',
        'country_id',
        'pincode',
        'gender',
        'profile_photo',
        'password',
        'profile_visibility',
        'registration_step',
        'kyc_status',
        'primary_kyc_status',
        'aif_status',
        'preipo_kyc_status',
        'ekyc_kyc_status',
        'manual_kyc_status',
        'aadhar_verified_type',
        'is_fake_investor',
        'is_verified_mobile',
        'is_verified_email',
        'ask_password_change',
        'is_active',
        'is_blocked',
        'is_deleted',
        'is_self',
        'is_demo',
        'is_primary_access',
        'is_secondary_access',
        'is_preipo_access',
        'is_event_investor',
        'created_by',
        'updated_by'
    ];

    protected $hidden = [
        'password'
    ];

    protected $table = 'investor';

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($query) {
            $query->uuid = (string) Str::uuid();
        });
    }

    // protected static function booted()
    // {
    //     static::addGlobalScope('hide_investors', function (Builder $builder) {

    //         $hiddenPartnerIds = UtillsHelper::hiddenPartnerIds();
    //         $hiddenInvestorIds = UtillsHelper::hiddenInvestorIds();

    //         if (!empty($hiddenPartnerIds)) {
    //             $builder->whereNotIn('partner_id', $hiddenPartnerIds);
    //         }

    //         if (!empty($hiddenInvestorIds)) {
    //             $builder->whereNotIn('id', $hiddenInvestorIds);
    //         }
    //     });
    // }

    public function getKycDataAttribute()
    {
        return [
            'demat' => $this->dematAccount,
            'pan' => $this->newPan,
            'aadhar' => $this->newAadhar,
            'bank' => $this->newBankAccount,
            'demat_manual' => $this->dematManual
                ? $this->dematManual->only(['id', 'status', 'reason'])
                : null,
        ];
    }

    public function getProfilePhotoAttribute($value)
    {
        if ($value) {
            return $value; // Return uploaded photo if available
        }

        // Return gender-based default image
        if ($this->gender === GenderEnum::male->value) {
            return MasterAvtarModel::where('display_order', 1)->where('is_deleted', '0')->orderby('id', 'asc')->first()->avtar_img ?? null;
        } else if ($this->gender === GenderEnum::female->value) {
            return MasterAvtarModel::where('display_order', 2)->where('is_deleted', '0')->orderby('id', 'asc')->first()->avtar_img ?? null;
        } else {
            return MasterAvtarModel::where('display_order', 3)->where('is_deleted', '0')->orderby('id', 'asc')->first()->avtar_img ?? null;
        }
    }

    function portfolio(): HasMany
    {
        return $this->hasMany(PortfolioModel::class, 'investor_id');
    }

    function pportfolio(): HasMany
    {
        return $this->hasMany(PortfolioPreIpoModel::class, 'investor_id');
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

    function admin(): BelongsTo
    {
        return $this->belongsTo(UserAdminModel::class, 'created_by');
    }

    public function AdminTrackingRecords(): BelongsTo
    {
        return $this->belongsTo(AdminTrackingRecordsModel::class, 'partner_id', 'id');
    }

    public function favouriteStartups()
    {
        return $this->belongsToMany(InvestorFavStartupModel::class, 'investor_id', 'id');
    }

    public function bankDetails(): HasOne
    {
        return $this->hasOne(BankDetailsModel::class, 'user_id', 'id')->latest();
    }

    public function newBankAccount()
    {
        return $this->hasOne(UserBankAccountModel::class, 'user_id')
            ->where('user_type', self::class);
    }
    public function dematAccount(): HasOne
    {
        return $this->hasOne(InvestorDematAccountModel::class, 'investor_id', 'id')->latest();
    }

    public function panDetails(): HasOne
    {
        return $this->hasOne(InvestorPanDetailsModel::class, 'investor_id', 'id')->latest();
    }

    public function aadharDetails(): HasOne
    {
        return $this->hasOne(InvestorKycModel::class, 'investor_id', 'id')->latest();
    }


    public function kyc(): HasOne
    {
        return $this->hasOne(InvestorKycModel::class, 'investor_id', 'id')->latest();
    }

    public function newPan(): HasOne
    {
        return $this->hasOne(InvestorKycPanModel::class, 'investor_id', 'id')->latest();
    }

    public function newAadhar(): HasOne
    {
        return $this->hasOne(InvestorKycAadharModel::class, 'investor_id', 'id')->latest();
    }

    public function aif(): HasOne
    {
        return $this->hasOne(InvestorAifKycModel::class, 'investor_id')->latest();
    }

    public function investor_detail(): HasOne
    {
        return $this->hasOne(InvestorDetailsModel::class, 'investor_id', 'id');
    }

    // public function investor_company_detail(): HasOne
    // {
    //     return $this->hasOne(InvestorCompanyDetailsModel::class, 'investor_id', 'id');
    // }

    public function primaryTransactions(): HasMany
    {
        return $this->hasMany(PrimaryTransactionModel::class, 'investor_id');
    }

    public function secondaryTransactions()
    {
        return $this->hasMany(SecondaryTransactionModel::class, 'buyer_id');
    }

    public function perIpoTransactions()
    {
        return $this->hasMany(PreIpoModel::class, 'investor_id');
    }

    public function relation(): BelongsTo
    {
        return $this->belongsTo(MasterFamilyRelationsModel::class, 'family_relation_id', 'id');
    }

    function partner(): BelongsTo
    {
        return $this->belongsTo(PartnerModel::class, 'partner_id');
    }

    public function referralsGiven(): HasMany
    {
        return $this->hasMany(InvestorReferralModel::class, 'referrer_investor_id');
    }

    public function referralReceived(): HasOne
    {
        return $this->hasOne(InvestorReferralModel::class, 'referred_investor_id');
    }

    public function referredByInvestor()
    {
        return $this->belongsTo(InvestorModel::class, 'referred_by_investor_id');
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(InvestorCouponModel::class, 'investor_id');
    }

    public function companyViews(): HasMany
    {
        return $this->hasMany(InvestorCompanyViewModel::class, 'investor_id');
    }

    public function consultancySlots(): HasMany
    {
        return $this->hasMany(InvestorConsultancySlotModel::class, 'investor_id');
    }
    public function dematManual()
    {
        return $this->hasOne(DematManualModel::class, 'investor_id', 'id');
    }
}
