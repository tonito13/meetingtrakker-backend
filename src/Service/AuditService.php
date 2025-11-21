<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Table\AuditLogsTable;
use App\Model\Table\AuditLogDetailsTable;
use Cake\ORM\TableRegistry;
use Cake\Log\Log;
use Cake\Utility\Text;
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
        if (!isset($GLOBALS['audit_debug'])) {
            $GLOBALS['audit_debug'] = [];
        }
        $GLOBALS['audit_debug']['service_construct_called'] = true;
        $GLOBALS['audit_debug']['service_company_id'] = $companyId;
        
        $this->companyId = $companyId;
        
        Log::debug('🔍 DEBUG: AuditService::__construct - START', [
            'company_id' => $companyId
        ]);
        
        // Get company-specific database connection
        try {
            $connection = $this->getConnection($companyId);
            $connectionName = $connection->configName();
            $connectionConfig = $connection->config();
            
            $GLOBALS['audit_debug']['connection_name'] = $connectionName;
            $GLOBALS['audit_debug']['database'] = $connectionConfig['database'] ?? 'unknown';
            $GLOBALS['audit_debug']['connection_success'] = true;
            
            Log::debug('🔍 DEBUG: AuditService::__construct - Connection established', [
                'connection_name' => $connectionName,
                'database' => $connectionConfig['database'] ?? 'unknown',
                'company_id' => $companyId
            ]);
        } catch (\Exception $e) {
            $GLOBALS['audit_debug']['connection_error'] = $e->getMessage();
            throw $e;
        }
        
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
        
        $GLOBALS['audit_debug']['tables_initialized'] = true;
        $GLOBALS['audit_debug']['audit_logs_table'] = $this->auditLogsTable->getTable();
        $GLOBALS['audit_debug']['audit_logs_connection'] = $this->auditLogsTable->getConnection()->configName();
        
        Log::debug('🔍 DEBUG: AuditService::__construct - Tables initialized', [
            'audit_logs_table' => $this->auditLogsTable->getTable(),
            'audit_log_details_table' => $this->auditLogDetailsTable->getTable(),
            'audit_logs_connection' => $this->auditLogsTable->getConnection()->configName(),
            'audit_log_details_connection' => $this->auditLogDetailsTable->getConnection()->configName(),
            'company_id' => $companyId
        ]);
        
        // Configure the association to use the correct table instance
        $association = $this->auditLogsTable->getAssociation('AuditLogDetails');
        if ($association) {
            $association->setTarget($this->auditLogDetailsTable);
        }
        
        Log::debug('🔍 DEBUG: AuditService::__construct - COMPLETE', [
            'company_id' => $companyId
        ]);
    }

    /**
     * Get database connection for company
     *
     * @param string $companyId
     * @return \Cake\Database\Connection
     */
    private function getConnection(string $companyId)
    {
        try {
            if ($companyId === 'default') {
                $connection = \Cake\Datasource\ConnectionManager::get('default');
                Log::debug('🔍 DEBUG: AuditService::getConnection - Using default connection', [
                    'company_id' => $companyId,
                    'connection_name' => 'default',
                    'database' => $connection->config()['database'] ?? 'unknown'
                ]);
                return $connection;
            }
            
            // In test environment, use the test company database connection for company-specific tables
            if (\Cake\Core\Configure::read('debug') && php_sapi_name() === 'cli') {
                // Try the alias first (test_client_{companyId}), then the direct connection name
                $testConnectionName = 'test_client_' . $companyId;
                $directConnectionName = 'client_' . $companyId . '_test';
                
                try {
                    // First try the alias (created in tests/bootstrap.php)
                    $connection = \Cake\Datasource\ConnectionManager::get($testConnectionName);
                } catch (\Exception $e1) {
                    try {
                        // Fallback to direct connection name
                        $connection = \Cake\Datasource\ConnectionManager::get($directConnectionName);
                    } catch (\Exception $e2) {
                        // If neither connection exists, throw a clear error
                        throw new \Exception(
                            "Test company database connection not found. Tried '{$testConnectionName}' and '{$directConnectionName}'. " .
                            "Company-specific tables should never use the central 'test' database. " .
                            "Error 1: " . $e1->getMessage() . " Error 2: " . $e2->getMessage()
                        );
                    }
                }
            } else {
                $connectionName = 'client_' . $companyId;
                $connection = \Cake\Datasource\ConnectionManager::get($connectionName);
            }
            
            Log::debug('🔍 DEBUG: AuditService::getConnection - Using company connection', [
                'company_id' => $companyId,
                'connection_name' => $connection->configName(),
                'database' => $connection->config()['database'] ?? 'unknown'
            ]);
            
            return $connection;
        } catch (\Exception $e) {
            Log::error('🔍 ERROR: AuditService::getConnection - Failed to get connection', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Log a simple audit action
     *
     * @param array $data
     * @return \App\Model\Entity\AuditLog|null
     */
    public function logAction(array $data): ?\App\Model\Entity\AuditLog
    {
        if (!isset($GLOBALS['audit_debug'])) {
            $GLOBALS['audit_debug'] = [];
        }
        $GLOBALS['audit_debug']['log_action_called'] = true;
        $GLOBALS['audit_debug']['log_action'] = $data['action'] ?? 'UNKNOWN';
        $GLOBALS['audit_debug']['log_entity_type'] = $data['entity_type'] ?? 'unknown';
        
        Log::debug('🔍 DEBUG: AuditService::logAction - START', [
            'company_id' => $this->companyId,
            'action' => $data['action'] ?? 'UNKNOWN',
            'entity_type' => $data['entity_type'] ?? 'unknown'
        ]);

        try {
            // Verify connection
            $connection = $this->auditLogsTable->getConnection();
            $connectionName = $connection->configName();
            $connectionConfig = $connection->config();
            
            Log::debug('🔍 DEBUG: AuditService::logAction - Connection info', [
                'connection_name' => $connectionName,
                'database' => $connectionConfig['database'] ?? 'unknown',
                'company_id' => $this->companyId
            ]);
            
            // Verify table exists
            try {
                $schema = $connection->getSchemaCollection();
                $tables = $schema->listTables();
                $tableExists = in_array('audit_logs', $tables);
                
                $GLOBALS['audit_debug']['table_check'] = true;
                $GLOBALS['audit_debug']['table_exists'] = $tableExists;
                $GLOBALS['audit_debug']['available_tables_count'] = count($tables);
                $GLOBALS['audit_debug']['sample_tables'] = array_slice($tables, 0, 10);
                
                Log::debug('🔍 DEBUG: AuditService::logAction - Table check', [
                    'table_exists' => $tableExists,
                    'table_name' => 'audit_logs',
                    'available_tables_count' => count($tables),
                    'sample_tables' => array_slice($tables, 0, 10)
                ]);
                
                if (!$tableExists) {
                    $GLOBALS['audit_debug']['table_not_found'] = true;
                    $GLOBALS['audit_debug']['available_tables'] = $tables;
                    
                    Log::error('🔍 ERROR: AuditService::logAction - audit_logs table does not exist', [
                        'connection' => $connectionName,
                        'database' => $connectionConfig['database'] ?? 'unknown',
                        'company_id' => $this->companyId,
                        'available_tables' => $tables
                    ]);
                    return null;
                }
            } catch (\Exception $e) {
                $GLOBALS['audit_debug']['table_check_error'] = $e->getMessage();
                
                Log::error('🔍 ERROR: AuditService::logAction - Failed to check table existence', [
                    'error' => $e->getMessage(),
                    'connection' => $connectionName,
                    'company_id' => $this->companyId,
                    'trace' => $e->getTraceAsString()
                ]);
                return null;
            }
            
            // Generate UUID for audit log if not provided
            $id = $data['id'] ?? Text::uuid();
            
            // Prepare user_data - keep as array for JSONB, CakePHP will handle conversion
            $userData = $data['user_data'] ?? [];
            if (is_string($userData)) {
                $userData = json_decode($userData, true) ?: [];
            }
            
            $auditLogData = [
                'id' => $id,
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
                'user_data' => $userData, // Keep as array for JSONB
            ];

            Log::debug('🔍 DEBUG: AuditService::logAction - Creating entity', [
                'audit_log_data_keys' => array_keys($auditLogData),
                'id' => $id,
                'company_id' => $this->companyId
            ]);

            $auditLog = $this->auditLogsTable->newEntity($auditLogData);
            
            // Check for validation errors
            if ($auditLog->hasErrors()) {
                $GLOBALS['audit_debug']['validation_errors'] = $auditLog->getErrors();
                
                Log::error('🔍 ERROR: AuditService::logAction - Validation errors', [
                    'errors' => $auditLog->getErrors(),
                    'audit_log_data' => $auditLogData
                ]);
                return null;
            }

            Log::debug('🔍 DEBUG: AuditService::logAction - Attempting save', [
                'entity_id' => $auditLog->id ?? 'not_set',
                'company_id' => $this->companyId
            ]);

            if ($this->auditLogsTable->save($auditLog)) {
                $GLOBALS['audit_debug']['save_success'] = true;
                $GLOBALS['audit_debug']['audit_log_id'] = $auditLog->id;
                
                Log::debug('🔍 DEBUG: AuditService::logAction - SUCCESS - Audit log saved', [
                    'audit_log_id' => $auditLog->id,
                    'entity_name' => $auditLog->entity_name,
                    'username' => $auditLog->username,
                    'connection' => $connectionName,
                    'database' => $connectionConfig['database'] ?? 'unknown'
                ]);
                return $auditLog;
            }

            $GLOBALS['audit_debug']['save_failed'] = true;
            $GLOBALS['audit_debug']['save_errors'] = $auditLog->getErrors();
            
            Log::error('🔍 ERROR: AuditService::logAction - FAILED to save audit log', [
                'data' => $data,
                'errors' => $auditLog->getErrors(),
                'connection' => $connectionName,
                'database' => $connectionConfig['database'] ?? 'unknown',
                'company_id' => $this->companyId,
                'entity_data' => $auditLog->toArray()
            ]);

            return null;
        } catch (\Throwable $e) {
            $GLOBALS['audit_debug']['exception'] = true;
            $GLOBALS['audit_debug']['exception_message'] = $e->getMessage();
            $GLOBALS['audit_debug']['exception_file'] = $e->getFile();
            $GLOBALS['audit_debug']['exception_line'] = $e->getLine();
            
            Log::error('🔍 ERROR: AuditService::logAction - EXCEPTION', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'data' => $data,
                'company_id' => $this->companyId,
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
                        'id' => Text::uuid(),
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
        // Determine the display name (full_name > employee_name > username > 'system')
        $displayName = $userData['full_name'] ?? $userData['employee_name'] ?? $userData['username'] ?? 'system';
        
        // If display name is empty or 'Unknown', try to fetch it again
        if (empty($displayName) || $displayName === 'Unknown') {
            $displayName = $userData['username'] ?? 'system';
        }
        
        $data = [
            'user_id' => $userData['user_id'] ?? 0,
            'username' => $displayName,
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
            'user_data' => $userData, // Include full user data with full_name
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
        // Determine the display name (full_name > employee_name > username > 'system')
        $displayName = $userData['full_name'] ?? $userData['employee_name'] ?? $userData['username'] ?? 'system';
        
        // If display name is empty or 'Unknown', try to fetch it again
        if (empty($displayName) || $displayName === 'Unknown') {
            $displayName = $userData['username'] ?? 'system';
        }
        
        $data = [
            'user_id' => $userData['user_id'] ?? 0,
            'username' => $displayName,
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
     * Log a bulk import action
     *
     * @param string $entityType 'employee' or 'role_level'
     * @param int $importedCount Number of items imported
     * @param array $userData User data for audit
     * @param array $requestData Request data
     * @param array $responseData Response data
     * @return \App\Model\Entity\AuditLog|null
     */
    public function logBulkImportAction(
        string $entityType,
        int $importedCount,
        array $userData,
        array $requestData = [],
        array $responseData = []
    ): ?\App\Model\Entity\AuditLog {
        // Determine the display name (full_name > employee_name > username > 'system')
        $displayName = $userData['full_name'] ?? $userData['employee_name'] ?? $userData['username'] ?? 'system';
        
        // If display name is empty or 'Unknown', try to fetch it again
        if (empty($displayName) || $displayName === 'Unknown') {
            $displayName = $userData['username'] ?? 'system';
        }
        
        // Format entity type for display (readable names)
        $entityDisplayName = match($entityType) {
            'employee' => 'Employee',
            'role_level' => 'Role Level',
            'job_role' => 'Job Role',
            'job_role_relationship' => 'Job Role Relationship',
            'employee_relationship' => 'Employee Relationship',
            default => ucfirst(str_replace('_', ' ', $entityType))
        };
        
        // Create entity name with quantity (for subheader)
        $entityName = $importedCount === 1 
            ? "1 {$entityDisplayName}" 
            : "{$importedCount} {$entityDisplayName}s";
        
        // Create description (for subheader) - quantity + entity type + "Imported"
        $description = $importedCount === 1
            ? "1 {$entityDisplayName} Imported"
            : "{$importedCount} {$entityDisplayName}s Imported";
        
        $data = [
            'user_id' => $userData['user_id'] ?? 0,
            'username' => $displayName,
            'action' => 'IMPORT',
            'entity_type' => $entityType,
            'entity_id' => null, // Bulk import doesn't have a single entity ID
            'entity_name' => $entityName,
            'description' => $description,
            'ip_address' => $userData['ip_address'] ?? null,
            'user_agent' => $userData['user_agent'] ?? null,
            'request_data' => array_merge($requestData, ['imported_count' => $importedCount]),
            'response_data' => $responseData,
            'status' => $userData['status'] ?? 'success',
            'error_message' => $userData['error_message'] ?? null,
            'user_data' => $userData,
        ];

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
        
        // Include audit log details
        $query->contain(['AuditLogDetails']);

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

        // Determine the display name (full_name > employee_name > username > 'system')
        $displayName = $userData['full_name'] ?? $userData['employee_name'] ?? $userData['username'] ?? 'system';
        
        // If display name is empty or 'Unknown', try to fetch it again
        if (empty($displayName) || $displayName === 'Unknown') {
            $displayName = $userData['username'] ?? 'system';
        }
        
        $data = [
            'user_id' => $userData['user_id'] ?? 0,
            'username' => $displayName,
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
            'user_data' => $userData, // Include full user data with full_name
        ];

        Log::debug('🔍 DEBUG: AuditService::logRoleLevelAction - Data array', [
            'data' => $data,
            'user_data_keys' => array_keys($userData),
            'user_data_full_name' => $userData['full_name'] ?? 'NOT_SET',
            'user_data_employee_name' => $userData['employee_name'] ?? 'NOT_SET',
            'user_data_username' => $userData['username'] ?? 'NOT_SET',
            'display_name' => $displayName,
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

        // Determine the display name (full_name > employee_name > username > 'system')
        $displayName = $userData['full_name'] ?? $userData['employee_name'] ?? $userData['username'] ?? 'system';
        
        // If display name is empty or 'Unknown', try to fetch it again
        if (empty($displayName) || $displayName === 'Unknown') {
            $displayName = $userData['username'] ?? 'system';
        }
        
        $data = [
            'user_id' => $userData['user_id'] ?? 0,
            'username' => $displayName,
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
            'user_data' => $userData, // Include full user data with full_name
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
