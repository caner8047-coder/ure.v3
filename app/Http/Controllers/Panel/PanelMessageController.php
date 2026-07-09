<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PanelMessageController extends Controller
{
    private function personelNo(Request $request): int
    {
        return intval($request->user()->PersonelNo ?? $request->user()->id ?? 0);
    }

    public function messages(Request $request)
    {
        $personelNo = $this->personelNo($request);
        $personel = DB::table('tbPersonel')->where('PersonelNo', $personelNo)->first();
        $bolumAdiNo = intval($personel->BolumAdiNo ?? 0);
        $hasOkundu = Schema::hasColumn('tbIletisim', 'Okundu');
        $hasAdSoyad = Schema::hasColumn('tbIletisim', 'AdSoyad');
        $hasSaat = Schema::hasColumn('tbIletisim', 'Saat');

        $senderExpression = $hasAdSoyad
            ? "COALESCE(NULLIF(m.AdSoyad, ''), CONCAT(IFNULL(p.Ad, ''), ' ', IFNULL(p.Soyad, '')), 'Sistem')"
            : "COALESCE(NULLIF(CONCAT(IFNULL(p.Ad, ''), ' ', IFNULL(p.Soyad, '')), ' '), 'Sistem')";
        $okunduExpression = $hasOkundu ? 'm.Okundu' : '0';
        $saatExpression = $hasSaat ? "IFNULL(m.Saat, '')" : "''";

        $messages = DB::table('tbIletisim as m')
            ->leftJoin('tbPersonel as p', 'm.PersonelNo', '=', 'p.PersonelNo')
            ->where(function ($q) use ($personelNo, $bolumAdiNo) {
                $q->where('m.PersonelNo', $personelNo);
                if ($bolumAdiNo > 0) $q->orWhere('m.BolumAdiNo', $bolumAdiNo);
            })
            ->selectRaw("m.MesajNo, m.Mesaj, m.Tarih, {$saatExpression} AS Saat, {$okunduExpression} AS Okundu, {$senderExpression} AS GonderenAdSoyad")
            ->orderByDesc('m.MesajNo')->limit(50)->get();

        return response()->json(['success' => true, 'messages' => $messages]);
    }

    public function unreadMessageCount(Request $request)
    {
        $personelNo = $this->personelNo($request);
        if ($personelNo <= 0 || !Schema::hasTable('tbIletisim') || !Schema::hasColumn('tbIletisim', 'Okundu')) {
            return response()->json(['success' => true, 'unread_count' => 0, 'latest_message_no' => null, 'latest_preview' => '']);
        }

        $unreadQuery = DB::table('tbIletisim')
            ->where('PersonelNo', $personelNo)
            ->where(function ($query) { $query->whereNull('Okundu')->orWhere('Okundu', 0)->orWhere('Okundu', '0'); });

        $count = (clone $unreadQuery)->count();
        $latest = (clone $unreadQuery)->orderByDesc('MesajNo')->first(['MesajNo', 'Mesaj']);
        $preview = preg_replace('/\s+/u', ' ', trim((string) ($latest->Mesaj ?? ''))) ?: '';

        return response()->json([
            'success' => true, 'unread_count' => intval($count),
            'latest_message_no' => $latest ? intval($latest->MesajNo ?? 0) : null,
            'latest_preview' => function_exists('mb_substr') ? mb_substr($preview, 0, 120) : substr($preview, 0, 120),
        ]);
    }

    public function markMessagesRead(Request $request)
    {
        $personelNo = $this->personelNo($request);
        if ($personelNo <= 0 || !Schema::hasTable('tbIletisim') || !Schema::hasColumn('tbIletisim', 'Okundu')) {
            return response()->json(['success' => true, 'updated' => 0]);
        }

        $updated = DB::table('tbIletisim')
            ->where('PersonelNo', $personelNo)
            ->where(function ($query) { $query->whereNull('Okundu')->orWhere('Okundu', 0)->orWhere('Okundu', '0'); })
            ->update(['Okundu' => 1]);

        return response()->json(['success' => true, 'updated' => intval($updated)]);
    }

    public function sendMessage(Request $request)
    {
        $personelNo = $this->personelNo($request);
        $personel = DB::table('tbPersonel')->where('PersonelNo', $personelNo)->first();
        $mesaj = trim((string) $request->input('mesaj', ''));
        $bolumAdiNo = intval($personel->BolumAdiNo ?? 0);

        if (empty($mesaj)) return response()->json(['success' => false, 'message' => 'Mesaj boş olamaz.'], 422);

        $insert = ['PersonelNo' => $personelNo, 'BolumAdiNo' => $bolumAdiNo > 0 ? $bolumAdiNo : null, 'Mesaj' => $mesaj, 'Tarih' => now()->format('d/m/Y')];
        if (Schema::hasColumn('tbIletisim', 'Saat')) $insert['Saat'] = now()->format('H:i');
        if (Schema::hasColumn('tbIletisim', 'Mail')) $insert['Mail'] = $personel->Mail ?? null;
        if (Schema::hasColumn('tbIletisim', 'AdSoyad')) $insert['AdSoyad'] = trim((string) ($personel->Ad ?? '') . ' ' . (string) ($personel->Soyad ?? ''));

        DB::table('tbIletisim')->insert($insert);
        return response()->json(['success' => true, 'message' => 'Mesaj gönderildi.']);
    }

    public function deleteMessage(Request $request, $id)
    {
        $personelNo = $this->personelNo($request);
        $deleted = DB::table('tbIletisim')->where('MesajNo', $id)->where('PersonelNo', $personelNo)->delete();
        if ($deleted) return response()->json(['success' => true, 'message' => 'Mesaj silindi.']);
        return response()->json(['success' => false, 'message' => 'Mesaj bulunamadı.'], 404);
    }
}
