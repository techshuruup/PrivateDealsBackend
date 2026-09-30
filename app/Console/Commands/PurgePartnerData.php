<?php

namespace App\Console\Commands;

use App\Models\PartnerModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class PurgePartnerData extends Command
{
    protected $signature = 'partners:purge {--force : Delete partner rows from the local database}';

    protected $description = 'Delete partner accounts and related database rows. Does not delete files.';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Refusing to run: APP_ENV must be local.');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->error('Refusing to run without --force. This deletes partner rows from the database only.');

            return self::FAILURE;
        }

        $partnerType = PartnerModel::class;
        $deleted = [];

        try {
            DB::transaction(function () use ($partnerType, &$deleted) {
                $deleted['personal_access_tokens'] = DB::table('personal_access_tokens')
                    ->where('tokenable_type', $partnerType)
                    ->delete();
                $deleted['notifications'] = DB::table('notifications')
                    ->where('user_type', $partnerType)
                    ->delete();
                $deleted['core_firebase_device_token'] = DB::table('core_firebase_device_token')
                    ->where('user_type', $partnerType)
                    ->delete();
                $deleted['report_notifications'] = DB::table('report_notifications')
                    ->where('user_type', $partnerType)
                    ->delete();
                $deleted['documents_signers'] = DB::table('documents_signers')
                    ->where('user_type', $partnerType)
                    ->delete();
                $deleted['user_bank_accounts'] = DB::table('user_bank_accounts')
                    ->where('user_type', $partnerType)
                    ->delete();
                $deleted['bank_details'] = DB::table('bank_details')
                    ->where('user_type', $partnerType)
                    ->delete();
                $deleted['kyc_history'] = DB::table('kyc_history')
                    ->where('user_type', $partnerType)
                    ->delete();
                $deleted['company_enquiries'] = DB::table('company_enquiries')
                    ->where('user_type', $partnerType)
                    ->delete();
                $deleted['reports_verification_codes'] = DB::table('reports_verification_codes')
                    ->where('user_type', $partnerType)
                    ->delete();

                $deleted['company.submitted_by_partner_id'] = DB::table('company')
                    ->whereNotNull('submitted_by_partner_id')
                    ->update(['submitted_by_partner_id' => null]);
                $deleted['company_deals.created_by_partner_id'] = DB::table('company_deals')
                    ->whereNotNull('created_by_partner_id')
                    ->update(['created_by_partner_id' => null]);
                $deleted['investor.partner_id'] = DB::table('investor')
                    ->whereNotNull('partner_id')
                    ->update(['partner_id' => null]);

                $deleted['broadcast_notification.partners_ids'] = DB::table('broadcast_notification')
                    ->whereNotNull('partners_ids')
                    ->update(['partners_ids' => null]);
                $deleted['broadcast_whatsapp.partners_ids'] = DB::table('broadcast_whatsapp')
                    ->whereNotNull('partners_ids')
                    ->update(['partners_ids' => null]);

                $deleted['partner'] = DB::table('partner')->delete();
            });
        } catch (Throwable $e) {
            $this->error('Purge rolled back: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Partner database rows deleted. Files were not touched.');
        $this->table(['Target', 'Rows'], collect($deleted)->map(
            fn ($count, $name) => [$name, $count]
        )->values()->all());

        return self::SUCCESS;
    }
}
