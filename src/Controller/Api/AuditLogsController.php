<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\Api\ApiController;
use App\Service\AuditService;
use Cake\Core\Configure;
use Cake\Log\Log;
use Exception;

class AuditLogsController extends ApiController
{
    private AuditService $auditService;

    public function initialize(): void
    {
        parent::initialize();
    }

    /**
     * Get audit logs with filtering and pagination
     */
    public function getAuditLogs()
    {
        $this->request->allowMethod(['get']);

        // Authentication check
        $authResult = $this->Authentication->getResult();
        if (!$authResult || !$authResult->isValid()) {
            return $this->response
                ->withStatus(401)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Unauthorized access',
                ]));
        }

        $companyId = (string)$this->getCompanyId($authResult);
        $this->auditService = new AuditService($companyId);

        try {
            // Get query parameters
            $options = [
                'company_id' => $companyId,
                'page' => (int)($this->request->getQuery('page', 1)),
                'limit' => (int)($this->request->getQuery('limit', 20)),
                'search' => $this->request->getQuery('search', ''),
                'action' => $this->request->getQuery('action', ''),
                'entity_type' => $this->request->getQuery('entity_type', ''),
                'status' => $this->request->getQuery('status', ''),
                'date_from' => $this->request->getQuery('date_from', ''),
                'date_to' => $this->request->getQuery('date_to', ''),
                'user_id' => $this->request->getQuery('user_id', ''),
            ];

            // Remove empty options
            $options = array_filter($options, function($value) {
                return $value !== '' && $value !== null;
            });

            $result = $this->auditService->getAuditLogs($options);

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'data' => $result['data'],
                    'pagination' => [
                        'total' => $result['total'],
                        'page' => $result['page'],
                        'limit' => $result['limit'],
                        'total_pages' => $result['total_pages']
                    ]
                ]));

        } catch (Exception $e) {
            Log::error('Error fetching audit logs: ' . $e->getMessage(), [
                'company_id' => $companyId,
                'options' => $options ?? [],
                'trace' => $e->getTraceAsString()
            ]);

            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error fetching audit logs: ' . $e->getMessage(),
                ]));
        }
    }

    /**
     * Get audit log details for a specific audit log
     */
    public function getAuditLogDetails()
    {
        $this->request->allowMethod(['get']);

        // Authentication check
        $authResult = $this->Authentication->getResult();
        if (!$authResult || !$authResult->isValid()) {
            return $this->response
                ->withStatus(401)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Unauthorized access',
                ]));
        }

        $companyId = (string)$this->getCompanyId($authResult);
        $auditLogId = $this->request->getQuery('audit_log_id');

        if (empty($auditLogId)) {
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Audit log ID is required',
                ]));
        }

        try {
            $this->auditService = new AuditService($companyId);
            
            // Get audit log and details using the service
            $result = $this->auditService->getAuditLogWithDetails($auditLogId, $companyId);

            if (!$result) {
                return $this->response
                    ->withStatus(404)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Audit log not found',
                    ]));
            }

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'data' => $result
                ]));

        } catch (Exception $e) {
            Log::error('Error fetching audit log details: ' . $e->getMessage(), [
                'company_id' => $companyId,
                'audit_log_id' => $auditLogId,
                'trace' => $e->getTraceAsString()
            ]);

            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error fetching audit log details: ' . $e->getMessage(),
                ]));
        }
    }

    /**
     * Get audit statistics
     */
    public function getAuditStats()
    {
        $this->request->allowMethod(['get']);

        // Authentication check
        $authResult = $this->Authentication->getResult();
        if (!$authResult || !$authResult->isValid()) {
            return $this->response
                ->withStatus(401)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Unauthorized access',
                ]));
        }

        $companyId = (string)$this->getCompanyId($authResult);
        $this->auditService = new AuditService($companyId);

        try {
            // Get query parameters
            $options = [
                'date_from' => $this->request->getQuery('date_from', ''),
                'date_to' => $this->request->getQuery('date_to', ''),
            ];

            // Remove empty options
            $options = array_filter($options, function($value) {
                return $value !== '' && $value !== null;
            });

            $stats = $this->auditService->getAuditStats($options);

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'data' => $stats
                ]));

        } catch (Exception $e) {
            Log::error('Error fetching audit stats: ' . $e->getMessage(), [
                'company_id' => $companyId,
                'options' => $options ?? [],
                'trace' => $e->getTraceAsString()
            ]);

            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error fetching audit stats: ' . $e->getMessage(),
                ]));
        }
    }

    /**
     * Create a new audit log entry (for testing or manual logging)
     */
    public function createAuditLog()
    {
        $this->request->allowMethod(['post']);

        // Authentication check
        $authResult = $this->Authentication->getResult();
        if (!$authResult || !$authResult->isValid()) {
            return $this->response
                ->withStatus(401)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Unauthorized access',
                ]));
        }

        $companyId = (string)$this->getCompanyId($authResult);
        $this->auditService = new AuditService($companyId);

        try {
            $data = $this->request->getData();
            $details = $data['details'] ?? [];

            // Add user information
            $authData = $authResult->getData();
            $data['user_id'] = $authData->id ?? 0;
            $data['username'] = $authData->username ?? 'system';
            $data['company_id'] = $companyId;

            // Add client information
            $clientInfo = AuditService::extractClientInfo($this->request);
            $data['ip_address'] = $clientInfo['ip_address'];
            $data['user_agent'] = $clientInfo['user_agent'];

            $auditLog = $this->auditService->logActionWithDetails($data, $details);

            if ($auditLog) {
                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => true,
                        'message' => 'Audit log created successfully',
                        'data' => $auditLog
                    ]));
            } else {
                return $this->response
                    ->withStatus(400)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Failed to create audit log',
                    ]));
            }

        } catch (Exception $e) {
            Log::error('Error creating audit log: ' . $e->getMessage(), [
                'company_id' => $companyId,
                'data' => $data ?? [],
                'trace' => $e->getTraceAsString()
            ]);

            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error creating audit log: ' . $e->getMessage(),
                ]));
        }
    }

    /**
     * Get available filter options for audit logs
     */
    public function getFilterOptions()
    {
        $this->request->allowMethod(['get']);

        // Authentication check
        $authResult = $this->Authentication->getResult();
        if (!$authResult || !$authResult->isValid()) {
            return $this->response
                ->withStatus(401)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Unauthorized access',
                ]));
        }

        $companyId = (string)$this->getCompanyId($authResult);

        try {
            $this->auditService = new AuditService($companyId);
            
            // Get filter options using the service
            $filterOptions = $this->auditService->getFilterOptions($companyId);

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'data' => $filterOptions
                ]));

        } catch (Exception $e) {
            Log::error('Error fetching filter options: ' . $e->getMessage(), [
                'company_id' => $companyId,
                'trace' => $e->getTraceAsString()
            ]);

            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error fetching filter options: ' . $e->getMessage(),
                ]));
        }
    }

    /**
     * Helper method to extract company_id from authentication result
     */
    private function getCompanyId($authResult)
    {
        $data = $authResult->getData();
        
        // Handle both ArrayObject and stdClass
        if (is_object($data)) {
            if (isset($data->company_id)) {
                return $data->company_id;
            }
            // Convert to array if needed
            $data = (array) $data;
        }
        
        if (is_array($data) && isset($data['company_id'])) {
            return $data['company_id'];
        }
        
        // Fallback: try to get from JWT payload
        if (method_exists($authResult, 'getPayload')) {
            $payload = $authResult->getPayload();
            if (isset($payload['company_id'])) {
                return $payload['company_id'];
            }
        }
        
        // Default fallback
        return '200001'; // Default company ID
    }
}
