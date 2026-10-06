<?php

namespace Database\Seeders;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DummyTicketSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Membuat tiket dummy untuk testing...');

        // Cari satu pelapor dan satu teknisi dari data asli yang baru di-import
        $reporter = User::role('user')->first();
        $technician = User::role(['petugas-tik', 'petugas-sarpras'])->first();

        if (! $reporter || ! $technician) {
            $this->command->error('Data user atau teknisi belum ada. Pastikan ImportUsersSeeder sudah dijalankan.');

            return;
        }

        // 1. Tiket untuk Testing Eskalasi (Status: Diproses, SLA: Mendekati Batas)
        $ticketDiproses = Ticket::create([
            'ticket_number' => 'TIK-2026-000001',
            'service' => 'tik',
            'description' => 'Komputer poli mati mendadak saat pelayanan.',
            'status' => 'diproses',
            'priority' => 'high',
            'reporter_id' => $reporter->id,
            'unit_id' => $reporter->unit_id,
            'assigned_officer_id' => $technician->id,
            'sla_started_at' => Carbon::now()->subHours(2),
            'sla_deadline' => Carbon::now()->addHours(1),
        ]);

        // 2. Tiket untuk Testing Verifikasi (Status: Terselesaikan, SLA: Melewati Batas)
        $ticketSelesai = Ticket::create([
            'ticket_number' => 'TIK-2026-000002',
            'service' => 'tik',
            'description' => 'Jaringan internet lantai 2 sangat lambat.',
            'status' => 'terselesaikan',
            'priority' => 'medium',
            'reporter_id' => $reporter->id,
            'unit_id' => $reporter->unit_id,
            'assigned_officer_id' => $technician->id,
            'sla_started_at' => Carbon::now()->subDays(1),
            'sla_deadline' => Carbon::now()->subHours(5),
            'completed_at' => Carbon::now()->subMinutes(30),
        ]);

        // 3. Tiket untuk Testing SLA Tepat Waktu (Status: Baru)
        $ticketBaru = Ticket::create([
            'ticket_number' => 'SPR-2026-000003',
            'service' => 'sarpras',
            'description' => 'AC Ruang Tunggu bocor meneteskan air.',
            'status' => 'baru',
            'reporter_id' => $reporter->id,
            'unit_id' => $reporter->unit_id,
        ]);

        $this->command->info('Selesai! 3 tiket dummy berhasil dibuat.');
        $this->command->info('ID TIKET DIPROSES (Untuk Eskalasi)  : '.$ticketDiproses->id);
        $this->command->info('ID TIKET SELESAI  (Untuk Verifikasi): '.$ticketSelesai->id);
        $this->command->info('---');
        $this->command->info('Login Pelapor : '.$reporter->email);
        $this->command->info('Login Teknisi : '.$technician->email);
    }
}
