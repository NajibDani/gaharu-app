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

    protected function prepareForValidation(): void
    {
        // Bersihkan format harga, qty, dan tax_service sebelum validasi
        $items = $this->input('items', []);
        foreach ($items as $key => $item) {
            if (isset($item['harga'])) {
                $items[$key]['harga'] = $this->parseNumericValue($item['harga']);
            }
            if (isset($item['qty'])) {
                $items[$key]['qty'] = $this->parseNumericValue($item['qty']);
            }
        }
        $this->merge(['items' => $items]);

        if ($this->has('tax_service')) {
            $tax = $this->input('tax_service');
            if ($tax !== null && $tax !== '') {
                $this->merge(['tax_service' => $this->parseNumericValue($tax)]);
            } else {
                $this->merge(['tax_service' => 0]);
            }
        }
    }

    /**
     * Parse input angka yang bisa berupa format Indonesia (1.250,50) atau standar decimal (4.65)
     */
    private function parseNumericValue(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (!is_string($value)) {
            return 0.0;
        }

        $str = trim($value);
        if ($str === '') {
            return 0.0;
        }

        // Jika terdapat titik dan koma, misal: 1.250,50
        if (str_contains($str, '.') && str_contains($str, ',')) {
            $str = str_replace('.', '', $str);
            $str = str_replace(',', '.', $str);
            return (float) $str;
        }

        // Jika hanya terdapat koma, misal: 4,65
        if (str_contains($str, ',')) {
            $str = str_replace(',', '.', $str);
            return (float) $str;
        }

        // Jika hanya terdapat titik:
        // Cek apakah format ribuan (misal 1.000 atau 1.000.000) atau desimal standar (misal 4.65 atau 4.6500)
        if (str_contains($str, '.')) {
            $parts = explode('.', $str);
            // Lebih dari satu titik pasti pemisah ribuan, misal: 1.000.000
            if (count($parts) > 2) {
                return (float) str_replace('.', '', $str);
            }

            // Tepat 1 titik: cek panjang bagian desimal
            // Jika tepat 3 digit dan bukan diawali 0 (misal 1.000 atau 25.000), anggap ribuan KECUALI bagian depan 0 (0.125)
            $integerPart = $parts[0];
            $decimalPart = $parts[1];
            if (strlen($decimalPart) === 3 && $integerPart !== '0' && strlen($integerPart) <= 3) {
                // Pola ribuan Indonesia (contoh: 1.000, 25.000, 100.000)
                return (float) str_replace('.', '', $str);
            }

            // Standar desimal (misal 4.65, 0.5, 12.5)
            return (float) $str;
        }

        return (float) $str;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'gudang_id' => ['required', 'exists:master_gudang,id'],
            'tanggal' => ['required', 'date'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'tax_service' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.barang_id' => ['required', 'exists:master_barang,id'],
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
            'tax_service.numeric' => 'Biaya tambahan harus berupa angka.',
            'tax_service.min' => 'Biaya tambahan tidak boleh kurang dari 0.',
            'tax_service.max' => 'Biaya tambahan tidak boleh melebihi 999.999.999.999.',
            'tanggal.after_or_equal' => 'Tanggal transaksi tidak boleh sebelum hari ini.',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $user = $this->user() ?: auth()->user();
            $isSuperAdmin = $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

            // Validasi tanggal transaksi minimal hari ini (hanya jika bukan Super Admin DAN hanya saat CREATE baru, bukan Edit)
            $isEditing = $this->isMethod('PUT') || $this->isMethod('PATCH') || $this->route('pembelian');
            if (!$isSuperAdmin && !$isEditing) {
                if ($this->input('tanggal') && date('Y-m-d', strtotime($this->input('tanggal'))) < date('Y-m-d')) {
                    $validator->errors()->add('tanggal', 'Tanggal transaksi tidak boleh sebelum hari ini.');
                }
            }
        });
    }
}
