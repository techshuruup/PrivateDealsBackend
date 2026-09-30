<?php

namespace Tests\Unit;

use App\Helpers\CommonHelper;
use App\Http\Controllers\Web\Admin\Setting\SettingController;
use App\Models\AppSettingsModel;
use App\Models\GlobalSettingModel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

class ProcessingFeeRulesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_processing_fee_percentage_reads_settings_and_falls_back_outside_1_to_100(): void
    {
        $this->assertTrue(
            AppSettingsModel::where('key', 'processing_fee_percentage')->exists(),
            'app_settings.processing_fee_percentage is missing'
        );

        $this->rebindFee('1');
        $this->assertSame(1.0, CommonHelper::processingFeePercentage());

        $this->rebindFee('1.5');
        $this->assertSame(1.5, CommonHelper::processingFeePercentage());

        $this->rebindFee('100');
        $this->assertSame(100.0, CommonHelper::processingFeePercentage());

        $this->rebindFee('0');
        $this->assertSame(1.0, CommonHelper::processingFeePercentage());

        $this->rebindFee('101');
        $this->assertSame(1.0, CommonHelper::processingFeePercentage());

        $this->rebindFee('nope');
        $this->assertSame(1.0, CommonHelper::processingFeePercentage());

        $this->rebindFee(null);
        $this->assertSame(1.0, CommonHelper::processingFeePercentage());
    }

    public function test_admin_settings_validation_accepts_1_1_5_and_100_and_rejects_0_and_101(): void
    {
        foreach ([1, 1.5, '1.5', 100] as $value) {
            $errors = $this->settingErrors($value);
            $this->assertFalse(
                $errors->has('processing_fee_percentage'),
                'Expected processing_fee_percentage '.$value.' to pass. Got: '.json_encode($errors->get('processing_fee_percentage'))
            );
        }

        foreach ([0, '0', 101, '101'] as $value) {
            $errors = $this->settingErrors($value);
            $this->assertTrue(
                $errors->has('processing_fee_percentage'),
                'Expected processing_fee_percentage '.$value.' to fail. Bag: '.json_encode($errors->messages())
            );
        }
    }

    private function settingErrors(mixed $fee): \Illuminate\Support\MessageBag
    {
        $request = Request::create('/admin/settings', 'POST', [
            'processing_fee_percentage' => $fee,
        ]);
        $session = $this->app['session.store'];
        if (!$session->isStarted()) {
            $session->start();
        }
        $request->setLaravelSession($session);
        $this->app->instance('request', $request);

        app(SettingController::class)->store($request);

        $errors = $session->get('errors');
        $this->assertNotNull($errors, 'Settings store did not return validation errors');

        return $errors->getBag('default');
    }

    private function rebindFee(?string $value): void
    {
        $settings = AppSettingsModel::query()
            ->get()
            ->reject(fn ($row) => $row->key === 'processing_fee_percentage')
            ->values();

        if ($value !== null) {
            $row = new AppSettingsModel();
            $row->key = 'processing_fee_percentage';
            $row->value = $value;
            $settings->push($row);
        }

        $this->app->instance(GlobalSettingModel::class, new GlobalSettingModel($settings));
    }
}
