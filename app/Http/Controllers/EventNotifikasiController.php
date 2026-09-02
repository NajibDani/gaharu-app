<?php

namespace App\Http\Controllers;

use App\Models\EventNotifikasi;
use Illuminate\Http\Request;

class EventNotifikasiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $query = EventNotifikasi::with('creator')->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', "%{$search}%")
                  ->orWhere('pesan', 'like', "%{$search}%")
                  ->orWhere('menu_target', 'like', "%{$search}%");
            });
        }

        $events = $query->paginate(10)->withQueryString();
        return view('event-notifikasi.index', compact('events'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('event-notifikasi.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'judul'           => 'required|string|max:255',
            'pesan'           => 'required|string',
            'menu_target'     => 'required|string',
            'tanggal_mulai'   => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'tipe_icon'       => 'required|in:warning,info,success,question,error',
            'is_active'       => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');
        $validated['created_by'] = auth()->id();

        EventNotifikasi::create($validated);

        return redirect()->route('event-notifikasi.index')->with('success', 'Event notifikasi berhasil dibuat.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $event = EventNotifikasi::findOrFail($id);
        return view('event-notifikasi.edit', compact('event'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $event = EventNotifikasi::findOrFail($id);

        $validated = $request->validate([
            'judul'           => 'required|string|max:255',
            'pesan'           => 'required|string',
            'menu_target'     => 'required|string',
            'tanggal_mulai'   => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'tipe_icon'       => 'required|in:warning,info,success,question,error',
            'is_active'       => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $event->update($validated);

        return redirect()->route('event-notifikasi.index')->with('success', 'Event notifikasi berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $event = EventNotifikasi::findOrFail($id);
        $event->delete();

        return redirect()->route('event-notifikasi.index')->with('success', 'Event notifikasi berhasil dihapus.');
    }

    /**
     * Toggle status aktif event
     */
    public function toggleActive(string $id)
    {
        $event = EventNotifikasi::findOrFail($id);
        $event->update(['is_active' => !$event->is_active]);

        $statusText = $event->is_active ? 'diaktifkan' : 'dinonaktifkan';
        return back()->with('success', "Event '{$event->judul}' berhasil {$statusText}.");
    }

    /**
     * Endpoint API JSON untuk mengambil event aktif pada menu tertentu
     */
    public function getActiveEvents(Request $request)
    {
        $menu = $request->query('menu', 'pembelian');
        $events = EventNotifikasi::aktifUntukMenu($menu)->get();

        return response()->json($events);
    }
}
