<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * UsersFixture
 */
class UsersFixture extends TestFixture
{
    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
               // 'id' => 1,
                'company_id' => 1,
                'first_name' => 'Test',
                'middle_name' => 'User',
                'last_name' => 'Account',
                'birth_date' => '1990-01-01',
                'birth_place' => 'Test City',
                'sex' => 'Male',
                'civil_status' => 'Single',
                'nationality' => 'Filipino',
                'blood_type' => 'O',
                'email_address' => 'test@example.com',
                'contact_number' => '09123456789',
                'street_number' => '123',
                'street_name' => 'Test Street',
                'barangay' => 'Test Barangay',
                'city_municipality' => 'Test City',
                'province' => 'Test Province',
                'zipcode' => '1234',
                'username' => 'test',
                'password' => '$2y$10$k9fsGxzCqKHb60STn.LsvewaYCMgtSVKyNvPYRbbB8wNImkVZXrAK',
                'system_user_role' => 'admin',
                'system_access_enabled' => 1,
                'active' => 1,
                'deleted' => 0,
                'created' => '2024-01-01 00:00:00',
                'modified' => '2024-01-01 00:00:00',
            ],
        ];
        parent::init();
    }
}
