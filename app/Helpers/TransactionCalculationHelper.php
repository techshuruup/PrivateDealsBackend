<?php

namespace App\Helpers;

use App\Models\CompanyModel;
use App\Models\InvestorCouponModel;
use App\Models\MasterCouponModel;
use App\Enums\CouponTypeEnum;
use App\Enums\InvestorCouponStatusEnum;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class TransactionCalculationHelper
{
    public static function calculateTransactionAmount(
        int $companyId,
        int $quantity,
        ?int $masterCouponId = null,
        string $transactionType = 'pre_ipo',
        ?int $investorCouponId = null
    ): array {

        $investorId = auth()->id();

        Log::info('calculateTransactionAmount called', [
            'company_id' => $companyId,
            'quantity' => $quantity,
            'master_coupon_id' => $masterCouponId,
            'transaction_type' => $transactionType,
            'investor_coupon_id' => $investorCouponId,
            'investor_id' => $investorId,
        ]);

        DB::beginTransaction();

        try {
            Log::debug("try investor : " . $investorId);
            $company = CompanyModel::with('grabOpportunitySlots')->findOrFail($companyId);
            $baseSharePrice = $company->share_price ?? 0;
            $isGrabOpportunityCoupon = false;

            if ($investorCouponId) {
                $checkCoupon = InvestorCouponModel::with('coupon')->find($investorCouponId);
                if ($checkCoupon && $checkCoupon->coupon && $checkCoupon->coupon->type === CouponTypeEnum::grabopportunity->value) {
                    $isGrabOpportunityCoupon = true;
                }
            } elseif ($masterCouponId) {
                $checkCoupon = MasterCouponModel::find($masterCouponId);
                if ($checkCoupon && $checkCoupon->type === CouponTypeEnum::grabopportunity->value) {
                    $isGrabOpportunityCoupon = true;
                }
            }

            $grabResult = self::applyGrabOpportunity(
                $company,
                $baseSharePrice,
                $quantity,
                $isGrabOpportunityCoupon
            );

            $finalSharePrice = $grabResult['share_price'];
            $grabPercentage  = $grabResult['applied_percentage'];

            $grabCouponDiscount = 0;
            if ($isGrabOpportunityCoupon) {
                $normalGrabResult   = self::applyGrabOpportunity($company, $baseSharePrice, $quantity, false);
                $normalFinalPrice   = $normalGrabResult['share_price'];
                $grabCouponDiscount = round(($normalFinalPrice * $quantity) - ($finalSharePrice * $quantity), 2);
            }
            Log::info('[GRAB-DEBUG]', [
                'isGrabOpportunityCoupon' => $isGrabOpportunityCoupon,
                'baseSharePrice'          => $baseSharePrice,
                'finalSharePrice'         => $finalSharePrice,
                'normalFinalPrice'        => $normalGrabResult['share_price'] ?? 'not calculated',
                'quantity'                => $quantity,
                'grabCouponDiscount'      => $grabCouponDiscount,
                'investorCouponId'        => $investorCouponId,
                'masterCouponId'          => $masterCouponId,
            ]);
            $investmentAmount = $finalSharePrice * $quantity;
            $processingFeePercentage = $company->processing_fee_percentage ?? CommonHelper::processingFeePercentage();
            $processingFee           = ($investmentAmount * $processingFeePercentage) / 100;
            $isFreeProcessingFee     = (int) ($company->is_free_processing_fee ?? 0);

            $previouslyAssignedCoupons = InvestorCouponModel::where('investor_id', $investorId)
                ->where('status', InvestorCouponStatusEnum::assigned->value)
                ->when($masterCouponId, fn($q) => $q->where('coupon_id', '!=', $masterCouponId))
                ->when($investorCouponId, fn($q) => $q->where('id', '!=', $investorCouponId))
                ->get();

            foreach ($previouslyAssignedCoupons as $ic) {
                // if ($ic->referral_id !== null) {
                //     $ic->status = InvestorCouponStatusEnum::active->value;
                // } else {
                //     $ic->status = InvestorCouponStatusEnum::cancelled->value;
                // }
                $ic->status = InvestorCouponStatusEnum::active->value;
                $ic->save();
            }

            $couponDiscount = 0;
            $couponCode     = null;
            $investorCoupon = null;
            if ($investorCouponId) {

                $icRow = InvestorCouponModel::where('id', $investorCouponId)
                    ->where('investor_id', $investorId)
                    ->whereIn('status', [
                        InvestorCouponStatusEnum::active->value,
                        InvestorCouponStatusEnum::assigned->value,
                    ])
                    ->with('coupon')
                    ->first();

                if ($icRow && $icRow->coupon && $icRow->coupon->is_active) {
                    $masterCoupon = $icRow->coupon;

                    if ($icRow->status === InvestorCouponStatusEnum::active->value) {
                        $icRow->status      = InvestorCouponStatusEnum::assigned->value;
                        $icRow->assigned_at = now();
                        $icRow->save();
                    }

                    if ($masterCoupon->type === CouponTypeEnum::grabopportunity->value) {
                        $couponDiscount = $grabCouponDiscount;
                    } else {
                        $couponDiscount = self::calculateDiscount($masterCoupon, $processingFee, $quantity);
                    }
                    $couponCode     = $masterCoupon->code;
                    $investorCoupon = $icRow;
                }
            }

            /*
        |----------------------------------------------------------------------
        | PATH B — called from calculate-transaction with master_coupon_id
        | Check referral row first, then create fresh row for normal coupons
        |----------------------------------------------------------------------
        */ elseif ($masterCouponId) {
                $coupon = MasterCouponModel::findOrFail($masterCouponId);

                if (self::isCouponValid($coupon, $company, $transactionType)) {

                    $redeemedCount = InvestorCouponModel::where('investor_id', $investorId)
                        ->where('coupon_id', $coupon->id)
                        ->where('status', InvestorCouponStatusEnum::redeemed->value)
                        ->count();

                    if (is_null($coupon->usage_limit_per_user) || $redeemedCount < $coupon->usage_limit_per_user) {

                        Log::info('[COUPON-DEBUG] PATH B existingRow check', [
                            'investor_id' => $investorId,
                            'coupon_id'   => $coupon->id,
                            'existing'    => InvestorCouponModel::where('investor_id', $investorId)
                                ->where('coupon_id', $coupon->id)
                                ->get(['id', 'status'])
                                ->toArray(),
                        ]);
                        // Always reuse existing row — never create duplicate
                        // Look for any non-redeemed row
                        $existingRow = InvestorCouponModel::where('investor_id', $investorId)
                            ->where('coupon_id', $coupon->id)
                            ->whereNotIn('status', [InvestorCouponStatusEnum::redeemed->value])
                            ->first();

                        if ($existingRow) {
                            // Reuse — flip to assigned
                            $existingRow->status      = InvestorCouponStatusEnum::assigned->value;
                            $existingRow->assigned_at = now();
                            $existingRow->save();
                            $investorCoupon = $existingRow;
                        } else {
                            // No row exists at all — create one fresh row
                            $investorCoupon = InvestorCouponModel::create([
                                'investor_id'  => $investorId,
                                'coupon_id'    => $coupon->id,
                                'referral_id'  => null,
                                'display_code' => null,
                                'reward_value' => null,
                                'status'       => InvestorCouponStatusEnum::assigned->value,
                                'assigned_at'  => now(),
                            ]);
                        }

                        if ($coupon->type === CouponTypeEnum::grabopportunity->value) {
                            $couponDiscount = $grabCouponDiscount;
                        } else {
                            $couponDiscount = self::calculateDiscount($coupon, $processingFee, $quantity);
                        }
                        $couponCode     = $coupon->code;
                    }
                }
                Log::debug("Not Valid Coupon : ");
            }

            // $payableAmount = $investmentAmount + $processingFee - $couponDiscount;

            $payableAmount = $investmentAmount - $couponDiscount;
            if (!$isFreeProcessingFee) {
                $payableAmount += $processingFee;
            }

            $transactionDate = Carbon::now();
            $settlementDate = SettlementDateHelper::getT1SettlementDate($transactionDate);
            DB::commit();

            return [
                'investment_amount' => round($investmentAmount, 2),
                'processing_fee_percentage' => $processingFeePercentage,
                'processing_fee' => round($processingFee, 2),
                'is_free_processing_fee' => $isFreeProcessingFee ? 1 : 0,
                'coupon_discount' => round($couponDiscount, 2),
                'coupon_code' => $couponCode,
                'payable_amount' => round($payableAmount, 2),
                'settlement_date' => $settlementDate->format('Y-m-d'),
                'settlement_date_formatted' => $settlementDate->format('d-m-Y'),
                'share_price'   => round($finalSharePrice, 2),
                'original_share_price'        => round($baseSharePrice, 2),
                'quantity' => $quantity,
                'base_share_price' => round($baseSharePrice, 2),
                'grab_opportunity_percentage' => $grabPercentage,
                // 'grab_opportunity_applied' => $grabPercentage > 0 ? 1 : 0,
                // 'grab_coupon_applied' => $isGrabOpportunityCoupon ? 1 : 0,
                'investor_coupon_id' => $investorCoupon?->id
            ];
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private static function calculateDiscount(
        MasterCouponModel $coupon,
        float $processingFee,
        int $quantity
    ): float {
        return match ($coupon->type) {
            CouponTypeEnum::flatdiscount->value => (float) $coupon->discount_value,
            CouponTypeEnum::percentagediscount->value => min(
                ($processingFee * $coupon->discount_value) / 100,
                $coupon->max_discount_amount ?? PHP_INT_MAX
            ),
            CouponTypeEnum::persharediscount->value => (float) $coupon->discount_value * $quantity,
            CouponTypeEnum::cashback->value => (float) $coupon->discount_value,
            default => 0,
        };
    }
    private static function applyGrabOpportunity(
        CompanyModel $company,
        float $sharePrice,
        int $quantity,
        bool $forceSlot3 = false
    ): array {

        $appliedPercentage = 0;
        $finalSharePrice = $sharePrice;

        if (!$company->is_grab_opportunity_enabled) {
            return [
                'share_price' => $finalSharePrice,
                'applied_percentage' => $appliedPercentage,
            ];
        }

        $investmentAmount = $sharePrice * $quantity;

        $slotQuery = $company->grabOpportunitySlots();

        if ($forceSlot3) {
            $slot = $slotQuery->orderBy('slot_number', 'desc')->first();
        } else {
            $slot = $slotQuery->where('min_amount', '<=', $investmentAmount)
                ->where(function ($q) use ($investmentAmount) {
                    $q->where('max_amount', '>=', $investmentAmount)
                        ->orWhereNull('max_amount');
                })
                ->orderBy('slot_number', 'desc')
                ->first();
        }

        if ($slot) {
            $basePrice = (float) ($company->base_price ?? $sharePrice);

            if ($slot->slot_number == 1) {
                $slotSharePrice = (float) $company->share_price; // slot 1 = retailer price
            } else {
                $slotSharePrice = $basePrice * (1 + ($slot->percentage / 100)); // slot 2,3 = base_price + %
            }

            $finalSharePrice   = $slotSharePrice;
            $appliedPercentage = $slot->percentage;
        }

        return [
            'share_price' => round($finalSharePrice, 2),
            'applied_percentage' => $appliedPercentage,
        ];
    }


    private static function isCouponValid(
        MasterCouponModel $coupon,
        CompanyModel $company,
        string $transactionType = 'pre_ipo'
    ): bool {
        if (!$coupon->is_active) {
            return false;
        }

        $now = Carbon::now();

        if ($coupon->valid_from && $now->isBefore($coupon->valid_from)) {
            return false;
        }

        if ($coupon->valid_to && $now->isAfter($coupon->valid_to)) {
            return false;
        }

        // Company scope check
        if ($coupon->company_id && $coupon->company_id != $company->id) {
            return false;
        }

        // Multiple companies scope check
        if (!$coupon->company_id && $coupon->companies()->count() > 0) {
            if (!$coupon->companies()->where('company.id', $company->id)->exists()) {
                return false;
            }
        }

        // Transaction type check — fixed, was hardcoded to pre_ipo before
        if ($coupon->applies_on !== 'all' && $coupon->applies_on !== $transactionType) {
            return false;
        }

        return true;
    }
}
