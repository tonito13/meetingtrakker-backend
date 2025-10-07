<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * AuditLogsFixture
 */
class AuditLogsFixture extends TestFixture
{
    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
                'company_id' => '200001',
                'user_id' => 1,
                'username' => 'test',
                'action' => 'CREATE',
                'entity_type' => 'employee',
                'entity_id' => 'emp-001',
                'entity_name' => 'John Doe',
                'description' => 'Created new employee John Doe',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Test Browser)',
                'request_data' => '{"name": "John Doe", "email": "john@example.com"}',
                'response_data' => '{"success": true, "id": "emp-001"}',
                'status' => 'success',
                'error_message' => null,
                'created' => '2024-01-15 10:00:00',
                'modified' => '2024-01-15 10:00:00'
            ],
            [
                'id' => 'b1fccb00-0d1c-4ff9-cc7e-7cc0ce491b22',
                'company_id' => '200001',
                'user_id' => 1,
                'username' => 'test',
                'action' => 'UPDATE',
                'entity_type' => 'employee',
                'entity_id' => 'emp-001',
                'entity_name' => 'John Doe',
                'description' => 'Updated employee John Doe',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Test Browser)',
                'request_data' => '{"name": "John Smith", "email": "johnsmith@example.com"}',
                'response_data' => '{"success": true}',
                'status' => 'success',
                'error_message' => null,
                'created' => '2024-01-15 11:00:00',
                'modified' => '2024-01-15 11:00:00'
            ],
            [
                'id' => 'c2addc11-1e2d-4aa0-dd8f-8dd1df502c33',
                'company_id' => '200001',
                'user_id' => 1,
                'username' => 'test',
                'action' => 'DELETE',
                'entity_type' => 'scorecard',
                'entity_id' => 'sc-001',
                'entity_name' => 'Q1 Performance Scorecard',
                'description' => 'Deleted scorecard Q1 Performance Scorecard',
                'ip_address' => '192.168.1.100',
                'user_agent' => 'Mozilla/5.0 (Test Browser)',
                'request_data' => '{"id": "sc-001"}',
                'response_data' => '{"success": true}',
                'status' => 'success',
                'error_message' => null,
                'created' => '2024-01-15 12:00:00',
                'modified' => '2024-01-15 12:00:00'
            ]
        ];
        parent::init();
    }
}