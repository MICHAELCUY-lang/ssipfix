<?php
/**
 * Script untuk membuat direktori yang diperlukan dan mengatur izin
 * Jalankan script ini sebelum menggunakan aplikasi SSIPFix
 */

// Tentukan direktori yang diperlukan
$directories = [
    'assets/uploads',
    'assets/uploads/photos',
    'assets/uploads/videos',
    'assets/images'
];

// Buat direktori jika belum ada dan atur izin
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        if (mkdir($dir, 0777, true)) {
            echo "Direktori '$dir' berhasil dibuat.<br>";
        } else {
            echo "GAGAL membuat direktori '$dir'.<br>";
        }
    } else {
        echo "Direktori '$dir' sudah ada.<br>";
    }
    
    // Atur izin menjadi 0777 (read/write/execute untuk semua)
    if (chmod($dir, 0777)) {
        echo "Izin untuk '$dir' berhasil diatur ke 0777.<br>";
    } else {
        echo "GAGAL mengatur izin untuk '$dir'.<br>";
    }
}

// Periksa apakah server dapat menulis ke direktori
foreach ($directories as $dir) {
    if (is_writable($dir)) {
        echo "Direktori '$dir' dapat ditulis oleh server.<br>";
    } else {
        echo "PERINGATAN: Direktori '$dir' TIDAK DAPAT DITULIS oleh server. Cek izin lebih lanjut.<br>";
    }
}

// Informasi tentang batas upload PHP
echo "<hr>";
echo "<h3>Informasi Konfigurasi PHP:</h3>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "max_execution_time: " . ini_get('max_execution_time') . " detik<br>";
echo "memory_limit: " . ini_get('memory_limit') . "<br>";

// Tips untuk mengatasi masalah izin
echo "<hr>";
echo "<h3>Tips Jika Masih Ada Masalah:</h3>";
echo "<ol>";
echo "<li>Pastikan user PHP (www-data, apache, nginx, dll.) memiliki izin menulis ke direktori upload.</li>";
echo "<li>Jika menggunakan Linux/Unix, coba perintah: <code>chown -R www-data:www-data assets/uploads</code></li>";
echo "<li>Jika menggunakan cara di atas tidak berhasil, coba: <code>chmod -R 777 assets/uploads</code></li>";
echo "<li>Jika masih ada masalah, periksa file php.ini dan pastikan upload file diaktifkan.</li>";
echo "</ol>";
?>