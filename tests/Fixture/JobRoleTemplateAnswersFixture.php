<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * JobRoleTemplateAnswersFixture
 * 
 * Test fixture for JobRoleTemplateAnswers model.
 * Provides comprehensive test data for job role template answers testing.
 */
class JobRoleTemplateAnswersFixture extends TestFixture
{
    /**
     * Connection name to use for this fixture
     * Company-specific tables should use the company-specific test database
     * 
     * @var string
     */
    public string $connection = 'test_client_200001';

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
        'job_role_unique_id' => ['type' => 'string', 'length' => 150, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
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
                'id' => 1001,
                'company_id' => 200001,
                'job_role_unique_id' => 'jr-20240101-ABCD1234',
                        'template_id' => 9001, // Use a unique ID to avoid conflicts
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
                'id' => 1002,
                'company_id' => 200001,
                'job_role_unique_id' => 'jr-20240102-EFGH5678',
                        'template_id' => 9001, // Use a unique ID to avoid conflicts
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
                'id' => 1003,
                'company_id' => 200001,
                'job_role_unique_id' => 'jr-20250915-3487298C6E',
                        'template_id' => 9001, // Use a unique ID to avoid conflicts
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
                'id' => 1004,
                'company_id' => 200002,
                'job_role_unique_id' => 'jr-20240104-MNOP3456',
                        'template_id' => 9001, // Use a unique ID to avoid conflicts
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
