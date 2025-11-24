<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * AuditLogDetailsFixture
 */
class AuditLogDetailsFixture extends TestFixture
{
    /**
     * Connection name to use for this fixture
     * Company-specific tables should use the company-specific test database
     * 
     * @var string
     */
    public string $connection = 'test_client_200001';

    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => 'd3aeee22-2f3e-4aa1-ee9a-9ee2ea613d44',
                'audit_log_id' => 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
                'field_name' => 'name',
                'field_label' => 'Name',
                'old_value' => null,
                'new_value' => 'John Doe',
                'change_type' => 'added',
                'created' => '2024-01-15 10:00:00'
            ],
            [
                'id' => 'e4affd33-3a4f-4aa2-ff0a-0ff3fa724e55',
                'audit_log_id' => 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
                'field_name' => 'email',
                'field_label' => 'Email',
                'old_value' => null,
                'new_value' => 'john@example.com',
                'change_type' => 'added',
                'created' => '2024-01-15 10:00:00'
            ],
            [
                'id' => 'f5a4e444-4a5a-4aa3-aa1a-1aa4aa835f66',
                'audit_log_id' => 'b1fccb00-0d1c-4ff9-cc7e-7cc0ce491b22',
                'field_name' => 'name',
                'field_label' => 'Name',
                'old_value' => 'John Doe',
                'new_value' => 'John Smith',
                'change_type' => 'changed',
                'created' => '2024-01-15 11:00:00'
            ],
            [
                'id' => 'a6a5f555-5a6a-4aa4-aa2a-2aa5aa946a77',
                'audit_log_id' => 'b1fccb00-0d1c-4ff9-cc7e-7cc0ce491b22',
                'field_name' => 'email',
                'field_label' => 'Email',
                'old_value' => 'john@example.com',
                'new_value' => 'johnsmith@example.com',
                'change_type' => 'changed',
                'created' => '2024-01-15 11:00:00'
            ],
            [
                'id' => 'b7a6a666-6a7a-4aa5-aa3a-3aa6aa057b88',
                'audit_log_id' => 'c2addc11-1e2d-4aa0-dd8f-8dd1df502c33',
                'field_name' => 'status',
                'field_label' => 'Status',
                'old_value' => 'active',
                'new_value' => 'deleted',
                'change_type' => 'removed',
                'created' => '2024-01-15 12:00:00'
            ]
        ];
        parent::init();
    }
}