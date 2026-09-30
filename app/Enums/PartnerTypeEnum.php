<?php

namespace App\Enums;

enum PartnerTypeEnum: string
{
    case wealthmanager = 'Wealth Manager';
    case distributor = 'Distributor';
    case retailer = 'Retailer';
    case relationmanager = 'Relation Manager';
    case institution = 'Institution';
}
