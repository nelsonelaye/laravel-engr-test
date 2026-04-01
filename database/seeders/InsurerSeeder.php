<?php

namespace Database\Seeders;

use App\Models\Insurer;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class InsurerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Insurer::create([
            'name' => 'HealthCare Plus',
            'code' => 'HCP-001',
            'min_batch_size' => 5,
            'max_batch_size' => 50,
            'daily_capacity' => 500000, // ₦500K per day
            'preferred_date_type' => 'encounter',
            'specialty_efficiencies' => [
                'cardiology' => 0.85,      // 15% cheaper (high volume)
                'orthopedics' => 1.15,     // 15% more expensive (complex)
                'neurology' => 1.25,       // 25% more expensive (rare)
                'pediatrics' => 0.9,       // 10% cheaper (standardized)
                'general_practice' => 1.0, // baseline
            ],
        ]);

        Insurer::create([
            'name' => 'MediSecure',
            'code' => 'MED-002',
            'min_batch_size' => 3,
            'max_batch_size' => 100,
            'daily_capacity' => 750000, // ₦750K per day
            'preferred_date_type' => 'submission',
            'specialty_efficiencies' => [
                'cardiology' => 1.0,       // baseline
                'orthopedics' => 0.95,     // 5% cheaper
                'neurology' => 1.1,        // 10% more expensive
                'pediatrics' => 1.05,      // 5% more expensive
                'general_practice' => 0.95, // slightly cheaper
            ],
        ]);

        Insurer::create([
            'name' => 'Elite Insurers',
            'code' => 'ELI-003',
            'min_batch_size' => 10,
            'max_batch_size' => 75,
            'daily_capacity' => 1000000, // ₦1M per day
            'preferred_date_type' => 'encounter',
            'specialty_efficiencies' => [
                'cardiology' => 1.2,       // 20% more (specialty focus)
                'orthopedics' => 0.8,      // 20% cheaper (high volume)
                'neurology' => 1.0,        // baseline
                'pediatrics' => 1.15,      // 15% more expensive
                'general_practice' => 1.1, // 10% more expensive
            ],
        ]);

        Insurer::create([
            'name' => 'Wellness Network',
            'code' => 'WN-004',
            'min_batch_size' => 2,
            'max_batch_size' => 200,
            'daily_capacity' => 2000000, // ₦2M per day (high capacity)
            'preferred_date_type' => 'submission',
            'specialty_efficiencies' => [
                'cardiology' => 0.95,      // 5% cheaper
                'orthopedics' => 1.05,     // 5% more expensive
                'neurology' => 0.95,       // 5% cheaper
                'pediatrics' => 0.9,       // 10% cheaper (high volume)
                'general_practice' => 1.0, // baseline
            ],
        ]);
    }
}
