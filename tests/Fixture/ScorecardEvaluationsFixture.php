<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class ScorecardEvaluationsFixture extends TestFixture
{
    public $fields = [
        'id' => ['type' => 'integer', 'length' => 11, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'autoIncrement' => true, 'precision' => null],
        'company_id' => ['type' => 'integer', 'length' => 11, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'scorecard_unique_id' => ['type' => 'string', 'length' => 255, 'null' => false, 'default' => null, 'collate' => 'utf8mb4_general_ci', 'comment' => '', 'precision' => null],
        'evaluator_id' => ['type' => 'string', 'length' => 255, 'null' => false, 'default' => null, 'collate' => 'utf8mb4_general_ci', 'comment' => '', 'precision' => null],
        'evaluation_type' => ['type' => 'string', 'length' => 50, 'null' => false, 'default' => 'self', 'collate' => 'utf8mb4_general_ci', 'comment' => '', 'precision' => null],
        'rating' => ['type' => 'decimal', 'length' => 3, 'precision' => 2, 'unsigned' => false, 'null' => true, 'default' => null, 'comment' => '', 'precision' => null],
        'comments' => ['type' => 'text', 'length' => 4294967295, 'null' => true, 'default' => null, 'collate' => 'utf8mb4_general_ci', 'comment' => '', 'precision' => null],
        'evaluation_data' => ['type' => 'text', 'length' => 4294967295, 'null' => true, 'default' => null, 'collate' => 'utf8mb4_general_ci', 'comment' => '', 'precision' => null],
        'status' => ['type' => 'string', 'length' => 50, 'null' => false, 'default' => 'draft', 'collate' => 'utf8mb4_general_ci', 'comment' => '', 'precision' => null],
        'submitted_at' => ['type' => 'datetime', 'length' => null, 'precision' => 6, 'null' => true, 'default' => null, 'comment' => ''],
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
            'evaluator_id' => 'EMP001',
            'evaluation_type' => 'self',
            'rating' => 3.5,
            'comments' => 'Good performance overall, met most objectives',
            'evaluation_data' => '{"goals":[{"id":"goal_1","rating":4,"comment":"Exceeded expectations"},{"id":"goal_2","rating":3,"comment":"Met expectations"}]}',
            'status' => 'submitted',
            'submitted_at' => '2024-01-15 10:30:00',
            'deleted' => 0,
            'created' => '2024-01-10 00:00:00',
            'modified' => '2024-01-15 10:30:00',
            'created_by' => 'EMP001',
        ],
        [
            'id' => 2,
            'company_id' => 200001,
            'scorecard_unique_id' => 'SC001',
            'evaluator_id' => 'EMP002',
            'evaluation_type' => 'manager',
            'rating' => 3.0,
            'comments' => 'Solid performance, room for improvement',
            'evaluation_data' => '{"goals":[{"id":"goal_1","rating":3,"comment":"Good work"},{"id":"goal_2","rating":3,"comment":"Average performance"}]}',
            'status' => 'submitted',
            'submitted_at' => '2024-01-20 14:15:00',
            'deleted' => 0,
            'created' => '2024-01-10 00:00:00',
            'modified' => '2024-01-20 14:15:00',
            'created_by' => 'EMP002',
        ],
        [
            'id' => 3,
            'company_id' => 200001,
            'scorecard_unique_id' => 'SC002',
            'evaluator_id' => 'EMP002',
            'evaluation_type' => 'self',
            'rating' => 4.0,
            'comments' => 'Excellent leadership demonstrated',
            'evaluation_data' => '{"leadership":[{"id":"team_performance","rating":4,"comment":"Outstanding team results"}]}',
            'status' => 'draft',
            'submitted_at' => null,
            'deleted' => 0,
            'created' => '2024-01-12 00:00:00',
            'modified' => '2024-01-12 00:00:00',
            'created_by' => 'EMP002',
        ],
        [
            'id' => 4,
            'company_id' => 200001,
            'scorecard_unique_id' => 'SC003',
            'evaluator_id' => 'EMP003',
            'evaluation_type' => 'self',
            'rating' => 2.5,
            'comments' => 'Needs improvement',
            'evaluation_data' => '{}',
            'status' => 'deleted',
            'submitted_at' => null,
            'deleted' => 1,
            'created' => '2024-01-13 00:00:00',
            'modified' => '2024-01-13 00:00:00',
            'created_by' => 'EMP003',
        ],
    ];
}
