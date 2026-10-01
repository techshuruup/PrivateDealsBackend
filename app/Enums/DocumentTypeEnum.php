<?php

namespace App\Enums;

enum DocumentTypeEnum: string
{
    case ssa = 'SSA';
    case loi = 'LOI';
    case offer = 'Offer Letter';
    case mgtzip = 'MGT-14 Zip';
    case mgtchallan = 'MGT-14 Challan';
    case pas = 'PAS-3';
    case sha = 'SHA';
    case rtgsreceipt = 'RTGS Receipt';
    case chequecounterslip = 'Cheque Counter Slip';
    case secondarysh = 'SH4';
    case paymentreceipt = 'Payment Receipt';
    case sharereceipt = 'Share Receipt';
    case preipoapproval = 'Pre-IPO Approval';
    case preiporejection = 'Pre-IPO Rejection';
    case preipodealslip = 'Pre-IPO Deal Slip';
    case buymandate = 'BuyMandate';
    case preiposharetransferreceipt = 'Pre-IPO Share Transfer Receipt';
    case aadharfront = 'Aadhar Front';
    case aadharback = 'Aadhar Back';
    case pancard = 'Pan Card';
    case bankcheque = 'Bank Cheque';
    case clientmaster = 'Client Master Report';
    case ppm = 'PPM';
    case ca = 'CA';

    public static function singlePrimary(): array
    {
        return [
            self::ssa,
            self::loi,
            self::offer,
            self::rtgsreceipt,
            self::chequecounterslip
        ];
    }

    /**
     * Get primary documents
     */
    public static function primaryDocument(): array
    {
        return [
            self::ssa,
            self::loi,
            self::offer,
            self::mgtzip,
            self::mgtchallan,
            self::pas,
            self::sha,
            self::rtgsreceipt,
            self::chequecounterslip
        ];
    }

    /**
     * Get documents for multiple primary transactions
     */
    public static function multiplePrimaryTransaction(): array
    {
        return [
            self::mgtzip,
            self::mgtchallan,
            self::pas,
            self::sha
        ];
    }

    /**
     * Get secondary documents
     */
    public static function secondary(): array
    {
        return [
            self::secondarysh,
            self::paymentreceipt,
            self::sharereceipt
        ];
    }

    /**
     * Get pre-IPO documents
     */
    public static function preIPO(): array
    {
        return [
            self::preipoapproval,
            self::preiporejection,
            self::preipodealslip,
            self::buymandate,
            self::preiposharetransferreceipt,
        ];
    }

    public static function singlePreIPO(): array
    {
        return [
            self::preipodealslip
        ];
    }

    /**
     * Get KYC documents
     */
    public static function kyc(): array
    {
        return [
            self::aadharfront,
            self::aadharback,
            self::pancard,
            self::bankcheque,
            self::clientmaster
        ];
    }

    /**
     * Get AIF documents
     */
    public static function aif(): array
    {
        return [
            self::ppm,
            self::ca
        ];
    }
}
