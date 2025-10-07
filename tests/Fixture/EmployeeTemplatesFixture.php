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
        'structure' => ['type' => 'json', 'length' => null, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
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
                'name' => 'Standard Employee Template',
                'created_by' => 'admin',
                'structure' => json_encode([
                    [
                        'id' => 'personal_info',
                        'title' => 'Personal Information',
                        'fields' => [
                            [
                                'id' => 'employee_id',
                                'customize_field_label' => 'Employee ID',
                                'field_type' => 'text',
                                'required' => true
                            ],
                            [
                                'id' => 'username',
                                'customize_field_label' => 'Username',
                                'field_type' => 'text',
                                'required' => true
                            ],
                            [
                                'id' => 'password',
                                'customize_field_label' => 'Password',
                                'field_type' => 'password',
                                'required' => true
                            ],
                            [
                                'id' => 'blood_type',
                                'customize_field_label' => 'Blood Type',
                                'field_type' => 'text',
                                'required' => false
                            ],
                            [
                                'id' => 'first_name',
                                'customize_field_label' => 'First Name',
                                'field_type' => 'text',
                                'required' => true
                            ],
                            [
                                'id' => 'last_name',
                                'customize_field_label' => 'Last Name',
                                'field_type' => 'text',
                                'required' => true
                            ],
                            [
                                'id' => 'email',
                                'customize_field_label' => 'Email Address',
                                'field_type' => 'email',
                                'required' => true
                            ],
                            [
                                'id' => 'phone',
                                'customize_field_label' => 'Phone Number',
                                'field_type' => 'text',
                                'required' => false
                            ]
                        ]
                    ],
                    [
                        'id' => 'job_info',
                        'title' => 'Job Information',
                        'fields' => [
                            [
                                'id' => 'position',
                                'customize_field_label' => 'Position',
                                'field_type' => 'text',
                                'required' => true
                            ],
                            [
                                'id' => 'department',
                                'customize_field_label' => 'Department',
                                'field_type' => 'text',
                                'required' => true
                            ],
                            [
                                'id' => 'manager',
                                'customize_field_label' => 'Manager',
                                'field_type' => 'text',
                                'required' => false
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
                        'title' => 'Personal Information',
                        'fields' => [
                            [
                                'id' => 'employee_id',
                                'customize_field_label' => 'Employee ID',
                                'field_type' => 'text',
                                'required' => true
                            ],
                            [
                                'id' => 'username',
                                'customize_field_label' => 'Username',
                                'field_type' => 'text',
                                'required' => true
                            ],
                            [
                                'id' => 'password',
                                'customize_field_label' => 'Password',
                                'field_type' => 'password',
                                'required' => true
                            ],
                            [
                                'id' => 'blood_type',
                                'customize_field_label' => 'Blood Type',
                                'field_type' => 'text',
                                'required' => false
                            ],
                            [
                                'id' => 'first_name',
                                'customize_field_label' => 'First Name',
                                'field_type' => 'text',
                                'required' => true
                            ],
                            [
                                'id' => 'last_name',
                                'customize_field_label' => 'Last Name',
                                'field_type' => 'text',
                                'required' => true
                            ],
                            [
                                'id' => 'email',
                                'customize_field_label' => 'Email Address',
                                'field_type' => 'email',
                                'required' => true
                            ]
                        ]
                    ],
                    [
                        'id' => 'job_info',
                        'title' => 'Job Information',
                        'fields' => [
                            [
                                'id' => 'position',
                                'customize_field_label' => 'Position',
                                'field_type' => 'text',
                                'required' => true
                            ],
                            [
                                'id' => 'department',
                                'customize_field_label' => 'Department',
                                'field_type' => 'text',
                                'required' => true
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
