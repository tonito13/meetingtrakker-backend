<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * OrgtrakkerEmployeeTemplateAnswersFixture
 * 
 * This fixture provides mock orgtrakker employee data for import testing.
 * It simulates data from the orgtrakker database that would be imported
 * into scorecardtrakker.
 */
class OrgtrakkerEmployeeTemplateAnswersFixture extends TestFixture
{
    /**
     * Connection name to use for this fixture
     * Orgtrakker test data should use the orgtrakker test database
     * Fixtures require connection names starting with 'test', so we use the alias
     * 
     * @var string
     */
    public string $connection = 'test_orgtrakker_100000';

    /**
     * Table name
     *
     * @var string
     */
    public string $table = 'employee_template_answers';

    /**
     * Fields configuration
     * 
     * @var array
     */
    public array $fields = [
        'id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null, 'autoIncrement' => true],
        'company_id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'employee_unique_id' => ['type' => 'string', 'length' => 150, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'employee_id' => ['type' => 'string', 'length' => 150, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'username' => ['type' => 'string', 'length' => 150, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'template_id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'answers' => ['type' => 'jsonb', 'length' => null, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'deleted' => ['type' => 'boolean', 'length' => null, 'null' => true, 'default' => false, 'comment' => '', 'precision' => null],
        'created_by' => ['type' => 'string', 'length' => 150, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
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
                'id' => 2001,
                'company_id' => 100000, // Orgtrakker company ID
                'employee_unique_id' => 'org-emp-001',
                'employee_id' => 'ORG001',
                'username' => 'org.employee1',
                'template_id' => 1001,
                'answers' => json_encode([
                    'personal_info' => [
                        'employee_id' => 'ORG001',
                        'username' => 'org.employee1',
                        'first_name' => 'John',
                        'last_name' => 'Doe',
                        'email' => 'john.doe@orgtrakker.com',
                        'phone' => '123-456-7890',
                        'blood_type' => 'A+',
                    ],
                    'job_info' => [
                        'position' => 'Senior Developer',
                        'department' => 'Engineering',
                        'manager' => 'Jane Smith',
                    ]
                ]),
                'deleted' => false,
                'created_by' => 'admin',
                'created' => '2024-01-01 10:00:00',
                'modified' => '2024-01-01 10:00:00',
            ],
            [
                'id' => 2002,
                'company_id' => 100000,
                'employee_unique_id' => 'org-emp-002',
                'employee_id' => 'ORG002',
                'username' => 'org.employee2',
                'template_id' => 1001,
                'answers' => json_encode([
                    'personal_info' => [
                        'employee_id' => 'ORG002',
                        'username' => 'org.employee2',
                        'first_name' => 'Jane',
                        'last_name' => 'Smith',
                        'email' => 'jane.smith@orgtrakker.com',
                        'phone' => '123-456-7891',
                        'blood_type' => 'B+',
                    ],
                    'job_info' => [
                        'position' => 'Project Manager',
                        'department' => 'Management',
                        'manager' => 'Bob Johnson',
                    ]
                ]),
                'deleted' => false,
                'created_by' => 'admin',
                'created' => '2024-01-01 10:00:00',
                'modified' => '2024-01-01 10:00:00',
            ],
            [
                'id' => 2003,
                'company_id' => 100000,
                'employee_unique_id' => 'org-emp-003',
                'employee_id' => 'ORG003',
                'username' => 'org.employee3',
                'template_id' => 1001,
                'answers' => json_encode([
                    'personal_info' => [
                        'employee_id' => 'ORG003',
                        'username' => 'org.employee3',
                        'first_name' => 'Bob',
                        'last_name' => 'Johnson',
                        'email' => 'bob.johnson@orgtrakker.com',
                        'phone' => '123-456-7892',
                        'blood_type' => 'O+',
                    ],
                    'job_info' => [
                        'position' => 'Director',
                        'department' => 'Executive',
                        'manager' => null,
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

