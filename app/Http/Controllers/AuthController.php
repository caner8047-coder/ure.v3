<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Personnel;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $credentials['email'] = trim($credentials['email']);
        $user = Personnel::where('Mail', $credentials['email'])->first();

        if ($user && $this->passwordMatches($credentials['password'], $user->Sifre)) {
            Auth::login($user);
            $request->session()->regenerate();

            return $this->attachLegacyCookies(
                redirect()->intended($this->intendedHome($user)),
                $user,
                $credentials['email'],
                $credentials['password'],
                $request->boolean('remember')
            );
        }

        return back()->withErrors([
            'email' => 'Girilen e-posta veya sifre hatali.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->clearLegacyCookies(redirect('/login'));
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:6|confirmed',
        ]);

        /** @var Personnel $user */
        $user = Auth::user();

        if (!$user || !$this->passwordMatches($request->current_password, $user->Sifre)) {
            return back()->withErrors(['current_password' => 'Mevcut şifre hatalı.']);
        }

        $user->Sifre = hash('sha256', $request->new_password);
        $user->save();

        return back()->with('success', 'Şifreniz başarıyla güncellendi.');
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = Personnel::where('Mail', $request->email)->first();
        if (!$user) {
            return back()->withErrors(['email' => 'Bu e-posta adresi ile kayıtlı hesap bulunamadı.']);
        }

        // Geçici şifre oluştur ve kaydet
        $tempPassword = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->Sifre = hash('sha256', $tempPassword);
        $user->save();

        return back()->with('status', 'Geçici şifreniz: ' . $tempPassword . ' — Lütfen giriş yaptıktan sonra şifrenizi değiştirin.');
    }

    private function passwordMatches(string $plainText, ?string $storedHash): bool
    {
        $storedHash = trim((string) $storedHash);
        if ($storedHash === '') {
            return false;
        }

        $isLaravelHash = str_starts_with($storedHash, '$2y$')
            || str_starts_with($storedHash, '$2a$')
            || str_starts_with($storedHash, '$argon2');

        if ($isLaravelHash) {
            return Hash::check($plainText, $storedHash);
        }

        return strtolower(hash('sha256', $plainText)) === strtolower($storedHash);
    }

    private function intendedHome(Personnel $user): string
    {
        return $user->isAdmin() ? route('admin.index') : route('user.dashboard');
    }

    private function attachLegacyCookies(
        RedirectResponse $response,
        Personnel $user,
        string $email,
        string $plainPassword,
        bool $remember
    ): RedirectResponse {
        $personelNo = (int) ($user->PersonelNo ?? 0);
        $bolumAdiNo = (int) ($user->BolumAdiNo ?? 0);
        $bolumAdi = trim((string) (DB::table('tbBolum')->where('No', $bolumAdiNo)->value('BolumAdi') ?? ''));

        $longMinutes = 60 * 24 * 15;
        $shortMinutes = 60;
        $rememberMinutes = $remember ? $longMinutes : $shortMinutes;

        foreach ([
            ['PersonelNo', (string) $personelNo, $longMinutes],
            ['BolumAdi', urlencode($bolumAdi), $longMinutes],
            ['BolumAdiNo', (string) $bolumAdiNo, $rememberMinutes],
            ['userid', $email, $rememberMinutes],
            ['pwd', hash('sha256', $plainPassword), $rememberMinutes],
        ] as [$name, $value, $minutes]) {
            $response->cookie(
                cookie(
                    $name,
                    $value,
                    $minutes,
                    '/',
                    null,
                    false,
                    false,
                    false,
                    'lax'
                )
            );
        }

        return $response;
    }

    private function clearLegacyCookies(RedirectResponse $response): RedirectResponse
    {
        foreach (['userid', 'pwd', 'PersonelNo', 'BolumAdi', 'BolumAdiNo'] as $cookieName) {
            $response->withoutCookie($cookieName, '/');
        }

        return $response;
    }
}
