<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * RoleLevelsFixture
 */
class RoleLevelsFixture extends TestFixture
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
                'rank' => 1,
                'custom_fields' => '',
                'created_by' => 'Lorem ipsum dolor sit amet',
                'deleted' => 1,
                'created' => 1749203413,
                'modified' => 1749203413,
            ],
        ];
        parent::init();
    }
}
