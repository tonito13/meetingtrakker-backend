<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * EmployeeAnswerFilesFixture
 */
class EmployeeAnswerFilesFixture extends TestFixture
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
                'answer_id' => 1,
                'file_name' => 'Lorem ipsum dolor sit amet',
                'file_path' => 'Lorem ipsum dolor sit amet',
                'file_type' => 'Lorem ipsum dolor sit amet',
                'file_size' => 1,
                'deleted' => 1,
                'created' => 1747719622,
                'modified' => 1747719622,
            ],
        ];
        parent::init();
    }
}
