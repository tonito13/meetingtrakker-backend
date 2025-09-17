<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * JobRoleTemplateAnswersFixture
 */
class JobRoleTemplateAnswersFixture extends TestFixture
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
                'job_role_unique_id' => 'Lorem ipsum dolor sit amet',
                'template_id' => 1,
                'answers' => '',
                'deleted' => 1,
                'created' => 1746673963,
                'modified' => 1746673963,
            ],
        ];
        parent::init();
    }
}
