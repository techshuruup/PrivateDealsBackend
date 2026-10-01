<?php

namespace App\Helpers;

use App\Enums\GenderEnum;
use App\Models\BseHolidayModel;
use App\Models\CompanyModel;
use App\Models\CompanyNewsModel;
use App\Models\InvestorModel;
use App\Models\MasterFindCmlModel;
use App\Models\PartnerModel;
use App\Models\SellerMasterModel;
use App\Models\StartupModel;
use App\Traits\FileUploadTrait;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileUpDownHelper
{

    use FileUploadTrait;

    static function generateUrl($filePath): string|NULL
    {
        // if ($filePath) {
        //     $disk = Storage::disk('s3');
        //     return $disk->temporaryUrl($filePath, now()->addDay(1));
        // }
        if ($filePath) {
            return Storage::url($filePath);
        }
        return NULL;
    }

    static function master_pages_news_img_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.jpg';
        $path = 'company/news/image/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function upload_preipo_sell_cmr_document($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'preipo_transactions/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function upload_secondary_transaction_document($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'secondary_transaction/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function upload_preipo_transaction_payment_receipt_document($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'preipo_transaction_payment_receipt/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function upload_primary_transaction_document($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'primary_transaction/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function master_pages_blog_banner_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        // Log::alert($name);
        $path = 'master/pages/blog/banner/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function master_pages_avtar_img_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        // Log::alert($name);
        $path = 'master/pages/avtar/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function master_pages_findcml_logo_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        // Log::alert($name);
        $path = 'master/pages/findcml/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function book_slot_image_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        // Log::alert($name);
        $path = 'bookslot/image/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function master_pages_findcml_video_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'findcml/video/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function app_tutorial_video_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'apptutorial/video/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function master_holiday_img_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        // Log::alert($name);
        $path = 'master/pages/holiday/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function app_build_upload($file): ?string
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'app-builds/' . $name;

        $isUploaded = self::uploadFile($path, $file, 'public');

        return $isUploaded ? $path : null;
    }
    static function aadhar_front_img_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        // Log::alert($name);
        $path = 'kyc/aadhar/front/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function aadhar_back_img_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        // Log::alert($name);
        $path = 'kyc/aadhar/back/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function pan_front_img_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        // Log::alert($name);
        $path = 'kyc/pan/front/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }
    static function master_pages_banner_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        // Log::alert($name);
        $path = 'master/pages/banner/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function master_sector_image_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'master/sectors/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function master_supported_countries_flag_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'master/supported-countries/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function resource_billing_image_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'resource-billing/file/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function broadcast_header_file_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'broadcast/header_file/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function broadcast_push_notification_file_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'broadcast/notification/image/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function subadmin_profile_photo_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'profile/update/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }
    static function subadmin_profile_photo_url($path): string
    {
        return $path != '' && $path != NULL ? self::fileUrl($path) : asset('core/placeholders/square.png');
    }

    static function master_industry_image_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'master/industry/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function website_banner_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'website/media/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function investor_profile_photo_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'investor/profile_photo/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function company_logo_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'company/logo/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function seller_logo_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'seller/logo/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function partner_profile_photo_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'partner/profile_photo/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function company_exported_category_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'company/exported/category' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function preipo_document_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'preipo/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function company_event_file_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'company/events/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function company_news_file_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'company/news/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function startup_team_profile_photo_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'startup/team/profile_photo/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function startup_document_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'startup/document/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function startup_short_banner_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'startup/short_banner/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }
    static function startup_long_banner_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'startup/long_banner/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }
    static function startup_pitch_deck_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'startup/pitch_deck/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }
    static function startup_financial_projection_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'startup/financial_projection/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function startup_dd_report_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'startup/dd_report/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function startup_valuation_report_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'startup/valuation_report/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function startup_dpiit_certificate_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'startup/dpiit_certificate/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function startup_shuruup_research_report_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'startup/shuruup_research_report/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function startup_logo_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'startup/logo/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function startup_product_video_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'startup/product_video/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function startup_pitch_video_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'startup/pitch_video/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function startup_video_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'startup/video/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function startup_updates_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'startup/updates/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function startup_mis_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'startup/mis/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function startup_mgt14_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'startup/mgt14/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function startup_pas3_upload($file): string|NULL
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'startup/pas3/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function get_master_sector_url($path): string
    {
        return $path != '' && $path != NULL ? self::fileUrl($path) : asset('core/placeholders/square.png');
    }
    static function get_master_supported_countries_flag_url($path): string
    {
        return $path != '' && $path != NULL ? self::fileUrl($path) : asset('core/placeholders/square.png');
    }

    static function get_master_industry_url($path): string
    {
        return $path != '' && $path != NULL ? self::fileUrl($path) : asset('core/placeholders/square.png');
    }

    static function get_website_media_banner_url($path): string
    {
        return $path != '' && $path != NULL ? self::fileUrl($path) : asset('core/placeholders/square.png');
    }
    static function get_page_banner_url($path): string
    {
        return $path != '' && $path != NULL ? self::fileUrl($path) : asset('core/placeholders/2560x891.png');
    }

    static function get_page_blog_banner_url($path): string
    {
        return $path != '' && $path != NULL ? self::fileUrl($path) : asset('core/placeholders/long.png');
    }

    static function get_cms_avtar_url($path): string
    {
        return $path != '' && $path != NULL ? self::fileUrl($path) : asset('core/placeholders/long.png');
    }

    static function get_findcml_logo_url($path): string
    {
        return $path != '' && $path != NULL ? self::fileUrl($path) : asset('core/placeholders/long.png');
    }

    static function get_app_build_url($path): string
    {
        return $path != '' && $path != null
            ? self::fileUrl($path)
            : '#';
    }

    static function get_startup_updates_url($path): string
    {
        return $path != '' && $path != NULL ? self::fileUrl($path) : asset('core/placeholders/square.png');
    }
    static function getStartupVideo(StartupModel $startup): string|NULL
    {
        // dd(self::fileUrl($startup->details->product_video));
        if ($startup && $startup->details && $startup->details->product_video != '' && $startup->details->product_video != NULL) {
            return self::fileUrl($startup->details->product_video);
        } else {
            return NULL;
        }
    }
    static function getFindCmlVideo(MasterFindCmlModel $item): string|null
    {
        if ($item && $item->video != '' && $item->video != null) {
            return self::fileUrl($item->video);
        }

        return null;
    }

    static function getStartupBanner(StartupModel $startup): string
    {
        if ($startup && $startup->cms && $startup->cms->banner != '' && $startup->cms->banner != NULL) {
            return self::fileUrl($startup->cms->banner);
        } else {
            return asset('core/placeholders/370x290.png');
        }
    }
    static function getStartupBannerLong(StartupModel $startup): string
    {
        if ($startup && $startup->cms && $startup->cms->long_banner != '' && $startup->cms->long_banner != NULL) {
            return self::fileUrl($startup->cms->long_banner);
        } else {
            return asset('core/placeholders/2560x891.png');
        }
    }
    static function get_investor_profile_photo_url(InvestorModel $investor): string
    {
        if ($investor->profile_photo != '' && $investor->profile_photo != NULL) {
            return self::fileUrl($investor->profile_photo);
        } else {
            if ($investor->gender == GenderEnum::male->value) {
                return asset('core/placeholders/male_user.svg');
            } else if ($investor->gender == GenderEnum::female->value) {
                return asset('core/placeholders/female_user.svg');
            } else {
                return asset('core/placeholders/other_user.svg');
            }
        }
    }

    static function get_partner_profile_photo_url(PartnerModel $partner): string
    {
        if ($partner->profile_photo != '' && $partner->profile_photo != NULL) {
            return self::fileUrl($partner->profile_photo);
        } else {
            if ($partner->gender == GenderEnum::male->value) {
                return asset('core/placeholders/male_user.svg');
            } else if ($partner->gender == GenderEnum::female->value) {
                return asset('core/placeholders/female_user.svg');
            } else {
                return asset('core/placeholders/other_user.svg');
            }
        }
    }

    static function get_company_logo_url(CompanyModel $company): string
    {
        if ($company && $company->logo != '' && $company->logo != NULL) {
            return self::fileUrl($company->logo);
        } else {
            return asset('core/placeholders/square.png');
        }
    }

    static function get_seller_logo_url(SellerMasterModel $seller): string
    {
        if ($seller && $seller->logo != '' && $seller->logo != NULL) {
            return self::fileUrl($seller->logo);
        }

        $name = trim((string) ($seller->company_name ?? ''));
        if ($name === '') {
            $name = 'Seller';
        }

        return 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=0D8ABC&color=fff';
    }

    static function getCompanyNewsBanner(CompanyNewsModel $news): string
    {
        if ($news && $news->image && $news->image != '' && $news->image != NULL) {
            return self::fileUrl($news->image);
        } else {
            return asset('core/placeholders/370x290.png');
        }
    }

    static function get_startup_logo_url(StartupModel $startup): string
    {
        if ($startup && $startup->cms && $startup->cms->logo != '' && $startup->cms->logo != NULL) {
            return self::fileUrl($startup->cms->logo);
        } else {
            return asset('core/placeholders/square.png');
        }
    }

    static function get_holiday_img_url(BseHolidayModel $bseHoliday): string
    {
        if ($bseHoliday  && $bseHoliday->holiday_img != '' && $bseHoliday->holiday_img != NULL) {
            return self::fileUrl($bseHoliday->holiday_img);
        } else {
            return asset('core/placeholders/square.png');
        }
    }

    static function get_startup_document_download_link(StartupModel $startup, $column): string
    {
        if ($startup && $startup->cms && $startup->cms->$column != '' && $startup->cms->$column != NULL) {
            return '<a href="' . route('download.web', ['path' => $startup->cms->$column, 'name' => $column]) . '" class="btn-download" target="_blank"> <i class="fa-solid fa-download"></i> <span>Download</span> </a>';
        } else {
            return 'Not uploaded';
        }
    }

    static function get_startup_update_thumbnail_url($path): string
    {
        return $path != '' && $path != NULL ? self::fileUrl($path) : asset('core/placeholders/square.png');
    }

    static function get_startup_team_profile_photo_url($path): string
    {
        return $path != '' && $path != NULL ? self::fileUrl($path) : asset('core/placeholders/square.png');
    }

    static function get_aadhar_photo_url($path): string
    {
        return $path != '' && $path != NULL ? self::fileUrl($path) : asset('core/placeholders/square.png');
    }
    static function get_aadhar_back_photo_url($path): string
    {
        return $path != '' && $path != NULL ? self::fileUrl($path) : asset('core/placeholders/square.png');
    }
    static function get_pan_photo_url($path): string
    {
        return $path != '' && $path != NULL ? self::fileUrl($path) : asset('core/placeholders/square.png');
    }
    static function get_cml_photo_url($path): string
    {
        return $path != '' && $path != NULL ? self::fileUrl($path) : asset('core/placeholders/square.png');
    }
    static function get_cheque_photo_url($path): string
    {
        return $path != '' && $path != NULL ? self::fileUrl($path) : asset('core/placeholders/square.png');
    }

    static function get_company_event_file_url($path): string|NULL
    {
        return $path != '' && $path != NULL ? self::fileUrl($path) : NULL;
    }


    static function uploadInvestorDoc($file)
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'investor/kyc/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function uploadTempDematCml($file)
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'investor/temp/cml' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }

    static function uploadManualDocument($file)
    {
        $name = CommonHelper::generateFileName() . '.' . $file->getClientOriginalExtension();
        $path = 'document/manual/' . $name;
        $isUploaded = self::uploadFile(
            $path,
            $file,
            'public'
        );
        if ($isUploaded) {
            return $path;
        } else {
            return NULL;
        }
    }
    static function get_investor_kyc_img($path): string
    {
        return $path != '' && $path != NULL ? self::fileUrl($path) : asset('core/placeholders/square.png');
    }

    static function startupDocIsUploadedViewWeb($row)
    {
        if (!empty($row) && self::get_startup_document($row) !== '') {
            return '<a data-pdf="' . self::get_startup_document($row) . '" href="#" class="download_btn btn-open-pdf-viewer"><i class="fa-solid fa-eye"></i> <span>View</span></a>';
        }
        return 'Not Found';
    }

    static function get_startup_document($path): string
    {
        if ($path) {
            return self::fileUrl($path);
        }

        return asset('core/placeholders/square.png'); // Placeholder for missing document
    }
}
