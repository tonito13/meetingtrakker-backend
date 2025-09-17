<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * JobRoleTemplatesFixture
 */
class JobRoleTemplatesFixture extends TestFixture
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
                'name' => 'Lorem ipsum dolor sit amet',
                'structure' => '',
                'deleted' => 1,
                'created' => 1746372465,
                'modified' => 1746372465,
            ],
        ];
        parent::init();
    }
}
