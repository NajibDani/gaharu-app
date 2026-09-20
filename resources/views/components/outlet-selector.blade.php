@props(['selectedOutlet' => 'Gaharu', 'route' => null, 'extraParams' => []])

@php
    $user = auth()->user();
    $roleName = $user->role->nama ?? '';
    
    // Lock outlet if specific manager
    $isLocked = false;
    if ($roleName === 'Kepala Outlet Gaharu') {
        $selectedOutlet = 'Gaharu';
        $isLocked = true;
    } elseif ($roleName === 'Kepala Outlet Kejingga') {
        $selectedOutlet = 'Kejingga';
        $isLocked = true;
    }

    $outlets = ['Gaharu', 'Kejingga'];
@endphp

<div class="outlet-selector-card bg-white px-4 py-2.5 rounded-xl shadow-sm border border-amber-200 mb-3" style="border: 1px solid #fde68a; border-radius: 10px; background: #ffffff;">
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
        <div class="d-flex align-items-center gap-2.5">
            <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px; background-color: #fef3c7; color: #92400e; border-radius: 8px; font-size: 1rem;">
                <i class="bi bi-shop"></i>
            </div>
            <div>
                <span class="fw-bold text-slate-800" style="font-size: 0.85rem;">Outlet Penggajian:</span>
                <span class="text-muted ms-1 d-none d-md-inline" style="font-size: 0.75rem;">Pilih cabang outlet aktif</span>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            @foreach($outlets as $o)
                @php
                    $isActive = (strtolower($selectedOutlet) == strtolower($o));
                    $queryParams = array_merge(request()->query(), $extraParams, ['outlet' => $o]);
                    $targetUrl = $route ? route($route, $queryParams) : request()->fullUrlWithQuery(['outlet' => $o]);
                @endphp

                @if($isLocked)
                    @if($isActive)
                        <span class="btn fw-bold d-inline-flex align-items-center gap-1.5 px-3 py-1"
                              style="background-color: #78350f; color: #ffffff !important; border: 1.5px solid #78350f; border-radius: 8px; font-size: 0.8rem; cursor: default;">
                            <i class="bi bi-shop text-warning"></i>
                            <span>Outlet {{ $o }}</span>
                        </span>
                    @endif
                @else
                    <a href="{{ $targetUrl }}" 
                       class="btn fw-bold d-inline-flex align-items-center gap-1.5 px-3 py-1 text-decoration-none outlet-btn-tab {{ $isActive ? 'active-outlet' : 'inactive-outlet' }}"
                       style="{{ $isActive 
                           ? 'background-color: #78350f; color: #ffffff !important; border: 1.5px solid #78350f; border-radius: 8px; font-size: 0.8rem; box-shadow: 0 2px 6px rgba(120, 53, 15, 0.2);' 
                           : 'background-color: #ffffff; color: #334155 !important; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.8rem;' }}">
                        <i class="bi {{ $isActive ? 'bi-check-circle-fill' : 'bi-building' }}" style="{{ $isActive ? 'color: #fde68a;' : 'color: #64748b;' }}"></i>
                        <span style="{{ $isActive ? 'color: #ffffff !important;' : 'color: #1e293b !important;' }}">Outlet {{ $o }}</span>
                    </a>
                @endif
            @endforeach
        </div>
    </div>
</div>

<style>
.outlet-btn-tab.inactive-outlet:hover {
    background-color: #fffbeb !important;
    border-color: #f59e0b !important;
    color: #92400e !important;
}
.outlet-btn-tab.inactive-outlet:hover span {
    color: #92400e !important;
}
.outlet-btn-tab.inactive-outlet:hover i {
    color: #f59e0b !important;
}
</style>
