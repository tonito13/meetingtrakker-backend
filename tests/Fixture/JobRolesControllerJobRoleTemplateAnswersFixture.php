<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * JobRolesController JobRoleTemplateAnswers Fixture
 * 
 * This is a separate fixture file specifically for JobRolesControllerTest
 * to avoid conflicts with other test classes
 */
class JobRolesControllerJobRoleTemplateAnswersFixture extends TestFixture
{
    /**
     * Table name
     *
     * @var string
     */
    public string $table = 'job_role_template_answers';

    /**
     * Fields configuration
     * 
     * @var array
     */
    public array $fields = [
        'id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null, 'autoIncrement' => true],
        'company_id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'job_role_unique_id' => ['type' => 'string', 'length' => 255, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'template_id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'answers' => ['type' => 'text', 'length' => null, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'deleted' => ['type' => 'boolean', 'length' => null, 'null' => false, 'default' => false, 'comment' => '', 'precision' => null],
        'created' => ['type' => 'datetime', 'length' => null, 'null' => true, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '', 'precision' => null],
        'modified' => ['type' => 'datetime', 'length' => null, 'null' => true, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '', 'precision' => null],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id'], 'length' => []],
        ],
        '_options' => [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci'
        ],
    ];

    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => 50001,
                'company_id' => 200001,
                'job_role_unique_id' => 'jr-20240101-ABCD1234',
                'template_id' => 90001, // Use the new template ID
                'answers' => json_encode([
                    'job_info' => [
                        'role_code' => 'JR001',
                        'official_designation' => 'Software Engineer',
                        'level' => 'Junior'
                    ]
                ]),
                'deleted' => false,
                'created' => '2024-01-01 00:00:00',
                'modified' => '2024-01-01 00:00:00',
            ],
            [
                'id' => 50002,
                'company_id' => 200001,
                'job_role_unique_id' => 'jr-20240102-EFGH5678',
                'template_id' => 90001, // Use the new template ID
                'answers' => json_encode([
                    'job_info' => [
                        'role_code' => 'JR002',
                        'official_designation' => 'Senior Developer',
                        'level' => 'Senior'
                    ]
                ]),
                'deleted' => false,
                'created' => '2024-01-02 00:00:00',
                'modified' => '2024-01-02 00:00:00',
            ],
            [
                'id' => 50003,
                'company_id' => 200001,
                'job_role_unique_id' => 'jr-20240103-IJKL9012',
                'template_id' => 90001, // Use the new template ID
                'answers' => json_encode([
                    'job_info' => [
                        'role_code' => 'JR003',
                        'official_designation' => 'Manager',
                        'level' => 'Manager'
                    ]
                ]),
                'deleted' => true,
                'created' => '2024-01-03 00:00:00',
                'modified' => '2024-01-03 00:00:00',
            ],
            [
                'id' => 50004,
                'company_id' => 200002,
                'job_role_unique_id' => 'jr-20240104-MNOP3456',
                'template_id' => 90001, // Use the new template ID
                'answers' => json_encode([
                    'job_info' => [
                        'role_code' => 'JR004',
                        'official_designation' => 'Product Manager',
                        'level' => 'Manager'
                    ]
                ]),
                'deleted' => false,
                'created' => '2024-01-04 00:00:00',
                'modified' => '2024-01-04 00:00:00',
            ],
        ];
        parent::init();
    }
}
