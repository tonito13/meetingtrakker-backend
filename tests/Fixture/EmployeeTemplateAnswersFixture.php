<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * EmployeeTemplateAnswersFixture
 *
 * Test fixture for EmployeeTemplateAnswers model.
 * Provides comprehensive test data for employee testing.
 */
class EmployeeTemplateAnswersFixture extends TestFixture
{
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
        'answers' => ['type' => 'json', 'length' => null, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'deleted' => ['type' => 'boolean', 'length' => null, 'null' => true, 'default' => false, 'comment' => '', 'precision' => null],
        'created_by' => ['type' => 'string', 'length' => 150, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'created' => ['type' => 'datetime', 'length' => null, 'null' => true, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '', 'precision' => null],
        'modified' => ['type' => 'datetime', 'length' => null, 'null' => true, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '', 'precision' => null],
        'full_name' => ['type' => 'string', 'length' => 255, 'null' => true, 'default' => null, 'comment' => '', 'precision' => null],
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
                'id' => 1001,
                'company_id' => 200001,
                'employee_unique_id' => 'EMP001',
                'employee_id' => 'EMP001',
                'template_id' => 1001,
                'username' => 'john.doe',
                'answers' => json_encode([
                    'personal_info' => [
                        'first_name' => 'John',
                        'last_name' => 'Doe',
                        'email' => 'john.doe@company.com',
                        'phone' => '09123456789',
                    ],
                    'job_info' => [
                        'position' => 'Software Developer',
                        'department' => 'IT',
                        'manager' => 'Jane Smith',
                    ],
                ]),
                'deleted' => false,
                'created_by' => 'admin',
                'created' => '2024-01-01 00:00:00',
                'modified' => '2024-01-01 00:00:00',
                'full_name' => 'John Doe',
            ],
            [
                'id' => 1002,
                'company_id' => 200001,
                'employee_unique_id' => 'EMP002',
                'employee_id' => 'EMP002',
                'template_id' => 1001,
                'username' => 'jane.smith',
                'answers' => json_encode([
                    'personal_info' => [
                        'first_name' => 'Jane',
                        'last_name' => 'Smith',
                        'email' => 'jane.smith@company.com',
                        'phone' => '09123456790',
                    ],
                    'job_info' => [
                        'position' => 'Project Manager',
                        'department' => 'IT',
                        'manager' => 'Bob Johnson',
                    ],
                ]),
                'deleted' => false,
                'created_by' => 'admin',
                'created' => '2024-01-02 00:00:00',
                'modified' => '2024-01-02 00:00:00',
                'full_name' => 'Jane Smith',
            ],
            [
                'id' => 1003,
                'company_id' => 200001,
                'employee_unique_id' => 'EMP003',
                'employee_id' => 'EMP003',
                'template_id' => 1002,
                'username' => 'bob.johnson',
                'answers' => json_encode([
                    'personal_info' => [
                        'first_name' => 'Bob',
                        'last_name' => 'Johnson',
                        'email' => 'bob.johnson@company.com',
                        'phone' => '09123456791',
                    ],
                    'job_info' => [
                        'position' => 'Senior Developer',
                        'department' => 'IT',
                        'manager' => 'Alice Brown',
                    ],
                ]),
                'deleted' => false,
                'created_by' => 'admin',
                'created' => '2024-01-03 00:00:00',
                'modified' => '2024-01-03 00:00:00',
                'full_name' => 'Bob Johnson',
            ],
            [
                'id' => 1004,
                'company_id' => 200001,
                'employee_unique_id' => 'EMP004',
                'employee_id' => 'EMP004',
                'template_id' => 1001,
                'username' => 'deleted.employee',
                'answers' => json_encode([
                    'personal_info' => [
                        'first_name' => 'Deleted',
                        'last_name' => 'Employee',
                        'email' => 'deleted@company.com',
                    ],
                ]),
                'deleted' => true,
                'created_by' => 'admin',
                'created' => '2024-01-04 00:00:00',
                'modified' => '2024-01-04 00:00:00',
                'full_name' => 'Deleted Employee',
            ],
        ];
        parent::init();
    }
}
