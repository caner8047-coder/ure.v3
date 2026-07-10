<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MobileAuthController extends Controller
{
    /**
     * Authenticate mobile user and return Sanctum token.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'nullable|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Giriş bilgileri hatalı veya geçersiz.'],
            ]);
        }

        $deviceName = $request->input('device_name', 'Mobile Device');
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'surname' => $user->surname,
                'email' => $user->email,
                'personnel_no' => $user->personnel_no ?? $user->PersonelNo,
                'is_admin' => $user->isAdmin(),
            ]
        ]);
    }

    /**
     * Revoke current mobile user token.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Oturum başarıyla kapatıldı.'
        ]);
    }

    /**
     * Get authenticated mobile user info.
     */
    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'surname' => $user->surname,
                'email' => $user->email,
                'personnel_no' => $user->personnel_no ?? $user->PersonelNo,
                'is_admin' => $user->isAdmin(),
            ]
        ]);
    }
}
