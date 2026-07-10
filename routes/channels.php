<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels — ZemuRetim v3
|--------------------------------------------------------------------------
|
| Kanal yetkilendirme kuralları. Her kanal Spatie RBAC rolleri ve
| departman aidiyeti ile korunmaktadır.
|
*/

// ── Kişisel bildirim kanalı ──
// Her kullanıcı sadece kendi kanalını dinleyebilir.
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->getKey() === (int) $id;
});

// ── Departman kanalı ──
// Admin ve planlama rolü tüm departmanları, personel sadece kendi departmanını dinler.
Broadcast::channel('department.{id}', function ($user, $id) {
    if ($user->isAdmin()) {
        return true;
    }

    if (method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo('view planning')) {
        return true;
    }

    $userDeptId = (int) ($user->BolumAdiNo ?? $user->department_id ?? 0);

    return $userDeptId > 0 && $userDeptId === (int) $id;
});

// ── Genel üretim kanalı ──
// Tüm authenticated kullanıcılar dinleyebilir.
Broadcast::channel('production', function ($user) {
    return $user !== null;
});

// ── Stok uyarı kanalı ──
// Stok görüntüleme veya planlama yetkisi olanlar.
Broadcast::channel('stock-alerts', function ($user) {
    if ($user->isAdmin()) {
        return true;
    }

    if (!method_exists($user, 'hasPermissionTo')) {
        return false;
    }

    return $user->hasPermissionTo('view stocks')
        || $user->hasPermissionTo('view planning');
});

// ── Tahmin/Forecasting kanalı ──
// Sadece planlama ve yönetim rolleri.
Broadcast::channel('forecasting', function ($user) {
    if ($user->isAdmin()) {
        return true;
    }

    return method_exists($user, 'hasPermissionTo')
        && $user->hasPermissionTo('view planning');
});
