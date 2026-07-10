<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Http\Resources\StockAlertResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class MobileDashboardController extends Controller
{
    /**
     * Get recent notifications for authenticated worker.
     */
    public function notifications(Request $request)
    {
        $user = Auth::user();
        
        $notifications = DB::table('notifications')
            ->where('notifiable_id', $user->id)
            ->where('notifiable_type', get_class($user))
            ->orderBy('created_at', 'desc')
            ->limit(30)
            ->get();

        return NotificationResource::collection($notifications);
    }

    /**
     * Mark a specific notification as read.
     */
    public function readNotification(Request $request, $id)
    {
        $user = Auth::user();

        $updated = DB::table('notifications')
            ->where('id', $id)
            ->where('notifiable_id', $user->id)
            ->update([
                'read_at' => now(),
            ]);

        if (!$updated) {
            return response()->json([
                'success' => false,
                'message' => 'Bildirim bulunamadı.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bildirim okundu olarak işaretlendi.'
        ]);
    }

    /**
     * Get critical stock alerts (components where stock quantity is below min threshold).
     */
    public function stockAlerts(Request $request)
    {
        // Query components where current stock in tbStoklar is less than MinAdet in tbAraUrun
        $alerts = DB::table('tbAraUrun as au')
            ->join('tbBolum as b', 'au.BolumAdiNo', '=', 'b.No')
            ->leftJoin('tbStoklar as s', 'au.No', '=', 's.AraUrunNo')
            ->select(
                'au.No as id',
                'au.No as component_id',
                'au.AraUrunAdi as component_name',
                DB::raw('COALESCE(s.Adet, 0) as current_quantity'),
                DB::raw('COALESCE(au.MinAdet, 0) as min_threshold')
            )
            ->whereRaw('COALESCE(s.Adet, 0) < COALESCE(au.MinAdet, 0)')
            ->orderBy('component_name', 'asc')
            ->get();

        return StockAlertResource::collection($alerts);
    }
}
