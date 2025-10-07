<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * JobRoleTemplatesFixture
 *
 * Test fixture for JobRoleTemplates model.
 * Provides comprehensive test data for job role template testing.
 */
class JobRoleTemplatesFixture extends TestFixture
{
    /**
     * Table name
     *
     * @var string
     */
    public string $table = 'job_role_templates';

    /**
     * Fields configuration
     *
     * @var array
     */
    public array $fields = [
        'id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null, 'autoIncrement' => true],
        'company_id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'name' => ['type' => 'string', 'length' => 150, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'structure' => ['type' => 'text', 'length' => null, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
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
                'id' => 9001, // Use a unique ID to avoid conflicts
                'company_id' => 200001,
                'name' => 'Standard Job Role Template',
                'created_by' => 'admin',
                'structure' => json_encode([
                    [
                        'id' => 'job_info',
                        'title' => 'Job Information',
                        'fields' => [
                            [
                                'id' => 'role_code',
                                'label' => 'Role Code',
                                'customize_field_label' => 'Role Code',
                                'field_type' => 'text',
                                'required' => true,
                            ],
                            [
                                'id' => 'official_designation',
                                'label' => 'Official Designation',
                                'customize_field_label' => 'Official Designation',
                                'field_type' => 'text',
                                'required' => true,
                            ],
                            [
                                'id' => 'level',
                                'label' => 'Level',
                                'customize_field_label' => 'Level',
                                'field_type' => 'select',
                                'required' => true,
                                'options' => [
                                    ['value' => 'Junior'],
                                    ['value' => 'Senior'],
                                    ['value' => 'Manager'],
                                    ['value' => 'Director'],
                                ],
                            ],
                        ],
                    ],
                ]),
                'deleted' => false,
                'created' => '2024-01-01 00:00:00',
                'modified' => '2024-01-01 00:00:00',
            ],
            [
                'id' => 9002, // Use a unique ID to avoid conflicts
                'company_id' => 200001,
                'name' => 'Deleted Job Role Template',
                'created_by' => 'admin',
                'structure' => json_encode([]),
                'deleted' => true,
                'created' => '2024-01-02 00:00:00',
                'modified' => '2024-01-02 00:00:00',
            ],
        ];
        parent::init();
    }
}
