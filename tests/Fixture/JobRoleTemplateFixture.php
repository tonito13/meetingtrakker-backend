<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * JobRoleTemplateFixture
 * 
 * Test fixture for JobRoleTemplate model.
 * Provides consistent test data for all JobRoleTemplate related tests.
 */
class JobRoleTemplateFixture extends TestFixture
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
        'created_by' => ['type' => 'string', 'length' => 150, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
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
                'name' => 'Default Job Role Template',
                'created_by' => 'test',
                'structure' => json_encode([
                    'groups' => [
                        [
                            'id' => 'group_1',
                            'label' => 'Job Information',
                            'fields' => [
                                [
                                    'id' => 'field_1',
                                    'label' => 'Role Code',
                                    'type' => 'text',
                                    'required' => true,
                                    'placeholder' => 'Enter role code'
                                ],
                                [
                                    'id' => 'field_2',
                                    'label' => 'Official Designation',
                                    'type' => 'text',
                                    'required' => true,
                                    'placeholder' => 'Enter official designation'
                                ],
                                [
                                    'id' => 'field_3',
                                    'label' => 'Level',
                                    'type' => 'select',
                                    'required' => true,
                                    'options' => ['Junior', 'Mid', 'Senior', 'Lead', 'Manager']
                                ],
                                [
                                    'id' => 'field_4',
                                    'label' => 'Description',
                                    'type' => 'textarea',
                                    'required' => false,
                                    'placeholder' => 'Enter job description'
                                ]
                            ]
                        ],
                        [
                            'id' => 'group_2',
                            'label' => 'Requirements',
                            'fields' => [
                                [
                                    'id' => 'field_5',
                                    'label' => 'Experience Required',
                                    'type' => 'number',
                                    'required' => true,
                                    'min' => 0,
                                    'max' => 20
                                ],
                                [
                                    'id' => 'field_6',
                                    'label' => 'Skills Required',
                                    'type' => 'textarea',
                                    'required' => false,
                                    'placeholder' => 'List required skills'
                                ]
                            ]
                        ]
                    ]
                ]),
                'deleted' => false,
                'created' => '2024-01-01 00:00:00',
                'modified' => '2024-01-01 00:00:00',
            ],
            [
                'company_id' => '200001',
                'name' => 'Advanced Job Role Template',
                'created_by' => 'test',
                'structure' => json_encode([
                    'groups' => [
                        [
                            'id' => 'group_1',
                            'label' => 'Basic Information',
                            'fields' => [
                                [
                                    'id' => 'field_1',
                                    'label' => 'Role Name',
                                    'type' => 'text',
                                    'required' => true
                                ],
                                [
                                    'id' => 'field_2',
                                    'label' => 'Role Code',
                                    'type' => 'text',
                                    'required' => true
                                ]
                            ]
                        ]
                    ]
                ]),
                'deleted' => false,
                'created' => '2024-01-02 00:00:00',
                'modified' => '2024-01-02 00:00:00',
            ],
            [
                'company_id' => '200001',
                'name' => 'Deleted Template',
                'created_by' => 'test',
                'structure' => json_encode(['groups' => []]),
                'deleted' => true,
                'created' => '2024-01-03 00:00:00',
                'modified' => '2024-01-03 00:00:00',
            ]
        ];
        
        parent::init();
    }
}
