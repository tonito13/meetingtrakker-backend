<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * EmployeeTemplateFixture
 * 
 * Test fixture for EmployeeTemplate model.
 * Provides consistent test data for all EmployeeTemplate related tests.
 */
class EmployeeTemplateFixture extends TestFixture
{

    /**
     * Fields configuration
     * 
     * Define the table structure for this fixture.
     * This should match the actual database table structure.
     * 
     * @var array
     */
    public array $fields = [
        'id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null, 'autoIncrement' => true],
        'company_id' => ['type' => 'string', 'length' => 255, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'name' => ['type' => 'string', 'length' => 150, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'structure' => ['type' => 'text', 'length' => null, 'null' => true, 'default' => null, 'comment' => '', 'precision' => null],
        'created_by' => ['type' => 'string', 'length' => 255, 'null' => true, 'default' => null, 'comment' => '', 'precision' => null],
        'deleted' => ['type' => 'boolean', 'length' => null, 'null' => false, 'default' => false, 'comment' => '', 'precision' => null],
        'created' => ['type' => 'datetime', 'length' => null, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'modified' => ['type' => 'datetime', 'length' => null, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
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
     * Initialize the fixture with test data.
     * This method is called before each test runs.
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                'company_id' => '200001',
                'name' => 'Default Employee Template',
                'structure' => json_encode([
                    'groups' => [
                        [
                            'id' => 'group_1',
                            'label' => 'Personal Information',
                            'fields' => [
                                [
                                    'id' => 'field_1',
                                    'label' => 'First Name',
                                    'type' => 'text',
                                    'required' => true,
                                    'placeholder' => 'Enter first name'
                                ],
                                [
                                    'id' => 'field_2',
                                    'label' => 'Last Name',
                                    'type' => 'text',
                                    'required' => true,
                                    'placeholder' => 'Enter last name'
                                ],
                                [
                                    'id' => 'field_3',
                                    'label' => 'Email',
                                    'type' => 'email',
                                    'required' => true,
                                    'placeholder' => 'Enter email address'
                                ],
                                [
                                    'id' => 'field_4',
                                    'label' => 'Phone',
                                    'type' => 'text',
                                    'required' => false,
                                    'placeholder' => 'Enter phone number'
                                ]
                            ]
                        ],
                        [
                            'id' => 'group_2',
                            'label' => 'Employment Information',
                            'fields' => [
                                [
                                    'id' => 'field_5',
                                    'label' => 'Position',
                                    'type' => 'text',
                                    'required' => true,
                                    'placeholder' => 'Enter job position'
                                ],
                                [
                                    'id' => 'field_6',
                                    'label' => 'Department',
                                    'type' => 'text',
                                    'required' => false,
                                    'placeholder' => 'Enter department'
                                ],
                                [
                                    'id' => 'field_7',
                                    'label' => 'Start Date',
                                    'type' => 'date',
                                    'required' => true
                                ]
                            ]
                        ]
                    ]
                ]),
                'created_by' => 'test_user',
                'deleted' => false,
                'created' => '2024-01-01 00:00:00',
                'modified' => '2024-01-01 00:00:00',
            ],
            [
                'company_id' => '200001',
                'name' => 'Advanced Employee Template',
                'structure' => json_encode([
                    'groups' => [
                        [
                            'id' => 'group_1',
                            'label' => 'Basic Information',
                            'fields' => [
                                [
                                    'id' => 'field_1',
                                    'label' => 'Employee ID',
                                    'type' => 'text',
                                    'required' => true
                                ],
                                [
                                    'id' => 'field_2',
                                    'label' => 'Full Name',
                                    'type' => 'text',
                                    'required' => true
                                ]
                            ]
                        ]
                    ]
                ]),
                'created_by' => 'test_user',
                'deleted' => false,
                'created' => '2024-01-02 00:00:00',
                'modified' => '2024-01-02 00:00:00',
            ],
            [
                'company_id' => '200001',
                'name' => 'Deleted Template',
                'structure' => json_encode(['groups' => []]),
                'created_by' => 'test_user',
                'deleted' => true,
                'created' => '2024-01-03 00:00:00',
                'modified' => '2024-01-03 00:00:00',
            ]
        ];
        
        parent::init();
    }
}
