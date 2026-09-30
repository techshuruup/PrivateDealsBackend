<?php

namespace App\Helpers;

use App\Models\CoreFirebaseDeviceTokenModel;
use App\Models\GlobalSettingModel;
use App\Models\StartupModel;
use App\Models\InvestorModel;
use App\Models\PortfolioModel;
use App\Models\StartupSharePriceModel;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

class CommonHelper
{

    static function getUserFromSanctum(): int|bool
    {
        $request = request();
        $token = $request->bearerToken();
        if ($token) {
            $accessToken = PersonalAccessToken::findToken($token);
            if ($accessToken) {
                $user = $accessToken->tokenable;
                return $user->id;
            }
        }
        return false;
    }

    static function appSettings($key): string
    {
        return app(GlobalSettingModel::class)->get($key);
    }

    /**
     * Admin-managed processing fee percent (app_settings.processing_fee_percentage).
     * Allowed range is 1–100. Missing or out-of-range values fall back to 1.
     */
    public static function processingFeePercentage(): float
    {
        $raw = self::appSettings('processing_fee_percentage');
        if (!is_numeric($raw)) {
            return 1.0;
        }

        $value = (float) $raw;
        if ($value < 1 || $value > 100) {
            return 1.0;
        }

        return $value;
    }

    public static function generateFileName(): string
    {
        return microtime(true) . '-' . Str::random(length: 60);
    }

    /**
     * Normalize company financial custom_data values into a 2D matrix:
     * [ ['Particulars', 'FY23', 'FY24'], ['Revenue', 100, 120], ... ]
     *
     * Accepts already-matrix data or year-keyed objects from AI ingest:
     * { "FY23": { "revenue": 100 }, "FY24": { "revenue": 120 } }
     *
     * @param  mixed  $values
     * @return list<list<mixed>>
     */
    public static function normalizeFinancialValuesToMatrix(mixed $values): array
    {
        if (is_string($values)) {
            $decoded = json_decode($values, true);
            $values = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }
        if (!is_array($values) || $values === []) {
            return [];
        }

        // Already a list of rows with first row headers
        if (array_is_list($values) && isset($values[0]) && is_array($values[0])) {
            return array_values($values);
        }

        // Year-keyed metrics: { FY23: { metric: n }, ... }
        $looksYearKeyed = false;
        foreach ($values as $yearKey => $metrics) {
            if (is_string($yearKey) && is_array($metrics) && !array_is_list($metrics)) {
                $looksYearKeyed = true;
                break;
            }
        }
        if (!$looksYearKeyed) {
            return [];
        }

        $years = array_keys($values);
        usort($years, function ($a, $b) {
            preg_match('/(\d+)/', (string) $a, $ma);
            preg_match('/(\d+)/', (string) $b, $mb);
            $na = isset($ma[1]) ? (int) $ma[1] : 0;
            $nb = isset($mb[1]) ? (int) $mb[1] : 0;
            if ($na === $nb) {
                return strcmp((string) $a, (string) $b);
            }

            return $na <=> $nb;
        });

        $metricKeys = [];
        foreach ($years as $year) {
            $metrics = is_array($values[$year] ?? null) ? $values[$year] : [];
            foreach ($metrics as $metricKey => $_) {
                if (!in_array($metricKey, $metricKeys, true)) {
                    $metricKeys[] = $metricKey;
                }
            }
        }

        $table = [array_merge(['Particulars'], $years)];
        foreach ($metricKeys as $metricKey) {
            $label = ucwords(str_replace(['_', '-'], ' ', (string) $metricKey));
            $row = [$label];
            foreach ($years as $year) {
                $metrics = is_array($values[$year] ?? null) ? $values[$year] : [];
                $cell = $metrics[$metricKey] ?? '';
                if (is_bool($cell)) {
                    $cell = $cell ? 'Yes' : 'No';
                } elseif (is_array($cell)) {
                    $cell = json_encode($cell);
                }
                $row[] = $cell;
            }
            $table[] = $row;
        }

        return $table;
    }

    public static function setEmptyValues($var, $isInt = false): string
    {
        // print_r($var);die;
        if (!isset($var) || $var == NULL || $var == '') {
            if ($isInt) {
                return 0;
            }
            return '';
        } else {
            return $var;
        }
    }

    public static function getInvested($startup)
    {
        // Changed At 10-01-2023
        $invested = InvestorModel::where('is_mandate', '>=', '1')->where('startup', $startup->id)->get()->sum('amount');
        // Changed At 10-01-2023

        $ask = 0;
        $done = 0;
        $per = 0;
        if ($startup->ask != 0) {
            $ask = $startup->ask;
        }
        if ($invested != 0) {
            $done = $invested;
            if ($ask != 0) {
                $per = ($done / $ask) * 100;
            }
        }

        if ($startup->status == '6') {
            if ($per == 0) {
                $per = 100;
            }
        }

        return array('ask' => $ask, 'done' => $done, 'percentage' => $per);
    }

    // public static function sendSSA($investment){
    //     $startup = StartupModel::where('id',$investment->startup_id)->with('details')->first();
    //     if($startup){
    //         if($startup->details->ssa_id != '' && $startup->details->ssa_id != NULL && $startup->details->ssa_id != 'NA'){
    //             $doc = CommonHelper::sendSSADocNew($investment->id);
    //             if ($doc->getStatusCode() == "200") {
    //                 $getDocResponse = json_decode($doc->getBody()->getContents());
    //                 $ssaId = $getDocResponse->id;

    //                 AllDocuments::create([
    //                     'utype'		    => 'both',
    //                     'investment' 	=> $investment->id,
    //                     'investor' 	    => $investment->investor_id,
    //                     'startup' 	    => $investment->startup_id,
    //                     'did'		    => $ssaId,
    //                     'file'		    => '',
    //                     'type'		    => 'ssa',
    //                     'status'	    => '0',
    //                     'cat'		    => Common::_now(),
    //                     'uat'		    => Common::_now()
    //                 ]);

    //                 $ssa = new PrimaryTransactionSsaModel;
    //                 $ssa->transaction_id = $investment->id;
    //                 $ssa->document_id = $ssaId;
    //                 $ssa->save();

    //             }else{
    //                 $getDocResponse = json_decode($doc->getBody()->getContents());
    //                 DigioErrors::create([
    //                     'type'		=> 'ssa',
    //                     'error' 	=> $getDocResponse->message
    //                 ]);
    //             }
    //         }
    //     }
    // }
    public static function getIndianCurrencyinWords(float $number)
    {
        $decimal = round($number - ($no = floor($number)), 2) * 100;
        $hundred = null;
        $digits_length = strlen($no);
        $i = 0;
        $str = array();
        $words = array(
            0 => '',
            1 => 'one',
            2 => 'two',
            3 => 'three',
            4 => 'four',
            5 => 'five',
            6 => 'six',
            7 => 'seven',
            8 => 'eight',
            9 => 'nine',
            10 => 'ten',
            11 => 'eleven',
            12 => 'twelve',
            13 => 'thirteen',
            14 => 'fourteen',
            15 => 'fifteen',
            16 => 'sixteen',
            17 => 'seventeen',
            18 => 'eighteen',
            19 => 'nineteen',
            20 => 'twenty',
            30 => 'thirty',
            40 => 'forty',
            50 => 'fifty',
            60 => 'sixty',
            70 => 'seventy',
            80 => 'eighty',
            90 => 'ninety'
        );
        $digits = array('', 'hundred', 'thousand', 'lakh', 'crore');
        while ($i < $digits_length) {
            $divider = ($i == 2) ? 10 : 100;
            $number = floor($no % $divider);
            $no = floor($no / $divider);
            $i += $divider == 10 ? 1 : 2;
            if ($number) {
                $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
                $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
                $str[] = ($number < 21) ? $words[$number] . ' ' . $digits[$counter] . $plural . ' ' . $hundred : $words[floor($number / 10) * 10] . ' ' . $words[$number % 10] . ' ' . $digits[$counter] . $plural . ' ' . $hundred;
            } else $str[] = null;
        }
        $Rupees = implode('', array_reverse($str));
        $paise = ($decimal > 0) ? "." . ($words[$decimal / 10] . " " . $words[$decimal % 10]) . ' Paise' : '';
        return ($Rupees ? $Rupees . 'Rupees ' : '') . $paise;
    }
    public static function _now($type = false)
    {
        if ($type) {
            return date('Y-m-d');
        } else {
            return date('Y-m-d H:i:s');
        }
    }
    public static function dtplusDays($date, $days)
    {
        if ($date != NULL) {
            return Carbon::parse($date)->addDays($days)->format('Y-m-d');
        } else {
            return NULL;
        }
    }
    public static function isLoggedInInvestor()
    {
        if (Auth::guard('investor')->check()) {
            return true;
        } else {
            return false;
        }
    }
    public static function getInvestor()
    {
        return Auth::guard('investor')->user();
    }

    public static function isKycDoneInvestor()
    {
        $investor = Auth::guard('investor')->user();
        if ($investor->preipo_kyc_status == 1) {
            return true;
        } else {
            return false;
        }
    }
    public static function isValidRow($item, $key)
    {
        if ($item) {
            return $item->$key;
        } else {
            return "";
        }
    }

    static function updatePortfolioSecondary($buyer, $seller, $instrument, $startup, $shares, $transaction)
    {
        if ($buyer && $seller && $startup) {
            $portfolio = PortfolioModel::where('instrument', $instrument)->where('startup_id', $startup->id)->where('investor_id', $seller->id)->first();
            if ($portfolio) {
                $portfolio->shares = $portfolio->shares - $shares;
                $portfolio->save();

                $transaction->portfolio_id = $portfolio->id;
                $transaction->save();
            }
        }
    }


    static function deleteInvestors(InvestorModel $investor)
    {
        CoreFirebaseDeviceTokenModel::where('user_id', $investor->id)->where('user_type', InvestorModel::class)
            ->delete();

        if ($investor) {
            $investor->is_deleted = '1';
            $investor->save();
        }
        $investor->tokens()->delete();
    }
}
