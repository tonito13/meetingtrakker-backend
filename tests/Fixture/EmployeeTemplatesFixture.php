<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * EmployeeTemplatesFixture
 */
class EmployeeTemplatesFixture extends TestFixture
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
                'name' => 'Lorem ipsum dolor sit amet',
                'structure' => '',
                'deleted' => 1,
                'created' => 1747227047,
                'modified' => 1747227047,
            ],
        ];
        parent::init();
    }
}
