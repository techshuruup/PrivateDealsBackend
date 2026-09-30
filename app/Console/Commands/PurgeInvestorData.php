<?php

namespace App\Console\Commands;

use App\Models\InvestorModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class PurgeInvestorData extends Command
{
    protected $signature = 'investors:purge {--force : Delete investor rows from the local database}';

    protected $description = 'Delete investor accounts and related database rows. Does not delete files.';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Refusing to run: APP_ENV must be local.');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->error('Refusing to run without --force. This deletes investor rows from the database only.');

            return self::FAILURE;
        }

        $investorType = InvestorModel::class;
        $deleted = [];

        try {
            DB::transaction(function () use ($investorType, &$deleted) {
                $deleted['documents_signers'] = DB::table('documents_signers')
                    ->where(function ($query) use ($investorType) {
                        $query->where('user_type', $investorType)
                            ->orWhereIn('document_id', function ($sub) {
                                $sub->from('documents')->select('id');
                                $this->whereInvestorDocument($sub);
                            });
                    })
                    ->delete();

                $deleted['documents'] = $this->whereInvestorDocument(DB::table('documents'))->delete();

                foreach ($this->fullTables() as $table) {
                    $deleted[$table] = DB::table($table)->delete();
                }

                $deleted['user_bank_accounts'] = DB::table('user_bank_accounts')
                    ->where('user_type', $investorType)
                    ->delete();
                $deleted['bank_details'] = DB::table('bank_details')
                    ->where('user_type', $investorType)
                    ->delete();
                $deleted['kyc_history'] = DB::table('kyc_history')
                    ->where('user_type', $investorType)
                    ->delete();
                $deleted['personal_access_tokens'] = DB::table('personal_access_tokens')
                    ->where('tokenable_type', $investorType)
                    ->delete();
                $deleted['notifications'] = DB::table('notifications')
                    ->where('user_type', $investorType)
                    ->delete();
                $deleted['core_firebase_device_token'] = DB::table('core_firebase_device_token')
                    ->where('user_type', $investorType)
                    ->delete();
                $deleted['report_notifications'] = DB::table('report_notifications')
                    ->where('user_type', $investorType)
                    ->delete();
                $deleted['reports_verification_codes'] = DB::table('reports_verification_codes')
                    ->where('user_type', $investorType)
                    ->delete();
                $deleted['company_enquiries'] = DB::table('company_enquiries')
                    ->where('user_type', $investorType)
                    ->delete();

                $deleted['broadcast_notification.investors_ids'] = DB::table('broadcast_notification')
                    ->whereNotNull('investors_ids')
                    ->update(['investors_ids' => null]);
                $deleted['broadcast_whatsapp.investors_ids'] = DB::table('broadcast_whatsapp')
                    ->whereNotNull('investors_ids')
                    ->update(['investors_ids' => null]);

                $deleted['investor'] = DB::table('investor')->delete();
            });
        } catch (Throwable $e) {
            $this->error('Purge rolled back: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Investor database rows deleted. Files were not touched.');
        $this->table(['Target', 'Rows'], collect($deleted)->map(
            fn ($count, $name) => [$name, $count]
        )->values()->all());

        return self::SUCCESS;
    }

    /**
     * Tables that only store investor-owned rows.
     *
     * @return list<string>
     */
    private function fullTables(): array
    {
        return [
            'pre_ipo_transaction_status_logs',
            'pre_ipo_transaction_payments',
            'pre_ipo_sell_requests',
            'pre_ipo_transaction',
            'primary_transaction_mgt14',
            'primary_transaction_pas3',
            'primary_transaction_payment',
            'primary_transaction_presentation',
            'primary_transaction',
            'secondary_payments',
            'secondary_escrow_account',
            'secondary_share_transfer',
            'secondary_transaction',
            'secondary_sell_request',
            'portfolio',
            'portfolio_preipo',
            'portfolio_import',
            'investor_kyc',
            'investor_kyc_aadhar',
            'investor_kyc_pan',
            'investor_kyc_demat',
            'investor_aif_kyc',
            'investor_pan_details',
            'investor_details',
            'investor_mandates',
            'demat_manual',
            'temp_demat_cml_files',
            'temp_aadhar_pan_details',
            'investor_coupon',
            'investor_referral',
            'investor_company_views',
            'investor_consultancy_slots',
            'investor_favourite_company',
            'investor_favourite_startup',
            'company_price_alerts',
            'company_share_links',
            'leads',
            'investor_register_request',
        ];
    }

    private function whereInvestorDocument($query)
    {
        return $query->where(function ($inner) {
            $inner->whereRaw("JSON_EXTRACT(meta, '$.investor') IS NOT NULL")
                ->orWhereRaw("JSON_EXTRACT(meta, '$.primary_transactions') IS NOT NULL")
                ->orWhereRaw("JSON_EXTRACT(meta, '$.secondary_transaction') IS NOT NULL")
                ->orWhereRaw("JSON_EXTRACT(meta, '$.preipo_transactions') IS NOT NULL")
                ->orWhereRaw("JSON_EXTRACT(meta, '$.aif_kyc') IS NOT NULL");
        });
    }
}
