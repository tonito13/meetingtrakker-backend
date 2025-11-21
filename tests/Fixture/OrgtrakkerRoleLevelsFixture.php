<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * OrgtrakkerRoleLevelsFixture
 * 
 * This fixture provides mock orgtrakker role level data for import testing.
 */
class OrgtrakkerRoleLevelsFixture extends TestFixture
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
    public string $table = 'role_levels';

    /**
     * Fields configuration
     * 
     * @var array
     */
    public array $fields = [
        'id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null, 'autoIncrement' => true],
        'company_id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'level_unique_id' => ['type' => 'string', 'length' => 150, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'name' => ['type' => 'string', 'length' => 255, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'rank' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'custom_fields' => ['type' => 'json', 'length' => null, 'null' => true, 'default' => null, 'comment' => '', 'precision' => null],
        'template_id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
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
                'id' => 3001,
                'company_id' => 100000,
                'level_unique_id' => 'org-level-001',
                'name' => 'Junior',
                'rank' => 10,
                'template_id' => 4001,
                'custom_fields' => json_encode([
                    'level_info' => [
                        'level_name' => 'Junior',
                        'rank' => 10,
                        'description' => 'Junior level position',
                    ]
                ]),
                'deleted' => false,
                'created_by' => 'admin',
                'created' => '2024-01-01 10:00:00',
                'modified' => '2024-01-01 10:00:00',
            ],
            [
                'id' => 3002,
                'company_id' => 100000,
                'level_unique_id' => 'org-level-002',
                'name' => 'Senior',
                'rank' => 20,
                'template_id' => 4001,
                'custom_fields' => json_encode([
                    'level_info' => [
                        'level_name' => 'Senior',
                        'rank' => 20,
                        'description' => 'Senior level position',
                    ]
                ]),
                'deleted' => false,
                'created_by' => 'admin',
                'created' => '2024-01-01 10:00:00',
                'modified' => '2024-01-01 10:00:00',
            ],
            [
                'id' => 3003,
                'company_id' => 100000,
                'level_unique_id' => 'org-level-003',
                'name' => 'Lead',
                'rank' => 30,
                'template_id' => 4001,
                'custom_fields' => json_encode([
                    'level_info' => [
                        'level_name' => 'Lead',
                        'rank' => 30,
                        'description' => 'Lead level position',
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

