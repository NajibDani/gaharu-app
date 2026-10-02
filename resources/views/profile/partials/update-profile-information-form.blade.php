<section>
    <header>
        <div class="flex items-center gap-2">
            <span class="text-xl">👤</span>
            <h2 class="text-lg font-bold text-gray-900">
                Informasi Akun &amp; Username
            </h2>
        </div>

        <p class="mt-1 text-sm text-gray-600">
            Perbarui nama pengguna (username) dan informasi profil Anda untuk menjaga keamanan dan kerahasiaan data (khususnya data gaji dan HRD).
        </p>

        @if(auth()->user()->role && in_array(auth()->user()->role->nama, ['HRD', 'Super Admin', 'Superadmin', 'Administrator']))
            <div class="mt-3 p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800 flex items-start gap-2">
                <span class="text-base leading-none">🔒</span>
                <div>
                    <strong>Pemberitahuan Kerahasiaan (Data HRD &amp; Payroll):</strong>
                    <div class="mt-0.5 text-amber-700">
                        Demi menjaga kerahasiaan nominal dan rincian gaji karyawan, pastikan <strong>Username</strong> dan <strong>Password</strong> akun Anda bersifat rahasia dan tidak dibagikan ke orang lain. Ganti secara berkala jika diperlukan.
                    </div>
                </div>
            </div>
        @endif
    </header>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="nama" :value="__('Nama Lengkap')" />
            <x-text-input id="nama" name="nama" type="text" class="mt-1 block w-full" :value="old('nama', $user->nama ?? $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('nama')" />
            <x-input-error class="mt-1" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="username" :value="__('Username (Digunakan untuk Login)')" />
            <x-text-input id="username" name="username" type="text" class="mt-1 block w-full font-mono" :value="old('username', $user->username)" required autocomplete="username" />
            <p class="mt-1 text-xs text-gray-500">Username digunakan untuk masuk (login) ke aplikasi. Gunakan kombinasi huruf, angka, underscore, atau strip tanpa spasi.</p>
            <x-input-error class="mt-2" :messages="$errors->get('username')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email (Opsional)')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" autocomplete="email" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button style="background: #DE8958 !important;">{{ __('Simpan Perubahan Akun') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 3000)"
                    class="text-sm font-semibold text-emerald-600 flex items-center gap-1"
                >
                    <span>✓</span> {{ __('Berhasil disimpan.') }}
                </p>
            @endif
        </div>
    </form>
</section>
