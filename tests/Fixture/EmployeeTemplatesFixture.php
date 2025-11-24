<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * EmployeeTemplatesFixture
 * 
 * Test fixture for EmployeeTemplates model.
 * Provides comprehensive test data for employee template testing.
 */
class EmployeeTemplatesFixture extends TestFixture
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
    public string $table = 'employee_templates';

    /**
     * Fields configuration
     * 
     * @var array
     */
    public array $fields = [
        'id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null, 'autoIncrement' => true],
        'company_id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'name' => ['type' => 'string', 'length' => 150, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'structure' => ['type' => 'jsonb', 'length' => null, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'deleted' => ['type' => 'boolean', 'length' => null, 'null' => false, 'default' => false, 'comment' => '', 'precision' => null],
        'created_by' => ['type' => 'string', 'length' => 150, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'created' => ['type' => 'datetime', 'length' => null, 'null' => true, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '', 'precision' => null],
        'modified' => ['type' => 'datetime', 'length' => null, 'null' => true, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '', 'precision' => null],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id'], 'length' => []],
        ],
        '_options' => [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci'
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
                'name' => 'employee',
                'created_by' => 'admin',
                'structure' => json_encode([
                    [
                        'id' => 'personal_info',
                        'label' => 'Personal Information',
                        'customize_group_label' => 'Personal Information',
                        'fields' => [
                            [
                                'id' => 'employee_id',
                                'label' => 'Employee ID',
                                'customize_field_label' => 'Employee ID',
                                'type' => 'text',
                                'is_required' => true
                            ],
                            [
                                'id' => 'username',
                                'label' => 'Username',
                                'customize_field_label' => 'Username',
                                'type' => 'text',
                                'is_required' => true
                            ],
                            [
                                'id' => 'password',
                                'label' => 'Password',
                                'customize_field_label' => 'Password',
                                'type' => 'password',
                                'is_required' => true
                            ],
                            [
                                'id' => 'blood_type',
                                'label' => 'Blood Type',
                                'customize_field_label' => 'Blood Type',
                                'type' => 'text',
                                'is_required' => false
                            ],
                            [
                                'id' => 'first_name',
                                'label' => 'First Name',
                                'customize_field_label' => 'First Name',
                                'type' => 'text',
                                'is_required' => true
                            ],
                            [
                                'id' => 'last_name',
                                'label' => 'Last Name',
                                'customize_field_label' => 'Last Name',
                                'type' => 'text',
                                'is_required' => true
                            ],
                            [
                                'id' => 'email',
                                'label' => 'Email Address',
                                'customize_field_label' => 'Email Address',
                                'type' => 'email',
                                'is_required' => true
                            ],
                            [
                                'id' => 'phone',
                                'label' => 'Phone Number',
                                'customize_field_label' => 'Phone Number',
                                'type' => 'text',
                                'is_required' => false
                            ]
                        ]
                    ],
                    [
                        'id' => 'job_info',
                        'label' => 'Job Information',
                        'customize_group_label' => 'Job Information',
                        'fields' => [
                            [
                                'id' => 'position',
                                'label' => 'Position',
                                'customize_field_label' => 'Position',
                                'type' => 'text',
                                'is_required' => true
                            ],
                            [
                                'id' => 'department',
                                'label' => 'Department',
                                'customize_field_label' => 'Department',
                                'type' => 'text',
                                'is_required' => true
                            ],
                            [
                                'id' => 'manager',
                                'label' => 'Manager',
                                'customize_field_label' => 'Manager',
                                'type' => 'text',
                                'is_required' => false
                            ]
                        ]
                    ]
                ]),
                'deleted' => false,
                'created' => '2024-01-01 00:00:00',
                'modified' => '2024-01-01 00:00:00',
            ],
            [
                'id' => 1002,
                'company_id' => 200001,
                'name' => 'Senior Employee Template',
                'created_by' => 'admin',
                'structure' => json_encode([
                    [
                        'id' => 'personal_info',
                        'label' => 'Personal Information',
                        'customize_group_label' => 'Personal Information',
                        'fields' => [
                            [
                                'id' => 'employee_id',
                                'label' => 'Employee ID',
                                'customize_field_label' => 'Employee ID',
                                'type' => 'text',
                                'is_required' => true
                            ],
                            [
                                'id' => 'username',
                                'label' => 'Username',
                                'customize_field_label' => 'Username',
                                'type' => 'text',
                                'is_required' => true
                            ],
                            [
                                'id' => 'password',
                                'label' => 'Password',
                                'customize_field_label' => 'Password',
                                'type' => 'password',
                                'is_required' => true
                            ],
                            [
                                'id' => 'blood_type',
                                'label' => 'Blood Type',
                                'customize_field_label' => 'Blood Type',
                                'type' => 'text',
                                'is_required' => false
                            ],
                            [
                                'id' => 'first_name',
                                'label' => 'First Name',
                                'customize_field_label' => 'First Name',
                                'type' => 'text',
                                'is_required' => true
                            ],
                            [
                                'id' => 'last_name',
                                'label' => 'Last Name',
                                'customize_field_label' => 'Last Name',
                                'type' => 'text',
                                'is_required' => true
                            ],
                            [
                                'id' => 'email',
                                'label' => 'Email Address',
                                'customize_field_label' => 'Email Address',
                                'type' => 'email',
                                'is_required' => true
                            ]
                        ]
                    ],
                    [
                        'id' => 'job_info',
                        'label' => 'Job Information',
                        'customize_group_label' => 'Job Information',
                        'fields' => [
                            [
                                'id' => 'position',
                                'label' => 'Position',
                                'customize_field_label' => 'Position',
                                'type' => 'text',
                                'is_required' => true
                            ],
                            [
                                'id' => 'department',
                                'label' => 'Department',
                                'customize_field_label' => 'Department',
                                'type' => 'text',
                                'is_required' => true
                            ]
                        ]
                    ]
                ]),
                'deleted' => false,
                'created' => '2024-01-02 00:00:00',
                'modified' => '2024-01-02 00:00:00',
            ],
            [
                'id' => 1003,
                'company_id' => 200001,
                'name' => 'Deleted Template',
                'created_by' => 'admin',
                'structure' => json_encode([]),
                'deleted' => true,
                'created' => '2024-01-03 00:00:00',
                'modified' => '2024-01-03 00:00:00',
            ],
        ];
        parent::init();
    }
}
