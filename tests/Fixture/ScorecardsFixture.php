<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class ScorecardsFixture extends TestFixture
{
    public $fields = [
        'id' => ['type' => 'integer', 'length' => 11, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'autoIncrement' => true, 'precision' => null],
        'company_id' => ['type' => 'integer', 'length' => 11, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'scorecard_unique_id' => ['type' => 'string', 'length' => 255, 'null' => false, 'default' => null, 'collate' => 'utf8mb4_general_ci', 'comment' => '', 'precision' => null],
        'template_id' => ['type' => 'integer', 'length' => 11, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'employee_id' => ['type' => 'string', 'length' => 255, 'null' => false, 'default' => null, 'collate' => 'utf8mb4_general_ci', 'comment' => '', 'precision' => null],
        'manager_id' => ['type' => 'string', 'length' => 255, 'null' => true, 'default' => null, 'collate' => 'utf8mb4_general_ci', 'comment' => '', 'precision' => null],
        'title' => ['type' => 'string', 'length' => 255, 'null' => false, 'default' => null, 'collate' => 'utf8mb4_general_ci', 'comment' => '', 'precision' => null],
        'description' => ['type' => 'text', 'length' => 4294967295, 'null' => true, 'default' => null, 'collate' => 'utf8mb4_general_ci', 'comment' => '', 'precision' => null],
        'status' => ['type' => 'string', 'length' => 50, 'null' => false, 'default' => 'draft', 'collate' => 'utf8mb4_general_ci', 'comment' => '', 'precision' => null],
        'period_start' => ['type' => 'date', 'length' => null, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'period_end' => ['type' => 'date', 'length' => null, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'data' => ['type' => 'text', 'length' => 4294967295, 'null' => true, 'default' => null, 'collate' => 'utf8mb4_general_ci', 'comment' => '', 'precision' => null],
        'deleted' => ['type' => 'boolean', 'length' => null, 'null' => false, 'default' => '0', 'comment' => '', 'precision' => null],
        'created' => ['type' => 'datetime', 'length' => null, 'precision' => 6, 'null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => ''],
        'modified' => ['type' => 'datetime', 'length' => null, 'precision' => 6, 'null' => false, 'default' => 'CURRENT_TIMESTAMP', 'comment' => ''],
        'created_by' => ['type' => 'string', 'length' => 255, 'null' => true, 'default' => null, 'collate' => 'utf8mb4_general_ci', 'comment' => '', 'precision' => null],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id'], 'length' => []],
        ],
        '_options' => [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_general_ci',
        ],
    ];

    public array $records = [
        [
            'id' => 1,
            'company_id' => 200001,
            'scorecard_unique_id' => 'SC001',
            'template_id' => 1,
            'employee_id' => 'EMP001',
            'manager_id' => 'EMP002',
            'title' => 'Q1 2024 Performance Scorecard',
            'description' => 'First quarter performance evaluation',
            'status' => 'active',
            'period_start' => '2024-01-01',
            'period_end' => '2024-03-31',
            'data' => '{"goals":[{"id":"goal_1","value":"Complete project A","rating":3},{"id":"goal_2","value":"Improve team efficiency","rating":2}]}',
            'deleted' => 0,
            'created' => '2024-01-01 00:00:00',
            'modified' => '2024-01-01 00:00:00',
            'created_by' => 'admin',
        ],
        [
            'id' => 2,
            'company_id' => 200001,
            'scorecard_unique_id' => 'SC002',
            'template_id' => 2,
            'employee_id' => 'EMP002',
            'manager_id' => 'EMP003',
            'title' => 'Q1 2024 Leadership Scorecard',
            'description' => 'Leadership performance evaluation',
            'status' => 'completed',
            'period_start' => '2024-01-01',
            'period_end' => '2024-03-31',
            'data' => '{"leadership":[{"id":"team_performance","value":"Exceeded expectations","rating":4}]}',
            'deleted' => 0,
            'created' => '2024-01-02 00:00:00',
            'modified' => '2024-01-02 00:00:00',
            'created_by' => 'admin',
        ],
        [
            'id' => 3,
            'company_id' => 200001,
            'scorecard_unique_id' => 'SC003',
            'template_id' => 1,
            'employee_id' => 'EMP003',
            'manager_id' => 'EMP001',
            'title' => 'Deleted Scorecard',
            'description' => 'This scorecard was deleted',
            'status' => 'draft',
            'period_start' => '2024-01-01',
            'period_end' => '2024-03-31',
            'data' => '{}',
            'deleted' => 1,
            'created' => '2024-01-03 00:00:00',
            'modified' => '2024-01-03 00:00:00',
            'created_by' => 'admin',
        ],
    ];
}
