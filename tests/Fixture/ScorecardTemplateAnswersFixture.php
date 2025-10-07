<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * ScorecardTemplateAnswersFixture
 * 
 * Test fixture for ScorecardTemplateAnswers model.
 * Provides comprehensive test data for scorecard template answers testing.
 */
class ScorecardTemplateAnswersFixture extends TestFixture
{
    /**
     * Table name
     * 
     * @var string
     */
    public string $table = 'scorecard_template_answers';

    /**
     * Fields configuration
     * 
     * @var array
     */
    public array $fields = [
        'id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null, 'autoIncrement' => true],
        'company_id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'scorecard_unique_id' => ['type' => 'string', 'length' => 150, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'template_id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'assigned_employee_username' => ['type' => 'string', 'length' => 100, 'null' => true, 'default' => null, 'comment' => '', 'precision' => null],
        'answers' => ['type' => 'text', 'length' => null, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'created_by' => ['type' => 'string', 'length' => 150, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'deleted' => ['type' => 'boolean', 'length' => null, 'null' => false, 'default' => false, 'comment' => '', 'precision' => null],
        'created' => ['type' => 'datetime', 'length' => null, 'null' => true, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '', 'precision' => null],
        'modified' => ['type' => 'datetime', 'length' => null, 'null' => true, 'default' => 'CURRENT_TIMESTAMP', 'comment' => '', 'precision' => null],
    ];

    /**
     * Records
     * 
     * @var array
     */
    public array $records = [
        [
            'id' => 1001,
            'company_id' => 200001,
            'scorecard_unique_id' => 'SC001',
            'template_id' => 1,
            'assigned_employee_username' => 'EMP001',
            'answers' => '{"scorecard_info":{"scorecard_name":"Q1 2024 Performance Scorecard","department":"Engineering","quarter":"Q1 2024","assigned_employee":"EMP001","job_role":"Software Engineer","role_level":"Senior"}}',
            'created_by' => 'test',
            'deleted' => false,
            'created' => '2024-01-01 00:00:00',
            'modified' => '2024-01-01 00:00:00',
        ],
        [
            'id' => 1002,
            'company_id' => 200001,
            'scorecard_unique_id' => 'SC002',
            'template_id' => 2,
            'assigned_employee_username' => 'EMP002',
            'answers' => '{"scorecard_info":{"scorecard_name":"Q1 2024 Leadership Scorecard","department":"Engineering","quarter":"Q1 2024","assigned_employee":"EMP002","job_role":"Manager","role_level":"Lead"}}',
            'created_by' => 'test',
            'deleted' => false,
            'created' => '2024-01-02 00:00:00',
            'modified' => '2024-01-02 00:00:00',
        ],
        [
            'id' => 1003,
            'company_id' => 200001,
            'scorecard_unique_id' => 'SC003',
            'template_id' => 1,
            'assigned_employee_username' => 'EMP003',
            'answers' => '{"scorecard_info":{"scorecard_name":"Deleted Scorecard","department":"Engineering","quarter":"Q1 2024","assigned_employee":"EMP003","job_role":"Software Engineer","role_level":"Junior"}}',
            'created_by' => 'test',
            'deleted' => true,
            'created' => '2024-01-03 00:00:00',
            'modified' => '2024-01-03 00:00:00',
        ],
        [
            'id' => 1004,
            'company_id' => 200001,
            'scorecard_unique_id' => 'SC004',
            'template_id' => 1,
            'assigned_employee_username' => 'test',
            'answers' => '{"scorecard_info":{"scorecard_name":"Test User Scorecard","department":"Engineering","quarter":"Q1 2024","assigned_employee":"test","job_role":"Software Engineer","role_level":"Senior"}}',
            'created_by' => 'test',
            'deleted' => false,
            'created' => '2024-01-04 00:00:00',
            'modified' => '2024-01-04 00:00:00',
        ],
    ];
}
