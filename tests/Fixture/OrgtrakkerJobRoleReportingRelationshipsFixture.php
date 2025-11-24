<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * OrgtrakkerJobRoleReportingRelationshipsFixture
 * 
 * This fixture provides mock orgtrakker job role reporting relationship data for import testing.
 */
class OrgtrakkerJobRoleReportingRelationshipsFixture extends TestFixture
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
    public string $table = 'job_role_reporting_relationships';

    /**
     * Fields configuration
     * 
     * @var array
     */
    public array $fields = [
        'id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null, 'autoIncrement' => true],
        'company_id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'job_role' => ['type' => 'string', 'length' => 150, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'reporting_to' => ['type' => 'string', 'length' => 150, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'deleted' => ['type' => 'boolean', 'length' => null, 'null' => false, 'default' => false, 'comment' => '', 'precision' => null],
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
                'id' => 5001,
                'company_id' => 100000,
                'job_role' => 'org-jobrole-001',
                'reporting_to' => 'org-jobrole-002',
                'deleted' => false,
                'created' => '2024-01-01 10:00:00',
                'modified' => '2024-01-01 10:00:00',
            ],
            [
                'id' => 5002,
                'company_id' => 100000,
                'job_role' => 'org-jobrole-002',
                'reporting_to' => 'org-jobrole-003',
                'deleted' => false,
                'created' => '2024-01-01 10:00:00',
                'modified' => '2024-01-01 10:00:00',
            ],
        ];
        parent::init();
    }
}

