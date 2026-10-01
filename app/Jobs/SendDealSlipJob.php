<?php

namespace App\Jobs;

use App\Models\PreIpoModel;
use App\Helpers\DocumentHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendDealSlipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $transactionId;
    protected $ignoreKycCheck = false;

    public function __construct($transactionId, $ignoreKycCheck = false)
    {
        $this->transactionId = $transactionId;
        $this->ignoreKycCheck = $ignoreKycCheck;
    }

    public function handle(): void
    {
        $transaction = PreIpoModel::find($this->transactionId);
        if ($transaction) {
            if ($transaction->usesOrderStep()) {
                return;
            }
            DocumentHelper::dealSlipDocumentSend($transaction, $this->ignoreKycCheck);
        }
    }
}
