<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * RoleLevelsFixture
 *
 * Test fixture for RoleLevels model.
 * Provides comprehensive test data for role levels testing.
 */
class RoleLevelsFixture extends TestFixture
{
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
        'template_id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'name' => ['type' => 'string', 'length' => 255, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'rank' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'custom_fields' => ['type' => 'json', 'length' => null, 'null' => true, 'default' => null, 'comment' => '', 'precision' => null],
        'created_by' => ['type' => 'string', 'length' => 150, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
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
                'id' => 1001,
                'company_id' => 200001,
                'level_unique_id' => 'rl-20240101-ABCD1234',
                'template_id' => 4001,
                'name' => 'Junior Level',
                'rank' => 1,
                'custom_fields' => json_encode([
                    'description' => 'Entry level position',
                    'requirements' => ['Basic skills', 'Willingness to learn'],
                ]),
                'created_by' => 'admin',
                'deleted' => false,
                'created' => '2024-01-01 00:00:00',
                'modified' => '2024-01-01 00:00:00',
            ],
            [
                'id' => 1002,
                'company_id' => 200001,
                'level_unique_id' => 'rl-20240102-EFGH5678',
                'template_id' => 4001,
                'name' => 'Senior Level',
                'rank' => 3,
                'custom_fields' => json_encode([
                    'description' => 'Senior level position',
                    'requirements' => ['Advanced skills', 'Leadership experience'],
                ]),
                'created_by' => 'admin',
                'deleted' => false,
                'created' => '2024-01-02 00:00:00',
                'modified' => '2024-01-02 00:00:00',
            ],
            [
                'id' => 1003,
                'company_id' => 200001,
                'level_unique_id' => 'rl-20240103-IJKL9012',
                'template_id' => 4001,
                'name' => 'Manager Level',
                'rank' => 5,
                'custom_fields' => json_encode([
                    'description' => 'Management level position',
                    'requirements' => ['Management experience', 'Strategic thinking'],
                ]),
                'created_by' => 'admin',
                'deleted' => true,
                'created' => '2024-01-03 00:00:00',
                'modified' => '2024-01-03 00:00:00',
            ],
            [
                'id' => 1004,
                'company_id' => 200002,
                'level_unique_id' => 'rl-20240104-MNOP3456',
                'template_id' => 4001,
                'name' => 'Executive Level',
                'rank' => 7,
                'custom_fields' => json_encode([
                    'description' => 'Executive level position',
                    'requirements' => ['Executive experience', 'Visionary leadership'],
                ]),
                'created_by' => 'admin',
                'deleted' => false,
                'created' => '2024-01-04 00:00:00',
                'modified' => '2024-01-04 00:00:00',
            ],
        ];
        parent::init();
    }
}
