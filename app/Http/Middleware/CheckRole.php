<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        // Ambil nama role user yang login (secara aman)
        $userRole = Auth::user()->role?->nama;

        if (!$userRole) {
            abort(403, 'Anda tidak memiliki role yang didefinisikan.');
        }

        // Normalisasi alias nama role
        $roleMap = [
            'Superadmin'          => 'Super Admin',
            'Administrator'       => 'Super Admin',
            'Bagian Produksi'     => 'Central Kitchen',
            'Kepala Outlet Gaharu'  => 'Operasional Gaharu',
            'Kepala Outlet Kejingga' => 'Operasional Kejingga',
            'Direktur Keuangan'   => 'Management',
        ];

        $normalizedUserRole = $roleMap[$userRole] ?? $userRole;

        // Super Admin memiliki hak akses penuh ke semua menu dan route tanpa kecuali
        if (in_array($normalizedUserRole, ['Super Admin', 'Superadmin', 'Administrator'])) {
            return $next($request);
        }

        // Cek apakah parameter berisi opsi strict (misal: 'role:strict,HRD' atau khusus HRD)
        $isStrict = in_array('strict', $roles, true);
        $targetRoles = array_filter($roles, fn($r) => $r !== 'strict');

        // Cek apakah punya izin (membandingkan role asli maupun normalized)
        foreach ($targetRoles as $allowedRole) {
            $normalizedAllowed = $roleMap[$allowedRole] ?? $allowedRole;
            if (
                $userRole === $allowedRole ||
                $normalizedUserRole === $normalizedAllowed ||
                $userRole === $normalizedAllowed ||
                $normalizedUserRole === $allowedRole
            ) {
                return $next($request);
            }
        }

        abort(403, 'Anda tidak memiliki hak akses ke halaman ini.');
    }

}