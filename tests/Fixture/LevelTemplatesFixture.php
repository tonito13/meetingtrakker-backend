<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * LevelTemplatesFixture
 */
class LevelTemplatesFixture extends TestFixture
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
                'created_by' => 'Lorem ipsum dolor sit amet',
                'deleted' => 1,
                'created' => 1749211071,
                'modified' => 1749211071,
            ],
        ];
        parent::init();
    }
}
