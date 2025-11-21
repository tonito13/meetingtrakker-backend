<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * OrgtrakkerEmployeeReportingRelationshipsFixture
 * 
 * This fixture provides mock orgtrakker employee reporting relationship data for import testing.
 */
class OrgtrakkerEmployeeReportingRelationshipsFixture extends TestFixture
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
    public string $table = 'employee_reporting_relationships';

    /**
     * Fields configuration
     * 
     * @var array
     */
    public array $fields = [
        'id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null, 'autoIncrement' => true],
        'company_id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'employee_unique_id' => ['type' => 'string', 'length' => 150, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'report_to_employee_unique_id' => ['type' => 'string', 'length' => 150, 'null' => true, 'default' => null, 'comment' => '', 'precision' => null],
        'employee_first_name' => ['type' => 'string', 'length' => 150, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'employee_last_name' => ['type' => 'string', 'length' => 150, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'reporting_manager_first_name' => ['type' => 'string', 'length' => 150, 'null' => true, 'default' => null, 'comment' => '', 'precision' => null],
        'reporting_manager_last_name' => ['type' => 'string', 'length' => 150, 'null' => true, 'default' => null, 'comment' => '', 'precision' => null],
        'start_date' => ['type' => 'date', 'length' => null, 'null' => true, 'default' => null, 'comment' => '', 'precision' => null],
        'end_date' => ['type' => 'date', 'length' => null, 'null' => true, 'default' => null, 'comment' => '', 'precision' => null],
        'deleted' => ['type' => 'boolean', 'length' => null, 'null' => false, 'default' => false, 'comment' => '', 'precision' => null],
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
                'id' => 6001,
                'company_id' => 100000,
                'employee_unique_id' => 'org-emp-001',
                'report_to_employee_unique_id' => 'org-emp-002',
                'employee_first_name' => 'John',
                'employee_last_name' => 'Doe',
                'reporting_manager_first_name' => 'Jane',
                'reporting_manager_last_name' => 'Smith',
                'start_date' => '2024-01-01',
                'end_date' => null,
                'deleted' => false,
                'created_by' => 'admin',
                'created' => '2024-01-01 10:00:00',
                'modified' => '2024-01-01 10:00:00',
            ],
            [
                'id' => 6002,
                'company_id' => 100000,
                'employee_unique_id' => 'org-emp-002',
                'report_to_employee_unique_id' => 'org-emp-003',
                'employee_first_name' => 'Jane',
                'employee_last_name' => 'Smith',
                'reporting_manager_first_name' => 'Bob',
                'reporting_manager_last_name' => 'Johnson',
                'start_date' => '2024-01-01',
                'end_date' => null,
                'deleted' => false,
                'created_by' => 'admin',
                'created' => '2024-01-01 10:00:00',
                'modified' => '2024-01-01 10:00:00',
            ],
        ];
        parent::init();
    }
}

