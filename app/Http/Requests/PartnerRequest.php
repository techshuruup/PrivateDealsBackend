<?php

namespace App\Http\Requests;

use App\Enums\GenderEnum;
use App\Enums\PartnerTypeEnum;
use App\Helpers\CommonHelper;
use App\Helpers\UtillsHelper;
use App\Models\PartnerModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class PartnerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'is_primary_access' => $this->boolean('is_primary_access'),
            'is_secondary_access' => $this->boolean('is_secondary_access'),
            'is_preipo_access' => $this->boolean('is_preipo_access'),
        ]);
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $accessFields = [
                $this->input('is_primary_access'),
                $this->input('is_secondary_access'),
                $this->input('is_preipo_access'),
            ];

            // Ensure at least one of the fields is true
            if (!in_array(true, $accessFields, true)) {
                $validator->errors()->add(
                    'permission',
                    'At least one of Primary Startup, Secondary Startup, or Pre-IPO Companies permission must be Selected.'
                );
            }
        });
    }


    public function rules(): array
    {
        $uuid = false;
        $request = request();
        if ($request->_method == 'PUT') {
            $uuid = $this->route('uuid');
        }
        $rules = [
            'name' => 'required|string|max:255',
            'mobile_number' => [
                'required',
                'numeric',
                'digits:10',
                Rule::unique((new PartnerModel())->getTable())->where(function ($query) use ($uuid) {
                    if ($uuid) {
                        return $query->where('is_deleted', '0')->where('uuid', '!=', $uuid);
                    }
                    return $query->where('is_deleted', '0');
                }),
            ],
            'email' => [
                'required',
                'email',
                Rule::unique((new PartnerModel)->getTable())->where(function ($query) use ($uuid) {
                    if ($uuid) {
                        return $query->where('is_deleted', '0')->where('uuid', '!=', $uuid);
                    }
                    return $query->where('is_deleted', '0');
                }),
            ],
            'gender'        => 'required',
            'logo' => 'nullable|image|mimes:' . CommonHelper::appSettings('file_image_extensions_allowed') . '|max:' . UtillsHelper::maxFileImageSizeInKB(),
            'is_primary_access'     => 'required|boolean',
            'is_secondary_access'   => 'required|boolean',
            'is_preipo_access'      => 'required|boolean',
        ];


        if(!$request->routeIs('*.relationalManager.*')){
            $rules['commission'] = 'required|numeric|between:0,99.99';
        }else{
            $rules['partner_id'] = ['required'];
        }
        
        if (!$uuid) {
            $rules['password'] = 'required';
        }
        return $rules;
    }
    /**
     * Override the response returned when validation fails.
     *
     * @param Validator $validator
     * @return void
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {

        $response =  redirect()->back()->withInput()
            ->with('error', 'Please check form errors.')->withErrors($validator);

        throw new HttpResponseException($response);
    }
}
