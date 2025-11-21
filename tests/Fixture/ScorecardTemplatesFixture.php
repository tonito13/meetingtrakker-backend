<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class ScorecardTemplatesFixture extends TestFixture
{
    /**
     * Connection name to use for this fixture
     * Company-specific tables should use the company-specific test database
     * 
     * @var string
     */
    public string $connection = 'test_client_200001';

    public $fields = [
        'id' => ['type' => 'integer', 'length' => 11, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'autoIncrement' => true, 'precision' => null],
        'company_id' => ['type' => 'integer', 'length' => 11, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'name' => ['type' => 'string', 'length' => 255, 'null' => false, 'default' => null, 'collate' => 'utf8mb4_general_ci', 'comment' => '', 'precision' => null],
        'structure' => ['type' => 'text', 'length' => 4294967295, 'null' => false, 'default' => null, 'collate' => 'utf8mb4_general_ci', 'comment' => '', 'precision' => null],
        'deleted' => ['type' => 'boolean', 'length' => null, 'null' => false, 'default' => '0', 'comment' => '', 'precision' => null],
        'created' => ['type' => 'datetime', 'length' => null, 'precision' => 6, 'null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => ''],
        'modified' => ['type' => 'datetime', 'length' => null, 'precision' => 6, 'null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => ''],
        'created_by' => ['type' => 'string', 'length' => 255, 'null' => true, 'default' => null, 'collate' => 'utf8mb4_general_ci', 'comment' => '', 'precision' => null],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id'], 'length' => []],
        ],
        '_options' => [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_general_ci'
        ],
    ];

    public array $records = [
        [
            'id' => 1,
            'company_id' => 200001,
            'name' => 'Default Scorecard Template',
            'structure' => '{"groups":[{"id":"group_1","name":"Performance Metrics","fields":[{"id":"code","label":"Code","type":"text","value":"","customize_field_label":null,"options":[]},{"id":"strategies","label":"Strategies/Tactics","type":"text","value":"","customize_field_label":null,"options":[]},{"id":"measures","label":"Measures","type":"text","value":"","customize_field_label":null,"options":[]},{"id":"deadline","label":"Deadline","type":"date","value":"","customize_field_label":null,"options":[]},{"id":"points","label":"Points","type":"number","value":"","customize_field_label":null,"options":[]},{"id":"weight","label":"Weight (%)","type":"number","value":"","customize_field_label":null,"options":[]}]}]}',
            'deleted' => 0,
            'created' => '2024-01-01 00:00:00',
            'modified' => '2024-01-01 00:00:00',
            'created_by' => 'admin',
        ],
        [
            'id' => 2,
            'company_id' => 200001,
            'name' => 'Manager Scorecard Template',
            'structure' => '{"groups":[{"id":"group_1","name":"Leadership Metrics","fields":[{"id":"code","label":"Code","type":"text","value":"","customize_field_label":null,"options":[]},{"id":"strategies","label":"Strategies/Tactics","type":"text","value":"","customize_field_label":null,"options":[]},{"id":"measures","label":"Measures","type":"text","value":"","customize_field_label":null,"options":[]},{"id":"deadline","label":"Deadline","type":"date","value":"","customize_field_label":null,"options":[]},{"id":"points","label":"Points","type":"number","value":"","customize_field_label":null,"options":[]},{"id":"weight","label":"Weight (%)","type":"number","value":"","customize_field_label":null,"options":[]}]}]}',
            'deleted' => 0,
            'created' => '2024-01-02 00:00:00',
            'modified' => '2024-01-02 00:00:00',
            'created_by' => 'admin',
        ],
        [
            'id' => 3,
            'company_id' => 200001,
            'name' => 'Deleted Template',
            'structure' => '{"groups":[]}',
            'deleted' => 1,
            'created' => '2024-01-03 00:00:00',
            'modified' => '2024-01-03 00:00:00',
            'created_by' => 'admin',
        ],
    ];
}
