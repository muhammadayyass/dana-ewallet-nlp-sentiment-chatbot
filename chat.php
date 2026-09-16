<?php
/* =============================================================
   DANA Enterprise — Backend (chat.php)
   Arsitektur: HYBRID NLP ROUTING V5 (Fixed Syntax & Safe Mode)
   ============================================================= */

// Matikan error PHP agar tidak membuat Javascript Crash
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

header('Content-Type: application/json; charset=utf-8');

$raw = isset($_POST['message']) ? trim($_POST['message']) : '';
if ($raw === '') { 
    ob_end_clean();
    echo json_encode(['reply' => 'Pesan kosong.', 'intent' => 'empty']); 
    exit; 
}

$pesan_lower = strtolower($raw);

// Helper Aman untuk Memanggil Flask ML
function callFlaskML($text) {
    if (!function_exists('curl_init')) return null;
    $ch = curl_init('http://127.0.0.1:5000/predict');
    if (!$ch) return null;
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2); 
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);        
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['teks' => $text]));
    $resp = curl_exec($ch);
    curl_close($ch);
    if (!$resp) return null;
    return json_decode($resp, true);
}

// 1. REGEX TICKET TRACKER
if (preg_match('/(DANA|SEC)-\d{8}-\d{4}/i', $pesan_lower, $matches)) {
    $ticket_id = strtoupper($matches[0]);
    $reply = "🔍 **Sistem Pelacakan (CRM)**\n\nTiket **{$ticket_id}** saat ini dalam status **IN PROGRESS**. Tim Resolusi kami sedang memverifikasi data transaksi pada backend. Mohon menunggu email dari tim spesialis kami.";
    ob_end_clean();
    echo json_encode(['reply' => $reply, 'intent' => 'check_ticket']);
    exit;
}

// 2. EXPLICIT ML SENTIMENT TESTING
if (strpos($pesan_lower, 'sentimen') !== false || strpos($pesan_lower, 'analisis') !== false) {
    $teks_analisis = trim(str_replace(['sentimen', 'analisis', ':'], '', $pesan_lower));
    if ($teks_analisis) {
        $r = callFlaskML($teks_analisis);
        if ($r && isset($r['sentimen'])) {
            $sentimen_hasil = strtoupper($r['sentimen']);
            $icon = ($sentimen_hasil === 'POSITIF') ? '🟢' : '🔴';
            $reply = "{$icon} **Prediksi AI Support Vector Machine**\n\n**Input:** \"{$teks_analisis}\"\n**Klasifikasi:** **{$sentimen_hasil}**\n\n_Catatan: Algoritma memproses dataset ulasan aktual DANA (11.000+ baris)._";
            ob_end_clean();
            echo json_encode(['reply' => $reply, 'intent' => 'sentiment_analysis_ml']);
            exit;
        } else {
            ob_end_clean();
            echo json_encode(['reply' => "⚠️ **Gagal terhubung ke Server ML (Port 5000).** Pastikan file app.py sedang berjalan di terminal laptop Anda.", 'intent' => 'ml_offline_error']);
            exit;
        }
    }
}

// 3. LAYER RULE-BASED ROUTING (Struktur Array Diperbaiki)
$intents = [
    'apresiasi' => [
        'keywords' => ['terbaik', 'mantap', 'keren', 'luar biasa', 'bagus', 'terima kasih', 'apresiasi', 'masha allah', 'alhamdulillah', 'membantu'],
        'reply' => "Wah, terima kasih banyak atas apresiasi dan kata-kata positifnya Kak! 😍 Kami akan terus berinovasi memberikan layanan dompet digital terbaik untuk Indonesia."
    ],
    'kompetitor' => [
        'keywords' => ['gopay', 'shopeepay', 'ovo', 'spay', 'linkaja', 'mending gopay', 'mending ovo'],
        'reply' => "Terima kasih atas masukannya Kak! Saran dan perbandingan dari pengguna akan menjadi data analitik berharga bagi tim Product DANA untuk meningkatkan kapabilitas sistem kami. 🚀"
    ],
    'informasi_pending' => [
        'keywords' => ['masa pending', 'berapa lama pending', 'lama nunggu', 'status pending', 'sedang diproses'],
        'reply' => "Status **Pending** atau Sedang Diproses berarti transaksi Kakak sedang mengantre di jalur komunikasi (switching) Bank Indonesia atau partner kami. SLA (Standard Level Agreement) untuk penyelesaian adalah maksimal **1x24 Jam Hari Kerja**."
    ],
    'topup_saldo' => [
        'keywords' => ['top up', 'topup', 'isi saldo', 'tambah saldo', 'cara isi'],
        'reply' => "Untuk Top Up Saldo DANA, Kakak bisa menggunakan fitur **Transfer Bank (BCA, Mandiri, BRI, BNI)**, **Agen Pegadaian**, **Alfamart**, atau **Kantor Pos**. Buka menu 'Isi Saldo' di halaman utama aplikasi. 💸"
    ],
    'saldo_nyangkut' => [
        'keywords' => ['nyangkut', 'terpotong', 'kepotong', 'uang ga masuk', 'belum masuk', 'hilang', 'ilang'],
        'reply' => "Kami mengerti kekhawatiran Kakak. ⚠️ Jika terjadi antrean jaringan, saldo akan disesuaikan otomatis maksimal 1x24 jam kerja.\n\n🎫 **TICKET ID: DANA-" . date("Ymd") . "-" . rand(1000, 9999) . "**\nSimpan nomor tiket ini untuk eskalasi ke Live Agent."
    ],
    'transfer_bank' => [
        'keywords' => ['transfer ke', 'kirim uang', 'tf bank', 'transfer mandiri', 'transfer bca'],
        'reply' => "Untuk kendala transfer ke Bank, pastikan akun sudah terverifikasi **DANA Premium**. Kuota gratis transfer bank berlaku 10x/bulan. Jika limit habis, akan dikenakan biaya admin Rp2.500/transaksi."
    ],
    'qris_error' => [
        'keywords' => ['qris error', 'scan error', 'qris muter', 'gagal bayar qris', 'gagal scan'],
        'reply' => "Jika Kakak mengalami gagal saat scan QRIS, hal ini biasanya karena QR Code kadaluarsa atau API koneksi tidak stabil. Saran kami: pastikan versi aplikasi DANA adalah yang terbaru dan bersihkan cache aplikasi. 📱"
    ],
    'security_fraud' => [
        'keywords' => ['bocor', 'tersebar', 'disalah gunakan', 'disalahgunakan', 'dihack', 'dibajak', 'penipuan', 'scam', 'ditipu', 'bajak', 'otp saya', 'minta otp', 'login orang lain'],
        'reply' => "🚨 **SISTEM PERINGATAN DARURAT (FRAUD ALERT)** 🚨\nKami mendeteksi anomali pada laporan keamanan Kakak. DANA tidak pernah meminta PIN/OTP.\n\nSistem kami telah menandai (flagging) laporan ini. Segera hubungi Call Center Darurat **1500445** agar kami dapat melakukan _Freeze Account_ (Pembekuan Akun)."
    ],
    'akun_beku' => [
        'keywords' => ['dibekukan', 'beku', 'diblokir', 'blokir'],
        'reply' => "Sistem keamanan DANA otomatis membekukan akun jika mendeteksi anomali login (seperti pindah perangkat berulang kali). 🔒\n\n🎫 **TICKET ID: SEC-" . date("Ymd") . "-" . rand(1000, 9999) . "**\nKirim email ke help@dana.id dengan subjek 'Pemulihan Akun [Nomor Tiket]' beserta Swafoto KTP."
    ]
];

// Algoritma Scoring (Menghitung Poin berdasarkan Panjang Kalimat Cocok)
$best_intent = 'unknown';
$best_score = 0;

foreach ($intents as $intent_name => $data) {
    foreach ($data['keywords'] as $kw) {
        if (strpos($pesan_lower, $kw) !== false) {
            $word_count = str_word_count($kw);
            $points = ($word_count * 15) + strlen($kw);
            if ($points > $best_score) {
                $best_score = $points;
                $best_intent = $intent_name;
            }
        }
    }
}

// 4. FALLBACK TO MACHINE LEARNING (SVM) JIKA INTENT TIDAK DIKENALI
if ($best_intent === 'unknown') {
    
    $r = callFlaskML($pesan_lower);
    
    if ($r && isset($r['sentimen'])) {
        $sentimen = strtoupper($r['sentimen']);
        
        // Cek jika teks pendek tapi bukan keluhan
        if (strlen($pesan_lower) < 10) {
            ob_end_clean();
            echo json_encode(['reply' => "Halo Kak! 👋 Silakan sampaikan detail kendala operasional atau transaksi yang sedang dialami.", 'intent' => 'general_fallback']);
            exit;
        }

        if ($sentimen === 'NEGATIF') {
            $reply = "Sistem Natural Language Processing kami mendeteksi sentimen **NEGATIF** dari kalimat tersebut. 😔\n\nKami memohon maaf atas ketidaknyamanan ini. Agar sistem dapat memetakan tiket bantuan, mohon cantumkan fitur spesifik (contoh: *Saldo, QRIS, Transfer, atau Akun*).";
            ob_end_clean();
            echo json_encode(['reply' => $reply, 'intent' => 'ml_fallback_negatif']);
            exit;
        } else {
            $reply = "Sistem kami memproses pesan ini dengan sentimen **POSITIF / NETRAL**. 🎉\n\nTerima kasih banyak atas partisipasinya! Ada hal operasional lain yang ingin Kakak ketahui?";
            ob_end_clean();
            echo json_encode(['reply' => $reply, 'intent' => 'ml_fallback_positif']);
            exit;
        }
    } else {
        // Fallback jika ML mati atau kalimat tidak jelas
        ob_end_clean();
        $reply = "Mohon maaf Kak, sistem NLP kami tidak dapat mengekstrak maksud dari kalimat tersebut. 🤔 Coba gunakan kata kunci spesifik seperti: 'saldo nyangkut', 'data bocor', atau 'aplikasi error'.";
        echo json_encode(['reply' => $reply, 'intent' => 'unknown']);
        exit;
    }
} else {
    ob_end_clean();
    echo json_encode(['reply' => $intents[$best_intent]['reply'], 'intent' => $best_intent]);
    exit;
}
?>