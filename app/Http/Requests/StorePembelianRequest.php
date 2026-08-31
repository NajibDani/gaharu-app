<?php

namespace App\Http\Requests;

// use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePembelianRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'gudang_id' => ['required', 'exists:master_gudang,id'],
            'tanggal' => ['required', 'date'],
            'tax_service' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.barang_id' => ['required', \Illuminate\Validation\Rule::exists('master_barang', 'id')->where('is_active', true)],
            'items.*.qty' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'items.*.harga' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'items.*.batch_number' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Supplier wajib dipilih.',
            'gudang_id.required' => 'Gudang tujuan wajib dipilih.',
            'items.required' => 'Minimal harus ada 1 barang pembelian.',
            'items.*.barang_id.required' => 'Barang wajib dipilih.',
            'items.*.qty.required' => 'Qty wajib diisi.',
            'items.*.qty.max' => 'Qty tidak boleh melebihi 99.999.999.',
            'items.*.harga.required' => 'Harga wajib diisi.',
            'items.*.harga.max' => 'Harga tidak boleh melebihi 999.999.999.999.',
            'tanggal.after_or_equal' => 'Tanggal transaksi tidak boleh sebelum hari ini.',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validasi tanggal transaksi minimal hari ini
            if ($this->input('tanggal') && date('Y-m-d', strtotime($this->input('tanggal'))) < date('Y-m-d')) {
                $validator->errors()->add('tanggal', 'Tanggal transaksi tidak boleh sebelum hari ini.');
            }

            // Validasi tax_service jika diisi
            if ($this->input('tax_service')) {
                $taxClean = (float) str_replace('.', '', $this->input('tax_service'));
                if ($taxClean < 0) {
                    $validator->errors()->add('tax_service', 'Biaya tambahan tidak boleh kurang dari 0.');
                }
                if ($taxClean > 999999999999) {
                    $validator->errors()->add('tax_service', 'Biaya tambahan tidak boleh melebihi 999.999.999.999.');
                }
            }
        });
    }
}
