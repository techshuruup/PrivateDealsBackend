<?php

namespace App\Models;

use App\Enums\CompanyApprovalStatusEnum;
use App\Helpers\CommonHelper;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class CompanyModel extends Model
{
    protected $table = 'company';
    private ?CompanySharePriceModel $latestSharePriceCache = null;
    protected $fillable = [
        'logo',
        'sector_id',
        'brand_name',
        'company_name',
        'keywords',
        'negative_keywords',
        'alternative_names',
        'about',
        'uuid',
        'is_deleted',
        'cin',
        'min_investment_type',
        'min_investment_amount',
        'final_min_investment_amount',
        'commission',
        'processing_fee_percentage',
        'category',
        'type',
        'is_drhp',
        'is_trending',
        'bg_color_code',
        'status',
        'approval_status',
        'submitted_by_seller_id',
        'submitted_by_partner_id',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'list_order',
        'share_price',
        'distributer_price',
        'base_price',
        'price_updated_today',
        'is_price_updated_today',
        'last_year_share_price',
        'current_split_ratio',
        'last_split_date',
        'has_active_split',
        'is_grab_opportunity_enabled',
        'is_free_processing_fee',
        'slug'
    ];

    protected $hidden = [
        'is_deleted',
        // 'uuid',
        'sector_id',
        'updated_at'
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($query) {
            $query->uuid = (string) Str::uuid();
            if (empty($query->approval_status)) {
                $query->approval_status = CompanyApprovalStatusEnum::pending->value;
            }
        });
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('approval_status', CompanyApprovalStatusEnum::approved->value);
    }

    public function submittedBySeller(): BelongsTo
    {
        return $this->belongsTo(SellerMasterModel::class, 'submitted_by_seller_id');
    }

    public function submittedByPartner(): BelongsTo
    {
        return $this->belongsTo(PartnerModel::class, 'submitted_by_partner_id');
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(MasterSectorsModel::class, 'sector_id');
    }

    public function customData(): HasMany
    {
        return $this->hasMany(CompanyCustomDataModel::class, 'company_id');
    }

    public function sharePrices(): HasMany
    {
        return $this->hasMany(CompanySharePriceModel::class, 'company_id');
    }

    public function sellerSharePrices(): HasMany
    {
        return $this->hasMany(SellerCompanySharePriceModel::class, 'company_id');
    }

    /**
     * Copy latest company_share_price history onto denormalized company price columns.
     * If no history remains, current prices and 52-week high/low are set to zero.
     */
    public function syncPricesFromHistory(): void
    {
        $latestPrice = $this->sharePrices()
            ->orderByDesc('id')
            ->first();

        if (!$latestPrice) {
            $this->share_price = 0;
            $this->distributer_price = 0;
            $this->base_price = 0;
            $this->price_updated_today = 0;
            $this->last_year_share_price = 0;
            $this->save();

            if ($this->fundamentals) {
                $this->fundamentals->update([
                    'fifty_two_week_high' => 0,
                    'fifty_two_week_low' => 0,
                ]);
            }

            return;
        }

        $this->share_price = $latestPrice->price;
        $this->distributer_price = $latestPrice->distributer_price;
        $this->base_price = $latestPrice->base_price;
        $this->price_updated_today = $latestPrice->created_at->isToday();

        $oneYearAgo = now()->subYear();
        $lastYearPrice = $this->sharePrices()
            ->where('date', '<=', $oneYearAgo)
            ->orderByDesc('date')
            ->first();

        if (!$lastYearPrice) {
            $lastYearPrice = $this->sharePrices()
                ->orderBy('date', 'asc')
                ->first();
        }

        $this->last_year_share_price = $lastYearPrice?->price ?? 0.00;
        $this->save();

        $share_price = (float) $this->share_price;
        if ($share_price > 0 && $this->final_min_investment_amount > 0) {
            $minQuantity = ceil($this->final_min_investment_amount / $share_price);
            $this->min_investment_type = 'Quantity';
            $this->min_investment_amount = $minQuantity;
            $this->save();
            if ($this->fundamentals) {
                $this->fundamentals->lot_size = $minQuantity;
                $this->fundamentals->save();
            }
        }

        if ($this->fundamentals) {
            $high = $this->sharePrices()
                ->where('date', '>=', now()->subWeeks(52))
                ->max('price') ?? 0.00;

            $low = $this->sharePrices()
                ->where('date', '>=', now()->subWeeks(52))
                ->min('price') ?? 0.00;

            $this->fundamentals->update([
                'fifty_two_week_high' => round((float) $high, 2),
                'fifty_two_week_low' => round((float) $low, 2),
            ]);
        }
    }

    public function shareHolders(): HasMany
    {
        return $this->hasMany(CompanyShareHolderModel::class, 'company_id');
    }

    public function fundamentals(): HasOne
    {
        return $this->hasOne(CompanyFundamentalsModel::class, 'company_id');
    }

    public function promoters(): HasMany
    {
        return $this->hasMany(CompanyPromotersModel::class, 'company_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CompanyEventsModel::class, 'company_id');
    }

    public function news(): HasMany
    {
        return $this->hasMany(CompanyNewsModel::class, 'company_id');
    }

    public function peerratio(): HasMany
    {
        return $this->hasMany(CompanyPeerRatioModel::class, 'company_id');
    }

    public function coupons()
    {
        return $this->belongsToMany(MasterCouponModel::class, 'coupon_company', 'company_id', 'coupon_id');
    }

    // protected $appends = ['is_favorite', 'share_price', 'distributer_price', 'base_price', 'price_updated_today', 'last_year_share_price'];
    protected $appends = ['is_favorite'];






    public function getIsFavoriteAttribute()
    {
        $userId = CommonHelper::getUserFromSanctum();
        if ($userId) {
            return InvestorFavouriteCompanyModel::where('investor_id', $userId)->where('company_id', $this->id)->exists();
        }
        return false;
    }

    /**
     * Get the latest base price from company_share_price table.
     * 
     * @return float
     */
    public function getLatestBasePriceAttribute(): float
    {
        if ($this->relationLoaded('sharePrices')) {
            $latestPrice = $this->sharePrices->first();
        } else {
            $latestPrice = $this->sharePrices()->orderByDesc('id')->first();
        }
        return $latestPrice ? (float)$latestPrice->base_price : 0.00;
    }

    /**
     * Get the latest price from company_share_price table (price column).
     * 
     * @return float
     */
    public function getLatestPriceAttribute(): float
    {
        if ($this->relationLoaded('sharePrices')) {
            $latestPrice = $this->sharePrices->first();
        } else {
            $latestPrice = $this->sharePrices()->orderByDesc('date')->first();
        }
        return $latestPrice ? (float)$latestPrice->price : 0.00;
    }




    public function bonusHistory()
    {
        return $this->hasMany(BonusHistoryModel::class, 'company_id', 'id');
    }

    public function grabOpportunitySlots(): HasMany
    {
        return $this->hasMany(CompanyGrabOpportunitySlotModel::class, 'company_id');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(CompanyDealModel::class, 'company_id')
            ->notDeleted()
            ->notExpired()
            ->orderByDesc('is_hot_deal')
            ->orderByDesc('id');
    }

    // Get latest bonus/split
    public function latestBonus()
    {
        return $this->hasOne(BonusHistoryModel::class, 'company_id', 'id')
            ->where('status', 'applied')
            ->latest('applied_at');
    }
    public function applySplit($splitRatio, $splitDate)
    {
        $ratio = explode(':', $splitRatio);
        $existingRatio = (int) $ratio[0];
        $additionalRatio = (int) $ratio[1];

        // Calculate multiplier: 1:1 = 2x, 1:2 = 3x
        $totalAfterBonus = $existingRatio + $additionalRatio;
        $multiplier = $totalAfterBonus / $existingRatio;

        DB::beginTransaction();

        try {
            $beforeSplit = [
                'share_price' => $this->share_price,
                'distributer_price' => $this->distributer_price,
                'base_price' => $this->base_price,
                'face_value' => $this->fundamentals->face_value ?? 0,
                'total_shares' => $this->fundamentals->total_shares ?? 0
            ];

            // 1. Update company share prices (divide by multiplier)
            $newSharePrice = $this->share_price / $multiplier;
            $newDistributerPrice = $this->distributer_price / $multiplier;
            $newBasePrice = $this->base_price / $multiplier;

            $this->update([
                'share_price' => $newSharePrice,
                'distributer_price' => $newDistributerPrice,
                'base_price' => $newBasePrice,
                'current_split_ratio' => $splitRatio,
                'last_split_date' => $splitDate,
                'has_active_split' => true
            ]);

            // 2. Update fundamentals
            if ($this->fundamentals) {
                $this->fundamentals->update([
                    'face_value' => $this->fundamentals->face_value / $multiplier,
                    'total_shares' => $this->fundamentals->total_shares * $multiplier
                ]);
            }

            // 3. Update all PRE-IPO portfolios for this company (using company_id)
            $affectedPortfolios = PortfolioPreIpoModel::where('company_id', $this->id)
                ->update([
                    'shares' => DB::raw("shares * {$multiplier}"),
                    'purchase_price' => DB::raw("purchase_price / {$multiplier}")
                ]);

            // 4. Update historical share prices
            CompanySharePriceModel::where('company_id', $this->id)
                ->where('date', '<', $splitDate)
                ->update([
                    'price' => DB::raw("price / {$multiplier}"),
                    'base_price' => DB::raw("base_price / {$multiplier}"),
                    'distributer_price' => DB::raw("distributer_price / {$multiplier}")
                ]);

            // Store after split values
            $afterSplit = [
                'share_price' => $newSharePrice,
                'distributer_price' => $newDistributerPrice,
                'base_price' => $newBasePrice,
                'face_value' => $this->fundamentals->face_value ?? 0,
                'total_shares' => $this->fundamentals->total_shares ?? 0
            ];

            // 5. Create bonus history record
            BonusHistoryModel::create([
                'company_id' => $this->id,
                'split_ratio' => $splitRatio,
                'split_date' => $splitDate,
                'multiplier' => $multiplier,
                'before_split' => $beforeSplit,
                'after_split' => $afterSplit,
                'affected_portfolios' => $affectedPortfolios,
                'status' => 'applied',
                'applied_at' => now()
            ]);

            DB::commit();
            return ['success' => true, 'affected_portfolios' => $affectedPortfolios];
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function revertLastSplit()
    {
        $lastBonus = BonusHistoryModel::where('company_id', $this->id)
            ->where('status', 'applied')
            ->orderBy('applied_at', 'desc')
            ->first();

        if (!$lastBonus) {
            throw new Exception('No split history found to revert');
        }

        $multiplier = $lastBonus->multiplier;
        $reverseMultiplier = 1 / $multiplier;

        DB::beginTransaction();

        try {
            // 1. Revert company prices to before split values
            $this->update([
                'share_price' => $lastBonus->before_split['share_price'],
                'distributer_price' => $lastBonus->before_split['distributer_price'],
                'base_price' => $lastBonus->before_split['base_price'],
                'current_split_ratio' => null,
                'last_split_date' => null,
                'has_active_split' => false
            ]);

            // 2. Revert fundamentals
            if ($this->fundamentals) {
                $this->fundamentals->update([
                    'face_value' => $lastBonus->before_split['face_value'],
                    'total_shares' => $lastBonus->before_split['total_shares']
                ]);
            }

            // 3. Revert PRE-IPO portfolios (using company_id)
            PortfolioPreIpoModel::where('company_id', $this->id)
                ->update([
                    'shares' => DB::raw("shares * {$reverseMultiplier}"),
                    'purchase_price' => DB::raw("purchase_price * {$multiplier}")
                ]);

            // 4. Revert historical prices
            CompanySharePriceModel::where('company_id', $this->id)
                ->where('date', '<', $lastBonus->split_date)
                ->update([
                    'price' => DB::raw("price * {$multiplier}"),
                    'base_price' => DB::raw("base_price * {$multiplier}"),
                    'distributer_price' => DB::raw("distributer_price * {$multiplier}")
                ]);

            // 5. Mark bonus as reverted
            $lastBonus->update([
                'status' => 'reverted',
                'reverted_at' => now()
            ]);

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }


    // not used
    // public function getLastYearSharePriceAttribute(): float
    // {
    //     // $oneYearAgo = now()->subYear();

    //     // // Try to get the price from one year ago or before
    //     // $price = $this->sharePrices()
    //     //     ->where('date', '<=', $oneYearAgo)
    //     //     ->orderBy('date', 'desc')
    //     //     ->first();

    //     // // If no price found for one year ago, get the oldest available price
    //     // if (!$price) {
    //     //     $price = $this->sharePrices()
    //     //         ->orderBy('date', 'asc')
    //     //         ->first();
    //     // }

    //     // return $price->price ?? 0.00;
    //     return 0;
    // }
    // private function getLatestSharePrice(): ?CompanySharePriceModel
    // {
    //     return $this->latestSharePriceCache ??= $this->sharePrices()
    //         ->orderBy('id', 'desc')
    //         ->first();
    // }
    // public function getPriceUpdatedTodayAttribute(): bool
    // {
    //     $latestSharePrice = $this->getLatestSharePrice();
    //     if ($latestSharePrice) {
    //         return $latestSharePrice->created_at->isToday();
    //     }
    //     return false;
    // }

    // public function getSharePriceAttribute(): float
    // {
    //     return (float) ($this->getLatestSharePrice()?->price ?? 0.00);
    // }

    // public function getDistributerPriceAttribute(): float
    // {
    //     return (float) ($this->getLatestSharePrice()?->distributer_price ?? 0.00);
    // }

    // public function getBasePriceAttribute(): float
    // {
    //     return (float) ($this->getLatestSharePrice()?->base_price ?? 0.00);
    // }
    // public function getTransactionAttribute()
    // {
    //     $user = request()->user();
    //     $token = request()->bearerToken();
    //     if ($user) {
    //         $sanctumToken = PersonalAccessToken::findToken($token);
    //         if ($sanctumToken && $sanctumToken->tokenable_type == InvestorModel::class) {
    //             return PreIpoModel::where('company_id', $this->id)->where('investor_id', $user->id)->first() ?? NULL;
    //         }
    //     }

    //     return null;
    // }
}
