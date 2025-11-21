<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * OrgtrakkerJobRoleTemplateAnswersFixture
 * 
 * This fixture provides mock orgtrakker job role data for import testing.
 */
class OrgtrakkerJobRoleTemplateAnswersFixture extends TestFixture
{
    /**
     * Connection name to use for this fixture
     * Orgtrakker test data should use the orgtrakker test database
     * 
     * @var string
     */
    public string $connection = 'test_orgtrakker_100000';

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
        'answers' => ['type' => 'json', 'length' => null, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'level_unique_id' => ['type' => 'string', 'length' => 150, 'null' => true, 'default' => null, 'comment' => '', 'precision' => null],
        'deleted' => ['type' => 'boolean', 'length' => null, 'null' => false, 'default' => false, 'comment' => '', 'precision' => null],
        'created_by' => ['type' => 'string', 'length' => 150, 'null' => true, 'default' => null, 'comment' => '', 'precision' => null],
        'created' => ['type' => 'datetime', 'length' => null, 'null' => true, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '', 'precision' => null],
        'modified' => ['type' => 'datetime', 'length' => null, 'null' => true, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '', 'precision' => null],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id'], 'length' => []],
        ],
        '_options' => [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
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
                'id' => 4001,
                'company_id' => 100000,
                'job_role_unique_id' => 'org-jobrole-001',
                'template_id' => 5001,
                'level_unique_id' => 'org-level-001',
                'answers' => json_encode([
                    'job_role_info' => [
                        'job_role_name' => 'Software Engineer',
                        'department' => 'Engineering',
                        'description' => 'Develops software applications',
                    ]
                ]),
                'deleted' => false,
                'created_by' => 'admin',
                'created' => '2024-01-01 10:00:00',
                'modified' => '2024-01-01 10:00:00',
            ],
            [
                'id' => 4002,
                'company_id' => 100000,
                'job_role_unique_id' => 'org-jobrole-002',
                'template_id' => 5001,
                'level_unique_id' => 'org-level-002',
                'answers' => json_encode([
                    'job_role_info' => [
                        'job_role_name' => 'Senior Software Engineer',
                        'department' => 'Engineering',
                        'description' => 'Senior software development role',
                    ]
                ]),
                'deleted' => false,
                'created_by' => 'admin',
                'created' => '2024-01-01 10:00:00',
                'modified' => '2024-01-01 10:00:00',
            ],
            [
                'id' => 4003,
                'company_id' => 100000,
                'job_role_unique_id' => 'org-jobrole-003',
                'template_id' => 5001,
                'level_unique_id' => 'org-level-003',
                'answers' => json_encode([
                    'job_role_info' => [
                        'job_role_name' => 'Engineering Lead',
                        'department' => 'Engineering',
                        'description' => 'Leads engineering team',
                    ]
                ]),
                'deleted' => false,
                'created_by' => 'admin',
                'created' => '2024-01-01 10:00:00',
                'modified' => '2024-01-01 10:00:00',
            ],
        ];
        parent::init();
    }
}

