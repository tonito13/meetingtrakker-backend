<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * JobRolesController JobRoleTemplates Fixture
 *
 * This is a separate fixture file specifically for JobRolesControllerTest
 * to avoid conflicts with other test classes that use JobRoleTemplates
 */
class JobRolesControllerJobRoleTemplatesFixture extends TestFixture
{
    /**
     * Connection name to use for this fixture
     * Company-specific tables should use the company-specific test database
     * 
     * @var string
     */
    public string $connection = 'test_client_200001';

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
                'id' => 90001, // Use a very high ID to avoid conflicts
                'company_id' => 200001,
                'name' => 'JobRolesController Test Template',
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
                'id' => 90002, // Use a very high ID to avoid conflicts
                'company_id' => 200001,
                'name' => 'JobRolesController Deleted Template',
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
