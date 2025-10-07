<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * EmployeeAnswerFilesFixture
 *
 * Test fixture for EmployeeAnswerFiles model.
 * Provides comprehensive test data for employee file testing.
 */
class EmployeeAnswerFilesFixture extends TestFixture
{
    /**
     * Table name
     *
     * @var string
     */
    public string $table = 'employee_answer_files';

    /**
     * Fields configuration
     *
     * @var array
     */
    public array $fields = [
        'id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null, 'autoIncrement' => true],
        'company_id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'answer_id' => ['type' => 'integer', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'group_id' => ['type' => 'string', 'length' => 255, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'field_id' => ['type' => 'string', 'length' => 255, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'file_name' => ['type' => 'string', 'length' => 255, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'file_path' => ['type' => 'text', 'length' => null, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'file_type' => ['type' => 'string', 'length' => 50, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'file_size' => ['type' => 'biginteger', 'length' => null, 'unsigned' => false, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'deleted' => ['type' => 'boolean', 'length' => null, 'null' => true, 'default' => false, 'comment' => '', 'precision' => null],
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
                'id' => 1001,
                'company_id' => 200001,
                'answer_id' => 1001,
                'group_id' => 'personal_info',
                'field_id' => 'profile_picture',
                'file_name' => 'profile_picture.jpg',
                'file_path' => '/uploads/employees/EMP001/profile_picture.jpg',
                'file_type' => 'image/jpeg',
                'file_size' => 1024000,
                'deleted' => false,
                'created' => '2024-01-01 00:00:00',
                'modified' => '2024-01-01 00:00:00',
            ],
            [
                'id' => 1002,
                'company_id' => 200001,
                'answer_id' => 1002,
                'group_id' => 'personal_info',
                'field_id' => 'resume',
                'file_name' => 'jane_smith_resume.pdf',
                'file_path' => '/uploads/employees/EMP002/jane_smith_resume.pdf',
                'file_type' => 'application/pdf',
                'file_size' => 2048000,
                'deleted' => false,
                'created' => '2024-01-02 00:00:00',
                'modified' => '2024-01-02 00:00:00',
            ],
        ];
        parent::init();
    }
}
