<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- HEADER CARD --}}
            <div class="p-6 bg-white shadow-sm border border-slate-200 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-2xl bg-amber-100 text-amber-800 flex items-center justify-center text-2xl font-bold border border-amber-300 shadow-sm">
                        {{ strtoupper(substr(auth()->user()->nama ?? auth()->user()->name ?? 'U', 0, 1)) }}
                    </div>
                    <div>
                        <h1 class="text-xl font-extrabold text-slate-800">
                            Pengaturan Akun &amp; Keamanan
                        </h1>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-500 text-white">
                                {{ auth()->user()->role->nama ?? auth()->user()->role->name ?? 'Staff' }}
                            </span>
                            <span class="text-xs text-slate-500 font-mono">
                                @ {{ auth()->user()->username }}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="text-xs text-slate-500 bg-slate-50 p-3 rounded-xl border border-slate-200 max-w-xs">
                    🛡️ <strong>Kerahasiaan Akun:</strong> Selalu jaga kerahasiaan username &amp; password Anda, terutama untuk hak akses data penggajian.
                </div>
            </div>

            {{-- FORM INFORMASI & USERNAME --}}
            <div class="p-6 sm:p-8 bg-white shadow-sm border border-slate-200 rounded-2xl">
                <div class="max-w-2xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            {{-- FORM PASSWORD --}}
            <div class="p-6 sm:p-8 bg-white shadow-sm border border-slate-200 rounded-2xl">
                <div class="max-w-2xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
