<?php

declare(strict_types=1);

namespace App\Helper;

use App\Service\AuditService;
use Cake\Http\ServerRequest;
use Cake\Log\Log;

/**
 * Audit Helper
 * 
 * Helper functions for easy audit logging integration
 */
class AuditHelper
{
    /**
     * Normalizes a value for consistent comparison.
     * Treats null, empty strings, and empty arrays as a single 'empty' representation.
     *
     * @param mixed $value The value to normalize.
     * @return string The normalized string representation.
     */
    private static function normalizeValue($value): string
    {
        if ($value === null || (is_string($value) && trim($value) === '') || (is_array($value) && empty($value))) {
            return ''; // Treat null, empty strings, and empty arrays as equivalent empty string
        }
        if (is_array($value)) {
            // For non-empty arrays, serialize them to a string for comparison
            return json_encode($value);
        }
        return (string)$value; // Convert other scalar types to string
    }
    /**
     * Log a scorecard action
     *
     * @param string $action 
     * @param string $scorecardId
     * @param string $scorecardName
     * @param array $userData
     * @param \Cake\Http\ServerRequest $request
     * @param array $fieldChanges
     * @return void
     */
    public static function logScorecardAction(
        string $action,
        string $scorecardId,
        string $scorecardName,
        array $userData,
        ServerRequest $request,
        array $fieldChanges = []
    ): void {
        try {
            $companyId = $userData['company_id'] ?? 'default';
            $auditService = new AuditService($companyId);

            $clientInfo = AuditService::extractClientInfo($request);
            $userData = array_merge($userData, $clientInfo);

            $auditService->logScorecardAction(
                $action,
                $scorecardId,
                $scorecardName,
                $userData,
                $request->getParsedBody() ?? [],
                [],
                $fieldChanges
            );
        } catch (\Exception $e) {
            Log::error('Error logging scorecard action: ' . $e->getMessage(), [
                'action' => $action,
                'scorecard_id' => $scorecardId,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Log an employee action
     *
     * @param string $action
     * @param string $employeeId
     * @param string $employeeName
     * @param array $userData
     * @param \Cake\Http\ServerRequest $request
     * @param array $fieldChanges
     * @return void
     */
    public static function logEmployeeAction(
        string $action,
        string $employeeId,
        string $employeeName,
        array $userData,
        ServerRequest $request,
        array $fieldChanges = []
    ): void {
        try {
            Log::debug('🔍 DEBUG: logEmployeeAction - Input parameters', [
                'action' => $action,
                'employee_id' => $employeeId,
                'employee_name' => $employeeName,
                'user_data' => $userData,
                'field_changes_count' => count($fieldChanges),
                'field_changes' => $fieldChanges
            ]);
            
            $companyId = $userData['company_id'] ?? 'default';
            $auditService = new AuditService($companyId);

            $clientInfo = AuditService::extractClientInfo($request);
            $userData = array_merge($userData, $clientInfo);

            $auditService->logEmployeeAction(
                $action,
                $employeeId,
                $employeeName,
                $userData,
                $request->getParsedBody() ?? [],
                [],
                $fieldChanges
            );
            
            Log::debug('🔍 DEBUG: logEmployeeAction - Successfully logged employee action');
        } catch (\Exception $e) {
            Log::error('Error logging employee action: ' . $e->getMessage(), [
                'action' => $action,
                'employee_id' => $employeeId,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Log an authentication action
     *
     * @param string $action
     * @param array $userData
     * @param \Cake\Http\ServerRequest $request
     * @param string $status
     * @param string $errorMessage
     * @return void
     */
    public static function logAuthAction(
        string $action,
        array $userData,
        ServerRequest $request,
        string $status = 'success',
        string $errorMessage = null
    ): void {
        try {
            $companyId = $userData['company_id'] ?? 'default';
            $auditService = new AuditService($companyId);

            $clientInfo = AuditService::extractClientInfo($request);
            $userData = array_merge($userData, $clientInfo);

            $auditService->logAuthAction(
                $action,
                $userData,
                $request->getParsedBody() ?? [],
                $status,
                $errorMessage
            );
        } catch (\Exception $e) {
            Log::error('Error logging auth action: ' . $e->getMessage(), [
                'action' => $action,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Log a generic action
     *
     * @param string $action
     * @param string $entityType
     * @param string $entityId
     * @param string $entityName
     * @param array $userData
     * @param \Cake\Http\ServerRequest $request
     * @param array $fieldChanges
     * @return void
     */
    public static function logAction(
        string $action,
        string $entityType,
        string $entityId,
        string $entityName,
        array $userData,
        ServerRequest $request,
        array $fieldChanges = []
    ): void {
        try {
            $companyId = $userData['company_id'] ?? 'default';
            $auditService = new AuditService($companyId);

            $clientInfo = AuditService::extractClientInfo($request);
            $userData = array_merge($userData, $clientInfo);

            $data = [
                'user_id' => $userData['user_id'] ?? 0,
                'username' => $userData['username'] ?? 'system',
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'entity_name' => $entityName,
                'description' => ucfirst(strtolower($action)) . " {$entityType}: {$entityName}",
                'ip_address' => $userData['ip_address'] ?? null,
                'user_agent' => $userData['user_agent'] ?? null,
                'request_data' => $request->getParsedBody() ?? [],
                'status' => $userData['status'] ?? 'success',
                'error_message' => $userData['error_message'] ?? null,
            ];

            if (!empty($fieldChanges)) {
                $auditService->logActionWithDetails($data, $fieldChanges);
            } else {
                $auditService->logAction($data);
            }
        } catch (\Exception $e) {
            Log::error('Error logging action: ' . $e->getMessage(), [
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Compare two arrays and generate field changes
     *
     * @param array $oldData
     * @param array $newData
     * @param array $fieldMapping
     * @return array
     */
    public static function generateFieldChanges(array $oldData, array $newData, array $fieldMapping = []): array
    {
        Log::debug('🔍 DEBUG: generateFieldChanges - Input data', [
            'old_data' => $oldData,
            'new_data' => $newData,
            'field_mapping' => $fieldMapping
        ]);

        $changes = [];

        foreach ($newData as $key => $newValue) {
            $oldValue = $oldData[$key] ?? null;
            
            // Special handling for password field (field 38)
            if ($key == 38) {
                // If new password is empty/null, and old password exists, it means no change to password
                if ((self::normalizeValue($newValue) === '') && (self::normalizeValue($oldValue) !== '')) {
                    Log::debug('🔍 DEBUG: generateFieldChanges - Password field (38) treated as no change (new value empty, old value exists)');
                    // Console output for debugging
                    error_log("🔍 AUDIT HELPER - Field 38 (Password) treated as NO CHANGE (new value empty, old value exists)");
                    continue; // Skip this field, no change detected
                }
            }
            
            $normalizedOldValue = self::normalizeValue($oldValue);
            $normalizedNewValue = self::normalizeValue($newValue);
            
            Log::debug('🔍 DEBUG: generateFieldChanges - Comparing field (normalized)', [
                'key' => $key,
                'old_value_raw' => $oldValue,
                'new_value_raw' => $newValue,
                'normalized_old_value' => $normalizedOldValue,
                'normalized_new_value' => $normalizedNewValue,
                'old_type' => gettype($oldValue),
                'new_type' => gettype($newValue),
                'are_equal_strict' => $oldValue === $newValue,
                'are_equal_normalized' => $normalizedOldValue === $normalizedNewValue
            ]);
            
            // Console output for debugging (using error_log to avoid breaking JSON response)
            if (in_array($key, [38, 39, 43])) {
                error_log("🔍 AUDIT HELPER - Field {$key} comparison (normalized):");
                error_log("  Old Raw: " . json_encode($oldValue) . " (Type: " . gettype($oldValue) . ")");
                error_log("  New Raw: " . json_encode($newValue) . " (Type: " . gettype($newValue) . ")");
                error_log("  Normalized Old: " . json_encode($normalizedOldValue) . " (Type: " . gettype($normalizedOldValue) . ")");
                error_log("  Normalized New: " . json_encode($normalizedNewValue) . " (Type: " . gettype($normalizedNewValue) . ")");
                error_log("  Equal (Normalized ===): " . ($normalizedOldValue === $normalizedNewValue ? 'YES' : 'NO'));
            }
            
            if ($normalizedOldValue !== $normalizedNewValue) {
                $fieldLabel = $fieldMapping[$key] ?? ucfirst(str_replace('_', ' ', (string)$key));
                
                $change = [
                    'field_name' => $key,
                    'field_label' => $fieldLabel,
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                    'change_type' => $oldValue === null ? 'added' : 'changed',
                ];
                
                $changes[] = $change;
                
                Log::debug('🔍 DEBUG: generateFieldChanges - Change detected', [
                    'change' => $change
                ]);
                
                // Console output for debugging (using error_log to avoid breaking JSON response)
                if (in_array($key, [38, 39, 43])) {
                    error_log("🔍 AUDIT HELPER - CHANGE DETECTED for Field {$key}:");
                    error_log("  Change Type: " . $change['change_type']);
                    error_log("  Field Label: " . $change['field_label']);
                    error_log("  Old Value: " . json_encode($change['old_value']));
                    error_log("  New Value: " . json_encode($change['new_value']));
                }
            }
        }

        // Check for removed fields
        foreach ($oldData as $key => $oldValue) {
            if (!array_key_exists($key, $newData)) {
                // If the old value was already effectively empty, and the field is now missing, treat as no change
                if (self::normalizeValue($oldValue) === '') {
                    Log::debug('🔍 DEBUG: generateFieldChanges - Removed field (old value empty) treated as no change', ['key' => $key]);
                    // Console output for debugging
                    if (in_array($key, [38, 39, 43])) {
                        error_log("🔍 AUDIT HELPER - Field {$key} (old value empty) treated as NO CHANGE (removed)");
                    }
                    continue; // Skip this field, no change detected
                }
                
                $fieldLabel = $fieldMapping[$key] ?? ucfirst(str_replace('_', ' ', (string)$key));
                
                $change = [
                    'field_name' => $key,
                    'field_label' => $fieldLabel,
                    'old_value' => $oldValue,
                    'new_value' => null,
                    'change_type' => 'removed',
                ];
                
                $changes[] = $change;
                
                Log::debug('🔍 DEBUG: generateFieldChanges - Removed field detected', [
                    'key' => $key,
                    'old_value' => $oldValue,
                    'old_type' => gettype($oldValue),
                    'exists_in_new_data' => array_key_exists($key, $newData),
                    'change' => $change
                ]);
                
                // Console output for debugging (using error_log to avoid breaking JSON response)
                if (in_array($key, [38, 39, 43])) {
                    error_log("🔍 AUDIT HELPER - REMOVED FIELD DETECTED for Field {$key}:");
                    error_log("  Old Value: " . json_encode($oldValue) . " (" . gettype($oldValue) . ")");
                    error_log("  Exists in New Data: " . (array_key_exists($key, $newData) ? 'YES' : 'NO'));
                    error_log("  Change Type: " . $change['change_type']);
                }
            }
        }

        Log::debug('🔍 DEBUG: generateFieldChanges - Final changes', [
            'changes' => $changes,
            'changes_count' => count($changes)
        ]);

        return $changes;
    }

    /**
     * Extract user data from authentication result
     *
     * @param mixed $authResult
     * @return array
     */
    public static function extractUserData($authResult): array
    {
        Log::debug('🔍 DEBUG: extractUserData - Input authResult', [
            'auth_result' => $authResult,
            'is_valid' => $authResult ? $authResult->isValid() : false
        ]);

        if (!$authResult || !$authResult->isValid()) {
            Log::debug('🔍 DEBUG: extractUserData - Invalid auth result, returning system data');
            return [
                'user_id' => 0,
                'username' => 'system',
                'employee_name' => 'System',
                'company_id' => 'default',
            ];
        }

        $data = $authResult->getData();
        
        Log::debug('🔍 DEBUG: extractUserData - Auth data', [
            'data' => $data,
            'data_type' => gettype($data),
            'id' => $data->id ?? 'not_set',
            'username' => $data->username ?? 'not_set',
            'employee_name' => $data->employee_name ?? 'not_set',
            'company_id' => $data->company_id ?? 'not_set'
        ]);
        
        $userData = [
            'user_id' => $data->id ?? 0,
            'username' => $data->username ?? 'system',
            'employee_name' => $data->employee_name ?? $data->username ?? 'Unknown',
            'company_id' => (string)($data->company_id ?? 'default'),
        ];

        Log::debug('🔍 DEBUG: extractUserData - Final user data', [
            'user_data' => $userData
        ]);
        
        return $userData;
    }

    /**
     * Extract scorecard code from parsed answers
     *
     * @param array $answers
     * @return string
     */
    public static function extractScorecardCode(array $answers): string
    {
        Log::debug('🔍 DEBUG: extractScorecardCode - Input answers', [
            'answers' => $answers,
            'answers_type' => gettype($answers),
            'answers_count' => count($answers)
        ]);

        if (empty($answers)) {
            Log::debug('🔍 DEBUG: extractScorecardCode - Empty answers, returning Unnamed Scorecard');
            return 'Unnamed Scorecard';
        }

        // Handle nested structure (group ID -> field ID -> value)
        $flatAnswers = [];
        $answerKeys = array_keys($answers);
        
        Log::debug('🔍 DEBUG: extractScorecardCode - Answer keys', [
            'answer_keys' => $answerKeys,
            'first_key' => $answerKeys[0] ?? 'none',
            'first_key_type' => isset($answerKeys[0]) ? gettype($answers[$answerKeys[0]]) : 'none'
        ]);
        
        if (!empty($answerKeys)) {
            $firstKey = $answerKeys[0];
            if (is_array($answers[$firstKey])) {
                Log::debug('🔍 DEBUG: extractScorecardCode - Nested structure detected, flattening');
                // It's nested, flatten it
                foreach ($answers as $groupId => $groupAnswers) {
                    if (is_array($groupAnswers)) {
                        $flatAnswers = array_merge($flatAnswers, $groupAnswers);
                        Log::debug('🔍 DEBUG: extractScorecardCode - Flattened group', [
                            'group_id' => $groupId,
                            'group_answers' => $groupAnswers,
                            'flat_answers_so_far' => $flatAnswers
                        ]);
                    }
                }
            } else {
                Log::debug('🔍 DEBUG: extractScorecardCode - Flat structure detected');
                // It's already flat
                $flatAnswers = $answers;
            }
        }

        Log::debug('🔍 DEBUG: extractScorecardCode - Final flat answers', [
            'flat_answers' => $flatAnswers,
            'flat_answer_keys' => array_keys($flatAnswers)
        ]);

        // Get the first field value (scorecard code)
        $flatAnswerKeys = array_keys($flatAnswers);
        if (!empty($flatAnswerKeys)) {
            $scorecardCode = $flatAnswers[$flatAnswerKeys[0]] ?? 'Unnamed Scorecard';
            Log::debug('🔍 DEBUG: extractScorecardCode - Extracted scorecard code', [
                'first_key' => $flatAnswerKeys[0],
                'scorecard_code' => $scorecardCode
            ]);
            return $scorecardCode;
        }

        Log::debug('🔍 DEBUG: extractScorecardCode - No flat answer keys, returning Unnamed Scorecard');
        return 'Unnamed Scorecard';
    }

    /**
     * Extract employee name from nested answers structure
     *
     * @param array $answers
     * @return string
     */
    public static function extractEmployeeName(array $answers): string
    {
        Log::debug('🔍 DEBUG: extractEmployeeName - Input answers', [
            'answers' => $answers,
            'answers_type' => gettype($answers),
            'answers_count' => count($answers)
        ]);

        if (empty($answers)) {
            Log::debug('🔍 DEBUG: extractEmployeeName - Empty answers, returning Unnamed Employee');
            return 'Unnamed Employee';
        }

        // Handle nested structure (group ID -> field ID -> value)
        $flatAnswers = [];
        $answerKeys = array_keys($answers);
        
        Log::debug('🔍 DEBUG: extractEmployeeName - Answer keys', [
            'answer_keys' => $answerKeys,
            'first_key' => $answerKeys[0] ?? 'none',
            'first_key_type' => isset($answerKeys[0]) ? gettype($answers[$answerKeys[0]]) : 'none'
        ]);
        
        if (!empty($answerKeys)) {
            $firstKey = $answerKeys[0];
            if (is_array($answers[$firstKey])) {
                Log::debug('🔍 DEBUG: extractEmployeeName - Nested structure detected, flattening');
                // It's nested, flatten it
                foreach ($answers as $groupId => $groupAnswers) {
                    if (is_array($groupAnswers)) {
                        $flatAnswers = array_merge($flatAnswers, $groupAnswers);
                        Log::debug('🔍 DEBUG: extractEmployeeName - Flattened group', [
                            'group_id' => $groupId,
                            'group_answers' => $groupAnswers,
                            'flat_answers_so_far' => $flatAnswers
                        ]);
                    }
                }
            } else {
                Log::debug('🔍 DEBUG: extractEmployeeName - Flat structure detected');
                // It's already flat
                $flatAnswers = $answers;
            }
        }

        Log::debug('🔍 DEBUG: extractEmployeeName - Final flat answers', [
            'flat_answers' => $flatAnswers,
            'flat_answer_keys' => array_keys($flatAnswers)
        ]);

        // Look for first name and last name in various possible field names
        $firstNameFields = ['first_name', 'firstName', 'First Name', 'Given Name'];
        $lastNameFields = ['last_name', 'lastName', 'Last Name', 'Surname'];
        
        $firstName = '';
        $lastName = '';
        
        foreach ($firstNameFields as $field) {
            if (isset($flatAnswers[$field]) && !empty($flatAnswers[$field])) {
                $firstName = $flatAnswers[$field];
                Log::debug('🔍 DEBUG: extractEmployeeName - Found first name', [
                    'field' => $field,
                    'first_name' => $firstName
                ]);
                break;
            }
        }
        
        foreach ($lastNameFields as $field) {
            if (isset($flatAnswers[$field]) && !empty($flatAnswers[$field])) {
                $lastName = $flatAnswers[$field];
                Log::debug('🔍 DEBUG: extractEmployeeName - Found last name', [
                    'field' => $field,
                    'last_name' => $lastName
                ]);
                break;
            }
        }

        $fullName = trim($firstName . ' ' . $lastName);
        
        Log::debug('🔍 DEBUG: extractEmployeeName - Final name', [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => $fullName
        ]);

        return !empty($fullName) ? $fullName : 'Unnamed Employee';
    }

    /**
     * Log a role level action
     *
     * @param string $action
     * @param string $roleLevelId
     * @param string $roleLevelName
     * @param array $userData
     * @param \Cake\Http\ServerRequest $request
     * @param array $fieldChanges
     * @return void
     */
    public static function logRoleLevelAction(
        string $action,
        string $roleLevelId,
        string $roleLevelName,
        array $userData,
        ServerRequest $request,
        array $fieldChanges = []
    ): void {
        try {
            Log::debug('🔍 DEBUG: logRoleLevelAction - Input parameters', [
                'action' => $action,
                'role_level_id' => $roleLevelId,
                'role_level_name' => $roleLevelName,
                'user_data' => $userData,
                'field_changes' => $fieldChanges,
                'field_changes_count' => count($fieldChanges)
            ]);

            $companyId = $userData['company_id'] ?? 'default';
            $auditService = new AuditService($companyId);

            $clientInfo = AuditService::extractClientInfo($request);
            $userData = array_merge($userData, $clientInfo);

            Log::debug('🔍 DEBUG: logRoleLevelAction - Calling auditService->logRoleLevelAction', [
                'action' => $action,
                'role_level_id' => $roleLevelId,
                'role_level_name' => $roleLevelName,
                'field_changes' => $fieldChanges,
                'field_changes_count' => count($fieldChanges)
            ]);

            $result = $auditService->logRoleLevelAction(
                $action,
                $roleLevelId,
                $roleLevelName,
                $userData,
                $request->getParsedBody() ?? [],
                [],
                $fieldChanges
            );

            Log::debug('🔍 DEBUG: logRoleLevelAction - AuditService result', [
                'result' => $result,
                'result_id' => $result ? $result->id : null
            ]);
        } catch (\Exception $e) {
            Log::error('Error logging role level action: ' . $e->getMessage(), [
                'action' => $action,
                'role_level_id' => $roleLevelId,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Log a job role action
     *
     * @param string $action
     * @param string $jobRoleId
     * @param string $jobRoleName
     * @param array $userData
     * @param \Cake\Http\ServerRequest $request
     * @param array $fieldChanges
     * @return void
     */
    public static function logJobRoleAction(
        string $action,
        string $jobRoleId,
        string $jobRoleName,
        array $userData,
        ServerRequest $request,
        array $fieldChanges = []
    ): void {
        try {
            Log::debug('🔍 DEBUG: logJobRoleAction - Input parameters', [
                'action' => $action,
                'job_role_id' => $jobRoleId,
                'job_role_name' => $jobRoleName,
                'user_data' => $userData,
                'field_changes' => $fieldChanges,
                'field_changes_count' => count($fieldChanges)
            ]);

            $companyId = $userData['company_id'] ?? 'default';
            $auditService = new AuditService($companyId);

            $clientInfo = AuditService::extractClientInfo($request);
            $userData = array_merge($userData, $clientInfo);

            Log::debug('🔍 DEBUG: logJobRoleAction - Calling auditService->logJobRoleAction', [
                'action' => $action,
                'job_role_id' => $jobRoleId,
                'job_role_name' => $jobRoleName,
                'field_changes' => $fieldChanges,
                'field_changes_count' => count($fieldChanges)
            ]);

            $result = $auditService->logJobRoleAction(
                $action,
                $jobRoleId,
                $jobRoleName,
                $userData,
                $request->getParsedBody() ?? [],
                [],
                $fieldChanges
            );

            Log::debug('🔍 DEBUG: logJobRoleAction - AuditService result', [
                'result' => $result,
                'result_id' => $result ? $result->id : null
            ]);
        } catch (\Exception $e) {
            Log::error('Error logging job role action: ' . $e->getMessage(), [
                'action' => $action,
                'job_role_id' => $jobRoleId,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Extract role level name from parsed answers
     *
     * @param array $answers
     * @return string
     */
    public static function extractRoleLevelName(array $answers): string
    {
        Log::debug('🔍 DEBUG: extractRoleLevelName - Input answers', [
            'answers' => $answers,
            'answers_type' => gettype($answers),
            'answers_count' => count($answers)
        ]);

        if (empty($answers)) {
            Log::debug('🔍 DEBUG: extractRoleLevelName - Empty answers, returning Unnamed Role Level');
            return 'Unnamed Role Level';
        }

        // Handle nested structure (group ID -> field ID -> value)
        $flatAnswers = [];
        $answerKeys = array_keys($answers);
        
        if (!empty($answerKeys)) {
            $firstKey = $answerKeys[0];
            if (is_array($answers[$firstKey])) {
                Log::debug('🔍 DEBUG: extractRoleLevelName - Nested structure detected, flattening');
                // It's nested, flatten it
                foreach ($answers as $groupId => $groupAnswers) {
                    if (is_array($groupAnswers)) {
                        $flatAnswers = array_merge($flatAnswers, $groupAnswers);
                    }
                }
            } else {
                Log::debug('🔍 DEBUG: extractRoleLevelName - Flat structure detected');
                // It's already flat
                $flatAnswers = $answers;
            }
        }

        Log::debug('🔍 DEBUG: extractRoleLevelName - Final flat answers', [
            'flat_answers' => $flatAnswers,
            'flat_answer_keys' => array_keys($flatAnswers)
        ]);

        // Get the first field value (role level name)
        $flatAnswerKeys = array_keys($flatAnswers);
        if (!empty($flatAnswerKeys)) {
            $roleLevelName = $flatAnswers[$flatAnswerKeys[0]] ?? 'Unnamed Role Level';
            Log::debug('🔍 DEBUG: extractRoleLevelName - Extracted role level name', [
                'first_key' => $flatAnswerKeys[0],
                'role_level_name' => $roleLevelName
            ]);
            return $roleLevelName;
        }

        Log::debug('🔍 DEBUG: extractRoleLevelName - No flat answer keys, returning Unnamed Role Level');
        return 'Unnamed Role Level';
    }

    /**
     * Extract job role name from parsed answers
     *
     * @param array $answers
     * @return string
     */
    public static function extractJobRoleName(array $answers): string
    {
        Log::debug('🔍 DEBUG: extractJobRoleName - Input answers', [
            'answers' => $answers,
            'answers_type' => gettype($answers),
            'answers_count' => count($answers)
        ]);

        if (empty($answers)) {
            Log::debug('🔍 DEBUG: extractJobRoleName - Empty answers, returning Unnamed Job Role');
            return 'Unnamed Job Role';
        }

        // Handle nested structure (group ID -> field ID -> value)
        $flatAnswers = [];
        $answerKeys = array_keys($answers);
        
        if (!empty($answerKeys)) {
            $firstKey = $answerKeys[0];
            if (is_array($answers[$firstKey])) {
                Log::debug('🔍 DEBUG: extractJobRoleName - Nested structure detected, flattening');
                // It's nested, flatten it
                foreach ($answers as $groupId => $groupAnswers) {
                    if (is_array($groupAnswers)) {
                        $flatAnswers = array_merge($flatAnswers, $groupAnswers);
                    }
                }
            } else {
                Log::debug('🔍 DEBUG: extractJobRoleName - Flat structure detected');
                // It's already flat
                $flatAnswers = $answers;
            }
        }

        Log::debug('🔍 DEBUG: extractJobRoleName - Final flat answers', [
            'flat_answers' => $flatAnswers,
            'flat_answer_keys' => array_keys($flatAnswers)
        ]);

        // Get the first field value (job role name)
        $flatAnswerKeys = array_keys($flatAnswers);
        if (!empty($flatAnswerKeys)) {
            $jobRoleName = $flatAnswers[$flatAnswerKeys[0]] ?? 'Unnamed Job Role';
            Log::debug('🔍 DEBUG: extractJobRoleName - Extracted job role name', [
                'first_key' => $flatAnswerKeys[0],
                'job_role_name' => $jobRoleName
            ]);
            return $jobRoleName;
        }

        Log::debug('🔍 DEBUG: extractJobRoleName - No flat answer keys, returning Unnamed Job Role');
        return 'Unnamed Job Role';
    }
}
