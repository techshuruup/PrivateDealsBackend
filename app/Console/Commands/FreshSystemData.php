<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class FreshSystemData extends Command
{
    protected $signature = 'system:fresh {--force : Wipe user, transaction, log, and file data. Keeps admin, core settings, and companies.}';

    protected $description = 'Reset operational data. Keeps admin users, core settings, and the company catalog.';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->error('Refusing to run without --force. This deletes investors, partners, sellers, startups, transactions, logs, and their files. Admin users, core settings, and companies are kept.');

            return self::FAILURE;
        }

        $deleted = [];
        $extraPaths = [];

        Schema::disableForeignKeyConstraints();

        try {
            DB::transaction(function () use (&$deleted, &$extraPaths) {
                $extraPaths = $this->collectFilePaths();

                $deleted['company.submitted_by_partner_id'] = $this->nullColumn('company', 'submitted_by_partner_id');
                $deleted['company.submitted_by_seller_id'] = $this->nullColumn('company', 'submitted_by_seller_id');
                $deleted['company_deals.created_by_partner_id'] = $this->nullColumn('company_deals', 'created_by_partner_id');
                $deleted['company_deals.created_by_seller_id'] = $this->nullColumn('company_deals', 'created_by_seller_id');
                $deleted['broadcast_notification.investors_ids'] = $this->nullColumn('broadcast_notification', 'investors_ids');
                $deleted['broadcast_notification.partners_ids'] = $this->nullColumn('broadcast_notification', 'partners_ids');
                $deleted['broadcast_whatsapp.investors_ids'] = $this->nullColumn('broadcast_whatsapp', 'investors_ids');
                $deleted['broadcast_whatsapp.partners_ids'] = $this->nullColumn('broadcast_whatsapp', 'partners_ids');

                foreach ($this->tables() as $table) {
                    $deleted[$table] = $this->wipeTable($table);
                }
            });
        } catch (Throwable $e) {
            $this->error('Fresh start rolled back: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $deleted = array_merge($deleted, $this->deleteStoredFiles($extraPaths));
        $deleted['storage/logs/laravel.log'] = $this->deleteLogFiles();

        $this->info('Fresh start complete. Admin users, core settings, and the company catalog were kept.');
        $this->table(['Target', 'Rows'], collect($deleted)->map(
            fn ($count, $name) => [$name, $count]
        )->values()->all());

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function tables(): array
    {
        return [
            'documents_signers',
            'documents',
            'pre_ipo_transaction_status_logs',
            'pre_ipo_transaction_payments',
            'pre_ipo_sell_requests',
            'pre_ipo_transaction',
            'primary_transaction_mgt14',
            'primary_transaction_pas3',
            'primary_transaction_payment',
            'primary_transaction_presentation',
            'primary_transaction_offer',
            'primary_transaction',
            'secondary_payments',
            'secondary_escrow_account',
            'secondary_share_transfer',
            'secondary_existing_investors',
            'secondary_transaction',
            'secondary_sell_request',
            'portfolio_preipo',
            'portfolio_import',
            'portfolio',
            'investor_kyc_aadhar',
            'investor_kyc_pan',
            'investor_kyc_demat',
            'investor_kyc',
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
            'investor_register_request',
            'investor_pitch',
            'leads',
            'company_price_alerts',
            'company_share_links',
            'company_enquiries',
            'user_bank_accounts',
            'bank_details',
            'kyc_history',
            'personal_access_tokens',
            'notifications',
            'report_notifications',
            'core_firebase_device_token',
            'reports_verification_codes',
            'api_logs',
            'report_webhook_logs',
            'report_messages_whatsapp_replies',
            'report_messages_whatsapp',
            'report_messages_sms',
            'report_messages_email',
            'report_error_logs',
            'admin_tracking_records',
            'cms_contact',
            'cms_feedback',
            'request_access_parameter',
            'beta_testing',
            'sessions',
            'jobs',
            'failed_jobs',
            'job_batches',
            'password_reset_tokens',
            'telescope_entries_tags',
            'telescope_entries',
            'telescope_monitoring',
            'seller_company_share_price',
            'startup_team',
            'startup_legal',
            'startup_faqs',
            'startup_social_media',
            'startup_updates',
            'startup_mis',
            'startup_pitch',
            'startup_share_price',
            'startup_round',
            'startup_fund_raise',
            'startup_key_metrics',
            'startup_financial_details',
            'startup_other_details',
            'startup_manage_captable',
            'startup_offerrequest',
            'startup_cms',
            'startup_details',
            'startup_mgt14',
            'startup_pas3',
            'live_pitch',
            'startup',
            'temp_company',
            'seller_master',
            'partner',
            'investor',
        ];
    }

    /**
     * @return list<string>
     */
    private function fileDirectories(): array
    {
        return [
            'kyc',
            'investor',
            'preipo',
            'preipo_transactions',
            'preipo_transaction_payment_receipt',
            'primary_transaction',
            'secondary_transaction',
            'document',
            'startup',
            'seller',
            'profile',
        ];
    }

    /**
     * @return list<string>
     */
    private function collectFilePaths(): array
    {
        $paths = [];

        if (Schema::hasTable('documents')) {
            DB::table('documents')->orderBy('id')->select(['id', 'path', 'signed_path'])->chunkById(500, function ($rows) use (&$paths) {
                foreach ($rows as $row) {
                    foreach (['path', 'signed_path'] as $column) {
                        if (! empty($row->{$column})) {
                            $paths[] = $row->{$column};
                        }
                    }
                }
            });
        }

        if (Schema::hasTable('temp_demat_cml_files') && Schema::hasColumn('temp_demat_cml_files', 'file_path')) {
            DB::table('temp_demat_cml_files')->orderBy('id')->select(['id', 'file_path'])->chunkById(500, function ($rows) use (&$paths) {
                foreach ($rows as $row) {
                    if (! empty($row->file_path)) {
                        $paths[] = $row->file_path;
                    }
                }
            });
        }

        return array_values(array_unique($paths));
    }

    private function wipeTable(string $table): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return DB::table($table)->delete();
    }

    private function nullColumn(string $table, string $column): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return DB::table($table)->whereNotNull($column)->update([$column => null]);
    }

    /**
     * @param  list<string>  $extraPaths
     * @return array<string, int|string>
     */
    private function deleteStoredFiles(array $extraPaths): array
    {
        $result = [];

        foreach (['local', 's3'] as $diskName) {
            try {
                $disk = Storage::disk($diskName);
                foreach ($this->fileDirectories() as $directory) {
                    $disk->deleteDirectory($directory);
                    $result[$diskName.':'.$directory] = 'deleted';
                }
                foreach (array_chunk($extraPaths, 100) as $chunk) {
                    $disk->delete($chunk);
                }
                if ($extraPaths !== []) {
                    $result[$diskName.':document paths'] = count($extraPaths);
                }
            } catch (Throwable $e) {
                $result[$diskName] = 'failed: '.$e->getMessage();
            }
        }

        return $result;
    }

    private function deleteLogFiles(): int
    {
        $deleted = 0;
        foreach (File::glob(storage_path('logs/laravel*.log')) ?: [] as $path) {
            if (File::delete($path)) {
                $deleted++;
            }
        }

        return $deleted;
    }
}
