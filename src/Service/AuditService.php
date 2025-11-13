<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Table\AuditLogsTable;
use App\Model\Table\AuditLogDetailsTable;
use Cake\ORM\TableRegistry;
use Cake\Log\Log;
use Exception;

/**
 * Audit Service
 * 
 * Centralized service for audit logging functionality
 */
class AuditService
{
    private AuditLogsTable $auditLogsTable;
    private AuditLogDetailsTable $auditLogDetailsTable;
    private string $companyId;

    public function __construct(string $companyId = 'default')
    {
        $this->companyId = $companyId;
        
        // Get company-specific database connection
        $connection = $this->getConnection($companyId);
        
        // Get table locator
        $locator = TableRegistry::getTableLocator();
        
        // Get table instances directly with the specific connection
        // Use the standard table names but ensure they use the correct connection
        // Clear any existing instances to avoid conflicts
        try {
            $locator->remove('AuditLogs');
        } catch (\Exception $e) {
            // Table might not exist, that's okay
        }
        
        try {
            $locator->remove('AuditLogDetails');
        } catch (\Exception $e) {
            // Table might not exist, that's okay
        }
        
        // Get table instances with the specific connection
        $this->auditLogsTable = $locator->get('AuditLogs', [
            'connection' => $connection
        ]);
        
        $this->auditLogDetailsTable = $locator->get('AuditLogDetails', [
            'connection' => $connection
        ]);
        
        // Configure the association to use the correct table instance
        $association = $this->auditLogsTable->getAssociation('AuditLogDetails');
        if ($association) {
            $association->setTarget($this->auditLogDetailsTable);
        }
    }

    /**
     * Get database connection for company
     *
     * @param string $companyId
     * @return \Cake\Database\Connection
     */
    private function getConnection(string $companyId)
    {
        if ($companyId === 'default') {
            return \Cake\Datasource\ConnectionManager::get('default');
        }
        return \Cake\Datasource\ConnectionManager::get('client_' . $companyId);
    }

    /**
     * Log a simple audit action
     *
     * @param array $data
     * @return \App\Model\Entity\AuditLog|null
     */
    public function logAction(array $data): ?\App\Model\Entity\AuditLog
    {
        Log::debug('🔍 DEBUG: AuditService::logAction - Input data', [
            'data' => $data,
            'company_id' => $this->companyId
        ]);

        try {
            $auditLogData = [
                'company_id' => $this->companyId,
                'user_id' => $data['user_id'] ?? 0,
                'username' => $data['username'] ?? 'system',
                'action' => $data['action'] ?? 'UNKNOWN',
                'entity_type' => $data['entity_type'] ?? 'unknown',
                'entity_id' => $data['entity_id'] ?? null,
                'entity_name' => $data['entity_name'] ?? null,
                'description' => $data['description'] ?? null,
                'ip_address' => $data['ip_address'] ?? null,
                'user_agent' => $data['user_agent'] ?? null,
                'request_data' => $data['request_data'] ?? null,
                'response_data' => $data['response_data'] ?? null,
                'status' => $data['status'] ?? 'success',
                'error_message' => $data['error_message'] ?? null,
                'user_data' => json_encode($data['user_data'] ?? []),
            ];

            Log::debug('🔍 DEBUG: AuditService::logAction - Audit log data', [
                'audit_log_data' => $auditLogData,
                'user_data_json' => $auditLogData['user_data']
            ]);

            $auditLog = $this->auditLogsTable->newEntity($auditLogData);

            if ($this->auditLogsTable->save($auditLog)) {
                Log::debug('🔍 DEBUG: AuditService::logAction - Audit log saved successfully', [
                    'audit_log_id' => $auditLog->id,
                    'entity_name' => $auditLog->entity_name,
                    'username' => $auditLog->username
                ]);
                return $auditLog;
            }

            Log::error('Failed to save audit log', [
                'data' => $data,
                'errors' => $auditLog->getErrors()
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('Error logging audit action: ' . $e->getMessage(), [
                'data' => $data,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Log an audit action with detailed field changes
     *
     * @param array $data
     * @param array $details
     * @return \App\Model\Entity\AuditLog|null
     */
    public function logActionWithDetails(array $data, array $details = []): ?\App\Model\Entity\AuditLog
    {
        Log::debug('🔍 DEBUG: AuditService::logActionWithDetails - Input data', [
            'data' => $data,
            'details' => $details,
            'details_count' => count($details)
        ]);

        try {
            // Start transaction
            $connection = $this->auditLogsTable->getConnection();
            $connection->begin();

            // Create audit log
            $auditLog = $this->logAction($data);

            if (!$auditLog) {
                Log::debug('🔍 DEBUG: AuditService::logActionWithDetails - Failed to create audit log, rolling back');
                $connection->rollback();
                return null;
            }

            Log::debug('🔍 DEBUG: AuditService::logActionWithDetails - Audit log created', [
                'audit_log_id' => $auditLog->id
            ]);

            // Add details if provided
            if (!empty($details)) {
                Log::debug('🔍 DEBUG: AuditService::logActionWithDetails - Processing details', [
                    'details_count' => count($details)
                ]);
                
                foreach ($details as $index => $detail) {
                    Log::debug('🔍 DEBUG: AuditService::logActionWithDetails - Processing detail', [
                        'index' => $index,
                        'detail' => $detail
                    ]);
                    
                    $auditLogDetail = $this->auditLogDetailsTable->newEntity([
                        'audit_log_id' => $auditLog->id,
                        'field_name' => $detail['field_name'] ?? '',
                        'field_label' => $detail['field_label'] ?? null,
                        'old_value' => $detail['old_value'] ?? null,
                        'new_value' => $detail['new_value'] ?? null,
                        'change_type' => $detail['change_type'] ?? 'changed',
                    ]);

                    if (!$this->auditLogDetailsTable->save($auditLogDetail)) {
                        Log::error('Failed to save audit log detail', [
                            'detail' => $detail,
                            'errors' => $auditLogDetail->getErrors()
                        ]);
                        $connection->rollback();
                        return null;
                    }
                    
                    Log::debug('🔍 DEBUG: AuditService::logActionWithDetails - Detail saved', [
                        'detail_id' => $auditLogDetail->id
                    ]);
                }
            } else {
                Log::debug('🔍 DEBUG: AuditService::logActionWithDetails - No details provided');
            }

            $connection->commit();
            Log::debug('🔍 DEBUG: AuditService::logActionWithDetails - Transaction committed');
            return $auditLog;

        } catch (Exception $e) {
            if (isset($connection) && $connection->inTransaction()) {
                $connection->rollback();
            }
            Log::error('Error logging audit action with details: ' . $e->getMessage(), [
                'data' => $data,
                'details' => $details,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Log a scorecard action
     *
     * @param string $action
     * @param string $scorecardId
     * @param string $scorecardName
     * @param array $userData
     * @param array $requestData
     * @param array $responseData
     * @param array $fieldChanges
     * @return \App\Model\Entity\AuditLog|null
     */
    public function logScorecardAction(
        string $action,
        string $scorecardId,
        string $scorecardName,
        array $userData,
        array $requestData = [],
        array $responseData = [],
        array $fieldChanges = []
    ): ?\App\Model\Entity\AuditLog {
        $data = [
            'user_id' => $userData['user_id'] ?? 0,
            'username' => $userData['username'] ?? 'system',
            'action' => $action,
            'entity_type' => 'scorecard',
            'entity_id' => $scorecardId,
            'entity_name' => $scorecardName,
            'description' => $this->getScorecardDescription($action, $scorecardName),
            'ip_address' => $userData['ip_address'] ?? null,
            'user_agent' => $userData['user_agent'] ?? null,
            'request_data' => $requestData,
            'response_data' => $responseData,
            'status' => $userData['status'] ?? 'success',
            'error_message' => $userData['error_message'] ?? null,
        ];

        if (!empty($fieldChanges)) {
            return $this->logActionWithDetails($data, $fieldChanges);
        }

        return $this->logAction($data);
    }

    /**
     * Log an employee action
     *
     * @param string $action
     * @param string $employeeId
     * @param string $employeeName
     * @param array $userData
     * @param array $requestData
     * @param array $responseData
     * @param array $fieldChanges
     * @return \App\Model\Entity\AuditLog|null
     */
    public function logEmployeeAction(
        string $action,
        string $employeeId,
        string $employeeName,
        array $userData,
        array $requestData = [],
        array $responseData = [],
        array $fieldChanges = []
    ): ?\App\Model\Entity\AuditLog {
        $data = [
            'user_id' => $userData['user_id'] ?? 0,
            'username' => $userData['username'] ?? 'system',
            'action' => $action,
            'entity_type' => 'employee',
            'entity_id' => $employeeId,
            'entity_name' => $employeeName,
            'description' => $this->getEmployeeDescription($action, $employeeName),
            'ip_address' => $userData['ip_address'] ?? null,
            'user_agent' => $userData['user_agent'] ?? null,
            'request_data' => $requestData,
            'response_data' => $responseData,
            'status' => $userData['status'] ?? 'success',
            'error_message' => $userData['error_message'] ?? null,
            'user_data' => $userData, // Add user_data to the data array
        ];

        if (!empty($fieldChanges)) {
            return $this->logActionWithDetails($data, $fieldChanges);
        }

        return $this->logAction($data);
    }

    /**
     * Log an authentication action
     *
     * @param string $action
     * @param array $userData
     * @param array $requestData
     * @param string $status
     * @param string $errorMessage
     * @return \App\Model\Entity\AuditLog|null
     */
    public function logAuthAction(
        string $action,
        array $userData,
        array $requestData = [],
        string $status = 'success',
        string $errorMessage = null
    ): ?\App\Model\Entity\AuditLog {
        $data = [
            'user_id' => $userData['user_id'] ?? 0,
            'username' => $userData['username'] ?? 'unknown',
            'action' => $action,
            'entity_type' => 'user',
            'entity_id' => $userData['user_id'] ?? null,
            'entity_name' => $userData['username'] ?? 'Unknown User',
            'description' => $this->getAuthDescription($action, $userData['username'] ?? 'Unknown'),
            'ip_address' => $userData['ip_address'] ?? null,
            'user_agent' => $userData['user_agent'] ?? null,
            'request_data' => $requestData,
            'status' => $status,
            'error_message' => $errorMessage,
        ];

        return $this->logAction($data);
    }

    /**
     * Get audit logs with filtering
     *
     * @param array $options
     * @return array
     */
    public function getAuditLogs(array $options = []): array
    {
        $query = $this->auditLogsTable->find('filtered', $options);

        // Pagination
        $page = (int)($options['page'] ?? 1);
        $limit = (int)($options['limit'] ?? 20);
        $offset = ($page - 1) * $limit;

        $query->limit($limit)->offset($offset);

        $auditLogs = $query->toArray();
        $total = $this->auditLogsTable->find('filtered', $options)->count();

        return [
            'data' => $auditLogs,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ];
    }

    /**
     * Get table instance for external access
     *
     * @param string $tableName
     * @return \Cake\ORM\Table
     */
    public function getTable(string $tableName)
    {
        return TableRegistry::getTableLocator()->get($tableName, [
            'connection' => $this->getConnection($this->companyId)
        ]);
    }

    /**
     * Get audit log with details
     *
     * @param string $auditLogId
     * @param string $companyId
     * @return array|null
     */
    public function getAuditLogWithDetails(string $auditLogId, string $companyId): ?array
    {
        // Get audit log
        $auditLog = $this->auditLogsTable->find()
            ->where([
                'id' => $auditLogId,
                'company_id' => $companyId
            ])
            ->first();

        if (!$auditLog) {
            return null;
        }

        // Get details
        $details = $this->auditLogDetailsTable->find()
            ->where(['audit_log_id' => $auditLogId])
            ->orderAsc('field_name')
            ->toArray();

        return [
            'audit_log' => $auditLog,
            'details' => $details
        ];
    }

    /**
     * Get available filter options for audit logs
     *
     * @param string $companyId
     * @return array
     */
    public function getFilterOptions(string $companyId): array
    {
        // Get unique values for filters
        $actions = $this->auditLogsTable->find()
            ->select(['action'])
            ->where(['company_id' => $companyId])
            ->group(['action'])
            ->orderAsc('action')
            ->toArray();

        $entityTypes = $this->auditLogsTable->find()
            ->select(['entity_type'])
            ->where(['company_id' => $companyId])
            ->group(['entity_type'])
            ->orderAsc('entity_type')
            ->toArray();

        $statuses = $this->auditLogsTable->find()
            ->select(['status'])
            ->where(['company_id' => $companyId])
            ->group(['status'])
            ->orderAsc('status')
            ->toArray();

        $users = $this->auditLogsTable->find()
            ->select(['username'])
            ->where(['company_id' => $companyId])
            ->group(['username'])
            ->orderAsc('username')
            ->toArray();

        return [
            'actions' => array_column($actions, 'action'),
            'entity_types' => array_column($entityTypes, 'entity_type'),
            'statuses' => array_column($statuses, 'status'),
            'users' => array_column($users, 'username')
        ];
    }

    /**
     * Get audit statistics
     *
     * @param array $options
     * @return array
     */
    public function getAuditStats(array $options = []): array
    {
        return $this->auditLogsTable->getAuditStats($this->companyId, $options);
    }

    /**
     * Get scorecard action description
     *
     * @param string $action
     * @param string $scorecardName
     * @return string
     */
    private function getScorecardDescription(string $action, string $scorecardName): string
    {
        return match (strtoupper($action)) {
            'CREATE' => "Created scorecard: {$scorecardName}",
            'UPDATE' => "Updated scorecard: {$scorecardName}",
            'DELETE' => "Deleted scorecard: {$scorecardName}",
            'EVALUATE' => "Evaluated scorecard: {$scorecardName}",
            'ASSIGN' => "Assigned scorecard: {$scorecardName}",
            'UNASSIGN' => "Unassigned scorecard: {$scorecardName}",
            default => "Performed {$action} on scorecard: {$scorecardName}",
        };
    }

    /**
     * Get employee action description
     *
     * @param string $action
     * @param string $employeeName
     * @return string
     */
    private function getEmployeeDescription(string $action, string $employeeName): string
    {
        return match (strtoupper($action)) {
            'CREATE' => "Created employee: {$employeeName}",
            'UPDATE' => "Updated employee: {$employeeName}",
            'DELETE' => "Deleted employee: {$employeeName}",
            'FILE_UPLOAD' => "Uploaded file for employee: {$employeeName}",
            'FILE_DELETE' => "Deleted file for employee: {$employeeName}",
            default => "Performed {$action} on employee: {$employeeName}",
        };
    }

    /**
     * Get authentication action description
     *
     * @param string $action
     * @param string $username
     * @return string
     */
    private function getAuthDescription(string $action, string $username): string
    {
        return match (strtoupper($action)) {
            'LOGIN' => "User {$username} logged in",
            'LOGOUT' => "User {$username} logged out",
            'LOGIN_FAILED' => "Failed login attempt for user {$username}",
            'PASSWORD_CHANGE' => "User {$username} changed password",
            'ACCOUNT_LOCKED' => "Account locked for user {$username}",
            default => "Authentication action {$action} for user {$username}",
        };
    }

    /**
     * Extract client information from request
     *
     * @param \Cake\Http\ServerRequest $request
     * @return array
     */
    public static function extractClientInfo(\Cake\Http\ServerRequest $request): array
    {
        return [
            'ip_address' => $request->clientIp(),
            'user_agent' => $request->getHeaderLine('User-Agent'),
        ];
    }

    /**
     * Log a role level action
     *
     * @param string $action
     * @param string $roleLevelId
     * @param string $roleLevelName
     * @param array $userData
     * @param array $requestData
     * @param array $responseData
     * @param array $fieldChanges
     * @return \App\Model\Entity\AuditLog|null
     */
    public function logRoleLevelAction(
        string $action,
        string $roleLevelId,
        string $roleLevelName,
        array $userData,
        array $requestData = [],
        array $responseData = [],
        array $fieldChanges = []
    ): ?\App\Model\Entity\AuditLog {
        Log::debug('🔍 DEBUG: AuditService::logRoleLevelAction - Input parameters', [
            'action' => $action,
            'role_level_id' => $roleLevelId,
            'role_level_name' => $roleLevelName,
            'field_changes' => $fieldChanges,
            'field_changes_count' => count($fieldChanges),
            'field_changes_empty' => empty($fieldChanges)
        ]);

        $data = [
            'user_id' => $userData['user_id'] ?? 0,
            'username' => $userData['username'] ?? 'system',
            'action' => $action,
            'entity_type' => 'role_level',
            'entity_id' => $roleLevelId,
            'entity_name' => $roleLevelName,
            'description' => $this->getRoleLevelDescription($action, $roleLevelName),
            'ip_address' => $userData['ip_address'] ?? null,
            'user_agent' => $userData['user_agent'] ?? null,
            'request_data' => $requestData,
            'response_data' => $responseData,
            'status' => $userData['status'] ?? 'success',
            'error_message' => $userData['error_message'] ?? null,
        ];

        Log::debug('🔍 DEBUG: AuditService::logRoleLevelAction - Data array', [
            'data' => $data,
            'field_changes' => $fieldChanges,
            'field_changes_empty' => empty($fieldChanges)
        ]);

        if (!empty($fieldChanges)) {
            Log::debug('🔍 DEBUG: AuditService::logRoleLevelAction - Calling logActionWithDetails', [
                'field_changes' => $fieldChanges
            ]);
            $result = $this->logActionWithDetails($data, $fieldChanges);
            Log::debug('🔍 DEBUG: AuditService::logRoleLevelAction - logActionWithDetails result', [
                'result' => $result,
                'result_id' => $result ? $result->id : null
            ]);
            return $result;
        }

        Log::debug('🔍 DEBUG: AuditService::logRoleLevelAction - Calling logAction (no field changes)', []);
        $result = $this->logAction($data);
        Log::debug('🔍 DEBUG: AuditService::logRoleLevelAction - logAction result', [
            'result' => $result,
            'result_id' => $result ? $result->id : null
        ]);
        return $result;
    }

    /**
     * Log a job role action
     *
     * @param string $action
     * @param string $jobRoleId
     * @param string $jobRoleName
     * @param array $userData
     * @param array $requestData
     * @param array $responseData
     * @param array $fieldChanges
     * @return \App\Model\Entity\AuditLog|null
     */
    public function logJobRoleAction(
        string $action,
        string $jobRoleId,
        string $jobRoleName,
        array $userData,
        array $requestData = [],
        array $responseData = [],
        array $fieldChanges = []
    ): ?\App\Model\Entity\AuditLog {
        Log::debug('🔍 DEBUG: AuditService::logJobRoleAction - Input parameters', [
            'action' => $action,
            'job_role_id' => $jobRoleId,
            'job_role_name' => $jobRoleName,
            'field_changes' => $fieldChanges,
            'field_changes_count' => count($fieldChanges),
            'field_changes_empty' => empty($fieldChanges)
        ]);

        $data = [
            'user_id' => $userData['user_id'] ?? 0,
            'username' => $userData['username'] ?? 'system',
            'action' => $action,
            'entity_type' => 'job_role',
            'entity_id' => $jobRoleId,
            'entity_name' => $jobRoleName,
            'description' => $this->getJobRoleDescription($action, $jobRoleName),
            'ip_address' => $userData['ip_address'] ?? null,
            'user_agent' => $userData['user_agent'] ?? null,
            'request_data' => $requestData,
            'response_data' => $responseData,
            'status' => $userData['status'] ?? 'success',
            'error_message' => $userData['error_message'] ?? null,
        ];

        Log::debug('🔍 DEBUG: AuditService::logJobRoleAction - Data array', [
            'data' => $data,
            'field_changes' => $fieldChanges,
            'field_changes_empty' => empty($fieldChanges)
        ]);

        if (!empty($fieldChanges)) {
            Log::debug('🔍 DEBUG: AuditService::logJobRoleAction - Calling logActionWithDetails', [
                'field_changes' => $fieldChanges
            ]);
            $result = $this->logActionWithDetails($data, $fieldChanges);
            Log::debug('🔍 DEBUG: AuditService::logJobRoleAction - logActionWithDetails result', [
                'result' => $result,
                'result_id' => $result ? $result->id : null
            ]);
            return $result;
        }

        Log::debug('🔍 DEBUG: AuditService::logJobRoleAction - Calling logAction (no field changes)', []);
        $result = $this->logAction($data);
        Log::debug('🔍 DEBUG: AuditService::logJobRoleAction - logAction result', [
            'result' => $result,
            'result_id' => $result ? $result->id : null
        ]);
        return $result;
    }

    /**
     * Get role level action description
     *
     * @param string $action
     * @param string $roleLevelName
     * @return string
     */
    private function getRoleLevelDescription(string $action, string $roleLevelName): string
    {
        return match (strtoupper($action)) {
            'CREATE' => "Created role level: {$roleLevelName}",
            'UPDATE' => "Updated role level: {$roleLevelName}",
            'DELETE' => "Deleted role level: {$roleLevelName}",
            default => "Performed {$action} on role level: {$roleLevelName}",
        };
    }

    /**
     * Get job role action description
     *
     * @param string $action
     * @param string $jobRoleName
     * @return string
     */
    private function getJobRoleDescription(string $action, string $jobRoleName): string
    {
        return match (strtoupper($action)) {
            'CREATE' => "Created job role: {$jobRoleName}",
            'UPDATE' => "Updated job role: {$jobRoleName}",
            'DELETE' => "Deleted job role: {$jobRoleName}",
            default => "Performed {$action} on job role: {$jobRoleName}",
        };
    }
}
