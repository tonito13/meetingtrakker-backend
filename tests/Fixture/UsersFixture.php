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
                'id' => 1,
                'company_id' => 1,
                'first_name' => 'Lorem ipsum dolor sit amet',
                'middle_name' => 'Lorem ipsum dolor sit amet',
                'last_name' => 'Lorem ipsum dolor sit amet',
                'birth_date' => '2025-05-04',
                'birth_place' => 'Lorem ipsum dolor sit amet',
                'sex' => 'Lorem ipsum dolor sit amet',
                'civil_status' => 'Lorem ipsum dolor sit amet',
                'nationality' => 'Lorem ipsum dolor sit amet',
                'blood_type' => 'L',
                'email_address' => 'Lorem ipsum dolor sit amet',
                'contact_number' => 'Lorem ipsum dolor ',
                'street_number' => 'Lorem ipsum dolor ',
                'street_name' => 'Lorem ipsum dolor sit amet',
                'barangay' => 'Lorem ipsum dolor sit amet',
                'city_municipality' => 'Lorem ipsum dolor sit amet',
                'province' => 'Lorem ipsum dolor sit amet',
                'zipcode' => 'Lorem ip',
                'username' => 'Lorem ipsum dolor sit amet',
                'password' => 'Lorem ipsum dolor sit amet',
                'system_user_role' => 'Lorem ipsum dolor sit amet',
                'system_access_enabled' => 1,
                'active' => 1,
                'deleted' => 1,
                'created' => 1746357262,
                'modified' => 1746357262,
            ],
        ];
        parent::init();
    }
}
