<section>
    <header>
        <div class="flex items-center gap-2">
            <span class="text-xl">🔑</span>
            <h2 class="text-lg font-bold text-gray-900">
                Ubah Password Akun
            </h2>
        </div>

        <p class="mt-1 text-sm text-gray-600">
            Pastikan akun Anda menggunakan kata sandi (password) yang kuat dan aman untuk menjaga kerahasiaan seluruh data perusahaan dan penggajian.
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('put')

        <div>
            <x-input-label for="update_password_current_password" :value="__('Password Saat Ini (Lama)')" />
            <x-text-input id="update_password_current_password" name="current_password" type="password" class="mt-1 block w-full" autocomplete="current-password" placeholder="Masukkan kata sandi lama Anda" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password" :value="__('Password Baru')" />
            <x-text-input id="update_password_password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" placeholder="Minimal 8 karakter" />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" :value="__('Konfirmasi Password Baru')" />
            <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" placeholder="Ulangi kata sandi baru" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button style="background: #DE8958 !important;">{{ __('Simpan Password Baru') }}</x-primary-button>

            @if (session('status') === 'password-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 3000)"
                    class="text-sm font-semibold text-emerald-600 flex items-center gap-1"
                >
                    <span>✓</span> {{ __('Password berhasil diperbarui!') }}
                </p>
            @endif
        </div>
    </form>
</section>
