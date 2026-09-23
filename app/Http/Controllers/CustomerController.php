<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('search');
        $query = Customer::query();

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('nama', 'like', '%' . $search . '%')
                  ->orWhere('jenis', 'like', '%' . $search . '%')
                  ->orWhere('no_hp', 'like', '%' . $search . '%');
            });
        }

        $data = $query->orderBy('id', 'desc')->paginate(10)->withQueryString();
        return view('customer.index', compact('data'));
    }

    public function show($id)
    {
        $customer = Customer::findOrFail($id);
        return view('customer.show', compact('customer'));
    }

    public function create()
    {
        return view('customer.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama'   => 'required|string|max:255',
            'jenis'  => 'required|string|max:100',
            'no_hp'  => 'nullable|string|max:50',
            'alamat' => 'nullable|string',
        ]);

        Customer::create([
            'nama'   => $request->nama,
            'jenis'  => $request->jenis,
            'no_hp'  => $request->no_hp ?? '-',
            'alamat' => $request->alamat ?? '-',
        ]);

        return redirect()->route('customer.index')
            ->with('success', 'Data Konsumen / Pelanggan berhasil ditambahkan');
    }

    public function edit($id)
    {
        $data = Customer::findOrFail($id);
        return view('customer.edit', compact('data'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nama'   => 'required|string|max:255',
            'jenis'  => 'required|string|max:100',
            'no_hp'  => 'nullable|string|max:50',
            'alamat' => 'nullable|string',
        ]);

        $data = Customer::findOrFail($id);
        $data->update([
            'nama'   => $request->nama,
            'jenis'  => $request->jenis,
            'no_hp'  => $request->no_hp ?? '-',
            'alamat' => $request->alamat ?? '-',
        ]);

        return redirect()->route('customer.index')
            ->with('success', 'Data berhasil diupdate');
    }

    public function destroy($id)
    {
        $data = Customer::findOrFail($id);
        $data->delete();

        return redirect()->route('customer.index')
            ->with('success', 'Data berhasil dihapus');
    }
}