<?php

namespace Database\Seeders;

use App\Models\CertificationType;
use Illuminate\Database\Seeder;

class CertificationTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $certifications = [
            ['name' => 'CPR & First Aid', 'description' => 'Cardiopulmonary Resuscitation and First Aid certification', 'expires_required' => true],
            ['name' => 'Background Check', 'description' => 'Criminal background verification', 'expires_required' => true],
            ['name' => 'Trustline', 'description' => 'State registry certification', 'expires_required' => true],
            ['name' => 'Care.com Certified', 'description' => 'Care.com verification badge', 'expires_required' => false],
            ['name' => 'Food Handler', 'description' => 'Food safety certification', 'expires_required' => false],
        ];

        foreach ($certifications as $cert) {
            CertificationType::create($cert);
        }
    }
}
