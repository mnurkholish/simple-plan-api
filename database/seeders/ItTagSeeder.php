<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItTagSeeder extends Seeder
{
    public function run(): void
    {
        $timestamp = now();
        $tags = [
            'Lain-lain',
            'Manage Batal Kunjungan Pasien',
            'Edit Asesmen Medis Dokter (Form Perubahan Data)',
            'Support Apps Hapus Asesmen Medis Dokter',
            'Edit Asesmen Keperawatan (Form Perubahan Data)',
            'Support Apps Hapus Asesmen Keperawatan',
            'Edit Data Operasi dari Modul Operasi',
            'Hapus Request, Regis dan ID Penjualan LAB',
            'Hapus Request, Regis dan ID Penjualan RAD',
            'Manage Edit ID Penjualan LAB',
            'Manage Edit ID Penjualan RAD',
            'Manage ACC dan Ticketing Unit',
            'Hapus Request BMHP Farmasi',
            'Manage Master Baru (Jasa, Rikjang, Fasilitas)',
            'Manage Mapping Master (Jasa, Rikjang, Fasilitas)',
            'Manage Mapping untuk Bridging IT',
            'Manage Master LAB Test',
            'Manage Master RAD Test',
            'Manage Casemix Tidak Muncul',
            'Manage Unvalidasi RPP',
            'Manage Program Reham Medik Poli Fisioterapi',
            'Manage Master Barang Medis Farmasi',
            'Manage Tambah Hak Akses User NUHA',
            'Manage Edit Hak Akses User NUHA',
            'Manage Hapus Hak Akses User NUHA',
            'Manage Pelatihan Modul ke Unit',
            'Manage Input Diagnostik Medis',
            'Manage Input Tindakan Kunjungan di Modul Vaksin',
            'Manage Tambah Master Jam Kerja + Mapping Unit',
            'Manage Transfer Mutasi Saldo di Kas V3',
            'Manage Master Tenaga Medis',
            'Manage Mapping Master Ruangan Operasi',
            'Pergantian Sparepart',
            'Perbaikan Perangkat',
        ];

        DB::table('it_tags')->upsert(
            array_map(fn (string $name): array => [
                'name' => $name,
                'is_active' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ], $tags),
            ['name'],
            ['is_active', 'updated_at'],
        );
    }
}
