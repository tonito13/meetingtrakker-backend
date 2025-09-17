<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * EmployeeTemplateAnswersFixture
 */
class EmployeeTemplateAnswersFixture extends TestFixture
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
                'employee_unique_id' => 'Lorem ipsum dolor sit amet',
                'template_id' => 1,
                'answers' => '',
                'deleted' => 1,
                'created' => 1747639064,
                'modified' => 1747639064,
            ],
        ];
        parent::init();
    }
}
