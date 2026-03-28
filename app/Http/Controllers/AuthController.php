<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

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

        $user = User::where('email', $credentials['email'])->first();

        if ($user) {
            // Check if password uses old SHA256 format (length is 64 hex chars and doesn't start with $2y$)
            if (strlen($user->password) === 64 && !str_starts_with($user->password, '$2y$')) {
                // Verify with SHA256, case-insensitive check to be safe against X2 vs x2 formatting in C#
                if (strtolower(hash('sha256', $credentials['password'])) === strtolower($user->password)) {
                    // Migrate to secure Bcrypt
                    $user->password = Hash::make($credentials['password']);
                    $user->save();
                    
                    Auth::login($user);
                    return redirect()->intended('/dashboard');
                }
            } else {
                // Normal Laravel Bcrypt verification
                if (Hash::check($credentials['password'], $user->password)) {
                    Auth::login($user);
                    return redirect()->intended('/dashboard');
                }
            }
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect('/login');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:6|confirmed',
        ]);

        $user = Auth::user();
        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Mevcut şifre hatalı.']);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return back()->with('success', 'Şifreniz başarıyla güncellendi.');
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return back()->withErrors(['email' => 'Bu e-posta adresi ile kayıtlı hesap bulunamadı.']);
        }

        // Geçici şifre oluştur ve kaydet
        $tempPassword = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->password = Hash::make($tempPassword);
        $user->save();

        return back()->with('status', 'Geçici şifreniz: ' . $tempPassword . ' — Lütfen giriş yaptıktan sonra şifrenizi değiştirin.');
    }
}
