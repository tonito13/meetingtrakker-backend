<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * JobRolesController LevelTemplates Fixture
 *
 * This is a separate fixture file specifically for JobRolesControllerTest
 * to avoid conflicts with other test classes
 */
class JobRolesControllerLevelTemplatesFixture extends TestFixture
{
    /**
     * Table name
     *
     * @var string
     */
    public string $table = 'level_templates';

    /**
     * Fields configuration
     *
     * @var array
     */
    public array $fields = [
        'id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null, 'autoIncrement' => true],
        'company_id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'name' => ['type' => 'string', 'length' => 255, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'structure' => ['type' => 'json', 'length' => null, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
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
                'id' => 30001, // Use a very high ID to avoid conflicts
                'company_id' => 200001,
                'name' => 'JobRolesController Level Template',
                'structure' => json_encode([
                    [
                        'id' => 'level_info',
                        'title' => 'Level Information',
                        'fields' => [
                            [
                                'id' => 'level_name',
                                'label' => 'Level Name',
                                'field_type' => 'text',
                                'required' => true,
                            ],
                            [
                                'id' => 'rank',
                                'label' => 'Rank',
                                'field_type' => 'number',
                                'required' => true,
                            ],
                            [
                                'id' => 'description',
                                'label' => 'Description',
                                'field_type' => 'textarea',
                                'required' => false,
                            ],
                        ],
                    ],
                ]),
                'created_by' => 'admin',
                'deleted' => false,
                'created' => '2024-01-01 00:00:00',
                'modified' => '2024-01-01 00:00:00',
            ],
            [
                'id' => 30002, // Use a very high ID to avoid conflicts
                'company_id' => 200001,
                'name' => 'JobRolesController Deleted Level Template',
                'structure' => json_encode([]),
                'created_by' => 'admin',
                'deleted' => true,
                'created' => '2024-01-02 00:00:00',
                'modified' => '2024-01-02 00:00:00',
            ],
        ];
        parent::init();
    }
}
