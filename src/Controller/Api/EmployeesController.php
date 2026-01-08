<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\Api\ApiController;
use App\Helper\AuditHelper;
use App\Service\CompanyMappingService;
use App\Service\S3FileService;
use Cake\Core\Configure;
use Cake\Utility\Text;
use Exception;
use Cake\Log\Log;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;

class EmployeesController extends ApiController
{
    private ?CompanyMappingService $companyMappingService = null;
    private ?S3FileService $s3Service = null;

    public function initialize(): void
    {
        parent::initialize();
    }

    /**
     * Get S3FileService instance
     *
     * @return S3FileService
     */
    private function getS3Service(): S3FileService
    {
        if ($this->s3Service === null) {
            $this->s3Service = new S3FileService();
        }
        return $this->s3Service;
    }

    /**
     * Get CompanyMappingService instance
     *
     * @return CompanyMappingService
     */
    private function getCompanyMappingService(): CompanyMappingService
    {
        if ($this->companyMappingService === null) {
            $this->companyMappingService = new CompanyMappingService();
        }
        return $this->companyMappingService;
    }

    public function getEmployees()
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
                    'message' => 'Unauthorized access or invalid company ID',
                    'data' => []
                ]));
        }

        $company_id = $this->getCompanyId($authResult);

        try {
            $answersTable = $this->getTable('EmployeeTemplateAnswers', $company_id);

            // Optimize query with explicit join conditions and pagination
            $employees = $answersTable->find()
                ->select([
                    'employee_unique_id' => 'EmployeeTemplateAnswers.employee_unique_id',
                    'username' => 'EmployeeTemplateAnswers.username',
                    'template_id' => 'EmployeeTemplateAnswers.template_id',
                    'answer_id' => 'EmployeeTemplateAnswers.id',
                    'answers' => 'EmployeeTemplateAnswers.answers',
                    'structure' => 'employee_templates.structure',
                ])
                ->join([
                    'employee_templates' => [
                        'table' => 'employee_templates',
                        'type' => 'INNER',
                        'conditions' => [
                            'employee_templates.id = EmployeeTemplateAnswers.template_id',
                            'employee_templates.company_id = EmployeeTemplateAnswers.company_id',
                            'employee_templates.deleted' => 0
                        ]
                    ]
                ])
                ->where([
                    'EmployeeTemplateAnswers.company_id' => $company_id,
                    'EmployeeTemplateAnswers.deleted' => 0
                ])
                ->limit(100)
                ->all()
                ->map(function ($employee) {
                    // Decode structure JSON
                    $structure = is_string($employee->structure)
                        ? json_decode($employee->structure, true) ?? []
                        : (is_array($employee->structure) ? $employee->structure : []);

                    // Initialize result
                    $result = [
                        'employee_unique_id' => $employee->employee_unique_id,
                        'username' => $employee->username ?? '',
                        'first_name' => '',
                        'last_name' => '',
                        'job_role' => ''
                    ];

                    // Map field IDs to labels
                    $fieldMap = [];
                    if (is_string($structure)) {
                        $structure = json_decode($structure, true);
                    }
                    if (is_array($structure)) {
                        foreach ($structure as $section) {
                            if (isset($section['fields']) && is_array($section['fields'])) {
                                foreach ($section['fields'] as $field) {
                                    if (isset($field['id']) && isset($field['customize_field_label'])) {
                                        $fieldMap[$field['id']] = $field['customize_field_label'];
                                    }
                                }
                            }
                        }
                    }

                    // Extract answers
                    $answers = is_array($employee->answers) ? $employee->answers : [];
                    foreach ($answers as $sectionId => $sectionAnswers) {
                        foreach ($sectionAnswers as $fieldId => $value) {
                            $label = $fieldMap[$fieldId] ?? '';
                            if ($label === 'First Name') {
                                $result['first_name'] = $value;
                            } elseif ($label === 'Last Name') {
                                $result['last_name'] = $value;
                            } elseif ($label === 'Job Role') { // Note: customize_field_label for job role is "Job Title"
                                $result['job_role'] = $value;
                            }
                        }
                    }

                    return $result;
                })
                ->toArray();

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'data' => $employees,
                    'message' => 'Employees retrieved successfully'
                ]));
        } catch (\Exception $e) {
            // Log the error for debugging
            \Cake\Log\Log::error('Error fetching employees: ' . $e->getMessage());

            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'An error occurred while fetching employees',
                    'data' => []
                ]));
        }
    }

    public function addEmployee()
    {
        Configure::write('debug', true);
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

        // Require admin access for adding employees
        // Debug: Log authentication data before admin check
        $authData = $authResult->getData();
        Log::debug('🔍 DEBUG: addEmployee - Authentication data before admin check', [
            'auth_data_type' => gettype($authData),
            'auth_data_class' => is_object($authData) ? get_class($authData) : null,
            'auth_data_keys' => is_object($authData) ? array_keys((array)$authData) : (is_array($authData) ? array_keys($authData) : []),
            'auth_data' => is_object($authData) ? (array)$authData : $authData,
            'system_user_role_direct' => is_object($authData) ? ($authData->system_user_role ?? 'NOT_SET') : (is_array($authData) ? ($authData['system_user_role'] ?? 'NOT_SET') : 'NOT_OBJECT_OR_ARRAY'),
        ]);
        
        $adminCheck = $this->requireAdmin();
        if ($adminCheck !== null) {
            // Log why admin check failed
            Log::warning('🔍 DEBUG: addEmployee - Admin check failed', [
                'is_admin_result' => $this->isAdmin(),
                'auth_data_sample' => is_object($authData) ? (array)$authData : $authData
            ]);
            return $adminCheck;
        }
        
        Log::debug('🔍 DEBUG: addEmployee - Admin check passed, proceeding with employee creation');

        $companyId = $this->getCompanyId($authResult);
        $logged_username = $this->getUsername($authResult);
        $data = $this->request->getData();
        
        // Debug: Log the received data
        Log::debug("🔍 DEBUG: AddEmployee - Received data:", [
            'company_id' => $companyId,
            'logged_username' => $logged_username,
            'data' => $data
        ]);
        
        $employeeUniqueId = $data['employeeUniqueId'] ?? null;
        if (empty($employeeUniqueId)) {
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Employee Unique ID is required.',
                ]));
        }

        try {
            $EmployeeTemplateAnswersTable = $this->getTable('EmployeeTemplateAnswers', $companyId);

            // Validate required fields
            if (empty($data['template_id'])) {
                throw new Exception('Template ID is required.');
            }
            if (empty($data['answers'])) {
                throw new Exception('Answers are required.');
            }

            // Parse and validate answers
            $answers = $this->parseAnswers($data['answers']);
            $template = $this->validateTemplate($companyId, $data['template_id']);
            $jobRoles = $this->getValidJobRoles($companyId);

            $answerData = [];
            $reportToEmployeeUniqueId = null;
            $employeeId = null;
            $username = null;

            // Process answers and extract employee_id and username
            Log::debug("🔍 DEBUG: AddEmployee - About to process answers", [
                'answers' => $answers,
                'template_id' => $template->id,
                'template_structure' => $template->structure
            ]);
            
            list($answerData, $reportToEmployeeUniqueId, $employeeId, $username, $userData) = $this->processAnswers(
                $answers,
                $template,
                $jobRoles,
                $companyId,
                $employeeUniqueId
            );
            
            // Debug: Log the processed data
            Log::debug("🔍 DEBUG: AddEmployee - Processed data:", [
                'employeeId' => $employeeId,
                'username' => $username,
                'userData' => $userData,
                'reportToEmployeeUniqueId' => $reportToEmployeeUniqueId
            ]);

            // Check if employee_id or username already exists
            $this->checkExistingEmployeeIdAndUsername($companyId, $employeeId, $username);

            // Start transaction
            $connection = $EmployeeTemplateAnswersTable->getConnection();
            $connection->begin();

            // Save answers with employee_id, username, and reporting relationship
            $answerEntity = $this->saveEmployeeAnswers($companyId, $employeeUniqueId, $data['template_id'], $answerData, $employeeId, $username, $reportToEmployeeUniqueId);

            // Save to users table
            $userEntity = $this->saveUser($companyId, $userData, $username);

            // Commit transaction
            $connection->commit();

            // Extract employee name for audit logging
            // Handle both JSON string and array formats
            $employeeAnswers = $answerData;
            if (is_string($employeeAnswers)) {
                $employeeAnswers = json_decode($employeeAnswers, true) ?? [];
            }
            $employeeName = AuditHelper::extractEmployeeName($employeeAnswers);
            
            // Fallback to username if name extraction fails
            if (empty($employeeName) || $employeeName === 'Unnamed Employee') {
                $employeeName = $username ?? $employeeUniqueId ?? 'Unknown Employee';
            }
            
            // Extract user data for audit logging
            $auditUserData = AuditHelper::extractUserData($authResult);
            
            // Override company_id and username with the correct values from controller
            $authData = $authResult->getData();
            $username = null;
            if ($authData instanceof \ArrayObject || is_array($authData)) {
                $username = $authData['username'] ?? $authData['sub'] ?? null;
            } elseif (is_object($authData)) {
                $username = $authData->username ?? $authData->sub ?? null;
            }
            
            $auditUserData['company_id'] = (string)$companyId;
            $auditUserData['username'] = $username ?? $auditUserData['username'] ?? 'system';
            $auditUserData['user_id'] = $authData->id ?? $authData['id'] ?? $authData->sub ?? $authData['sub'] ?? $auditUserData['user_id'] ?? 0;
            
            // If we now have a user_id but full_name wasn't fetched, fetch it now
            if (!empty($auditUserData['user_id']) && (empty($auditUserData['full_name']) || $auditUserData['full_name'] === 'Unknown')) {
                try {
                    $usersTable = TableRegistry::getTableLocator()->get('Users', [
                        'connection' => ConnectionManager::get('default')
                    ]);
                    
                    $user = $usersTable->find()
                        ->select(['first_name', 'last_name'])
                        ->where(['id' => $auditUserData['user_id']])
                        ->first();
                    
                    if ($user) {
                        $firstName = $user->first_name ?? '';
                        $lastName = $user->last_name ?? '';
                        $fullName = trim($firstName . ' ' . $lastName);
                        if (!empty($fullName)) {
                            $auditUserData['full_name'] = $fullName;
                            $auditUserData['employee_name'] = $fullName;
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Error fetching user full name in controller: ' . $e->getMessage());
                }
            }
            
            // Ensure full_name is preserved (don't overwrite it if it was already fetched)
            if (empty($auditUserData['full_name']) && !empty($auditUserData['employee_name'])) {
                $auditUserData['full_name'] = $auditUserData['employee_name'];
            }
            
            // Initialize debug info
            $GLOBALS['audit_debug'] = [];
            $GLOBALS['audit_debug']['helper_called'] = true;
            $GLOBALS['audit_debug']['action'] = 'CREATE';
            $GLOBALS['audit_debug']['employee_unique_id'] = $employeeUniqueId;
            $GLOBALS['audit_debug']['employee_name'] = $employeeName;
            $GLOBALS['audit_debug']['company_id'] = (string)$companyId;
            $GLOBALS['audit_debug']['timestamp'] = date('Y-m-d H:i:s');
            $GLOBALS['audit_debug']['user_data'] = $auditUserData;
            
            // Log audit action with error handling
            try {
            AuditHelper::logEmployeeAction(
                'CREATE',
                $employeeUniqueId,
                $employeeName,
                $auditUserData,
                $this->request
            );
            } catch (\Exception $e) {
                Log::error('Error logging employee CREATE audit: ' . $e->getMessage(), [
                    'employee_unique_id' => $employeeUniqueId,
                    'trace' => $e->getTraceAsString()
                ]);
                // Don't fail the request if audit logging fails
            }

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'message' => 'Employee data saved successfully. Please upload files.',
                    'employee_id' => $employeeUniqueId,
                    'answer_id' => $answerEntity->id,
                    'user_id' => $userEntity->id,
                    'debug' => $GLOBALS['audit_debug'] ?? null,
                ]));
        } catch (Exception $e) {
            if (isset($connection) && $connection->inTransaction()) {
                $connection->rollback();
            }
            Log::error('AddEmployee Error: ' . $e->getMessage(), ['company_id' => $companyId]);
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]));
        }
    }

    public function uploadFiles()
    {
        Configure::write('debug', true);
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

        $companyId = $this->getCompanyId($authResult);
        $data = $this->request->getData();
        $answerId = $data['answerId'] ?? null;
        $employeeUniqueId = $data['employeeUniqueId'] ?? null;
        if (empty($answerId) || empty($employeeUniqueId)) {
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Answer ID and Employee Unique ID are required for file upload.',
                ]));
        }

        try {
            $EmployeeTemplateAnswersTable = $this->getTable('EmployeeTemplateAnswers', $companyId);
            $EmployeeAnswerFilesTable = $this->getTable('EmployeeAnswerFiles', $companyId);

            // Ensure schemas are loaded before any operations
            // This prevents "Cannot describe table. It has 0 columns" errors
            try {
                $EmployeeTemplateAnswersTable->getSchema();
                $EmployeeAnswerFilesTable->getSchema();
            } catch (\Exception $schemaError) {
                Log::error('Schema loading error in uploadFiles: ' . $schemaError->getMessage());
                // Re-get tables to force schema reload
                $EmployeeTemplateAnswersTable = $this->getTable('EmployeeTemplateAnswers', $companyId);
                $EmployeeAnswerFilesTable = $this->getTable('EmployeeAnswerFiles', $companyId);
            }

            // Start transaction
            $connection = $EmployeeTemplateAnswersTable->getConnection();
            $connection->begin();

            // Validate answer_id exists - use direct SQL query to avoid schema issues
            $answerResult = $connection->execute(
                'SELECT id, template_id, answers, employee_unique_id FROM employee_template_answers WHERE id = :id AND company_id = :company_id AND employee_unique_id = :employee_unique_id AND deleted = false',
                [
                    'id' => $answerId,
                    'company_id' => $companyId,
                    'employee_unique_id' => $employeeUniqueId
                ]
            )->fetch('assoc');
            
            if (!$answerResult) {
                throw new Exception('Invalid answer ID or employee unique ID.');
            }
            
            // Create a simple object to hold answer data
            $answer = (object)[
                'id' => $answerResult['id'],
                'template_id' => $answerResult['template_id'],
                'employee_unique_id' => $answerResult['employee_unique_id'],
                'answers' => is_string($answerResult['answers']) ? json_decode($answerResult['answers'], true) : $answerResult['answers']
            ];

            // Get template to validate required file fields
            $template = $this->validateTemplate($companyId, $answer->template_id);
            $templateStructure = is_string($template->structure) ? json_decode($template->structure, true) : $template->structure;
            $requiredFileFields = $this->getRequiredFileFields($templateStructure);

            $files = $this->request->getUploadedFiles();
            $fileMap = [];
            $uploadedFields = [];

            $targetFiles = isset($files['files']) && is_array($files['files']) ? $files['files'] : $files;

            // Get employee ID if available (Employees table may not exist in all Scorecardtrakker setups)
            $employeeId = null;
            try {
                $employeesTable = $this->getTable('Employees', $companyId);
                $employee = $employeesTable->find()
                    ->where([
                        'Employees.employee_unique_id' => $employeeUniqueId,
                        'Employees.company_id' => $companyId,
                        'Employees.deleted' => false
                    ])
                    ->first();
                
                if ($employee) {
                    $employeeId = $employee->id;
                }
            } catch (\Exception $e) {
                // Employees table may not exist, continue without employee_id
                // employee_unique_id is sufficient for S3 folder structure
            }

            $s3Service = $this->getS3Service();

            foreach ($targetFiles as $key => $file) {
                if (preg_match('/^(\d+)_([0-9_]+)$/', $key, $matches)) {
                    $groupId = $matches[1];
                    $fieldId = $matches[2];
                    $this->validateFile($file, "File for {$groupId}_{$fieldId}");

                    $fileName = $file->getClientFilename();
                    
                    // Check if there's an existing file for this field and soft delete it
                    // Use direct SQL to avoid schema description issues
                    // Note: deleted is a boolean in PostgreSQL, so use false instead of 0
                    $existingFileResult = $connection->execute(
                        'SELECT id, s3_bucket, s3_key FROM employee_answer_files WHERE answer_id = :answer_id AND employee_unique_id = :employee_unique_id AND field_id = :field_id AND company_id = :company_id AND deleted = false',
                        [
                            'answer_id' => $answerId,
                            'employee_unique_id' => $employeeUniqueId,
                            'field_id' => $fieldId,
                            'company_id' => $companyId
                        ]
                    )->fetch('assoc');
                    
                    if ($existingFileResult) {
                        // Soft delete the existing file using direct SQL
                        $connection->execute(
                            'UPDATE employee_answer_files SET deleted = true WHERE id = :id',
                            ['id' => $existingFileResult['id']]
                        );
                        
                        // Also delete the file from S3 if it exists
                        if (!empty($existingFileResult['s3_bucket']) && !empty($existingFileResult['s3_key'])) {
                            $s3Service->deleteFile($existingFileResult['s3_bucket'], $existingFileResult['s3_key']);
                        }
                    }

                    // Generate unique filename using the same convention as Skiltrakker
                    $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                    $baseFileName = pathinfo($fileName, PATHINFO_FILENAME);
                    $microtime = microtime(true);
                    $randomSuffix = mt_rand(1000, 9999);
                    $uniqueFileName = $baseFileName . '_' . number_format($microtime, 4, '', '') . '_' . $randomSuffix . '.' . $fileExtension;

                    // Read file content from uploaded file
                    $fileStream = $file->getStream();
                    $fileContent = $fileStream->getContents();
                    
                    if ($fileContent === false) {
                        throw new Exception("Failed to read file content for {$fieldId}.");
                    }

                    // Upload to S3 with proper folder structure: meetingtrakker/employees/{companyId}/{employeeUniqueId}/{fieldId}/{filename}
                    $s3Result = $s3Service->uploadFile(
                        $fileContent,
                        $uniqueFileName,
                        $companyId,
                        'employees',
                        null, // companyName - not needed
                        null, // employeeName - not needed
                        null, // No intervention unique ID for employee files
                        null, // No competency name for employee files
                        null, // No level name for employee files
                        $employeeUniqueId, // employeeUniqueId - required for folder structure
                        $fieldId // fieldId - required for folder structure
                    );

                    if (!$s3Result['success']) {
                        throw new Exception("Failed to upload file to S3 for {$fieldId}: " . ($s3Result['error'] ?? 'Unknown error'));
                    }

                    // Use direct SQL insert to avoid schema description issues
                    // Note: deleted is a boolean in PostgreSQL, so use false instead of 0
                    $insertResult = $connection->execute(
                        'INSERT INTO employee_answer_files (answer_id, employee_id, employee_unique_id, file_name, file_path, file_type, file_size, group_id, field_id, company_id, s3_bucket, s3_key, deleted, created, modified) 
                         VALUES (:answer_id, :employee_id, :employee_unique_id, :file_name, :file_path, :file_type, :file_size, :group_id, :field_id, :company_id, :s3_bucket, :s3_key, false, NOW(), NOW())',
                        [
                            'answer_id' => $answerId,
                            'employee_id' => $employeeId,
                            'employee_unique_id' => $employeeUniqueId,
                            'file_name' => $fileName,
                            'file_path' => $uniqueFileName,
                            'file_type' => $file->getClientMediaType(),
                            'file_size' => $file->getSize(),
                            'group_id' => $groupId,
                            'field_id' => $fieldId,
                            'company_id' => $companyId,
                            's3_bucket' => $s3Result['bucket'],
                            's3_key' => $s3Result['key']
                        ]
                    );
                    
                    if ($insertResult->rowCount() === 0) {
                        // Clean up S3 file if database save failed
                        $s3Service->deleteFile($s3Result['bucket'], $s3Result['key']);
                        throw new Exception("Failed to save file metadata for {$fieldId}.");
                    }

                    if (!isset($fileMap[$groupId])) {
                        $fileMap[$groupId] = [];
                    }
                    // Store unique filename in fileMap (not full path)
                    $fileMap[$groupId][$fieldId] = $uniqueFileName;
                    $uploadedFields[] = "{$groupId}_{$fieldId}";
                }
            }

            // Validate required file fields
            foreach ($requiredFileFields as $requiredField) {
                if (!in_array("{$requiredField['group_id']}_{$requiredField['field_id']}", $uploadedFields)) {
                    throw new Exception("Required file field {$requiredField['label']} is missing.");
                }
            }

            // Update answers with file identifiers (unique filenames)
            // Ensure answers is an array (handle JSONB/JSON type)
            $answerData = is_array($answer->answers) ? $answer->answers : (is_string($answer->answers) ? json_decode($answer->answers, true) : []);
            
            if (!is_array($answerData)) {
                $answerData = [];
            }
            
            foreach ($fileMap as $groupId => $fields) {
                foreach ($fields as $fieldId => $uniqueFileName) {
                    if (!isset($answerData[$groupId])) {
                        $answerData[$groupId] = [];
                    }
                    // Store unique filename as identifier (frontend will use API endpoints or presigned URLs)
                    $answerData[$groupId][$fieldId] = $uniqueFileName;
                }
            }
            
            // Use direct SQL update to avoid schema description issues
            // This bypasses CakePHP's entity save which requires schema description
            // Note: deleted is a boolean in PostgreSQL, so use false instead of 0
            $connection = $EmployeeTemplateAnswersTable->getConnection();
            $answersJson = json_encode($answerData, JSON_UNESCAPED_UNICODE);
            
            $updateResult = $connection->execute(
                'UPDATE employee_template_answers SET answers = :answers::jsonb, modified = NOW() WHERE id = :id AND company_id = :company_id AND employee_unique_id = :employee_unique_id AND deleted = false',
                [
                    'answers' => $answersJson,
                    'id' => $answerId,
                    'company_id' => $companyId,
                    'employee_unique_id' => $employeeUniqueId
                ]
            );
            
            if ($updateResult->rowCount() === 0) {
                throw new Exception('Failed to update employee answers with file paths. No rows updated.');
            }

            // Commit transaction
            $connection->commit();

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'message' => 'Files uploaded successfully.',
                    'files' => $fileMap,
                    'employeeUniqueId' => $employeeUniqueId,
                    'answerId' => $answerId,
                ]));
        } catch (Exception $e) {
            if (isset($connection) && $connection->inTransaction()) {
                $connection->rollback();
            }
            Log::error('UploadFiles Error: ' . $e->getMessage(), ['company_id' => $companyId]);
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errorDetails' => [
                        'field' => isset($fieldId) ? $fieldId : null,
                        'group' => isset($groupId) ? $groupId : null,
                    ],
                ]));
        }
    }

    private function parseAnswers($answers)
    {
        $parsed = is_string($answers) ? json_decode($answers, true) : $answers;
        if (json_last_error() !== JSON_ERROR_NONE && is_string($answers)) {
            throw new Exception('Invalid answers JSON format.');
        }
        if (!is_array($parsed)) {
            throw new Exception('Answers must be an array.');
        }
        return $parsed;
    }

    private function validateTemplate($companyId, $templateId)
    {
        $EmployeeTemplatesTable = $this->getTable('EmployeeTemplates', $companyId);
        $template = $EmployeeTemplatesTable
            ->find()
            ->where([
                'company_id' => $companyId,
                'id' => $templateId,
                'deleted' => false,
            ])
            ->first();

        if (!$template) {
            throw new Exception('Invalid or deleted template.');
        }
        return $template;
    }

    private function getValidJobRoles($companyId)
    {
        $JobRoleTemplatesTable = $this->getTable('JobRoleTemplates', $companyId);
        return $JobRoleTemplatesTable
            ->find()
            ->select(['job_role_template_answers.job_role_unique_id'])
            ->join([
                'job_role_template_answers' => [
                    'table' => 'job_role_template_answers',
                    'type' => 'INNER',
                    'conditions' => [
                        'job_role_template_answers.company_id = JobRoleTemplates.company_id',
                        'job_role_template_answers.deleted' => 0,
                    ],
                ],
            ])
            ->where([
                'JobRoleTemplates.company_id' => $companyId,
                'JobRoleTemplates.deleted' => 0,
            ])
            ->all()
            ->extract('job_role_template_answers.job_role_unique_id')
            ->toArray();
    }

    private function validateFile($file, $displayLabel)
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new Exception("Failed to upload {$displayLabel}.");
        }
        $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($file->getClientMediaType(), $allowedTypes)) {
            throw new Exception("{$displayLabel} must be a JPG, PNG, or PDF.");
        }
        if ($file->getSize() > 5 * 1024 * 1024) {
            throw new Exception("{$displayLabel} must be less than 5MB.");
        }
    }

    private function getRequiredFileFields($structure)
    {
        $requiredFields = [];
        foreach ($structure as $group) {
            foreach ($group['fields'] as $field) {
                if ($field['type'] === 'file' && $field['is_required']) {
                    $requiredFields[] = [
                        'group_id' => $group['id'],
                        'field_id' => $field['id'],
                        'label' => $field['customize_field_label'] ?? $field['label'],
                    ];
                }
            }
            if (!empty($group['subGroups'])) {
                foreach ($group['subGroups'] as $subGroupIndex => $subGroup) {
                    foreach ($subGroup['fields'] as $field) {
                        if ($field['type'] === 'file' && $field['is_required']) {
                            $requiredFields[] = [
                                'group_id' => $group['id'],
                                'field_id' => $field['id'] . '_' . $subGroupIndex,
                                'label' => $field['customize_field_label'] ?? $field['label'],
                            ];
                        }
                    }
                }
            }
        }
        return $requiredFields;
    }

    private function processAnswers($answers, $template, $jobRoles, $companyId, $employeeUniqueId, $isUpdate = false)
    {
        $answerData = [];
        $reportToEmployeeUniqueId = null;
        $employeeId = null;
        $username = null;
        $userData = [
            'first_name' => null,
            'middle_name' => null,
            'last_name' => null,
            'birth_date' => null,
            'birth_place' => null,
            'sex' => null,
            'civil_status' => null,
            'nationality' => null,
            'blood_type' => null,
            'email_address' => null,
            'contact_number' => null,
            'username' => null,
            'password' => null,
            'system_user_role' => null,
            'system_access_enabled' => false,
        ];

        // Map of field labels to userData keys
        $fieldMapping = [
            'Employee ID' => 'employee_id',
            'Username' => 'username',
            'First Name' => 'first_name',
            'Middle Name' => 'middle_name',
            'Last Name' => 'last_name',
            'Date of Birth' => 'birth_date',
            'Birth Place' => 'birth_place',
            'Sex' => 'sex',
            'Civil Status' => 'civil_status',
            'Nationality' => 'nationality',
            'Blood Type' => 'blood_type',
            'Email Address' => 'email_address',
            'Password' => 'password',
            'Role' => 'system_user_role',
            'User Role' => 'system_user_role',
            'System Role' => 'system_user_role',
            'System Access Enabled' => 'system_access_enabled',
            'Contact Number' => 'contact_number',
        ];

        // Decode template structure if it's a JSON string
        $templateStructure = $template->structure;
        if (is_string($templateStructure)) {
            $templateStructure = json_decode($templateStructure, true);
        }
        
        foreach ($answers as $groupId => $groupAnswers) {
            $answerData[$groupId] = [];
            foreach ($groupAnswers as $fieldId => $value) {
                $field = $this->findField($templateStructure, $groupId, $fieldId);
                $displayLabel = $this->getDisplayLabel($field, $fieldId);
                
                Log::debug("Processing field: groupId={$groupId}, fieldId={$fieldId}, displayLabel='{$displayLabel}', value=" . json_encode($value));
                Log::debug("Field found: " . json_encode($field));

                Log::debug("Processing field: groupId=$groupId, fieldId=$fieldId, value=" . ($field && isset($field['type']) && $field['type'] === 'file' ? '[File]' : json_encode($value)) . ", displayLabel=$displayLabel");

                // Skip file validation (handled in uploadFiles)
                if ($field && isset($field['type']) && $field['type'] === 'file') {
                    $answerData[$groupId][$fieldId] = null; // Placeholder
                    continue;
                }

                if ($field && isset($field['is_required']) && $field['is_required'] && (is_null($value) || $value === '')) {
                    throw new Exception("{$displayLabel} is required.");
                }

                // Map field to userData or specific variables based on displayLabel
                if (array_key_exists($displayLabel, $fieldMapping)) {
                    Log::debug("Mapping field: displayLabel='{$displayLabel}' to '{$fieldMapping[$displayLabel]}' with value: " . json_encode($value));
                    if ($fieldMapping[$displayLabel] === 'employee_id') {
                        $employeeId = $value;
                    } elseif ($fieldMapping[$displayLabel] === 'username') {
                        $username = $value;
                    } else {
                        if ($fieldMapping[$displayLabel] === 'system_access_enabled') {
                            $userData[$fieldMapping[$displayLabel]] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                        } elseif ($fieldMapping[$displayLabel] === 'password') {
                            $userData[$fieldMapping[$displayLabel]] = $value; // Will be hashed in saveUser
                        } else {
                            $userData[$fieldMapping[$displayLabel]] = $value;
                        }
                    }
                } else {
                    Log::debug("Field not mapped: displayLabel='{$displayLabel}' with value: " . json_encode($value));
                }

                if ($fieldId === 'reports_to' || $displayLabel === 'Reports To') {
                    if ($value !== '') {
                        // Validate if the reporting employee unique ID exists
                        if (!$this->validateEmployeeUniqueIdExists($companyId, $value)) {
                            throw new Exception("Reporting employee with unique ID '{$value}' not found.");
                        }
                        $reportToEmployeeUniqueId = $value;
                    }
                    $answerData[$groupId][$fieldId] = $value;
                } elseif ($field && isset($field['type']) && $field['type'] === 'job_role') {
                    if ($value !== '' && !in_array($value, $jobRoles)) {
                        throw new Exception("Invalid job role selected: $value");
                    }
                    $answerData[$groupId][$fieldId] = $value;
                } else {
                    $answerData[$groupId][$fieldId] = $this->validateFieldValue($field, $value, $displayLabel, $answers, $template->structure);
                }
            }
        }

        // Validate required fields for users table
        if (empty($employeeId)) {
            throw new Exception('Employee ID is required.');
        }
        if (empty($username)) {
            throw new Exception('Username is required.');
        }
        if (empty($userData['first_name'])) {
            throw new Exception('First Name is required.');
        }
        if (empty($userData['last_name'])) {
            throw new Exception('Last Name is required.');
        }

        // if (empty($userData['email_address'])) {
        //     throw new Exception('Email Address is required.');
        // }
        if (!$isUpdate && empty($userData['password'])) {
            throw new Exception('Password is required.');
        }

        // Set default values for optional fields if not provided
        if (empty($userData['system_user_role'])) {
            $userData['system_user_role'] = 'employee'; // Default role
        }
        if (empty($userData['system_access_enabled'])) {
            $userData['system_access_enabled'] = false; // Default to disabled
        }
        if (empty($userData['sex'])) {
            $userData['sex'] = 'Not Specified'; // Default sex
        }
        if (empty($userData['civil_status'])) {
            $userData['civil_status'] = 'Not Specified'; // Default civil status
        }
        if (empty($userData['nationality'])) {
            $userData['nationality'] = 'Not Specified'; // Default nationality
        }
        // Blood type has max length of 3 characters, so use null or empty string instead of 'Not Specified'
        if (empty($userData['blood_type'])) {
            $userData['blood_type'] = null; // Allow null/empty for blood type (max 3 chars in DB)
        }
        if (empty($userData['birth_place'])) {
            $userData['birth_place'] = 'Not Specified'; // Default birth place
        }
        if (empty($userData['contact_number'])) {
            $userData['contact_number'] = 'Not Specified'; // Default contact number
        }
        if (empty($userData['birth_date'])) {
            $userData['birth_date'] = '1900-01-01'; // Default birth date
        }
        if (empty($userData['middle_name'])) {
            $userData['middle_name'] = ''; // Default empty middle name
        }

        Log::debug("Final userData before return: " . json_encode($userData));
        Log::debug("Final employeeId: " . json_encode($employeeId));
        Log::debug("Final username: " . json_encode($username));

        return [$answerData, $reportToEmployeeUniqueId, $employeeId, $username, $userData];
    }

    private function findField($structure, $groupId, $fieldId)
    {
        if (!is_array($structure)) {
            return null;
        }
        
        foreach ($structure as $group) {
            if ($group['id'] == $groupId) {
                if (isset($group['fields']) && is_array($group['fields'])) {
                    foreach ($group['fields'] as $f) {
                        if ($f['id'] == $fieldId || $fieldId === 'reports_to') {
                            return $f;
                        }
                    }
                }
                if (!empty($group['subGroups'])) {
                    foreach ($group['subGroups'] as $subGroupIndex => $subGroup) {
                        foreach ($subGroup['fields'] as $f) {
                            $subFieldId = $f['id'] . '_' . $subGroupIndex;
                            if ($f['id'] == $fieldId || $subFieldId == $fieldId) {
                                return $f;
                            }
                        }
                    }
                }
            }
        }
        return null;
    }

    private function getDisplayLabel($field, $fieldId)
    {
        return $field
            ? (!empty($field['customize_field_label']) ? $field['customize_field_label'] : $field['label'])
            : ($fieldId === 'reports_to' ? 'Reports To' : 'Unknown');
    }

    private function validateFieldValue($field, $value, $displayLabel, $answers, $structure)
    {
        if ($value !== '' && !is_null($value) && $field) {
            if ($displayLabel === 'Email Address') {
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Invalid email address.');
                }
            } elseif ($displayLabel === 'Phone Number') {
                if (!preg_match('/^\(?([0-9]{3})\)?[-. ]?([0-9]{3})[-. ]?([0-9]{4})$/', $value)) {
                    throw new Exception('Invalid phone number.');
                }
            } elseif ($displayLabel === 'Password') {
                if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $value)) {
                    throw new Exception('Password must be at least 8 characters, including uppercase, lowercase, number, and special character.');
                }
                return password_hash($value, PASSWORD_DEFAULT);
            } elseif ($displayLabel === 'Date of Birth' && $field['type'] === 'date') {
                $dob = new \DateTime($value);
                $today = new \DateTime();
                $age = $today->diff($dob)->y;
                if ($age < 18) {
                    throw new Exception('Employee must be at least 18 years old.');
                }
                if ($age > 100) {
                    throw new Exception('Date of Birth seems invalid.');
                }
            } elseif ($displayLabel === 'Start Date' && $field['type'] === 'date') {
                $startDate = new \DateTime($value);
                $today = new \DateTime();
                if ($startDate > $today) {
                    throw new Exception('Start Date cannot be in the future.');
                }
                foreach ($answers as $gId => $gAnswers) {
                    foreach ($gAnswers as $fId => $val) {
                        $f = $this->findField($structure, $gId, $fId);
                        if ($f && ($f['customize_field_label'] === 'Date of Birth' || $f['label'] === 'Date of Birth') && $val) {
                            $dob = new \DateTime($val);
                            if ($startDate->diff($dob)->y < 18) {
                                throw new Exception('Start Date must be at least 18 years after Date of Birth.');
                            }
                        }
                    }
                }
            }
        }
        return $value;
    }



    private function saveEmployeeAnswers($companyId, $employeeUniqueId, $templateId, $answerData, $employeeId, $username, $reportToEmployeeUniqueId = null)
    {
        $EmployeeTemplateAnswersTable = $this->getTable('EmployeeTemplateAnswers', $companyId);
        $answerEntity = $EmployeeTemplateAnswersTable->newEntity([
            'company_id' => $companyId,
            'employee_unique_id' => $employeeUniqueId,
            'employee_id' => $employeeId,
            'username' => $username,
            'template_id' => $templateId,
            'answers' => $answerData,
            'report_to_employee_unique_id' => $reportToEmployeeUniqueId,
            'created_by' => $this->Authentication->getIdentity()->get('username') ?? 'system',
            'created' => date('Y-m-d H:i:s'),
            'modified' => date('Y-m-d H:i:s'),
        ]);
        if (!$EmployeeTemplateAnswersTable->save($answerEntity)) {
            throw new Exception('Failed to save employee answers.');
        }
        return $answerEntity;
    }

    private function saveUser($companyId, $userData, $username)
    {
        $UsersTable = $this->getTable('Users');
        
        Log::debug("saveUser called with companyId: {$companyId}, username: {$username}");
        Log::debug("userData: " . json_encode($userData));
        
        $userEntity = $UsersTable->newEntity([
            'company_id' => $companyId,
            'first_name' => $userData['first_name'],
            'middle_name' => $userData['middle_name'],
            'last_name' => $userData['last_name'],
            'birth_date' => $userData['birth_date'],
            'birth_place' => $userData['birth_place'],
            'sex' => $userData['sex'],
            'civil_status' => $userData['civil_status'],
            'nationality' => $userData['nationality'],
            'blood_type' => $userData['blood_type'],
            'email_address' => $userData['email_address'],
            'contact_number' => $userData['contact_number'],
            'username' => $username,
            'password' => password_hash($userData['password'], PASSWORD_DEFAULT),
            'system_user_role' => $userData['system_user_role'],
            'system_access_enabled' => $userData['system_access_enabled'],
            'active' => true,
            'deleted' => false,
            'created' => date('Y-m-d H:i:s'),
            'modified' => date('Y-m-d H:i:s'),
        ]);

        if (!$UsersTable->save($userEntity)) {
            $errors = $userEntity->getErrors();
            $errorMessages = [];
            foreach ($errors as $field => $fieldErrors) {
                foreach ($fieldErrors as $rule => $message) {
                    $errorMessages[] = "Field '{$field}': {$message}";
                }
            }
            $errorDetails = !empty($errorMessages) ? ' Validation errors: ' . implode(', ', $errorMessages) : '';
            throw new Exception('Failed to save user data.' . $errorDetails);
        }
        return $userEntity;
    }

    private function generateUniqueId(): string
    {
        return 'EMP-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
    }

    /**
     * Validate if employee unique ID exists in the system
     * @param string $companyId
     * @param string $employeeUniqueId
     * @return bool
     */
    private function validateEmployeeUniqueIdExists($companyId, $employeeUniqueId)
    {
        $EmployeeTemplateAnswersTable = $this->getTable('EmployeeTemplateAnswers', $companyId);
        
        $employee = $EmployeeTemplateAnswersTable
            ->find()
            ->select(['id'])
            ->where([
                'company_id' => $companyId,
                'employee_unique_id' => $employeeUniqueId,
                'deleted' => 0
            ])
            ->first();
            
        return $employee !== null;
    }

    /**
     * Check if employee unique ID exists and return appropriate error response if not
     * @param string $companyId
     * @param string $employeeUniqueId
     * @return bool|array Returns true if exists, error response array if not
     */
    private function checkEmployeeUniqueIdExists($companyId, $employeeUniqueId)
    {
        if (!$this->validateEmployeeUniqueIdExists($companyId, $employeeUniqueId)) {
            return [
                'success' => false,
                'message' => 'Employee not found with the provided unique ID',
                'status' => 404
            ];
        }
        return true;
    }

    public function tableHeaders()
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

        $company_id = $this->getCompanyId($authResult);
        try {
            $EmployeeTemplatesTable = $this->getTable('EmployeeTemplates', $company_id);

            $template = $EmployeeTemplatesTable
                ->find()
                ->select(['template_id' => 'id', 'structure'])
                ->where(['company_id' => $company_id, 'deleted' => 0])
                ->first();

            if (!$template) {
                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'No employee template found.',
                    ]));
            }

            // Process the structure to extract required fields
            $structure = json_decode(json_encode($template->structure), true); // Convert to array
            $headers = [];

            // Iterate through structure groups to find the fields
            if (is_array($structure)) {
                foreach ($structure as $group) {
                    if (isset($group['fields']) && is_array($group['fields'])) {
                        foreach ($group['fields'] as $field) {
                            $label = $field['label'] ?? '';
                            if (in_array($label, ['Employee ID', 'First Name', 'Last Name', 'Job Role', 'Reports To'])) {
                                $headers[] = [
                                    'id' => $this->getFieldId($label),
                                    'label' => !empty($field['customize_field_label']) ? $field['customize_field_label'] : $field['label']
                                ];
                            }
                        }
                    }
                }
            }

            // Add Actions column
            $headers[] = [
                'id' => 'actions',
                'label' => 'Actions'
            ];

            // Sort headers to ensure consistent order
            usort($headers, function ($a, $b) {
                $order = ['employeeId', 'firstName', 'lastName', 'jobRole', 'reportsTo', 'actions'];
                return array_search($a['id'], $order) - array_search($b['id'], $order);
            });

            // Validate that all required fields are present
            $requiredFields = ['employeeId', 'firstName', 'lastName', 'jobRole', 'reportsTo'];
            $foundFields = array_column($headers, 'id');
            if (count(array_intersect($requiredFields, $foundFields)) < count($requiredFields)) {
                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Required fields (Employee ID, First Name, Last Name, Job Role, or Reports To) not found in template.',
                    ]));
            }

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'data' => $headers
                ]));
        } catch (\Throwable $e) {
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error fetching employee template: ' . $e->getMessage(),
                ]));
        }
    }

    /**
     * Map field labels to consistent IDs
     * @param string $label
     * @return string
     */
    private function getFieldId($label)
    {
        $fieldIds = [
            'Employee ID' => 'employeeId',
            'First Name' => 'firstName',
            'Last Name' => 'lastName',
            'Job Role' => 'jobRole',
            'Reports To' => 'reportsTo'
        ];
        return $fieldIds[$label] ?? strtolower(str_replace(' ', '', $label));
    }

    /**
     * Check if required templates exist for employee import
     * 
     * @return \Cake\Http\Response
     */
    public function checkRequiredTemplates()
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

        $companyId = $this->getCompanyId($authResult);

        try {
            $missingTemplates = [];
            
            // Check Employee Template
            $employeeTemplatesTable = $this->getTable('EmployeeTemplates', $companyId);
            $employeeTemplate = $employeeTemplatesTable->find()
                ->where([
                    'company_id' => $companyId,
                    'deleted' => 0
                ])
                ->first();
            
            if (!$employeeTemplate) {
                $missingTemplates[] = 'employee';
            }
            
            // Check Job Role Template
            $jobRoleTemplatesTable = $this->getTable('JobRoleTemplates', $companyId);
            $jobRoleTemplate = $jobRoleTemplatesTable->find()
                ->where([
                    'company_id' => $companyId,
                    'deleted' => 0
                ])
                ->first();
            
            if (!$jobRoleTemplate) {
                $missingTemplates[] = 'job_role';
            }
            
            // Check Role Level Template
            $levelTemplatesTable = $this->getTable('LevelTemplates', $companyId);
            $levelTemplate = $levelTemplatesTable->find()
                ->where([
                    'company_id' => $companyId,
                    'deleted' => 0
                ])
                ->first();
            
            if (!$levelTemplate) {
                $missingTemplates[] = 'role_level';
            }
            
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'all_templates_exist' => empty($missingTemplates),
                    'missing_templates' => $missingTemplates,
                ]));
                
        } catch (\Exception $e) {
            Log::error('Error checking required templates: ' . $e->getMessage());
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error checking templates: ' . $e->getMessage(),
                ]));
        }
    }

    public function getEmployeesData()
    {
        Configure::write('debug', true);
        $this->request->allowMethod(['get']);

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

        $company_id = $this->getCompanyId($authResult);

        // Get pagination, search, and sorting parameters
        $page = (int)($this->request->getQuery('page') ?? 1);
        $limit = (int)($this->request->getQuery('limit') ?? 10);
        $search = $this->request->getQuery('search') ?? '';
        $sortField = $this->request->getQuery('sortField') ?? '';
        $sortOrder = $this->request->getQuery('sortOrder') ?? 'asc';

        // Validate parameters
        if ($page < 1) $page = 1;
        if ($limit < 1) $limit = 10;
        if ($limit > 100) $limit = 100; // Prevent excessive data retrieval
        $sortOrder = strtolower($sortOrder) === 'desc' ? 'desc' : 'asc';

        try {
            $EmployeeTemplatesTable = $this->getTable('EmployeeTemplates', $company_id);
            $JobRoleTemplatesTable = $this->getTable('JobRoleTemplates', $company_id);

            // Step 1: Build lookup of level_unique_id => rank and name (for job roles)
            $RoleLevelsTable = $this->getTable('RoleLevels', $company_id);
            $roleLevelsData = $RoleLevelsTable
                ->find()
                ->select(['level_unique_id', 'name', 'rank'])
                ->where([
                    'company_id' => $company_id,
                    'deleted' => 0,
                    'level_unique_id IS NOT' => null,
                ])
                ->all()
                ->toArray();

            // Lookup map: both ID and rank per level_unique_id
            $levelIdToInfo = [];
            foreach ($roleLevelsData as $level) {
                $levelIdToInfo[$level->level_unique_id] = [
                    'name' => $level->name,
                    'rank' => $level->rank
                ];
            }

            // Step 2: Fetch job role answers to map job_role_unique_id to job role details
            $jobRoleData = $JobRoleTemplatesTable
                ->find()
                ->select([
                    'job_role_unique_id' => 'job_role_template_answers.job_role_unique_id',
                    'job_role_answer' => 'job_role_template_answers.answers',
                    'job_role_structure' => 'JobRoleTemplates.structure',
                ])
                ->join([
                    'job_role_template_answers' => [
                        'table' => 'job_role_template_answers',
                        'type' => 'INNER',
                        'conditions' => [
                            'job_role_template_answers.company_id = JobRoleTemplates.company_id',
                            'job_role_template_answers.template_id = JobRoleTemplates.id',
                            'job_role_template_answers.deleted' => 0,
                        ],
                    ],
                ])
                ->where([
                    'JobRoleTemplates.company_id' => $company_id,
                    'JobRoleTemplates.deleted' => 0,
                    'job_role_template_answers.job_role_unique_id IS NOT NULL',
                ])
                ->all()
                ->toArray();

            // Create a lookup for job role details
            $jobRoleLookup = [];
            foreach ($jobRoleData as $jobRole) {
                // Decode job role answers if it's a JSON string
                $jobRoleAnswers = is_string($jobRole->job_role_answer) 
                    ? json_decode($jobRole->job_role_answer, true) 
                    : $jobRole->job_role_answer;
                
                // Decode job role structure if it's a JSON string
                $jobRoleStructure = is_string($jobRole->job_role_structure) 
                    ? json_decode($jobRole->job_role_structure, true) 
                    : $jobRole->job_role_structure;
                
                $jobRoleUniqueId = $jobRole->job_role_unique_id;

                $designation = null;
                if (is_array($jobRoleStructure) && is_array($jobRoleAnswers)) {
                    foreach ($jobRoleStructure as $group) {
                        $groupId = $group['id'] ?? null;
                        
                        // Check regular fields
                        if (isset($group['fields']) && is_array($group['fields'])) {
                        foreach ($group['fields'] as $field) {
                                $fieldLabel = $field['customize_field_label'] ?? $field['label'] ?? '';
                            if (in_array($fieldLabel, ['Job Role', 'Official Designation', 'Job Title'], true)) {
                                    $fieldId = $field['id'] ?? null;
                                    if ($groupId && $fieldId && isset($jobRoleAnswers[$groupId][$fieldId])) {
                                        $designation = $jobRoleAnswers[$groupId][$fieldId];
                                        break 2;
                                    }
                                }
                            }
                        }
                        
                        // Check subgroups
                        if (!$designation && isset($group['subGroups']) && is_array($group['subGroups'])) {
                            foreach ($group['subGroups'] as $subGroupIndex => $subGroup) {
                                $subGroupId = $subGroup['id'] ?? $groupId;
                                if (isset($subGroup['fields']) && is_array($subGroup['fields'])) {
                                    foreach ($subGroup['fields'] as $field) {
                                        $fieldLabel = $field['customize_field_label'] ?? $field['label'] ?? '';
                                        if (in_array($fieldLabel, ['Job Role', 'Official Designation', 'Job Title'], true)) {
                                            $fieldId = $field['id'] ?? null;
                                            if ($subGroupId && $fieldId && isset($jobRoleAnswers[$subGroupId][$fieldId])) {
                                                $designation = $jobRoleAnswers[$subGroupId][$fieldId];
                                                break 3;
                                    }
                                }
                                    }
                            }
                        }
                        }
                        
                        if ($designation) break;
                    }
                }
                $jobRoleLookup[$jobRoleUniqueId] = $designation ?: null;
            }
            
            Log::debug('🔍 DEBUG: Job role lookup created', [
                'total_job_roles' => count($jobRoleLookup),
                'sample_lookup' => array_slice($jobRoleLookup, 0, 5, true)
            ]);

            // Step 3: Fetch employee answers with pagination
            $query = $EmployeeTemplatesTable
                ->find()
                ->select([
                    'structure' => 'structure',
                    'employee_unique_id' => 'employee_template_answers.employee_unique_id',
                    'username' => 'employee_template_answers.username',
                    'template_id' => 'employee_template_answers.template_id',
                    'answer_id' => 'employee_template_answers.id',
                    'answers' => 'employee_template_answers.answers',
                ])
                ->join([
                    'employee_template_answers' => [
                        'table' => 'employee_template_answers',
                        'type' => 'LEFT',
                        'conditions' => [
                            'employee_template_answers.company_id = EmployeeTemplates.company_id',
                            'employee_template_answers.deleted' => 0,
                        ]
                    ],
                ])
                ->where([
                    'EmployeeTemplates.company_id' => $company_id,
                    'EmployeeTemplates.deleted' => 0,
                    'employee_template_answers.employee_unique_id IS NOT NULL',
                ]);

            // Get all data first for search and sorting (since we need to process JSON)
            $allEmployeeAnswers = $query->all()->toArray();

            if (empty($allEmployeeAnswers)) {
                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => true,
                        'data' => [
                            'records' => [],
                            'total' => 0
                        ],
                    ]));
            }

            $fieldMapping = [
                'Employee ID' => ['id' => 'employeeId', 'dataKey' => 'employee_id'],
                'First Name' => ['id' => 'firstName', 'dataKey' => 'first_name'],
                'Last Name' => ['id' => 'lastName', 'dataKey' => 'last_name'],
                'Job Role' => ['id' => 'jobRole', 'dataKey' => 'job_role'],
                'Reports To' => ['id' => 'reportsTo', 'dataKey' => 'reports_to'],
            ];

            $processedEmployees = array_map(function ($employee) use ($fieldMapping, $jobRoleLookup) {
                // Debug logging for employee data
                Log::debug('🔍 DEBUG: Processing employee in getEmployeesData', [
                    'answer_id' => $employee->answer_id,
                    'employee_unique_id' => $employee->employee_unique_id,
                    'username' => $employee->username ?? 'NULL',
                    'username_type' => gettype($employee->username ?? null),
                    'has_username_property' => property_exists($employee, 'username'),
                    'all_properties' => array_keys(get_object_vars($employee))
                ]);

                $result = [
                    'id' => $employee->answer_id,
                    'employee_unique_id' => $employee->employee_unique_id,
                    'username' => $employee->username ?? '',
                ];

                $structure = is_string($employee->structure) ? json_decode($employee->structure, true) : $employee->structure;
                
                if (is_string($employee->answers)) {
                    $answers = json_decode($employee->answers, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new \Exception('Invalid answers JSON format for employee_unique_id: ' . $employee->employee_unique_id);
                    }
                } else {
                    $answers = $employee->answers;
                }

                $jobRoleUniqueId = null;

                if (is_array($structure)) {
                    foreach ($structure as $group) {
                        $groupId = $group['id'] ?? null;

                        // Check regular fields
                        if (isset($group['fields']) && is_array($group['fields'])) {
                        foreach ($group['fields'] as $field) {
                                $fieldId = $field['id'];
                                $fieldLabel = $field['label'] ?? $field['customize_field_label'] ?? '';

                                if (isset($fieldMapping[$fieldLabel])) {
                                    $dataKey = $fieldMapping[$fieldLabel]['dataKey'];

                                    if (is_array($answers) && $groupId) {
                                        // Check in the group's answers
                                        if (isset($answers[$groupId][$fieldId])) {
                                            $answerValue = $answers[$groupId][$fieldId];
                                            
                                            if ($dataKey === 'job_role') {
                                                $jobRoleUniqueId = $answerValue; // Store job_role_unique_id
                                            } else {
                                                $result[$dataKey] = $answerValue;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        
                        // Check subgroups
                        if (isset($group['subGroups']) && is_array($group['subGroups'])) {
                            $groupLabel = $group['label'] ?? $group['id'];
                            foreach ($group['subGroups'] as $index => $subGroup) {
                                $subGroupLabel = "{$groupLabel}_{$index}";
                                if (isset($subGroup['fields']) && is_array($subGroup['fields'])) {
                                    foreach ($subGroup['fields'] as $field) {
                            $fieldId = $field['id'];
                            $fieldLabel = $field['label'] ?? $field['customize_field_label'] ?? '';

                            if (isset($fieldMapping[$fieldLabel])) {
                                $dataKey = $fieldMapping[$fieldLabel]['dataKey'];

                                if (is_array($answers)) {
                                                // Check in the subgroup's answers
                                                if (isset($answers[$subGroupLabel][$fieldId])) {
                                                    $answerValue = $answers[$subGroupLabel][$fieldId];
                                        
                                        if ($dataKey === 'job_role') {
                                            $jobRoleUniqueId = $answerValue; // Store job_role_unique_id
                                        } else {
                                            $result[$dataKey] = $answerValue;
                                        }
                                                }
                                            }
                                    }
                                }
                                }
                            }
                        }
                    }
                }

                // Add nulls for missing fields
                foreach ($fieldMapping as $mapping) {
                    $dataKey = $mapping['dataKey'];
                    if (!isset($result[$dataKey])) {
                        $result[$dataKey] = null;
                    }
                }

                // Set job_role based on jobRoleLookup
                if ($jobRoleUniqueId && isset($jobRoleLookup[$jobRoleUniqueId])) {
                    $result['job_role'] = $jobRoleLookup[$jobRoleUniqueId];
                } else {
                    // If job role not found in lookup, ensure it's set to null
                    if (!isset($result['job_role'])) {
                        $result['job_role'] = null;
                    }
                }
                
                // Debug logging for job role mapping
                if ($jobRoleUniqueId) {
                    Log::debug('🔍 DEBUG: Job role mapping for employee', [
                        'employee_unique_id' => $employee->employee_unique_id,
                        'username' => $employee->username ?? 'NULL',
                        'job_role_unique_id' => $jobRoleUniqueId,
                        'job_role_found' => isset($jobRoleLookup[$jobRoleUniqueId]),
                        'job_role_value' => $result['job_role'] ?? 'NULL'
                    ]);
                }

                // Debug logging for final result
                Log::debug('🔍 DEBUG: Final employee result in getEmployeesData', [
                    'result' => $result,
                    'username_in_result' => $result['username'] ?? 'NOT_SET',
                    'username_type' => gettype($result['username'] ?? null),
                    'job_role' => $result['job_role'] ?? 'NULL'
                ]);

                return $result;
            }, $allEmployeeAnswers);

            // Employee management page shows ALL employees for the company
            // No filtering by reporting relationships - this is the employee list view
            Log::debug('🔍 DEBUG: getEmployeesData - Showing all employees for company', [
                'company_id' => $company_id,
                'total_employees' => count($processedEmployees)
            ]);

            // Apply search filter if provided
            if (!empty($search)) {
                $processedEmployees = array_filter($processedEmployees, function ($employee) use ($search) {
                    $searchLower = strtolower($search);
                    return (
                        strpos(strtolower($employee['employee_id'] ?? ''), $searchLower) !== false ||
                        strpos(strtolower($employee['first_name'] ?? ''), $searchLower) !== false ||
                        strpos(strtolower($employee['last_name'] ?? ''), $searchLower) !== false ||
                        strpos(strtolower($employee['job_role'] ?? ''), $searchLower) !== false
                    );
                });
            }

            // Apply sorting if provided
            if (!empty($sortField)) {
                usort($processedEmployees, function ($a, $b) use ($sortField, $sortOrder) {
                    $aValue = $a[$sortField] ?? '';
                    $bValue = $b[$sortField] ?? '';
                    
                    if ($sortOrder === 'desc') {
                        return strcasecmp($bValue, $aValue);
                    } else {
                        return strcasecmp($aValue, $bValue);
                    }
                });
            }

            // Get total count after search
            $totalCount = count($processedEmployees);

            // Apply pagination
            $offset = ($page - 1) * $limit;
            $paginatedEmployees = array_slice($processedEmployees, $offset, $limit);

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'data' => [
                        'records' => $paginatedEmployees,
                        'total' => $totalCount
                    ],
                ]));
        } catch (\Throwable $e) {
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error fetching employees: ' . $e->getMessage(),
                ]));
        }
    }

    /**
     * Get employees for assignment (e.g., assigning child scorecards)
     * For non-admin users: only returns employees that report to the current user
     * For admin users: returns all employees
     */
    public function getEmployeesForAssignment()
    {
        Configure::write('debug', true);
        $this->request->allowMethod(['get']);

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

        $company_id = $this->getCompanyId($authResult);

        try {
            $EmployeeTemplatesTable = $this->getTable('EmployeeTemplates', $company_id);
            $JobRoleTemplatesTable = $this->getTable('JobRoleTemplates', $company_id);

            // Step 1: Build lookup of level_unique_id => rank and name (for job roles)
            $RoleLevelsTable = $this->getTable('RoleLevels', $company_id);
            $roleLevelsData = $RoleLevelsTable
                ->find()
                ->select(['level_unique_id', 'name', 'rank'])
                ->where([
                    'company_id' => $company_id,
                    'deleted' => 0,
                    'level_unique_id IS NOT' => null,
                ])
                ->all()
                ->toArray();

            // Step 2: Fetch job role answers to map job_role_unique_id to job role details
            $jobRoleData = $JobRoleTemplatesTable
                ->find()
                ->select([
                    'job_role_unique_id' => 'job_role_template_answers.job_role_unique_id',
                    'job_role_answer' => 'job_role_template_answers.answers',
                    'job_role_structure' => 'JobRoleTemplates.structure',
                ])
                ->join([
                    'job_role_template_answers' => [
                        'table' => 'job_role_template_answers',
                        'type' => 'INNER',
                        'conditions' => [
                            'job_role_template_answers.company_id = JobRoleTemplates.company_id',
                            'job_role_template_answers.template_id = JobRoleTemplates.id',
                            'job_role_template_answers.deleted' => 0,
                        ],
                    ],
                ])
                ->where([
                    'JobRoleTemplates.company_id' => $company_id,
                    'JobRoleTemplates.deleted' => 0,
                    'job_role_template_answers.job_role_unique_id IS NOT NULL',
                ])
                ->all()
                ->toArray();

            // Create a lookup for job role details
            $jobRoleLookup = [];
            foreach ($jobRoleData as $jobRole) {
                $jobRoleAnswers = is_string($jobRole->job_role_answer) 
                    ? json_decode($jobRole->job_role_answer, true) 
                    : $jobRole->job_role_answer;
                
                $jobRoleStructure = is_string($jobRole->job_role_structure) 
                    ? json_decode($jobRole->job_role_structure, true) 
                    : $jobRole->job_role_structure;
                
                $jobRoleUniqueId = $jobRole->job_role_unique_id;
                $designation = null;
                
                if (is_array($jobRoleStructure) && is_array($jobRoleAnswers)) {
                    foreach ($jobRoleStructure as $group) {
                        $groupId = $group['id'] ?? null;
                        
                        if (isset($group['fields']) && is_array($group['fields'])) {
                            foreach ($group['fields'] as $field) {
                                $fieldLabel = $field['customize_field_label'] ?? $field['label'] ?? '';
                                if (in_array($fieldLabel, ['Job Role', 'Official Designation', 'Job Title'], true)) {
                                    $fieldId = $field['id'] ?? null;
                                    if ($groupId && $fieldId && isset($jobRoleAnswers[$groupId][$fieldId])) {
                                        $designation = $jobRoleAnswers[$groupId][$fieldId];
                                        break 2;
                                    }
                                }
                            }
                        }
                        
                        if (!$designation && isset($group['subGroups']) && is_array($group['subGroups'])) {
                            foreach ($group['subGroups'] as $subGroup) {
                                $subGroupId = $subGroup['id'] ?? $groupId;
                                if (isset($subGroup['fields']) && is_array($subGroup['fields'])) {
                                    foreach ($subGroup['fields'] as $field) {
                                        $fieldLabel = $field['customize_field_label'] ?? $field['label'] ?? '';
                                        if (in_array($fieldLabel, ['Job Role', 'Official Designation', 'Job Title'], true)) {
                                            $fieldId = $field['id'] ?? null;
                                            if ($subGroupId && $fieldId && isset($jobRoleAnswers[$subGroupId][$fieldId])) {
                                                $designation = $jobRoleAnswers[$subGroupId][$fieldId];
                                                break 3;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        
                        if ($designation) break;
                    }
                }
                $jobRoleLookup[$jobRoleUniqueId] = $designation ?: null;
            }

            // Step 3: Fetch employee answers
            $query = $EmployeeTemplatesTable
                ->find()
                ->select([
                    'structure' => 'structure',
                    'employee_unique_id' => 'employee_template_answers.employee_unique_id',
                    'username' => 'employee_template_answers.username',
                    'template_id' => 'employee_template_answers.template_id',
                    'answer_id' => 'employee_template_answers.id',
                    'answers' => 'employee_template_answers.answers',
                ])
                ->join([
                    'employee_template_answers' => [
                        'table' => 'employee_template_answers',
                        'type' => 'LEFT',
                        'conditions' => [
                            'employee_template_answers.company_id = EmployeeTemplates.company_id',
                            'employee_template_answers.deleted' => 0,
                        ]
                    ],
                ])
                ->where([
                    'EmployeeTemplates.company_id' => $company_id,
                    'EmployeeTemplates.deleted' => 0,
                    'employee_template_answers.employee_unique_id IS NOT NULL',
                ]);

            $allEmployeeAnswers = $query->all()->toArray();

            if (empty($allEmployeeAnswers)) {
                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => true,
                        'data' => [
                            'records' => [],
                            'total' => 0
                        ],
                    ]));
            }

            $fieldMapping = [
                'Employee ID' => ['id' => 'employeeId', 'dataKey' => 'employee_id'],
                'First Name' => ['id' => 'firstName', 'dataKey' => 'first_name'],
                'Last Name' => ['id' => 'lastName', 'dataKey' => 'last_name'],
                'Job Role' => ['id' => 'jobRole', 'dataKey' => 'job_role'],
                'Reports To' => ['id' => 'reportsTo', 'dataKey' => 'reports_to'],
            ];

            $processedEmployees = array_map(function ($employee) use ($fieldMapping, $jobRoleLookup) {
                $result = [
                    'id' => $employee->answer_id,
                    'employee_unique_id' => $employee->employee_unique_id,
                    'username' => $employee->username ?? '',
                ];

                $structure = is_string($employee->structure) ? json_decode($employee->structure, true) : $employee->structure;
                
                if (is_string($employee->answers)) {
                    $answers = json_decode($employee->answers, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new \Exception('Invalid answers JSON format for employee_unique_id: ' . $employee->employee_unique_id);
                    }
                } else {
                    $answers = $employee->answers;
                }

                $jobRoleUniqueId = null;

                if (is_array($structure)) {
                    foreach ($structure as $group) {
                        $groupId = $group['id'] ?? null;

                        if (isset($group['fields']) && is_array($group['fields'])) {
                            foreach ($group['fields'] as $field) {
                                $fieldId = $field['id'];
                                $fieldLabel = $field['label'] ?? $field['customize_field_label'] ?? '';

                                if (isset($fieldMapping[$fieldLabel])) {
                                    $dataKey = $fieldMapping[$fieldLabel]['dataKey'];

                                    if (is_array($answers) && $groupId) {
                                        if (isset($answers[$groupId][$fieldId])) {
                                            $answerValue = $answers[$groupId][$fieldId];
                                            
                                            if ($dataKey === 'job_role') {
                                                $jobRoleUniqueId = $answerValue;
                                            } else {
                                                $result[$dataKey] = $answerValue;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        
                        if (isset($group['subGroups']) && is_array($group['subGroups'])) {
                            $groupLabel = $group['label'] ?? $group['id'];
                            foreach ($group['subGroups'] as $index => $subGroup) {
                                $subGroupLabel = "{$groupLabel}_{$index}";
                                if (isset($subGroup['fields']) && is_array($subGroup['fields'])) {
                                    foreach ($subGroup['fields'] as $field) {
                                        $fieldId = $field['id'];
                                        $fieldLabel = $field['label'] ?? $field['customize_field_label'] ?? '';

                                        if (isset($fieldMapping[$fieldLabel])) {
                                            $dataKey = $fieldMapping[$fieldLabel]['dataKey'];

                                            if (is_array($answers)) {
                                                if (isset($answers[$subGroupLabel][$fieldId])) {
                                                    $answerValue = $answers[$subGroupLabel][$fieldId];
                                        
                                                    if ($dataKey === 'job_role') {
                                                        $jobRoleUniqueId = $answerValue;
                                                    } else {
                                                        $result[$dataKey] = $answerValue;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                // Add nulls for missing fields
                foreach ($fieldMapping as $mapping) {
                    $dataKey = $mapping['dataKey'];
                    if (!isset($result[$dataKey])) {
                        $result[$dataKey] = null;
                    }
                }

                // Set job_role based on jobRoleLookup
                if ($jobRoleUniqueId && isset($jobRoleLookup[$jobRoleUniqueId])) {
                    $result['job_role'] = $jobRoleLookup[$jobRoleUniqueId];
                } else {
                    if (!isset($result['job_role'])) {
                        $result['job_role'] = null;
                    }
                }

                return $result;
            }, $allEmployeeAnswers);

            // Filter employees by reporting relationships for non-admin users
            $isAdmin = $this->isAdmin();
            if (!$isAdmin) {
                // Get logged-in user's username and employee_unique_id
                $loggedUsername = $this->getUsername($authResult);
                
                // Find the logged-in user's employee_unique_id
                $loggedUserEmployee = $EmployeeTemplatesTable
                    ->find()
                    ->select([
                        'employee_unique_id' => 'employee_template_answers.employee_unique_id',
                    ])
                    ->join([
                        'employee_template_answers' => [
                            'table' => 'employee_template_answers',
                            'type' => 'INNER',
                            'conditions' => [
                                'employee_template_answers.company_id = EmployeeTemplates.company_id',
                                'employee_template_answers.deleted' => 0,
                            ]
                        ],
                    ])
                    ->where([
                        'EmployeeTemplates.company_id' => $company_id,
                        'EmployeeTemplates.deleted' => 0,
                        'employee_template_answers.username' => $loggedUsername,
                        'employee_template_answers.employee_unique_id IS NOT NULL',
                    ])
                    ->first();
                
                if ($loggedUserEmployee && $loggedUserEmployee->employee_unique_id) {
                    $loggedUserEmployeeUniqueId = $loggedUserEmployee->employee_unique_id;
                    
                    // Filter to only show employees that report to the logged-in user
                    $processedEmployees = array_filter($processedEmployees, function ($employee) use ($loggedUserEmployeeUniqueId) {
                        $reportsTo = $employee['reports_to'] ?? null;
                        return $reportsTo === $loggedUserEmployeeUniqueId;
                    });
                    
                    Log::debug('🔍 DEBUG: getEmployeesForAssignment - Filtered employees by reporting relationship', [
                        'logged_username' => $loggedUsername,
                        'logged_user_employee_unique_id' => $loggedUserEmployeeUniqueId,
                        'filtered_count' => count($processedEmployees)
                    ]);
                } else {
                    // If logged-in user is not found in employee records, show no employees
                    $processedEmployees = [];
                    Log::warning('🔍 DEBUG: getEmployeesForAssignment - Logged-in user not found in employee records', [
                        'logged_username' => $loggedUsername,
                        'company_id' => $company_id
                    ]);
                }
            } else {
                Log::debug('🔍 DEBUG: getEmployeesForAssignment - Admin user - showing all employees', [
                    'total_employees' => count($processedEmployees)
                ]);
            }

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'data' => [
                        'records' => array_values($processedEmployees), // Re-index array
                        'total' => count($processedEmployees)
                    ],
                ]));
        } catch (\Throwable $e) {
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error fetching employees for assignment: ' . $e->getMessage(),
                ]));
        }
    }

    public function getAllMinimalEmployees()
{
    $this->request->allowMethod(['get']);
    $authResult = $this->Authentication->getResult();
    if (!$authResult || !$authResult->isValid()) {
        return $this->response
            ->withStatus(401)
            ->withType('application/json')
            ->withStringBody(json_encode(['success' => false, 'message' => 'Unauthorized']));
    }

    $companyId = $this->getCompanyId($authResult);
    // Note: Connection is not used in this method, getTable handles connection selection
    // Including test mode detection like getTable does
    $EmployeeTemplatesTable = $this->getTable('EmployeeTemplates', $companyId);

    $query = $EmployeeTemplatesTable
        ->find()
        ->select([
            'structure' => 'EmployeeTemplates.structure',
            'employee_unique_id' => 'employee_template_answers.employee_unique_id',
            'username' => 'employee_template_answers.username',
            'answers' => 'employee_template_answers.answers'
        ])
        ->join([
            'employee_template_answers' => [
                'table' => 'employee_template_answers',
                'type' => 'INNER',
                'conditions' => [
                    'employee_template_answers.company_id = EmployeeTemplates.company_id',
                    'employee_template_answers.template_id = EmployeeTemplates.id',
                    'employee_template_answers.deleted' => 0,
                ],
            ],
        ])
        ->where([
            'EmployeeTemplates.company_id' => $companyId,
            'EmployeeTemplates.deleted' => 0,
        ])
        ->enableHydration(true)
        ->all()
        ->toArray();

    $result = array_map(function ($record) use ($companyId) {
        $answers = is_array($record->answers) ? $record->answers : json_decode($record->answers, true);
        $structure = json_decode(json_encode($record->structure), true);

        $firstName = null;
        $lastName = null;

        if (is_array($structure) && is_array($answers)) {
            foreach ($structure as $group) {
                foreach ($group['fields'] as $field) {
                    $fieldId = $field['id'];
                    $fieldLabel = $field['customize_field_label'] ?? $field['label'];

                    if (in_array($fieldLabel, ['First Name', 'Given Name'], true)) {
                        foreach ($answers as $groupAnswers) {
                            if (isset($groupAnswers[$fieldId])) {
                                $firstName = $groupAnswers[$fieldId];
                                break;
                            }
                        }
                    }

                    if (in_array($fieldLabel, ['Last Name', 'Surname'], true)) {
                        foreach ($answers as $groupAnswers) {
                            if (isset($groupAnswers[$fieldId])) {
                                $lastName = $groupAnswers[$fieldId];
                                break;
                            }
                        }
                    }

                    if ($firstName && $lastName) break;
                }
                if ($firstName && $lastName) break;
            }
        }

        return [
            'employee_unique_id' => $record->employee_unique_id,
            'username' => $record->username ?? '',
            'first_name' => $firstName ?? '',
            'last_name' => $lastName ?? '',
        ];
    }, $query);

    return $this->response
        ->withType('application/json')
        ->withStringBody(json_encode([
            'success' => true,
            'data' => $result,
        ]));
}


    public function getEmployeeFieldsAndAnswers()
    {
        Configure::write('debug', true);
        $this->request->allowMethod(['post']);

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

        $companyId = $this->getCompanyId($authResult);
        $data = $this->request->getData();
        $employee_unique_id = $data['employee_unique_id'] ?? null;

        if (empty($employee_unique_id)) {
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Missing employee_unique_id',
                ]));
        }

        // Validate if employee unique ID exists
        $employeeExistsCheck = $this->checkEmployeeUniqueIdExists($companyId, $employee_unique_id);
        if ($employeeExistsCheck !== true) {
            return $this->response
                ->withStatus($employeeExistsCheck['status'])
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => $employeeExistsCheck['success'],
                    'message' => $employeeExistsCheck['message'],
                ]));
        }

        try {
            $EmployeeTemplatesTable = $this->getTable('EmployeeTemplates', $companyId);
            $EmployeeAnswerFilesTable = $this->getTable('EmployeeAnswerFiles', $companyId);

            $get_employee_detail = $EmployeeTemplatesTable
                ->find()
                ->select([
                    'structure' => 'structure',
                    'employee_unique_id' => 'employee_template_answers.employee_unique_id',
                    'template_id' => 'employee_template_answers.template_id',
                    'answer_id' => 'employee_template_answers.id',
                    'answers' => 'employee_template_answers.answers',
                ])
                ->join([
                    'employee_template_answers' => [
                        'table' => 'employee_template_answers',
                        'type' => 'LEFT',
                        'conditions' => [
                            'employee_template_answers.company_id = EmployeeTemplates.company_id',
                            'employee_template_answers.deleted' => 0,
                        ],
                    ],
                ])
                ->where([
                    'EmployeeTemplates.company_id' => $companyId,
                    'EmployeeTemplates.deleted' => 0,
                    'employee_template_answers.employee_unique_id' => $employee_unique_id,
                ])
                ->first();

            if (!$get_employee_detail) {
                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'No employee found for the provided employee_unique_id.',
                    ]));
            }

            $structure = $get_employee_detail->structure;
            $answers = is_array($get_employee_detail->answers) ? $get_employee_detail->answers : json_decode($get_employee_detail->answers, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid answers JSON format');
            }

            // Filter out Password fields from structure and answers
            foreach ($structure as &$group) {
                // Remove password fields from top-level fields
                if (isset($group['fields'])) {
                    $group['fields'] = array_filter($group['fields'], function ($field) use (&$answers, $group) {
                        $label = $field['customize_field_label'] ?? $field['label'];
                        if (strtolower($label) === 'password') {
                            unset($answers[$group['id']][$field['id']]);
                            return false;
                        }
                        return true;
                    });
                }

                // Remove password fields from subgroups
                if (isset($group['subGroups'])) {
                    foreach ($group['subGroups'] as $subGroupIndex => &$subGroup) {
                        $subGroup['fields'] = array_filter($subGroup['fields'], function ($field) use (&$answers, $group, $subGroupIndex) {
                            $label = $field['customize_field_label'] ?? $field['label'];
                            if (strtolower($label) === 'password') {
                                $key = $field['id'] . '_' . $subGroupIndex;
                                unset($answers[$group['id']][$key]);
                                return false;
                            }
                            return true;
                        });
                    }
                }
            }

            // Fetch attached files and merge into answers
            $get_employee_attached = $EmployeeAnswerFilesTable
                ->find('active')
                ->select([
                    'answer_id' => 'answer_id',
                    'file_path' => 'file_path',
                    'file_name' => 'file_name',
                    's3_bucket' => 's3_bucket',
                    's3_key' => 's3_key',
                    'group_id' => 'group_id',
                    'field_id' => 'field_id',
                ])
                ->join([
                    'employee_template_answers' => [
                        'table' => 'employee_template_answers',
                        'type' => 'LEFT',
                        'conditions' => [
                            'employee_template_answers.company_id = EmployeeAnswerFiles.company_id',
                            'employee_template_answers.id = EmployeeAnswerFiles.answer_id',
                            'employee_template_answers.deleted' => 0,
                        ],
                    ],
                ])
                ->where([
                    'EmployeeAnswerFiles.company_id' => $companyId,
                    'EmployeeAnswerFiles.deleted' => 0,
                    'EmployeeAnswerFiles.answer_id' => $get_employee_detail['answer_id'],
                ])
                ->toArray();

            // Generate presigned URLs for S3 files
            $s3Service = $this->getS3Service();
            foreach ($get_employee_attached as $file) {
                $group_id = $file['group_id'];
                $field_id = $file['field_id'];
                if (!isset($answers[$group_id])) {
                    $answers[$group_id] = [];
                }
                
                // If file is in S3, generate presigned URL; otherwise use file_path (backward compatibility)
                if (!empty($file['s3_bucket']) && !empty($file['s3_key'])) {
                    $presignedUrl = $s3Service->generatePresignedUrl(
                        $file['s3_bucket'],
                        $file['s3_key'],
                        3600 // 1 hour expiry
                    );
                    // Store presigned URL for S3 files
                    $answers[$group_id][$field_id] = $presignedUrl ?: $file['file_path'];
                } else {
                    // Fallback to file_path for backward compatibility (local files)
                    $answers[$group_id][$field_id] = $file['file_path'];
                }
            }

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'data' => [
                        'structure' => $structure,
                        'template_id' => $get_employee_detail->template_id,
                        'answers' => $answers,
                    ],
                ]));
        } catch (\Throwable $e) {
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error fetching employee data: ' . $e->getMessage(),
                ]));
        }
    }

    /**
     * Flatten nested answers structure for comparison
     *
     * @param array $answers
     * @return array
     */
    private function flattenAnswers($answers)
    {
        Log::debug('🔍 DEBUG: flattenAnswers - Input', [
            'answers' => $answers,
            'answers_type' => gettype($answers),
            'answers_empty' => empty($answers)
        ]);

        if (empty($answers)) {
            Log::debug('🔍 DEBUG: flattenAnswers - Empty answers, returning empty array');
            return [];
        }

        $flatAnswers = [];
        $answerKeys = array_keys($answers);

        Log::debug('🔍 DEBUG: flattenAnswers - Answer keys', [
            'answer_keys' => $answerKeys,
            'first_key' => $answerKeys[0] ?? 'none'
        ]);

        if (!empty($answerKeys)) {
            $firstKey = $answerKeys[0];
            if (is_array($answers[$firstKey])) {
                Log::debug('🔍 DEBUG: flattenAnswers - Nested structure detected, flattening');
                // It's nested, flatten it
                foreach ($answers as $groupId => $groupAnswers) {
                    if (is_array($groupAnswers)) {
                        $flatAnswers = $flatAnswers + $groupAnswers;
                        Log::debug('🔍 DEBUG: flattenAnswers - Flattened group', [
                            'group_id' => $groupId,
                            'group_answers' => $groupAnswers,
                            'flat_answers_so_far' => $flatAnswers
                        ]);
                    }
                }
            } else {
                Log::debug('🔍 DEBUG: flattenAnswers - Flat structure detected');
                // It's already flat
                $flatAnswers = $answers;
            }
        }

        Log::debug('🔍 DEBUG: flattenAnswers - Final result', [
            'flat_answers' => $flatAnswers,
            'flat_answers_count' => count($flatAnswers),
            'flat_answers_keys' => array_keys($flatAnswers)
        ]);

        return $flatAnswers;
    }

    private function checkExistingEmployeeIdAndUsername($companyId, $employeeId, $username, $employeeUniqueId = null)
{
    $EmployeeTemplateAnswersTable = $this->getTable('EmployeeTemplateAnswers', $companyId);
    $UsersTable = $this->getTable('Users');

    // Get current username if updating an employee
    $currentUsername = null;
    if ($employeeUniqueId) {
        $currentEmployee = $EmployeeTemplateAnswersTable
            ->find()
            ->select(['username'])
            ->where([
                'company_id' => $companyId,
                'employee_unique_id' => $employeeUniqueId,
                'deleted' => 0,
            ])
            ->first();
        $currentUsername = $currentEmployee ? $currentEmployee->username : null;
    }

    // Check EmployeeTemplateAnswers table for employee_id or username conflicts
    $conditions = [
        'company_id' => $companyId,
        'deleted' => 0,
        'OR' => [
            'employee_id' => $employeeId,
            'username' => $username,
        ],
    ];

    if ($employeeUniqueId) {
        // Exclude the current employee for updates
        $conditions['employee_unique_id !='] = $employeeUniqueId;
    }

    $existingAnswer = $EmployeeTemplateAnswersTable
        ->find()
        ->where($conditions)
        ->first();

    if ($existingAnswer) {
        if ($existingAnswer->employee_id === $employeeId) {
            throw new Exception('Employee ID already exists.');
        }
        if ($existingAnswer->username === $username) {
            throw new Exception('Username already exists.');
        }
    }

    // Check Users table for username conflicts
    if ($username) {
        $userConditions = [
            'company_id' => $companyId,
            'deleted' => false,
            'username' => $username,
        ];

        // If updating, exclude the current username from conflict check
        if ($currentUsername && $currentUsername === $username) {
            // Skip conflict check if the username hasn't changed
            return;
        }

        $existingUser = $UsersTable
            ->find()
            ->where($userConditions)
            ->first();

        if ($existingUser) {
            throw new Exception('Username already exists in users.');
        }
    }
}

    public function deleteEmployee()
    {
        Configure::write('debug', true); // Consider removing in production
        $this->request->allowMethod(['post']);

        // Authentication check
        $authResult = $this->Authentication->getResult();
        if (!$authResult || !$authResult->isValid()) {
            return $this->response
                ->withStatus(401)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ]));
        }

        // Require admin access for deleting employees
        $adminCheck = $this->requireAdmin();
        if ($adminCheck !== null) {
            return $adminCheck;
        }

        $data = $this->request->getData();
        $companyId = $this->getCompanyId($authResult);
        $employeeUniqueId = $data['employee_unique_id'] ?? null;

        // Initialize tables
        $employeeTemplateAnswersTable = $this->getTable('EmployeeTemplateAnswers', $companyId);
        $employeeAnswerFilesTable = $this->getTable('EmployeeAnswerFiles', $companyId);
        $UsersTable = $this->getTable('Users');

        // Validation
        if (empty($employeeUniqueId)) {
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Missing employee unique ID'
                ]));
        }

        try {
            // Start transaction
            $connection = $employeeTemplateAnswersTable->getConnection();
            $connection->begin();

            // Check if employee exists
            $employee = $employeeTemplateAnswersTable->find()
                ->where([
                    'company_id' => $companyId,
                    'deleted' => 0,
                    'employee_unique_id' => $employeeUniqueId
                ])
                ->first();

            if (!$employee) {
                $connection->rollback();
                return $this->response
                    ->withStatus(404)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Employee not found'
                    ]));
            }

            // Extract employee name for audit logging BEFORE deletion
            // Handle both JSON string and array formats
            $employeeAnswers = $employee->answers ?? [];
            if (is_string($employeeAnswers)) {
                $employeeAnswers = json_decode($employeeAnswers, true) ?? [];
            }
            
            // Try to get full name from template structure
            $employeeName = 'Unknown Employee';
            if (!empty($employee->template_id)) {
                try {
                    $EmployeeTemplatesTable = $this->getTable('EmployeeTemplates', $companyId);
                    $template = $EmployeeTemplatesTable->find()
                        ->where(['id' => $employee->template_id, 'deleted' => 0])
                        ->first();
                    
                    if ($template && !empty($template->structure)) {
                        $structure = is_string($template->structure) 
                            ? json_decode($template->structure, true) 
                            : $template->structure;
                        
                        // Build field map (field ID -> label)
                        $fieldMap = [];
                        if (is_array($structure)) {
                            foreach ($structure as $group) {
                                if (isset($group['fields']) && is_array($group['fields'])) {
                                    foreach ($group['fields'] as $field) {
                                        if (isset($field['id'])) {
                                            $label = $field['customize_field_label'] ?? $field['label'] ?? '';
                                            $fieldMap[$field['id']] = $label;
                                        }
                                    }
                                }
                                if (isset($group['subGroups']) && is_array($group['subGroups'])) {
                                    foreach ($group['subGroups'] as $subGroup) {
                                        if (isset($subGroup['fields']) && is_array($subGroup['fields'])) {
                                            foreach ($subGroup['fields'] as $field) {
                                                if (isset($field['id'])) {
                                                    $label = $field['customize_field_label'] ?? $field['label'] ?? '';
                                                    $fieldMap[$field['id']] = $label;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        
                        // Extract first name and last name using field map
                        $firstName = '';
                        $lastName = '';
                        if (is_array($employeeAnswers)) {
                            foreach ($employeeAnswers as $groupId => $groupAnswers) {
                                if (is_array($groupAnswers)) {
                                    foreach ($groupAnswers as $fieldId => $value) {
                                        $label = $fieldMap[$fieldId] ?? '';
                                        if (in_array($label, ['First Name', 'Given Name'])) {
                                            $firstName = $value ?? '';
                                        } elseif (in_array($label, ['Last Name', 'Surname', 'Family Name'])) {
                                            $lastName = $value ?? '';
                                        }
                                    }
                                }
                            }
                        }
                        
                        $fullName = trim($firstName . ' ' . $lastName);
                        if (!empty($fullName)) {
                            $employeeName = $fullName;
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Error extracting employee name from template: ' . $e->getMessage());
                }
            }
            
            // Fallback to AuditHelper extraction if template method failed
            if ($employeeName === 'Unknown Employee') {
                $employeeName = AuditHelper::extractEmployeeName($employeeAnswers);
            }
            
            // Final fallback to username if name extraction fails
            if (empty($employeeName) || $employeeName === 'Unnamed Employee') {
                $employeeName = $employee->username ?? $employeeUniqueId ?? 'Unknown Employee';
            }

            // Soft delete related records
            $employeeTemplateAnswersTable->updateAll(
                ['deleted' => 1, 'modified' => date('Y-m-d H:i:s')],
                [
                    'company_id' => $companyId,
                    'employee_unique_id' => $employeeUniqueId,
                    'deleted' => 0
                ]
            );



            // Soft delete answer files
            $employeeAnswerFilesTable->updateAll(
                ['deleted' => 1, 'modified' => date('Y-m-d H:i:s')],
                [
                    'company_id' => $companyId,
                    'answer_id' => $employee->id,
                    'deleted' => 0
                ]
            );

            // Soft delete users
            $UsersTable->updateAll(
                ['deleted' => 1, 'modified' => date('Y-m-d H:i:s')],
                [
                    'company_id' => $companyId,
                    'username' => $employee->username,
                    'deleted' => 0
                ]
            );

            // Commit transaction
            $connection->commit();

            // Extract user data for audit logging
            $auditUserData = AuditHelper::extractUserData($authResult);
            
            // Override company_id and username with the correct values from controller
            $authData = $authResult->getData();
            $username = null;
            if ($authData instanceof \ArrayObject || is_array($authData)) {
                $username = $authData['username'] ?? $authData['sub'] ?? null;
            } elseif (is_object($authData)) {
                $username = $authData->username ?? $authData->sub ?? null;
            }
            
            $auditUserData['company_id'] = (string)$companyId;
            $auditUserData['username'] = $username ?? $auditUserData['username'] ?? 'system';
            $auditUserData['user_id'] = $authData->id ?? $authData['id'] ?? $authData->sub ?? $authData['sub'] ?? $auditUserData['user_id'] ?? 0;
            
            // If we now have a user_id but full_name wasn't fetched, fetch it now
            if (!empty($auditUserData['user_id']) && (empty($auditUserData['full_name']) || $auditUserData['full_name'] === 'Unknown')) {
                try {
                    $usersTable = TableRegistry::getTableLocator()->get('Users', [
                        'connection' => ConnectionManager::get('default')
                    ]);
                    
                    $user = $usersTable->find()
                        ->select(['first_name', 'last_name'])
                        ->where(['id' => $auditUserData['user_id']])
                        ->first();
                    
                    if ($user) {
                        $firstName = $user->first_name ?? '';
                        $lastName = $user->last_name ?? '';
                        $fullName = trim($firstName . ' ' . $lastName);
                        if (!empty($fullName)) {
                            $auditUserData['full_name'] = $fullName;
                            $auditUserData['employee_name'] = $fullName;
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Error fetching user full name in controller: ' . $e->getMessage());
                }
            }
            
            // Ensure full_name is preserved (don't overwrite it if it was already fetched)
            if (empty($auditUserData['full_name']) && !empty($auditUserData['employee_name'])) {
                $auditUserData['full_name'] = $auditUserData['employee_name'];
            }
            
            // Initialize debug info
            $GLOBALS['audit_debug'] = [];
            $GLOBALS['audit_debug']['helper_called'] = true;
            $GLOBALS['audit_debug']['action'] = 'DELETE';
            $GLOBALS['audit_debug']['employee_unique_id'] = $employeeUniqueId;
            $GLOBALS['audit_debug']['employee_name'] = $employeeName;
            $GLOBALS['audit_debug']['company_id'] = (string)$companyId;
            $GLOBALS['audit_debug']['timestamp'] = date('Y-m-d H:i:s');
            $GLOBALS['audit_debug']['user_data'] = $auditUserData;
            
            // Log employee deletion with error handling
            try {
            AuditHelper::logEmployeeAction(
                'DELETE',
                $employeeUniqueId,
                $employeeName,
                $auditUserData,
                $this->request
            );
            } catch (\Exception $e) {
                Log::error('Error logging employee DELETE audit: ' . $e->getMessage(), [
                    'employee_unique_id' => $employeeUniqueId,
                    'trace' => $e->getTraceAsString()
                ]);
                // Don't fail the request if audit logging fails
            }

            return $this->response
                ->withStatus(200)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'message' => 'Employee deleted successfully',
                    'debug' => $GLOBALS['audit_debug'] ?? null,
                ]));
        } catch (\Exception $e) {
            $connection->rollback();
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Failed to delete employee',
                    'error' => $e->getMessage() // Consider removing in production
                ]));
        }
    }

    public function getEmployeeData()
    {
        Configure::write('debug', true); // Disable debug in production
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

        $companyId = $this->getCompanyId($authResult);
        $data = $this->request->getData();
        $employeeUniqueId = $data['employee_unique_id'] ?? null;

        // Validate input
        if (empty($employeeUniqueId)) {
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Missing employee unique ID',
                ]));
        }

        try {
            // Get tenant-specific tables
            $employeeTemplatesTable = $this->getTable('EmployeeTemplates', $companyId);
            $employeeTemplateAnswersTable = $this->getTable('EmployeeTemplateAnswers', $companyId);
            $employeeAnswerFilesTable = $this->getTable('EmployeeAnswerFiles', $companyId);
            $JobRoleTemplatesTable = $this->getTable('JobRoleTemplates', $companyId);

            // Fetch job role answers to map job_role_unique_id to job role details
            $jobRoleData = $JobRoleTemplatesTable
                ->find()
                ->select([
                    'job_role_unique_id' => 'job_role_template_answers.job_role_unique_id',
                    'job_role_answer' => 'job_role_template_answers.answers',
                    'job_role_structure' => 'JobRoleTemplates.structure',
                ])
                ->join([
                    'job_role_template_answers' => [
                        'table' => 'job_role_template_answers',
                        'type' => 'INNER',
                        'conditions' => [
                            'job_role_template_answers.company_id = JobRoleTemplates.company_id',
                            'job_role_template_answers.template_id = JobRoleTemplates.id',
                            'job_role_template_answers.deleted' => 0,
                        ],
                    ],
                ])
                ->where([
                    'JobRoleTemplates.company_id' => $companyId,
                    'JobRoleTemplates.deleted' => 0,
                    'job_role_template_answers.job_role_unique_id IS NOT NULL',
                ])
                ->all()
                ->toArray();

            // Create a lookup for job role details
            $jobRoleLookup = [];
            foreach ($jobRoleData as $jobRole) {
                $jobRoleAnswers = json_decode($jobRole->job_role_answer, true);
                $jobRoleStructure = $jobRole->job_role_structure;
                $jobRoleUniqueId = $jobRole->job_role_unique_id;

                $designation = null;
                if (is_array($jobRoleStructure) && is_array($jobRoleAnswers)) {
                    foreach ($jobRoleStructure as $group) {
                        foreach ($group['fields'] as $field) {
                            $fieldLabel = $field['customize_field_label'] ?? $field['label'] ?? 'Unknown Field';
                            if (in_array($fieldLabel, ['Job Role', 'Official Designation', 'Job Title'], true)) {
                                $fieldId = $field['id'];
                                foreach ($jobRoleAnswers as $groupAnswers) {
                                    if (isset($groupAnswers[$fieldId])) {
                                        $designation = $groupAnswers[$fieldId];
                                        break;
                                    }
                                }
                                break;
                            }
                        }
                        if ($designation) break;
                    }
                }
                $jobRoleLookup[$jobRoleUniqueId] = $designation ?: null;
            }

            // Fetch employee details
            $employeeDetail = $employeeTemplateAnswersTable
                ->find()
                ->select([
                    'employee_unique_id' => 'EmployeeTemplateAnswers.employee_unique_id',
                    'template_id' => 'EmployeeTemplateAnswers.template_id',
                    'answer_id' => 'EmployeeTemplateAnswers.id',
                    'answers' => 'EmployeeTemplateAnswers.answers',
                    'structure' => 'EmployeeTemplates.structure',
                ])
                ->join([
                    'EmployeeTemplates' => [
                        'table' => 'employee_templates',
                        'type' => 'LEFT',
                        'conditions' => [
                            'EmployeeTemplates.company_id' => $companyId,
                            'EmployeeTemplates.id = EmployeeTemplateAnswers.template_id',
                            'EmployeeTemplates.deleted' => 0,
                        ],
                    ],
                ])
                ->where([
                    'EmployeeTemplateAnswers.company_id' => $companyId,
                    'EmployeeTemplateAnswers.deleted' => 0,
                    'EmployeeTemplateAnswers.employee_unique_id' => $employeeUniqueId,
                ])
                ->first();

            if (!$employeeDetail) {
                return $this->response
                    ->withStatus(404)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Employee not found for the provided unique ID',
                    ]));
            }

            // Parse answers and structure JSON
            $answers = $employeeDetail->answers;
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid answers JSON format: ' . json_last_error_msg());
            }
            $structure = json_decode($employeeDetail->structure, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid structure JSON format: ' . json_last_error_msg());
            }

            // Enhance structure and filter out Password fields
            $filteredStructure = [];
            $passwordFieldIds = [];
            if (is_array($structure)) {
                foreach ($structure as $group) {
                $group['customize_group_label'] = isset($group['customize_group_label']) && $group['customize_group_label'] !== ''
                    ? $group['customize_group_label']
                    : ($group['label'] ?? 'Unnamed Group');
                $group['label'] = $group['label'] ?? 'Unnamed Group';

                $filteredFields = [];
                foreach ($group['fields'] as $field) {
                    $fieldLabel = $field['customize_field_label'] ?? $field['label'] ?? 'Unknown Field';
                    if (strtolower($fieldLabel) !== 'password') {
                        $field['customize_field_label'] = isset($field['customize_field_label']) && $field['customize_field_label'] !== ''
                            ? $field['customize_field_label']
                            : ($field['label'] ?? 'Unknown Field');
                        $field['label'] = $field['label'] ?? 'Unknown Field';
                        $filteredFields[] = $field;
                    } else {
                        $passwordFieldIds[] = ['group_id' => $group['id'], 'field_id' => $field['id']];
                    }
                }
                $group['fields'] = $filteredFields;

                if (isset($group['subGroups']) && is_array($group['subGroups'])) {
                    $filteredSubGroups = [];
                    foreach ($group['subGroups'] as $subGroup) {
                        $subGroup['customize_group_label'] = isset($subGroup['customize_group_label']) && $subGroup['customize_group_label'] !== ''
                            ? $subGroup['customize_group_label']
                            : ($subGroup['label'] ?? 'Unnamed Sub-Group');
                        $subGroup['label'] = $subGroup['label'] ?? 'Unnamed Sub-Group';

                        $filteredSubGroupFields = [];
                        foreach ($subGroup['fields'] as $field) {
                            $fieldLabel = $field['customize_field_label'] ?? $field['label'] ?? 'Unknown Field';
                            if (strtolower($fieldLabel) !== 'password') {
                                $field['customize_field_label'] = isset($field['customize_field_label']) && $field['customize_field_label'] !== ''
                                    ? $field['customize_field_label']
                                    : ($field['label'] ?? 'Unknown Field');
                                $field['label'] = $field['label'] ?? 'Unknown Field';
                                $filteredSubGroupFields[] = $field;
                            } else {
                                $passwordFieldIds[] = ['group_id' => $subGroup['id'], 'field_id' => $field['id']];
                            }
                        }
                        $subGroup['fields'] = $filteredSubGroupFields;
                        if (!empty($subGroup['fields'])) {
                            $filteredSubGroups[] = $subGroup;
                        }
                    }
                    $group['subGroups'] = $filteredSubGroups;
                }

                if (!empty($group['fields']) || !empty($group['subGroups'])) {
                    $filteredStructure[] = $group;
                }
                }
            }
            $structure = $filteredStructure;

            // Filter answers to exclude Password fields
            foreach ($passwordFieldIds as $passwordField) {
                $groupId = $passwordField['group_id'];
                $fieldId = $passwordField['field_id'];
                if (isset($answers[$groupId][$fieldId])) {
                    unset($answers[$groupId][$fieldId]);
                }
                if (isset($answers[$groupId]) && empty($answers[$groupId])) {
                    unset($answers[$groupId]);
                }
            }

            // Extract job_role_unique_id from answers and map to job role
            $jobRoleUniqueId = null;
            foreach ($structure as $group) {
                foreach ($group['fields'] as $field) {
                    $fieldLabel = $field['label'] ?? 'Unknown Field';
                    if (in_array($fieldLabel, ['Job Role', 'Job Role Unique ID', 'Role ID'], true)) {
                        $groupId = $group['id'];
                        $fieldId = $field['id'];
                        if (isset($answers[$groupId][$fieldId])) {
                            $jobRoleUniqueId = $answers[$groupId][$fieldId];
                            if (isset($jobRoleLookup[$jobRoleUniqueId])) {
                                $answers[$groupId][$fieldId] = $jobRoleLookup[$jobRoleUniqueId];
                            }
                        }
                        break 2;
                    }
                }
            }

            // Fetch attached files
            $attachedFiles = $employeeAnswerFilesTable
                ->find('active')
                ->select([
                    'answer_id',
                    'file_path',
                    'file_name',
                    's3_bucket',
                    's3_key',
                    'group_id',
                    'field_id',
                ])
                ->where([
                    'EmployeeAnswerFiles.company_id' => $companyId,
                    'EmployeeAnswerFiles.deleted' => 0,
                    'EmployeeAnswerFiles.answer_id' => $employeeDetail->answer_id,
                ])
                ->toArray();

            // Generate presigned URLs for S3 files
            $s3Service = $this->getS3Service();
            
            // Merge file paths/presigned URLs into answers, skipping Password fields
            foreach ($attachedFiles as $file) {
                $groupId = $file->group_id;
                $fieldId = $file->field_id;
                $isPasswordField = false;
                foreach ($passwordFieldIds as $passwordField) {
                    if ($passwordField['group_id'] == $groupId && $passwordField['field_id'] == $fieldId) {
                        $isPasswordField = true;
                        break;
                    }
                }
                if (!$isPasswordField) {
                    if (!isset($answers[$groupId])) {
                        $answers[$groupId] = [];
                    }
                    
                    // If file is in S3, generate presigned URL; otherwise use file_path (backward compatibility)
                    if (!empty($file->s3_bucket) && !empty($file->s3_key)) {
                        $presignedUrl = $s3Service->generatePresignedUrl(
                            $file->s3_bucket,
                            $file->s3_key,
                            3600 // 1 hour expiry
                        );
                        // Store presigned URL for S3 files
                        $answers[$groupId][$fieldId] = $presignedUrl ?: $file->file_path;
                    } else {
                        // Fallback to file_path for backward compatibility (local files)
                        $answers[$groupId][$fieldId] = $file->file_path;
                    }
                }
            }

            // Fetch reporting employee details
            $reportsTo = null;
            if ($employeeDetail->report_to_employee_unique_id) {
                $reportingEmployee = $employeeTemplateAnswersTable
                    ->find()
                    ->select(['answers'])
                    ->where([
                        'company_id' => $companyId,
                        'deleted' => 0,
                        'employee_unique_id' => $employeeDetail->report_to_employee_unique_id,
                    ])
                    ->first();
                if ($reportingEmployee) {
                    $reportingAnswers = $reportingEmployee->answers;
                    $reportsTo = [
                        'employee_unique_id' => $employeeDetail->report_to_employee_unique_id,
                        'first_name' => null,
                        'last_name' => null,
                    ];
                    foreach ($structure as $group) {
                        $groupLabel = $group['customize_group_label'] ?? $group['label'] ?? 'Unnamed Group';
                        if ($groupLabel === 'Personal Information') {
                            foreach ($group['fields'] as $field) {
                                $fieldLabel = $field['customize_field_label'] ?? $field['label'] ?? 'Unknown Field';
                                if ($fieldLabel === 'First Name') {
                                    $reportsTo['first_name'] = $reportingAnswers[$group['id']][$field['id']] ?? null;
                                }
                                if ($fieldLabel === 'Last Name') {
                                    $reportsTo['last_name'] = $reportingAnswers[$group['id']][$field['id']] ?? null;
                                }
                            }
                        }
                    }
                }
            }

            // Extract profile image
            $profileImage = null;
            foreach ($structure as $group) {
                foreach ($group['fields'] as $field) {
                    $fieldLabel = $field['customize_field_label'] ?? $field['label'] ?? 'Unknown Field';
                    if ($fieldLabel === 'Upload Employee Image') {
                        $profileImage = $answers[$group['id']][$field['id']] ?? null;
                        break 2;
                    }
                }
            }

            $employeeID = null;
            foreach ($structure as $group) {
                foreach ($group['fields'] as $field) {
                    $fieldLabel = $field['customize_field_label'] ?? $field['label'] ?? 'Unknown Field';
                    if ($fieldLabel === 'Upload ID Proofs') {
                        $employeeID = $answers[$group['id']][$field['id']] ?? null;
                        break 2;
                    }
                }
            }

            $employmentContract = null;
            foreach ($structure as $group) {
                foreach ($group['fields'] as $field) {
                    $fieldLabel = $field['customize_field_label'] ?? $field['label'] ?? 'Unknown Field';
                    if ($fieldLabel === 'Upload Employment Contract') {
                        $employmentContract = $answers[$group['id']][$field['id']] ?? null;
                        break 2;
                    }
                }
            }

            $resume = null;
            foreach ($structure as $group) {
                foreach ($group['fields'] as $field) {
                    $fieldLabel = $field['customize_field_label'] ?? $field['label'] ?? 'Unknown Field';
                    if ($fieldLabel === 'Upload Resume') {
                        $resume = $answers[$group['id']][$field['id']] ?? null;
                        break 2;
                    }
                }
            }

            // Prepare response data
            $employeeData = [
                'employee_unique_id' => $employeeDetail->employee_unique_id,
                'structure' => $structure,
                'answers' => $answers,
                'reports_to' => $reportsTo,
                'profile_image' => $profileImage,
                'job_role' => $jobRoleUniqueId && isset($jobRoleLookup[$jobRoleUniqueId]) ? $jobRoleLookup[$jobRoleUniqueId] : null,
                'employee_id' => $employeeID,
                'employment_contract' => $employmentContract,
                'resume' => $resume,
            ];

            return $this->response
                ->withStatus(200)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'data' => $employeeData,
                    'message' => 'Employee data retrieved successfully',
                ]));
        } catch (\Throwable $e) {
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error fetching employee data',
                    'error' => $e->getMessage(), // Remove in production
                ]));
        }
    }

    public function updateEmployee()
    {
        Configure::write('debug', true);
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

        // Require admin access for updating employees
        $adminCheck = $this->requireAdmin();
        if ($adminCheck !== null) {
            return $adminCheck;
        }

        $companyId = $this->getCompanyId($authResult);
        $logged_username = $this->getUsername($authResult);
        $data = $this->request->getData();
        $employeeUniqueId = $data['employee_unique_id'] ?? null;
        
        // Debug: Log the received data
        Log::debug("🔍 DEBUG: updateEmployee - Received data:", [
            'company_id' => $companyId,
            'logged_username' => $logged_username,
            'employee_unique_id' => $employeeUniqueId,
            'data' => $data
        ]);
        if (empty($employeeUniqueId)) {
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Employee Unique ID is required.',
                ]));
        }

        try {
            $EmployeeTemplateAnswersTable = $this->getTable('EmployeeTemplateAnswers', $companyId);

            // Start transaction
            $connection = $EmployeeTemplateAnswersTable->getConnection();
            $connection->begin();

            // Validate required fields
            if (empty($data['template_id'])) {
                throw new Exception('Template ID is required.');
            }
            if (empty($data['answers'])) {
                throw new Exception('Answers are required.');
            }

            // Parse and validate answers
            $answers = $this->parseAnswers($data['answers']);
            $template = $this->validateTemplate($companyId, $data['template_id']);
            $jobRoles = $this->getValidJobRoles($companyId);

            $answerData = [];
            $reportToEmployeeUniqueId = null;
            $employeeId = null;
            $username = null;
            $userData = [];

            // Process answers and extract employee_id, username, and userData
            list($answerData, $reportToEmployeeUniqueId, $employeeId, $username, $userData) = $this->processAnswers(
                $answers,
                $template,
                $jobRoles,
                $companyId,
                $employeeUniqueId,
                true // isUpdate = true
            );
            
            Log::debug('🔍 DEBUG: updateEmployee - Parsed vs processed answers', [
                'parsed_answers' => $answers,
                'processed_answer_data' => $answerData,
                'answers_type' => gettype($answers),
                'answer_data_type' => gettype($answerData)
            ]);

            // Check if employee_id or username already exists (exclude current employee)
            $this->checkExistingEmployeeIdAndUsername($companyId, $employeeId, $username, $employeeUniqueId);

            // Check if employee answers exist
            $existingAnswer = $EmployeeTemplateAnswersTable
                ->find()
                ->where([
                    'company_id' => $companyId,
                    'employee_unique_id' => $employeeUniqueId,
                    'template_id' => $data['template_id'],
                    'deleted' => 0,
                ])
                ->first();

            if (!$existingAnswer) {
                throw new Exception('Employee answers not found for the given employee and template.');
            }

            // Store old answers BEFORE any modifications for audit logging
            if (is_array($existingAnswer->answers)) {
                $oldAnswers = $existingAnswer->answers;
            } elseif (is_string($existingAnswer->answers) && !empty($existingAnswer->answers)) {
                $oldAnswers = json_decode($existingAnswer->answers, true) ?? [];
            } else {
                $oldAnswers = [];
            }
            
            Log::debug('🔍 DEBUG: updateEmployee - Old answers from database (BEFORE update)', [
                'existing_answers_raw' => $existingAnswer->answers,
                'existing_answers_type' => gettype($existingAnswer->answers),
                'old_answers_decoded' => $oldAnswers,
                'old_answers_type' => gettype($oldAnswers),
                'old_answers_count' => count($oldAnswers)
            ]);
            
            // Console output for debugging (using error_log to avoid breaking JSON response)
            error_log("\n🔍 OLD ANSWERS FROM DATABASE:");
            error_log("Type: " . gettype($oldAnswers));
            error_log("Count: " . count($oldAnswers));
            error_log("Keys: " . implode(', ', array_keys($oldAnswers)));
            error_log("Data: " . json_encode($oldAnswers, JSON_PRETTY_PRINT));

            // Update existing answers
            $existingAnswer->answers = array_replace_recursive($oldAnswers, $answerData);
            $existingAnswer->employee_id = $employeeId;
            $existingAnswer->username = $username;
            $existingAnswer->report_to_employee_unique_id = $reportToEmployeeUniqueId;
            $existingAnswer->modified = date('Y-m-d H:i:s');
            $existingAnswer->created_by = $logged_username;
            if (!$EmployeeTemplateAnswersTable->save($existingAnswer)) {
                throw new Exception('Failed to update employee answers.');
            }

            // Update user in users table
            $userEntity = $this->updateUser($companyId, $username, $userData);

            // Commit transaction
            $connection->commit();

            // Log audit action
            $userData = AuditHelper::extractUserData($authResult);
            
            // Override company_id and username with the correct values from controller
            $authData = $authResult->getData();
            $username = null;
            if ($authData instanceof \ArrayObject || is_array($authData)) {
                $username = $authData['username'] ?? $authData['sub'] ?? null;
            } elseif (is_object($authData)) {
                $username = $authData->username ?? $authData->sub ?? null;
            }
            
            $userData['company_id'] = (string)$companyId;
            $userData['username'] = $username ?? $userData['username'] ?? 'system';
            $userData['user_id'] = $authData->id ?? $authData['id'] ?? $authData->sub ?? $authData['sub'] ?? $userData['user_id'] ?? 0;
            
            // If we now have a user_id but full_name wasn't fetched, fetch it now
            if (!empty($userData['user_id']) && (empty($userData['full_name']) || $userData['full_name'] === 'Unknown')) {
                try {
                    $usersTable = TableRegistry::getTableLocator()->get('Users', [
                        'connection' => ConnectionManager::get('default')
                    ]);
                    
                    $user = $usersTable->find()
                        ->select(['first_name', 'last_name'])
                        ->where(['id' => $userData['user_id']])
                        ->first();
                    
                    if ($user) {
                        $firstName = $user->first_name ?? '';
                        $lastName = $user->last_name ?? '';
                        $fullName = trim($firstName . ' ' . $lastName);
                        if (!empty($fullName)) {
                            $userData['full_name'] = $fullName;
                            $userData['employee_name'] = $fullName;
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Error fetching user full name in controller: ' . $e->getMessage());
                }
            }
            
            // Ensure full_name is preserved (don't overwrite it if it was already fetched)
            if (empty($userData['full_name']) && !empty($userData['employee_name'])) {
                $userData['full_name'] = $userData['employee_name'];
            }
            
            // Handle both JSON string and array formats
            $employeeAnswers = $answerData;
            if (is_string($employeeAnswers)) {
                $employeeAnswers = json_decode($employeeAnswers, true) ?? [];
            }
            $employeeName = AuditHelper::extractEmployeeName($employeeAnswers);
            
            // Fallback to username if name extraction fails
            if (empty($employeeName) || $employeeName === 'Unnamed Employee') {
                $employeeName = $username ?? $employeeUniqueId ?? 'Unknown Employee';
            }
            
            Log::debug('🔍 DEBUG: updateEmployee - Audit logging data', [
                'employee_unique_id' => $employeeUniqueId,
                'employee_name' => $employeeName,
                'user_data' => $userData,
                'answer_data' => $answerData,
                'old_answers' => $oldAnswers
            ]);
            
            // Flatten both old and new answers for comparison
            // Use raw parsed answers instead of processed answerData to avoid false changes
            $oldFlatAnswers = $this->flattenAnswers($oldAnswers);
            $newFlatAnswers = $this->flattenAnswers($answers);
            
            Log::debug('🔍 DEBUG: updateEmployee - Flattened answers for comparison', [
                'old_flat_answers' => $oldFlatAnswers,
                'old_flat_answers_count' => count($oldFlatAnswers),
                'old_flat_answers_keys' => array_keys($oldFlatAnswers),
                'new_flat_answers' => $newFlatAnswers,
                'new_flat_answers_count' => count($newFlatAnswers),
                'new_flat_answers_keys' => array_keys($newFlatAnswers)
            ]);
            
            // Console output for debugging (using error_log to avoid breaking JSON response)
            error_log("🔍 FLATTENED ANSWERS COMPARISON:");
            error_log("OLD FLAT ANSWERS:");
            error_log("Count: " . count($oldFlatAnswers));
            error_log("Keys: " . implode(', ', array_keys($oldFlatAnswers)));
            error_log("Data: " . json_encode($oldFlatAnswers, JSON_PRETTY_PRINT));
            
            error_log("NEW FLAT ANSWERS:");
            error_log("Count: " . count($newFlatAnswers));
            error_log("Keys: " . implode(', ', array_keys($newFlatAnswers)));
            error_log("Data: " . json_encode($newFlatAnswers, JSON_PRETTY_PRINT));
            
            // Debug specific fields that are showing false changes
            $problemFields = [38, 39, 43];
            error_log("🔍 PROBLEM FIELDS COMPARISON:");
            foreach ($problemFields as $fieldId) {
                $oldValue = $oldFlatAnswers[$fieldId] ?? 'NOT_FOUND';
                $newValue = $newFlatAnswers[$fieldId] ?? 'NOT_FOUND';
                error_log("Field {$fieldId}:");
                error_log("  Old Value: " . json_encode($oldValue) . " (Type: " . gettype($oldValue) . ")");
                error_log("  New Value: " . json_encode($newValue) . " (Type: " . gettype($newValue) . ")");
                error_log("  Equal (===): " . ($oldValue === $newValue ? 'YES' : 'NO'));
                error_log("  Equal (==): " . ($oldValue == $newValue ? 'YES' : 'NO'));
                
                Log::debug("🔍 DEBUG: updateEmployee - Field {$fieldId} comparison", [
                    'field_id' => $fieldId,
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                    'values_equal' => $oldValue === $newValue,
                    'old_type' => gettype($oldValue),
                    'new_type' => gettype($newValue)
                ]);
            }
            
            // Create field mapping based on template structure
            $fieldMapping = $this->getFieldMappingFromTemplate($companyId, $data['template_id'], $newFlatAnswers);
            
            Log::debug('🔍 DEBUG: updateEmployee - Field mapping', [
                'field_mapping' => $fieldMapping,
                'field_mapping_count' => count($fieldMapping),
                'flat_answer_keys' => array_keys($newFlatAnswers)
            ]);
            
            // Debug field mapping for problem fields
            foreach ($problemFields as $fieldId) {
                $fieldLabel = $fieldMapping[$fieldId] ?? 'NOT_MAPPED';
                Log::debug("🔍 DEBUG: updateEmployee - Field {$fieldId} mapping", [
                    'field_id' => $fieldId,
                    'field_label' => $fieldLabel
                ]);
            }
            
            $fieldChanges = AuditHelper::generateFieldChanges(
                $oldFlatAnswers,
                $newFlatAnswers,
                $fieldMapping
            );
            
            Log::debug('🔍 DEBUG: updateEmployee - Field changes', [
                'field_changes' => $fieldChanges,
                'field_changes_count' => count($fieldChanges),
                'old_answers' => $oldAnswers
            ]);
            
            // Debug specific field changes for problem fields
            foreach ($problemFields as $fieldId) {
                $fieldLabel = $fieldMapping[$fieldId] ?? 'NOT_MAPPED';
                $hasChange = isset($fieldChanges[$fieldId]);
                Log::debug("🔍 DEBUG: updateEmployee - Field {$fieldId} change detection", [
                    'field_id' => $fieldId,
                    'field_label' => $fieldLabel,
                    'has_change' => $hasChange,
                    'change_data' => $fieldChanges[$fieldId] ?? 'NO_CHANGE'
                ]);
            }
            
            // Initialize debug info
            $GLOBALS['audit_debug'] = [];
            $GLOBALS['audit_debug']['helper_called'] = true;
            $GLOBALS['audit_debug']['action'] = 'UPDATE';
            $GLOBALS['audit_debug']['employee_unique_id'] = $employeeUniqueId;
            $GLOBALS['audit_debug']['employee_name'] = $employeeName;
            $GLOBALS['audit_debug']['company_id'] = (string)$companyId;
            $GLOBALS['audit_debug']['timestamp'] = date('Y-m-d H:i:s');
            $GLOBALS['audit_debug']['user_data'] = $userData;
            $GLOBALS['audit_debug']['field_changes_count'] = count($fieldChanges);
            
            // Log audit action with error handling
            try {
            AuditHelper::logEmployeeAction(
                'UPDATE',
                $employeeUniqueId,
                $employeeName,
                $userData,
                $this->request,
                $fieldChanges
            );
            } catch (\Exception $e) {
                Log::error('Error logging employee UPDATE audit: ' . $e->getMessage(), [
                    'employee_unique_id' => $employeeUniqueId,
                    'trace' => $e->getTraceAsString()
                ]);
                // Don't fail the request if audit logging fails
            }

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'message' => 'Employee data updated successfully. Please upload files if needed.',
                    'employee_id' => $employeeUniqueId,
                    'debug' => $GLOBALS['audit_debug'] ?? null,
                    'answer_id' => $existingAnswer->id,
                    'user_id' => $userEntity->id,
                ]));
        } catch (Exception $e) {
            if (isset($connection) && $connection->inTransaction()) {
                $connection->rollback();
            }
            Log::error('UpdateEmployee Error: ' . $e->getMessage(), ['company_id' => $companyId]);
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]));
        }
    }

    private function updateUser($companyId, $username, $userData)
    {
        $UsersTable = $this->getTable('Users');

        // First, try to find the user with the meetingtrakker company ID (for native employees)
        $user = $UsersTable
            ->find()
            ->where([
                'company_id' => $companyId,
                'username' => $username,
                'deleted' => false,
            ])
            ->first();

        // If not found, try to find the user with the orgtrakker company ID (for imported employees)
        if (!$user) {
            try {
                $mappingService = $this->getCompanyMappingService();
                $orgtrakkerCompanyId = $mappingService->getOrgtrakkerCompanyIdFromMeetingtrakker((int)$companyId);
                
                if ($orgtrakkerCompanyId) {
                    $user = $UsersTable
                        ->find()
                        ->where([
                            'company_id' => $orgtrakkerCompanyId,
                            'username' => $username,
                            'deleted' => false,
                        ])
                        ->first();
                }
            } catch (\Exception $e) {
                // Log but continue - will throw error below if user still not found
                Log::debug('Error checking orgtrakker company ID for user lookup: ' . $e->getMessage());
            }
        }

        if (!$user) {
            throw new Exception('User not found for the given employee.');
        }

        // Update user fields
        $user->first_name = $userData['first_name'];
        $user->middle_name = $userData['middle_name'];
        $user->last_name = $userData['last_name'];
        $user->birth_date = $userData['birth_date'];
        $user->birth_place = $userData['birth_place'];
        $user->sex = $userData['sex'];
        $user->civil_status = $userData['civil_status'];
        $user->nationality = $userData['nationality'];
        $user->blood_type = $userData['blood_type'];
        $user->email_address = $userData['email_address'];
        $user->contact_number = $userData['contact_number'];
        $user->username = $username;
        if (!empty($userData['password'])) {
            $user->password = password_hash($userData['password'], PASSWORD_DEFAULT);
        }
        $user->system_user_role = $userData['system_user_role'];
        $user->system_access_enabled = $userData['system_access_enabled'];
        $user->modified = date('Y-m-d H:i:s');

        if (!$UsersTable->save($user)) {
            throw new Exception('Failed to update user data.');
        }

        return $user;
    }

    public function updateUploadFiles()
    {
        Configure::write('debug', true);
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

        $companyId = $this->getCompanyId($authResult);
        $data = $this->request->getData();
        $answerId = $data['answerId'] ?? null;
        $employeeUniqueId = $data['employee_unique_id'] ?? null;
        if (empty($answerId) || empty($employeeUniqueId)) {
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Answer ID and Employee Unique ID are required for file upload.',
                ]));
        }

        try {
            $EmployeeTemplateAnswersTable = $this->getTable('EmployeeTemplateAnswers', $companyId);
            $EmployeeAnswerFilesTable = $this->getTable('EmployeeAnswerFiles', $companyId);

            // Ensure schemas are loaded before any operations
            // This prevents "Cannot describe table. It has 0 columns" errors
            try {
                $EmployeeTemplateAnswersTable->getSchema();
                $EmployeeAnswerFilesTable->getSchema();
            } catch (\Exception $schemaError) {
                Log::error('Schema loading error in uploadFiles: ' . $schemaError->getMessage());
                // Re-get tables to force schema reload
                $EmployeeTemplateAnswersTable = $this->getTable('EmployeeTemplateAnswers', $companyId);
                $EmployeeAnswerFilesTable = $this->getTable('EmployeeAnswerFiles', $companyId);
            }

            // Start transaction
            $connection = $EmployeeTemplateAnswersTable->getConnection();
            $connection->begin();

            // Validate answer_id exists - use direct SQL query to avoid schema issues
            $answerResult = $connection->execute(
                'SELECT id, template_id, answers, employee_unique_id FROM employee_template_answers WHERE id = :id AND company_id = :company_id AND employee_unique_id = :employee_unique_id AND deleted = false',
                [
                    'id' => $answerId,
                    'company_id' => $companyId,
                    'employee_unique_id' => $employeeUniqueId
                ]
            )->fetch('assoc');
            
            if (!$answerResult) {
                throw new Exception('Invalid answer ID or employee unique ID.');
            }
            
            // Create a simple object to hold answer data
            $answer = (object)[
                'id' => $answerResult['id'],
                'template_id' => $answerResult['template_id'],
                'employee_unique_id' => $answerResult['employee_unique_id'],
                'answers' => is_string($answerResult['answers']) ? json_decode($answerResult['answers'], true) : $answerResult['answers']
            ];

            // Get template to validate required file fields
            $template = $this->validateTemplate($companyId, $answer->template_id);
            $templateStructure = is_string($template->structure) ? json_decode($template->structure, true) : $template->structure;
            $requiredFileFields = $this->getRequiredFileFields($templateStructure);

            $files = $this->request->getUploadedFiles();
            $fileMap = [];
            $uploadedFields = [];

            $targetFiles = isset($files['files']) && is_array($files['files']) ? $files['files'] : $files;

            // Get employee ID if available (Employees table may not exist in all Scorecardtrakker setups)
            $employeeId = null;
            try {
                $employeesTable = $this->getTable('Employees', $companyId);
                $employee = $employeesTable->find()
                    ->where([
                        'Employees.employee_unique_id' => $employeeUniqueId,
                        'Employees.company_id' => $companyId,
                        'Employees.deleted' => false
                    ])
                    ->first();
                
                if ($employee) {
                    $employeeId = $employee->id;
                }
            } catch (\Exception $e) {
                // Employees table may not exist, continue without employee_id
                // employee_unique_id is sufficient for S3 folder structure
            }

            $s3Service = $this->getS3Service();

            // Process new or updated files
            foreach ($targetFiles as $key => $file) {
                if (preg_match('/^(\d+)_([0-9_]+)$/', $key, $matches)) {
                    $groupId = $matches[1];
                    $fieldId = $matches[2];
                    $this->validateFile($file, "File for {$groupId}_{$fieldId}");

                    $fileName = $file->getClientFilename();

                    // Check if file already exists for this answer_id, group_id, and field_id
                    // Use direct SQL to avoid schema description issues
                    // Note: deleted is a boolean in PostgreSQL, so use false instead of 0
                    $existingFileResult = $connection->execute(
                        'SELECT id, s3_bucket, s3_key FROM employee_answer_files WHERE answer_id = :answer_id AND employee_unique_id = :employee_unique_id AND group_id = :group_id AND field_id = :field_id AND company_id = :company_id AND deleted = false',
                        [
                            'answer_id' => $answerId,
                            'employee_unique_id' => $employeeUniqueId,
                            'group_id' => $groupId,
                            'field_id' => $fieldId,
                            'company_id' => $companyId
                        ]
                    )->fetch('assoc');
                    
                    $existingFile = $existingFileResult ? (object)$existingFileResult : null;

                    // Generate unique filename using the same convention as Skiltrakker
                    $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                    $baseFileName = pathinfo($fileName, PATHINFO_FILENAME);
                    $microtime = microtime(true);
                    $randomSuffix = mt_rand(1000, 9999);
                    $uniqueFileName = $baseFileName . '_' . number_format($microtime, 4, '', '') . '_' . $randomSuffix . '.' . $fileExtension;

                    // Read file content from uploaded file
                    $fileStream = $file->getStream();
                    $fileContent = $fileStream->getContents();
                    
                    if ($fileContent === false) {
                        throw new Exception("Failed to read file content for {$fieldId}.");
                    }

                    // Upload to S3 with proper folder structure: meetingtrakker/employees/{companyId}/{employeeUniqueId}/{fieldId}/{filename}
                    $s3Result = $s3Service->uploadFile(
                        $fileContent,
                        $uniqueFileName,
                        $companyId,
                        'employees',
                        null, // companyName - not needed
                        null, // employeeName - not needed
                        null, // No intervention unique ID for employee files
                        null, // No competency name for employee files
                        null, // No level name for employee files
                        $employeeUniqueId, // employeeUniqueId - required for folder structure
                        $fieldId // fieldId - required for folder structure
                    );

                    if (!$s3Result['success']) {
                        throw new Exception("Failed to upload file to S3 for {$fieldId}: " . ($s3Result['error'] ?? 'Unknown error'));
                    }

                    if ($existingFile) {
                        // Delete old file from S3 if it exists
                        if (!empty($existingFile->s3_bucket) && !empty($existingFile->s3_key)) {
                            $s3Service->deleteFile($existingFile->s3_bucket, $existingFile->s3_key);
                        }
                        // Update existing file record using direct SQL
                        // Note: deleted is a boolean in PostgreSQL, so use false instead of 0
                        $updateResult = $connection->execute(
                            'UPDATE employee_answer_files SET 
                                file_name = :file_name, 
                                file_path = :file_path, 
                                file_type = :file_type, 
                                file_size = :file_size, 
                                s3_bucket = :s3_bucket, 
                                s3_key = :s3_key, 
                                employee_unique_id = :employee_unique_id, 
                                employee_id = :employee_id, 
                                modified = NOW() 
                            WHERE id = :id',
                            [
                                'file_name' => $fileName,
                                'file_path' => $uniqueFileName,
                                'file_type' => $file->getClientMediaType(),
                                'file_size' => $file->getSize(),
                                's3_bucket' => $s3Result['bucket'],
                                's3_key' => $s3Result['key'],
                                'employee_unique_id' => $employeeUniqueId,
                                'employee_id' => $employeeId,
                                'id' => $existingFile->id
                            ]
                        );
                        
                        if ($updateResult->rowCount() === 0) {
                            // Clean up S3 file if database save failed
                            $s3Service->deleteFile($s3Result['bucket'], $s3Result['key']);
                            throw new Exception("Failed to update file metadata for {$fieldId}.");
                        }
                    } else {
                        // Create new file record using direct SQL
                        // Note: deleted is a boolean in PostgreSQL, so use false instead of 0
                        $insertResult = $connection->execute(
                            'INSERT INTO employee_answer_files (answer_id, employee_id, employee_unique_id, file_name, file_path, file_type, file_size, group_id, field_id, company_id, s3_bucket, s3_key, deleted, created, modified) 
                             VALUES (:answer_id, :employee_id, :employee_unique_id, :file_name, :file_path, :file_type, :file_size, :group_id, :field_id, :company_id, :s3_bucket, :s3_key, false, NOW(), NOW())',
                            [
                                'answer_id' => $answerId,
                                'employee_id' => $employeeId,
                                'employee_unique_id' => $employeeUniqueId,
                                'file_name' => $fileName,
                                'file_path' => $uniqueFileName,
                                'file_type' => $file->getClientMediaType(),
                                'file_size' => $file->getSize(),
                                'group_id' => $groupId,
                                'field_id' => $fieldId,
                                'company_id' => $companyId,
                                's3_bucket' => $s3Result['bucket'],
                                's3_key' => $s3Result['key']
                            ]
                        );
                        
                        if ($insertResult->rowCount() === 0) {
                            // Clean up S3 file if database save failed
                            $s3Service->deleteFile($s3Result['bucket'], $s3Result['key']);
                            throw new Exception("Failed to save file metadata for {$fieldId}.");
                        }
                    }

                    if (!isset($fileMap[$groupId])) {
                        $fileMap[$groupId] = [];
                    }
                    // Store unique filename in fileMap (not full path)
                    $fileMap[$groupId][$fieldId] = $uniqueFileName;
                    $uploadedFields[] = "{$groupId}_{$fieldId}";
                }
            }

            // Validate required file fields
            foreach ($requiredFileFields as $requiredField) {
                $fieldKey = "{$requiredField['group_id']}_{$requiredField['field_id']}";
                $fieldExists = false;
                foreach ($answer->answers as $groupId => $fields) {
                    if (isset($fields[$requiredField['field_id']]) && !empty($fields[$requiredField['field_id']])) {
                        $fieldExists = true;
                        break;
                    }
                }
                if (!$fieldExists && !in_array($fieldKey, $uploadedFields)) {
                    throw new Exception("Required file field {$requiredField['label']} is missing.");
                }
            }

            // Update answers with new file identifiers (unique filenames)
            // Ensure answers is an array (handle JSONB/JSON type)
            $answerData = is_array($answer->answers) ? $answer->answers : (is_string($answer->answers) ? json_decode($answer->answers, true) : []);
            
            if (!is_array($answerData)) {
                $answerData = [];
            }
            
            foreach ($fileMap as $groupId => $fields) {
                foreach ($fields as $fieldId => $uniqueFileName) {
                    if (!isset($answerData[$groupId])) {
                        $answerData[$groupId] = [];
                    }
                    // Store unique filename as identifier (frontend will use API endpoints or presigned URLs)
                    $answerData[$groupId][$fieldId] = $uniqueFileName;
                }
            }
            
            // Use direct SQL update to avoid schema description issues
            // This bypasses CakePHP's entity save which requires schema description
            // Note: deleted is a boolean in PostgreSQL, so use false instead of 0
            $answersJson = json_encode($answerData, JSON_UNESCAPED_UNICODE);
            
            $updateResult = $connection->execute(
                'UPDATE employee_template_answers SET answers = :answers::jsonb, modified = NOW() WHERE id = :id AND company_id = :company_id AND employee_unique_id = :employee_unique_id AND deleted = false',
                [
                    'answers' => $answersJson,
                    'id' => $answerId,
                    'company_id' => $companyId,
                    'employee_unique_id' => $employeeUniqueId
                ]
            );
            
            if ($updateResult->rowCount() === 0) {
                throw new Exception('Failed to update employee answers with file paths. No rows updated.');
            }

            // Commit transaction
            $connection->commit();

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'message' => 'Files updated successfully.',
                    'files' => $fileMap,
                    'employeeUniqueId' => $employeeUniqueId,
                    'answerId' => $answerId,
                ]));
        } catch (Exception $e) {
            if (isset($connection) && $connection->inTransaction()) {
                $connection->rollback();
            }
            Log::error('UpdateUploadFiles Error: ' . $e->getMessage(), ['company_id' => $companyId]);
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errorDetails' => [
                        'field' => isset($fieldId) ? $fieldId : null,
                        'group' => isset($groupId) ? $groupId : null,
                    ],
                ]));
        }
    }

    public function getEmployee()
    {
        // Only disable debug if not in CLI (test) mode
        if (php_sapi_name() !== 'cli') {
            Configure::write('debug', 0);
        }
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

        $companyId = $this->getCompanyId($authResult);
        $data = $this->request->getData();
        $employeeUniqueId = $data['employee_unique_id'] ?? null;

        // Validate input
        if (empty($employeeUniqueId)) {
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Missing employee unique ID',
                ]));
        }

        try {
            // Get tenant-specific tables
            $employeeTemplatesTable = $this->getTable('EmployeeTemplates', $companyId);
            $employeeTemplateAnswersTable = $this->getTable('EmployeeTemplateAnswers', $companyId);

            // Fetch employee details
            $employeeDetail = $employeeTemplateAnswersTable
                ->find()
                ->select([
                    'employee_unique_id' => 'EmployeeTemplateAnswers.employee_unique_id',
                    'template_id' => 'EmployeeTemplateAnswers.template_id',
                    'answer_id' => 'EmployeeTemplateAnswers.id',
                    'answers' => 'EmployeeTemplateAnswers.answers',
                ])
                ->where([
                    'EmployeeTemplateAnswers.company_id' => $companyId,
                    'EmployeeTemplateAnswers.deleted' => 0,
                    'EmployeeTemplateAnswers.employee_unique_id' => $employeeUniqueId,
                ])
                ->first();

            // Fetch template separately to ensure structure is properly decoded
            $template = null;
            if ($employeeDetail && $employeeDetail->template_id) {
                $template = $employeeTemplatesTable
                    ->find()
                    ->where([
                        'EmployeeTemplates.company_id' => $companyId,
                        'EmployeeTemplates.id' => $employeeDetail->template_id,
                        'EmployeeTemplates.deleted' => 0,
                    ])
                    ->first();
            }

            if (!$employeeDetail) {
                return $this->response
                    ->withStatus(404)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Employee not found for the provided unique ID',
                    ]));
            }

            if (!$template) {
                return $this->response
                    ->withStatus(404)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Employee template not found. Please ensure the employee has an associated template.',
                    ]));
            }

            // Parse structure (JSON in database)
            // The structure should be automatically decoded by CakePHP if configured as JSON type
            $structure = null;
            if (is_string($template->structure)) {
                $structure = json_decode($template->structure, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Invalid structure JSON format: ' . json_last_error_msg());
                }
            } elseif (is_array($template->structure)) {
                $structure = $template->structure;
            } else {
                throw new \Exception('Structure is not a valid array or JSON string. Got type: ' . gettype($template->structure));
            }
            
            if (!is_array($structure)) {
                throw new \Exception('Structure must be an array after parsing. Got type: ' . gettype($structure));
            }

            // Parse answers (array from JSONB or JSON string)
            if (is_array($employeeDetail->answers)) {
                $answers = $employeeDetail->answers;
            } elseif (is_string($employeeDetail->answers)) {
                $answers = json_decode($employeeDetail->answers, true) ?? [];
            } else {
                $answers = [];
            }

            $formattedData = [
                'employee_unique_id' => $employeeDetail->employee_unique_id,
                'template_id' => $employeeDetail->template_id,
                'answer_id' => $employeeDetail->answer_id,
            ];

            // Map answers using group and field IDs
            foreach ($structure as $group) {
                if (!is_array($group) || !isset($group['id'])) {
                    continue; // Skip invalid groups
                }
                
                $groupId = $group['id'];
                $groupLabel = $group['customize_group_label'] ?? $group['label'] ?? '';
                
                // Process main group fields
                if (isset($group['fields']) && is_array($group['fields']) && isset($answers[$groupId])) {
                    foreach ($group['fields'] as $field) {
                        if (!is_array($field) || !isset($field['id']) || !isset($field['label'])) {
                            continue; // Skip invalid fields
                        }
                        
                        $fieldId = $field['id'];
                        $fieldKey = $field['label'];
                        // Skip the password field
                        if (strtolower($fieldKey) === 'password') {
                            continue;
                        }
                        $value = $answers[$groupId][$fieldId] ?? null;
                        // Normalize key to lowercase for frontend compatibility
                        $normalizedKey = strtolower(preg_replace('/\s+/', '_', $fieldKey));
                        $formattedData[$normalizedKey] = $value;
                    }
                }
                
                // Process subgroups if they exist
                if (!empty($group['subGroups']) && is_array($group['subGroups'])) {
                    foreach ($group['subGroups'] as $index => $subGroup) {
                        if (!is_array($subGroup) || !isset($subGroup['id'])) {
                            continue; // Skip invalid subgroups
                        }
                        
                        $subGroupId = $subGroup['id'];
                        $subGroupLabel = "{$groupLabel}_{$index}";
                        if (isset($answers[$subGroupLabel]) && isset($subGroup['fields']) && is_array($subGroup['fields'])) {
                            foreach ($subGroup['fields'] as $field) {
                                if (!is_array($field) || !isset($field['id']) || !isset($field['label'])) {
                                    continue; // Skip invalid fields
                                }
                                
                                $fieldId = $field['id'];
                                $fieldKey = $field['label'];
                                // Skip the password field
                                if (strtolower($fieldKey) === 'password') {
                                    continue;
                                }
                                $value = $answers[$subGroupLabel][$fieldId] ?? null;
                                // Normalize key to lowercase
                                $normalizedKey = strtolower(preg_replace('/\s+/', '_', $fieldKey));
                                $formattedData[$normalizedKey] = $value;
                            }
                        }
                    }
                }
            }

            // Ensure required fields for MovementHistory
            $formattedData['first_name'] = $formattedData['first_name'] ?? 'N/A';
            $formattedData['last_name'] = $formattedData['last_name'] ?? 'N/A';

            return $this->response
                ->withStatus(200)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'data' => $formattedData,
                    'message' => 'Employee data retrieved successfully',
                ]));
        } catch (\Throwable $e) {
            // Include error message in debug mode or CLI (test) mode for better debugging
            $isDebugMode = Configure::read('debug') || php_sapi_name() === 'cli';
            Log::error('getEmployee error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error fetching employee data',
                    'error' => $isDebugMode ? $e->getMessage() : null,
                ]));
        }
    }

    public function changePassword()
    {
        Configure::write('debug', true);
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

        $companyId = $this->getCompanyId($authResult);
        $logged_username = $this->getUsername($authResult);
        $data = $this->request->getData();

        $userId = $data['userId'] ?? null;
        $username = $data['username'] ?? null;
        $currentPassword = $data['currentPassword'] ?? null;
        $newPassword = $data['newPassword'] ?? null;

        if (empty($userId) || empty($username) || empty($currentPassword) || empty($newPassword)) {
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'User ID, username, current password, and new password are required.',
                ]));
        }

        // Validate new password
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $newPassword)) {
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Password must be at least 8 characters, including uppercase, lowercase, number, and special character.',
                ]));
        }

        try {
            // Get tables
            $EmployeeTemplateAnswersTable = $this->getTable('EmployeeTemplateAnswers', $companyId);
            $UsersTable = $this->getTable('Users');

            // Start transaction
            $connection = $EmployeeTemplateAnswersTable->getConnection();
            $connection->begin();

            // Verify current password in Users table
            $user = $UsersTable
                ->find()
                ->where([
                    'company_id' => $companyId,
                    'id' => $userId,
                    'username' => $username,
                    'deleted' => false,
                ])
                ->first();

            if (!$user) {
                throw new Exception('User not found.');
            }

            if (!password_verify($currentPassword, $user->password)) {
                throw new Exception('Current password is incorrect.');
            }

            // Find employee answers to update password field
            $employeeAnswer = $EmployeeTemplateAnswersTable
                ->find()
                ->where([
                    'company_id' => $companyId,
                    'username' => $username,
                    'deleted' => 0,
                ])
                ->first();

            if (!$employeeAnswer) {
                throw new Exception('Employee answers not found.');
            }

            // Get template structure to find password field
            $EmployeeTemplatesTable = $this->getTable('EmployeeTemplates', $companyId);
            $template = $EmployeeTemplatesTable
                ->find()
                ->where([
                    'company_id' => $companyId,
                    'id' => $employeeAnswer->template_id,
                    'deleted' => 0,
                ])
                ->first();

            if (!$template) {
                throw new Exception('Employee template not found.');
            }

            // Parse template structure
            $structure = is_string($template->structure) 
                ? json_decode($template->structure, true) 
                : $template->structure;

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid template structure format.');
            }

            // Find password field in structure
            $passwordFieldFound = false;
            $answers = $employeeAnswer->answers;

            foreach ($structure as $group) {
                foreach ($group['fields'] as $field) {
                    $fieldLabel = $field['customize_field_label'] ?? $field['label'] ?? '';
                    if (strtolower($fieldLabel) === 'password') {
                        $fieldId = $field['id'];
                        $groupId = $group['id'];
                        
                        // Update password in answers
                        if (!isset($answers[$groupId])) {
                            $answers[$groupId] = [];
                        }
                        $answers[$groupId][$fieldId] = password_hash($newPassword, PASSWORD_DEFAULT);
                        $passwordFieldFound = true;
                        break 2;
                    }
                }
                
                // Check subgroups if password field not found in main fields
                if (!$passwordFieldFound && !empty($group['subGroups'])) {
                    foreach ($group['subGroups'] as $subGroupIndex => $subGroup) {
                        foreach ($subGroup['fields'] as $field) {
                            $fieldLabel = $field['customize_field_label'] ?? $field['label'] ?? '';
                            if (strtolower($fieldLabel) === 'password') {
                                $fieldId = $field['id'];
                                $groupId = $group['id'];
                                $subFieldId = $fieldId . '_' . $subGroupIndex;
                                
                                // Update password in answers
                                if (!isset($answers[$groupId])) {
                                    $answers[$groupId] = [];
                                }
                                $answers[$groupId][$subFieldId] = password_hash($newPassword, PASSWORD_DEFAULT);
                                $passwordFieldFound = true;
                                break 3;
                            }
                        }
                    }
                }
            }

            if (!$passwordFieldFound) {
                throw new Exception('Password field not found in employee template.');
            }

            // Update employee answers
            $employeeAnswer->answers = $answers;
            $employeeAnswer->modified = date('Y-m-d H:i:s');
            $employeeAnswer->created_by = $logged_username;
            
            if (!$EmployeeTemplateAnswersTable->save($employeeAnswer)) {
                throw new Exception('Failed to update employee answers with new password.');
            }

            // Update user password in Users table
            $user->password = password_hash($newPassword, PASSWORD_DEFAULT);
            $user->modified = date('Y-m-d H:i:s');
            
            if (!$UsersTable->save($user)) {
                throw new Exception('Failed to update user password.');
            }

            // Commit transaction
            $connection->commit();

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'message' => 'Password changed successfully.',
                    'user_id' => $userId,
                    'username' => $username,
                ]));

        } catch (Exception $e) {
            if (isset($connection) && $connection->inTransaction()) {
                $connection->rollback();
            }
            Log::error('ChangePassword Error: ' . $e->getMessage(), ['company_id' => $companyId, 'username' => $username]);
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]));
        }
    }

    /**
     * Get reporting relationships for job roles
     */
    public function getReportingRelationships()
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

        $companyId = $this->getCompanyId($authResult);

        try {
            // Get job roles with their reporting relationships
            $JobRoleTemplatesTable = $this->getTable('JobRoleTemplates', $companyId);
            
            $jobRoles = $JobRoleTemplatesTable
                ->find()
                ->select(['structure'])
                ->where([
                    'company_id' => $companyId,
                    'deleted' => 0,
                ])
                ->first();

            if (!$jobRoles) {
                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'No job role template found.',
                    ]));
            }

            $structure = is_string($jobRoles->structure) 
                ? json_decode($jobRoles->structure, true) 
                : $jobRoles->structure;

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid job role template structure format.');
            }

            $reportingRelationships = [];

            // Extract reporting relationships from job role template structure
            foreach ($structure as $group) {
                foreach ($group['fields'] as $field) {
                    if ($field['label'] === 'Reports To' || $field['customize_field_label'] === 'Reports To') {
                        // This field contains the reporting relationship
                        if (!empty($field['options'])) {
                            foreach ($field['options'] as $option) {
                                if (isset($option['value']) && isset($option['reports_to'])) {
                                    $reportingRelationships[$option['value']] = $option['reports_to'];
                                }
                            }
                        }
                    }
                }
            }

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'data' => $reportingRelationships,
                ]));

        } catch (Exception $e) {
            Log::error('GetReportingRelationships Error: ' . $e->getMessage(), ['company_id' => $companyId]);
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Failed to fetch reporting relationships: ' . $e->getMessage(),
                ]));
        }
    }

    /**
     * Get field mapping from template structure
     *
     * @param string $companyId
     * @param int $templateId
     * @param array $flatAnswers
     * @return array
     */
    private function getFieldMappingFromTemplate($companyId, $templateId, $flatAnswers)
    {
        Log::debug('🔍 DEBUG: getFieldMappingFromTemplate - Input parameters', [
            'company_id' => $companyId,
            'template_id' => $templateId,
            'flat_answers' => $flatAnswers,
            'flat_answers_count' => count($flatAnswers)
        ]);

        try {
            // Get template structure
            $EmployeeTemplatesTable = $this->getTable('EmployeeTemplates', $companyId);
            $template = $EmployeeTemplatesTable
                ->find()
                ->where([
                    'company_id' => $companyId,
                    'id' => $templateId,
                    'deleted' => 0,
                ])
                ->first();

            if (!$template) {
                Log::debug('🔍 DEBUG: getFieldMappingFromTemplate - Template not found', [
                    'template_id' => $templateId,
                    'company_id' => $companyId
                ]);
                return $this->getFallbackFieldMapping($flatAnswers);
            }

            $structure = is_string($template->structure) ? json_decode($template->structure, true) : $template->structure;
            
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($structure)) {
                Log::debug('🔍 DEBUG: getFieldMappingFromTemplate - Invalid structure JSON', [
                    'template_id' => $templateId,
                    'structure' => $template->structure,
                    'json_error' => json_last_error_msg()
                ]);
                return $this->getFallbackFieldMapping($flatAnswers);
            }

            $fieldMapping = [];
            $flatAnswerKeys = array_keys($flatAnswers);

            Log::debug('🔍 DEBUG: getFieldMappingFromTemplate - Template structure analysis', [
                'template_structure' => $structure,
                'flat_answer_keys' => $flatAnswerKeys,
                'flat_answer_keys_count' => count($flatAnswerKeys)
            ]);

            // Extract field labels from template structure
            $allTemplateFields = [];
            foreach ($structure as $group) {
                if (!isset($group['fields']) || !is_array($group['fields'])) {
                    continue;
                }

                foreach ($group['fields'] as $field) {
                    $fieldId = $field['id'] ?? null;
                    $fieldLabel = $field['label'] ?? null;
                    
                    // Store all template fields for debugging
                    if ($fieldId && $fieldLabel) {
                        $allTemplateFields[(string)$fieldId] = $fieldLabel;
                    }

                    // Convert field ID to string for comparison
                    $fieldIdStr = (string)$fieldId;
                    
                    if ($fieldId && $fieldLabel && in_array($fieldIdStr, $flatAnswerKeys)) {
                        $fieldMapping[$fieldIdStr] = $fieldLabel;
                        Log::debug('🔍 DEBUG: getFieldMappingFromTemplate - Field mapped', [
                            'field_id' => $fieldIdStr,
                            'field_label' => $fieldLabel
                        ]);
                    }
                }
            }
            
            Log::debug('🔍 DEBUG: getFieldMappingFromTemplate - All template fields', [
                'all_template_fields' => $allTemplateFields,
                'template_fields_count' => count($allTemplateFields)
            ]);
            
            // Console output for debugging
            error_log("🔍 FIELD MAPPING DEBUG:");
            error_log("Flat Answer Keys: " . implode(', ', $flatAnswerKeys));
            error_log("Template Fields: " . json_encode($allTemplateFields));
            error_log("Generated Field Mapping: " . json_encode($fieldMapping));

            // Add fallback for any missing fields
            foreach ($flatAnswerKeys as $fieldId) {
                if (!isset($fieldMapping[$fieldId])) {
                    $fieldMapping[$fieldId] = ucfirst(str_replace('_', ' ', (string)$fieldId));
                }
            }

            Log::debug('🔍 DEBUG: getFieldMappingFromTemplate - Generated mapping', [
                'field_mapping' => $fieldMapping,
                'field_mapping_count' => count($fieldMapping),
                'template_structure' => $structure,
                'flat_answer_keys' => $flatAnswerKeys,
                'flat_answer_keys_count' => count($flatAnswerKeys)
            ]);
            
            // Debug specific problem fields
            $problemFields = [38, 39, 43];
            foreach ($problemFields as $fieldId) {
                $fieldLabel = $fieldMapping[$fieldId] ?? 'NOT_MAPPED';
                Log::debug("🔍 DEBUG: getFieldMappingFromTemplate - Field {$fieldId} mapping", [
                    'field_id' => $fieldId,
                    'field_label' => $fieldLabel,
                    'field_exists_in_flat_keys' => in_array($fieldId, $flatAnswerKeys)
                ]);
            }

            return $fieldMapping;

        } catch (\Exception $e) {
            Log::error('🔍 DEBUG: getFieldMappingFromTemplate - Error', [
                'error' => $e->getMessage(),
                'template_id' => $templateId,
                'company_id' => $companyId
            ]);
            return $this->getFallbackFieldMapping($flatAnswers);
        }
    }

    /**
     * Get fallback field mapping when template is not available
     *
     * @param array $flatAnswers
     * @return array
     */
    private function getFallbackFieldMapping($flatAnswers)
    {
        $fieldMapping = [];
        $flatAnswerKeys = array_keys($flatAnswers);
        
        foreach ($flatAnswerKeys as $index => $fieldId) {
            $fieldMapping[$fieldId] = ucfirst(str_replace('_', ' ', $fieldId));
        }
        
        return $fieldMapping;
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

    /**
     * Helper method to extract username from authentication result
     */
    private function getUsername($authResult)
    {
        $authData = $authResult->getData();
        $username = null;
        $userId = null;
        
        // JWT authenticator with returnPayload => true returns the payload directly
        // The payload should contain: sub, username, company_id, system_user_role
        if (is_object($authData)) {
            // Handle stdClass (JWT decode returns stdClass)
            $username = $authData->username ?? null;
            $userId = $authData->sub ?? $authData->id ?? null;
            
            // Convert to array for easier access
            if (!$username) {
                $authData = (array) $authData;
            }
        }
        
        if (!$username && is_array($authData)) {
            $username = $authData['username'] ?? null;
            $userId = $authData['sub'] ?? $authData['id'] ?? null;
        }
        
        // If still not found, try to get from JWT payload method (fallback)
        if (!$username && method_exists($authResult, 'getPayload')) {
            try {
            $payload = $authResult->getPayload();
                if (isset($payload['username'])) {
                    $username = $payload['username'];
                } elseif (isset($payload['sub'])) {
                    $userId = $payload['sub'];
                }
            } catch (\Exception $e) {
                Log::debug('🔍 DEBUG: Could not get payload from auth result: ' . $e->getMessage());
            }
        }
        
        // If username still not found but we have userId, query Users table
        // Users table is in the workmatica database (default connection)
        // Query by userId only (it's unique), not by company_id, since users may have different company_ids
        if (!$username && $userId) {
            try {
                // Use default connection (workmatica database) for Users table
                $UsersTable = $this->getTable('Users', 'default');
                
                // Query by userId only (unique), not by company_id
                // Users can have different company_ids (original orgtrakker company_id vs mapped meetingtrakker company_id)
                $user = $UsersTable->find()
                    ->where([
                        'id' => $userId,
                        'deleted' => false
                    ])
                    ->first();
                
                if ($user && !empty($user->username)) {
                    $username = $user->username;
                    Log::debug('🔍 DEBUG: Username retrieved from Users table', [
                        'user_id' => $userId,
                        'username' => $username,
                        'user_company_id' => $user->company_id ?? null
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('🔍 DEBUG: Error querying Users table for username: ' . $e->getMessage(), [
                    'user_id' => $userId,
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        
        if (!$username) {
            Log::warning('🔍 DEBUG: Unable to extract username from auth result', [
                'auth_data_type' => gettype($authData),
                'user_id' => $userId
            ]);
            return 'unknown';
        }
        
        return $username;
    }

    /**
     * Get orgtrakker database connection
     *
     * @param string|int|null $companyId Current company ID (meetingtrakker)
     * @return \Cake\Database\Connection
     */
    private function getOrgtrakkerConnection(string|int|null $companyId = null)
    {
        // If companyId is provided, try to get mapped orgtrakker company ID
        if ($companyId !== null) {
            try {
                $mappingService = $this->getCompanyMappingService();
                $orgtrakkerCompanyId = $mappingService->getOrgtrakkerCompanyIdFromMeetingtrakker((int)$companyId);
                
                if ($orgtrakkerCompanyId !== null) {
                    // Use mapped company ID for connection
                    $connectionName = 'orgtrakker_' . $orgtrakkerCompanyId;
                    $databaseName = 'orgtrakker_' . $orgtrakkerCompanyId;
                    
                    // In test environment, use test database
                    if (Configure::read('debug') && php_sapi_name() === 'cli') {
                        // Try the alias first (used by fixtures), then the direct test connection
                        $testConnectionAlias = 'test_orgtrakker_' . $orgtrakkerCompanyId;
                        $directTestConnectionName = 'orgtrakker_' . $orgtrakkerCompanyId . '_test';
                        $databaseName = 'orgtrakker_' . $orgtrakkerCompanyId . '_test';
                        
                        try {
                            return ConnectionManager::get($testConnectionAlias);
                        } catch (\Exception $e) {
                            try {
                                return ConnectionManager::get($directTestConnectionName);
                            } catch (\Exception $e2) {
                                throw new \Exception(
                                    "Test Orgtrakker database connection '{$testConnectionAlias}' or '{$directTestConnectionName}' is not configured. " .
                                    "Error: " . $e->getMessage() . " | " . $e2->getMessage()
                                );
                            }
                        }
                    }
                    
                    try {
                        // Try to get existing connection
                        return ConnectionManager::get($connectionName);
                    } catch (\Exception $e) {
                        // Connection doesn't exist, create it dynamically
                        try {
                            ConnectionManager::setConfig($connectionName, [
                                'className' => Connection::class,
                                'driver' => \Cake\Database\Driver\Postgres::class,
                                'persistent' => false,
                                'host' => 'postgres_workmatica_template',
                                'port' => 5432,
                                'username' => 'workmatica_user',
                                'password' => 'securepassword',
                                'database' => $databaseName,
                                'encoding' => 'utf8',
                                'timezone' => 'UTC',
                                'cacheMetadata' => !(Configure::read('debug') && php_sapi_name() === 'cli'), // Disable caching in test mode
                                'quoteIdentifiers' => false,
                                'log' => false,
                            ]);
                            return ConnectionManager::get($connectionName);
                        } catch (\Exception $createException) {
                            Log::warning('Could not create connection for mapped orgtrakker company ID, falling back to default', [
                                'orgtrakker_company_id' => $orgtrakkerCompanyId,
                                'connection_name' => $connectionName,
                                'database_name' => $databaseName,
                                'error' => $createException->getMessage()
                            ]);
                        }
                    }
                } else {
                    Log::warning('No mapping found for company ID, falling back to default orgtrakker connection', [
                        'company_id' => is_string($companyId) ? $companyId : (string)$companyId
                    ]);
                }
            } catch (\Exception $e) {
                // If mapping service fails, log error and fall back to default
                Log::error('Error accessing company mapping service, falling back to default orgtrakker connection', [
                    'company_id' => is_string($companyId) ? $companyId : (string)$companyId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        
        // Fallback to default orgtrakker connection (backward compatibility)
        // In test environment, use test orgtrakker connection
        if (Configure::read('debug') && php_sapi_name() === 'cli') {
            // Try the alias first (used by fixtures), then the direct test connection
            $testConnectionAlias = 'test_orgtrakker_100000';
            $directTestConnectionName = 'orgtrakker_100000_test';
            try {
                return ConnectionManager::get($testConnectionAlias);
            } catch (\Exception $e) {
                try {
                    return ConnectionManager::get($directTestConnectionName);
                } catch (\Exception $e2) {
                    Log::warning('Test orgtrakker connection not found, trying default', [
                        'error' => $e->getMessage() . " | " . $e2->getMessage()
                    ]);
                }
            }
        }
        return ConnectionManager::get('orgtrakker_100000');
    }

    /**
     * Get orgtrakker company ID for current company
     *
     * @param string|int $companyId Current company ID (scorecardtrakker)
     * @return int Orgtrakker company ID, or 100000 as fallback
     */
    private function getOrgtrakkerCompanyId(string|int $companyId): int
    {
        // Convert to string for consistency
        $companyIdStr = (string)$companyId;
        $mappingService = $this->getCompanyMappingService();
        $orgtrakkerCompanyId = $mappingService->getOrgtrakkerCompanyIdFromMeetingtrakker((int)$companyId);
        
        if ($orgtrakkerCompanyId !== null) {
            return $orgtrakkerCompanyId;
        }
        
        // Fallback to default (backward compatibility)
        Log::warning('No mapping found for company ID, using default orgtrakker company ID 100000', [
            'company_id' => (string)$companyId
        ]);
        return 100000;
    }

    /**
     * Get default employee template for company
     *
     * @param string $companyId
     * @return \App\Model\Entity\EmployeeTemplate
     * @throws Exception
     */
    private function getDefaultEmployeeTemplate($companyId)
    {
        $EmployeeTemplatesTable = $this->getTable('EmployeeTemplates', $companyId);
        $template = $EmployeeTemplatesTable
            ->find()
            ->where([
                'company_id' => $companyId,
                'name' => 'employee',
                'deleted' => false,
            ])
            ->first();

        if (!$template) {
            throw new Exception('Default employee template not found. Please create an employee template first.');
        }
        return $template;
    }

    /**
     * Generate empty answers structure from template
     *
     * @param array $templateStructure
     * @return array
     */
    private function generateEmptyAnswersFromTemplate($templateStructure)
    {
        $emptyAnswers = [];
        
        if (is_string($templateStructure)) {
            $templateStructure = json_decode($templateStructure, true);
        }
        
        if (!is_array($templateStructure)) {
            return [];
        }
        
        foreach ($templateStructure as $group) {
            $groupId = $group['id'] ?? null;
            if (!$groupId) {
                continue;
            }
            
            $emptyAnswers[$groupId] = [];
            
            // Handle regular fields
            if (isset($group['fields']) && is_array($group['fields'])) {
                foreach ($group['fields'] as $field) {
                    $fieldId = $field['id'] ?? null;
                    if ($fieldId) {
                        $emptyAnswers[$groupId][$fieldId] = '';
                    }
                }
            }
            
            // Handle subGroups if they exist
            if (isset($group['subGroups']) && is_array($group['subGroups'])) {
                foreach ($group['subGroups'] as $index => $subGroup) {
                    $subGroupId = $subGroup['id'] ?? null;
                    if (!$subGroupId) {
                        continue;
                    }
                    
                    $groupLabel = $group['label'] ?? $groupId;
                    $subGroupLabel = "{$groupLabel}_{$index}";
                    
                    $emptyAnswers[$subGroupLabel] = [];
                    if (isset($subGroup['fields']) && is_array($subGroup['fields'])) {
                        foreach ($subGroup['fields'] as $field) {
                            $fieldId = $field['id'] ?? null;
                            if ($fieldId) {
                                $emptyAnswers[$subGroupLabel][$fieldId] = '';
                            }
                        }
                    }
                }
            }
        }
        
        return $emptyAnswers;
    }

    /**
     * Check if employee is already imported
     *
     * @param string $companyId
     * @param string $username
     * @param string $employeeUniqueId
     * @return bool
     */
    private function checkEmployeeAlreadyImported($companyId, $username, $employeeUniqueId)
    {
        $EmployeeTemplateAnswersTable = $this->getTable('EmployeeTemplateAnswers', $companyId);
        
        // Check for non-deleted employees
        $existing = $EmployeeTemplateAnswersTable
            ->find()
            ->where([
                'company_id' => $companyId,
                'OR' => [
                    'username' => $username,
                    'employee_unique_id' => $employeeUniqueId,
                ],
                'deleted' => false,
            ])
            ->first();
        
        return $existing !== null;
    }
    
    /**
     * Check if employee exists (including soft-deleted) and return the entity
     *
     * @param string $companyId
     * @param string $username
     * @param string $employeeUniqueId
     * @return \App\Model\Entity\EmployeeTemplateAnswer|null
     */
    private function findEmployeeIncludingDeleted($companyId, $username, $employeeUniqueId)
    {
        $EmployeeTemplateAnswersTable = $this->getTable('EmployeeTemplateAnswers', $companyId);
        
        // Check for any employee (including soft-deleted)
        $existing = $EmployeeTemplateAnswersTable
            ->find()
            ->where([
                'company_id' => $companyId,
                'OR' => [
                    'username' => $username,
                    'employee_unique_id' => $employeeUniqueId,
                ],
            ])
            ->first();
        
        return $existing;
    }

    /**
     * Get employee from orgtrakker database
     *
     * @param string $employeeUniqueId
     * @return array|null
     */
    private function getOrgtrakkerEmployee($employeeUniqueId, ?string $companyId = null)
    {
        try {
            $connection = $this->getOrgtrakkerConnection($companyId);
            $stmt = $connection->execute(
                'SELECT company_id, employee_unique_id, employee_id, username 
                 FROM employee_template_answers 
                 WHERE employee_unique_id = :employee_unique_id AND deleted = false 
                 LIMIT 1',
                ['employee_unique_id' => $employeeUniqueId]
            );
            
            $result = $stmt->fetch('assoc');
            return $result ?: null;
        } catch (\Exception $e) {
            Log::error('Error fetching orgtrakker employee: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get orgtrakker employees available for import
     *
     * @return \Cake\Http\Response
     */
    public function getOrgtrakkerEmployees()
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

        $companyId = $this->getCompanyId($authResult);

        try {
            $orgtrakkerCompanyId = $this->getOrgtrakkerCompanyId($companyId);
            $connection = $this->getOrgtrakkerConnection($companyId);
            
            // Get all non-deleted employees from orgtrakker with answers JSON
            $stmt = $connection->execute(
                'SELECT company_id, employee_unique_id, employee_id, username, answers, template_id
                 FROM employee_template_answers 
                 WHERE company_id = :company_id AND deleted = false 
                 ORDER BY username ASC',
                ['company_id' => $orgtrakkerCompanyId]
            );
            
            $orgtrakkerEmployees = $stmt->fetchAll('assoc');
            
            // Get orgtrakker employee template
            $orgtrakkerTemplateStmt = $connection->execute(
                'SELECT id, structure FROM employee_templates WHERE company_id = :company_id AND name = :name AND deleted = false LIMIT 1',
                ['company_id' => $orgtrakkerCompanyId, 'name' => 'employee']
            );
            $orgtrakkerTemplate = $orgtrakkerTemplateStmt->fetch('assoc');
            $orgtrakkerTemplateStructure = $orgtrakkerTemplate ? json_decode($orgtrakkerTemplate['structure'], true) : [];
            
            // Get scorecardtrakker employee template
            $scorecardtrakkerTemplate = $this->getDefaultEmployeeTemplate($companyId);
            $scorecardtrakkerTemplateStructure = is_string($scorecardtrakkerTemplate->structure) 
                ? json_decode($scorecardtrakkerTemplate->structure, true) 
                : $scorecardtrakkerTemplate->structure;
            
            // Get already imported employees from current company with full answers
            $EmployeeTemplateAnswersTable = $this->getTable('EmployeeTemplateAnswers', $companyId);
            $importedEmployees = $EmployeeTemplateAnswersTable
                ->find()
                ->select(['username', 'employee_unique_id', 'answers'])
                ->where([
                    'company_id' => $companyId,
                    'deleted' => false,
                ])
                ->toArray();
            
            // Create maps of imported employees by unique_id and username
            $importedEmployeesMap = [];
            foreach ($importedEmployees as $imported) {
                $key = $imported->employee_unique_id ?? $imported->username ?? '';
                if (!empty($key)) {
                    $importedEmployeesMap[$key] = $imported;
                }
            }
            
            // Process all employees
            $employeesList = [];
            foreach ($orgtrakkerEmployees as $employee) {
                $username = $employee['username'] ?? '';
                $employeeUniqueId = $employee['employee_unique_id'] ?? '';
                
                // Check if imported
                $importedEmployee = $importedEmployeesMap[$employeeUniqueId] ?? $importedEmployeesMap[$username] ?? null;
                $isImported = $importedEmployee !== null;
                
                // Extract first_name, last_name, and job_role_unique_id from answers
                $orgtrakkerAnswers = json_decode($employee['answers'] ?? '{}', true);
                $firstName = $this->extractFieldValueFromAnswers($orgtrakkerAnswers, $orgtrakkerTemplateStructure, 'First Name');
                $lastName = $this->extractFieldValueFromAnswers($orgtrakkerAnswers, $orgtrakkerTemplateStructure, 'Last Name');
                $jobRoleUniqueId = $this->extractFieldValueFromAnswers($orgtrakkerAnswers, $orgtrakkerTemplateStructure, 'Job Role');
                
                // Get job role name from orgtrakker
                $jobRoleName = '';
                if ($jobRoleUniqueId) {
                    $jobRoleStmt = $connection->execute(
                        'SELECT answers FROM job_role_template_answers WHERE job_role_unique_id = :job_role_unique_id AND deleted = false LIMIT 1',
                        ['job_role_unique_id' => $jobRoleUniqueId]
                    );
                    $jobRole = $jobRoleStmt->fetch('assoc');
                    if ($jobRole) {
                        $jobRoleAnswers = json_decode($jobRole['answers'] ?? '{}', true);
                        $jobRoleTemplateStmt = $connection->execute(
                            'SELECT structure FROM job_role_templates WHERE company_id = :company_id AND deleted = false LIMIT 1',
                            ['company_id' => $orgtrakkerCompanyId]
                        );
                        $jobRoleTemplate = $jobRoleTemplateStmt->fetch('assoc');
                        $jobRoleTemplateStructure = $jobRoleTemplate ? json_decode($jobRoleTemplate['structure'], true) : [];
                        
                        // Try to get job role name from answers
                        $jobRoleName = $this->extractFieldValueFromAnswers($jobRoleAnswers, $jobRoleTemplateStructure, 'Job Role');
                        if (!$jobRoleName) {
                            $jobRoleName = $this->extractFieldValueFromAnswers($jobRoleAnswers, $jobRoleTemplateStructure, 'Official Designation');
                        }
                        if (!$jobRoleName) {
                            $jobRoleName = $this->extractFieldValueFromAnswers($jobRoleAnswers, $jobRoleTemplateStructure, 'Job Title');
                        }
                    }
                }
                
                // Determine status
                $status = 'not_imported';
                if ($isImported) {
                    // Map orgtrakker answers to scorecardtrakker structure
                    $orgtrakkerMapped = $this->mapFieldValuesByLabel($orgtrakkerAnswers, $orgtrakkerTemplateStructure, $scorecardtrakkerTemplateStructure, $employeeUniqueId);
                    
                    // Scorecardtrakker answers are already in the correct structure
                    $scorecardtrakkerAnswers = is_string($importedEmployee->answers) ? json_decode($importedEmployee->answers, true) : $importedEmployee->answers;
                    if (!is_array($scorecardtrakkerAnswers)) {
                        $scorecardtrakkerAnswers = [];
                    }
                    
                    // Compare to detect if update is needed
                    $needsUpdate = $this->compareMappedAnswers($orgtrakkerMapped, $scorecardtrakkerAnswers, $scorecardtrakkerTemplateStructure, $orgtrakkerTemplateStructure);
                    $status = $needsUpdate ? 'needs_update' : 'imported';
                }
                
                $employeesList[] = [
                    'employee_unique_id' => $employeeUniqueId,
                    'employee_id' => $employee['employee_id'] ?? '',
                    'username' => $username,
                    'first_name' => $firstName ?? '',
                    'last_name' => $lastName ?? '',
                    'job_role' => $jobRoleName ?? '',
                    'imported' => $isImported, // Keep for backward compatibility
                    'status' => $status, // New: 'not_imported', 'imported', or 'needs_update'
                    'answers' => $orgtrakkerAnswers, // Include full answers for import
                ];
            }
            
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'data' => $employeesList,
                    'message' => 'Orgtrakker employees retrieved successfully',
                ]));
                
        } catch (\Exception $e) {
            Log::error('Error fetching orgtrakker employees: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error fetching orgtrakker employees: ' . $e->getMessage(),
                ]));
        }
    }

    /**
     * Get orgtrakker job roles with import status
     *
     * @return \Cake\Http\Response
     */
    public function getOrgtrakkerJobRoles()
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

        $companyId = $this->getCompanyId($authResult);

        try {
            $orgtrakkerCompanyId = $this->getOrgtrakkerCompanyId($companyId);
            $connection = $this->getOrgtrakkerConnection($companyId);
            
            // Get all non-deleted job roles from orgtrakker
            $stmt = $connection->execute(
                'SELECT job_role_unique_id, answers, template_id
                 FROM job_role_template_answers 
                 WHERE company_id = :company_id AND deleted = false 
                 ORDER BY job_role_unique_id ASC',
                ['company_id' => $orgtrakkerCompanyId]
            );
            
            $orgtrakkerJobRoles = $stmt->fetchAll('assoc');
            
            // Get orgtrakker job role template
            $orgtrakkerTemplateStmt = $connection->execute(
                'SELECT structure FROM job_role_templates WHERE company_id = :company_id AND deleted = false LIMIT 1',
                ['company_id' => $orgtrakkerCompanyId]
            );
            $orgtrakkerTemplate = $orgtrakkerTemplateStmt->fetch('assoc');
            $orgtrakkerTemplateStructure = $orgtrakkerTemplate ? json_decode($orgtrakkerTemplate['structure'], true) : [];
            
            // Get scorecardtrakker job role template
            $scorecardtrakkerTemplate = $this->getDefaultJobRoleTemplate($companyId);
            $scorecardtrakkerTemplateStructure = is_string($scorecardtrakkerTemplate->structure) 
                ? json_decode($scorecardtrakkerTemplate->structure, true) 
                : $scorecardtrakkerTemplate->structure;
            
            // Get already imported job roles from current company with full answers
            $JobRoleTemplateAnswersTable = $this->getTable('JobRoleTemplateAnswers', $companyId);
            $importedJobRoles = $JobRoleTemplateAnswersTable
                ->find()
                ->select(['job_role_unique_id', 'answers'])
                ->where([
                    'company_id' => $companyId,
                    'deleted' => false,
                ])
                ->toArray();
            
            // Create map of imported job roles
            $importedJobRolesMap = [];
            foreach ($importedJobRoles as $imported) {
                if (!empty($imported->job_role_unique_id)) {
                    $importedJobRolesMap[$imported->job_role_unique_id] = $imported;
                }
            }
            
            // Process all job roles
            $jobRolesList = [];
            foreach ($orgtrakkerJobRoles as $jobRole) {
                $jobRoleUniqueId = $jobRole['job_role_unique_id'] ?? '';
                
                // Check if imported
                $importedJobRole = $importedJobRolesMap[$jobRoleUniqueId] ?? null;
                $isImported = $importedJobRole !== null;
                
                // Extract role code and job title from answers
                $orgtrakkerAnswers = json_decode($jobRole['answers'] ?? '{}', true);
                $roleCode = $this->extractFieldValueFromAnswers($orgtrakkerAnswers, $orgtrakkerTemplateStructure, 'Role Code');
                $jobTitle = $this->extractFieldValueFromAnswers($orgtrakkerAnswers, $orgtrakkerTemplateStructure, 'Job Role');
                if (!$jobTitle) {
                    $jobTitle = $this->extractFieldValueFromAnswers($orgtrakkerAnswers, $orgtrakkerTemplateStructure, 'Official Designation');
                }
                if (!$jobTitle) {
                    $jobTitle = $this->extractFieldValueFromAnswers($orgtrakkerAnswers, $orgtrakkerTemplateStructure, 'Job Title');
                }
                
                // Determine status
                $status = 'not_imported';
                if ($isImported) {
                    // Map orgtrakker answers to scorecardtrakker structure
                    $orgtrakkerMapped = $this->mapFieldValuesByLabel($orgtrakkerAnswers, $orgtrakkerTemplateStructure, $scorecardtrakkerTemplateStructure, $jobRoleUniqueId);
                    
                    // Scorecardtrakker answers are already in the correct structure
                    $scorecardtrakkerAnswers = is_string($importedJobRole->answers) ? json_decode($importedJobRole->answers, true) : $importedJobRole->answers;
                    if (!is_array($scorecardtrakkerAnswers)) {
                        $scorecardtrakkerAnswers = [];
                    }
                    
                    // Compare to detect if update is needed
                    $needsUpdate = $this->compareMappedAnswers($orgtrakkerMapped, $scorecardtrakkerAnswers, $scorecardtrakkerTemplateStructure, $orgtrakkerTemplateStructure);
                    $status = $needsUpdate ? 'needs_update' : 'imported';
                }
                
                $jobRolesList[] = [
                    'job_role_unique_id' => $jobRoleUniqueId,
                    'role_code' => $roleCode ?? '',
                    'job_title' => $jobTitle ?? '',
                    'imported' => $isImported, // Keep for backward compatibility
                    'status' => $status, // New: 'not_imported', 'imported', or 'needs_update'
                    'answers' => $orgtrakkerAnswers, // Include full answers for import
                ];
            }
            
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'data' => $jobRolesList,
                    'message' => 'Orgtrakker job roles retrieved successfully',
                ]));
                
        } catch (\Exception $e) {
            Log::error('Error fetching orgtrakker job roles: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error fetching orgtrakker job roles: ' . $e->getMessage(),
                ]));
        }
    }

    /**
     * Get orgtrakker role levels with import status
     *
     * @return \Cake\Http\Response
     */
    public function getOrgtrakkerRoleLevels()
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

        $companyId = $this->getCompanyId($authResult);

        try {
            $orgtrakkerCompanyId = $this->getOrgtrakkerCompanyId($companyId);
            $connection = $this->getOrgtrakkerConnection($companyId);
            
            // Get all non-deleted role levels from orgtrakker
            $stmt = $connection->execute(
                'SELECT level_unique_id, name, rank, custom_fields
                 FROM role_levels 
                 WHERE company_id = :company_id AND deleted = false 
                 ORDER BY rank ASC, name ASC',
                ['company_id' => $orgtrakkerCompanyId]
            );
            
            $orgtrakkerRoleLevels = $stmt->fetchAll('assoc');
            
            // Get scorecardtrakker role level template
            $scorecardtrakkerTemplate = $this->getDefaultRoleLevelTemplate($companyId);
            $scorecardtrakkerTemplateStructure = is_string($scorecardtrakkerTemplate->structure) 
                ? json_decode($scorecardtrakkerTemplate->structure, true) 
                : $scorecardtrakkerTemplate->structure;
            
            // Get orgtrakker template
            $orgtrakkerTemplateStmt = $connection->execute(
                'SELECT structure FROM level_templates WHERE company_id = :company_id AND deleted = false LIMIT 1',
                ['company_id' => $orgtrakkerCompanyId]
            );
            $orgtrakkerTemplate = $orgtrakkerTemplateStmt->fetch('assoc');
            $orgtrakkerTemplateStructure = $orgtrakkerTemplate ? json_decode($orgtrakkerTemplate['structure'], true) : [];
            
            // Get already imported role levels from current company with custom_fields, name, and rank
            $RoleLevelsTable = $this->getTable('RoleLevels', $companyId);
            $importedRoleLevels = $RoleLevelsTable
                ->find()
                ->select(['level_unique_id', 'custom_fields', 'name', 'rank'])
                ->where([
                    'company_id' => $companyId,
                    'deleted' => false,
                ])
                ->toArray();
            
            // Create map of imported role levels
            $importedRoleLevelsMap = [];
            foreach ($importedRoleLevels as $imported) {
                if (!empty($imported->level_unique_id)) {
                    $importedRoleLevelsMap[$imported->level_unique_id] = $imported;
                }
            }
            
            // Process all role levels
            $roleLevelsList = [];
            foreach ($orgtrakkerRoleLevels as $roleLevel) {
                $levelUniqueId = $roleLevel['level_unique_id'] ?? '';
                
                // Check if imported
                $importedRoleLevel = $importedRoleLevelsMap[$levelUniqueId] ?? null;
                $isImported = $importedRoleLevel !== null;
                
                // Determine status
                $status = 'not_imported';
                if ($isImported) {
                    // Map orgtrakker custom_fields to scorecardtrakker structure
                    // Treat rank/order like any other field - no special handling
                    // Use the EXACT same logic as during import
                    $orgtrakkerCustomFieldsRaw = $roleLevel['custom_fields'] ?? null;
                    $orgtrakkerCustomFields = null;
                    
                    if (!empty($orgtrakkerCustomFieldsRaw)) {
                        $orgtrakkerCustomFields = json_decode($orgtrakkerCustomFieldsRaw, true);
                        if (!is_array($orgtrakkerCustomFields)) {
                            $orgtrakkerCustomFields = [];
                        }
                    }
                    
                    // Use the same mapping logic as importAllRoleLevelsFromOrgtrakker
                    if (!empty($orgtrakkerCustomFields) && is_array($orgtrakkerCustomFields) && !empty($orgtrakkerTemplateStructure) && !empty($scorecardtrakkerTemplateStructure)) {
                        $orgtrakkerMapped = $this->mapFieldValuesByLabel($orgtrakkerCustomFields, $orgtrakkerTemplateStructure, $scorecardtrakkerTemplateStructure, $levelUniqueId);
                    } elseif (!empty($scorecardtrakkerTemplateStructure)) {
                        // If orgtrakker has no custom_fields, create empty structure (same as import)
                        $orgtrakkerMapped = $this->mapFieldValuesByLabel([], [], $scorecardtrakkerTemplateStructure, $levelUniqueId);
                    } else {
                        $orgtrakkerMapped = $orgtrakkerCustomFields ?? [];
                    }
                    
                    // Scorecardtrakker custom_fields are already in the correct structure
                    $scorecardtrakkerCustomFields = is_string($importedRoleLevel->custom_fields) ? json_decode($importedRoleLevel->custom_fields, true) : $importedRoleLevel->custom_fields;
                    if (!is_array($scorecardtrakkerCustomFields)) {
                        $scorecardtrakkerCustomFields = [];
                    }
                    
                    // Debug: Log what we're comparing - BEFORE comparison
                    Log::debug("🔍 ROLE LEVEL COMPARISON DEBUG - BEFORE", [
                        'level_unique_id' => $levelUniqueId,
                        'orgtrakker_custom_fields_raw' => $orgtrakkerCustomFieldsRaw,
                        'orgtrakker_custom_fields_parsed' => $orgtrakkerCustomFields,
                        'orgtrakker_custom_fields_is_array' => is_array($orgtrakkerCustomFields),
                        'orgtrakker_custom_fields_empty' => empty($orgtrakkerCustomFields),
                        'orgtrakker_mapped' => $orgtrakkerMapped,
                        'orgtrakker_mapped_is_array' => is_array($orgtrakkerMapped),
                        'orgtrakker_mapped_empty' => empty($orgtrakkerMapped),
                        'orgtrakker_mapped_keys' => is_array($orgtrakkerMapped) ? array_keys($orgtrakkerMapped) : 'not_array',
                        'scorecardtrakker_custom_fields' => $scorecardtrakkerCustomFields,
                        'scorecardtrakker_custom_fields_is_array' => is_array($scorecardtrakkerCustomFields),
                        'scorecardtrakker_custom_fields_empty' => empty($scorecardtrakkerCustomFields),
                        'scorecardtrakker_custom_fields_keys' => is_array($scorecardtrakkerCustomFields) ? array_keys($scorecardtrakkerCustomFields) : 'not_array',
                        'orgtrakker_mapped_json' => json_encode($orgtrakkerMapped, JSON_PRETTY_PRINT),
                        'scorecardtrakker_custom_fields_json' => json_encode($scorecardtrakkerCustomFields, JSON_PRETTY_PRINT),
                        'templates_exist' => [
                            'orgtrakker_template_structure' => !empty($orgtrakkerTemplateStructure),
                            'scorecardtrakker_template_structure' => !empty($scorecardtrakkerTemplateStructure),
                        ],
                    ]);
                    
                    // Compare to detect if update is needed (exactly like employees and job roles)
                    $comparisonDifferences = [];
                    $needsUpdate = $this->compareMappedAnswers($orgtrakkerMapped, $scorecardtrakkerCustomFields, $scorecardtrakkerTemplateStructure, $orgtrakkerTemplateStructure, $comparisonDifferences);
                    
                    // Detailed debug logging to show EXACTLY what's different
                    if ($needsUpdate) {
                        Log::debug("🔍 ROLE LEVEL COMPARISON - DIFFERENCES FOUND", [
                            'level_unique_id' => $levelUniqueId,
                            'needsUpdate' => $needsUpdate,
                            'differences_count' => count($comparisonDifferences),
                            'differences' => $comparisonDifferences,
                            'orgtrakker_mapped_structure' => $orgtrakkerMapped,
                            'scorecardtrakker_custom_fields_structure' => $scorecardtrakkerCustomFields,
                            'orgtrakker_keys' => array_keys($orgtrakkerMapped),
                            'scorecardtrakker_keys' => array_keys($scorecardtrakkerCustomFields),
                        ]);
                        
                        // Also log each difference individually for clarity
                        foreach ($comparisonDifferences as $idx => $diff) {
                            Log::debug("🔍 ROLE LEVEL DIFFERENCE #{$idx}", [
                                'level_unique_id' => $levelUniqueId,
                                'field_label' => $diff['label'] ?? 'unknown',
                                'field_id' => $diff['fieldId'] ?? 'unknown',
                                'location' => $diff['location'] ?? 'unknown',
                                'orgtrakker_raw' => $diff['orgtrakker_value'] ?? null,
                                'orgtrakker_normalized' => $diff['orgtrakker_normalized'] ?? '',
                                'scorecardtrakker_raw' => $diff['scorecardtrakker_value'] ?? null,
                                'scorecardtrakker_normalized' => $diff['scorecardtrakker_normalized'] ?? '',
                                'are_equal' => ($diff['orgtrakker_normalized'] ?? '') === ($diff['scorecardtrakker_normalized'] ?? ''),
                            ]);
                        }
                    } else {
                        Log::debug("🔍 ROLE LEVEL COMPARISON - NO DIFFERENCES", [
                            'level_unique_id' => $levelUniqueId,
                            'needsUpdate' => false,
                        ]);
                    }
                    
                    // Also compare name and rank (these are direct column values, not in custom_fields)
                    // Normalize name: trim whitespace and compare as strings
                    $orgtrakkerName = trim((string)($roleLevel['name'] ?? ''));
                    $scorecardtrakkerName = trim((string)($importedRoleLevel->name ?? ''));
                    $nameChanged = $orgtrakkerName !== $scorecardtrakkerName;
                    
                    // Normalize rank: convert to integer for comparison (null becomes 0, but we'll handle null separately)
                    $orgtrakkerRank = $roleLevel['rank'] ?? null;
                    $scorecardtrakkerRank = $importedRoleLevel->rank ?? null;
                    
                    // Handle rank comparison: both null = same, both numeric = compare as numbers, otherwise compare as-is
                    if ($orgtrakkerRank === null && $scorecardtrakkerRank === null) {
                        $rankChanged = false;
                    } elseif ($orgtrakkerRank === null || $scorecardtrakkerRank === null) {
                        $rankChanged = true; // One is null, other is not
                    } else {
                        // Both have values, compare as integers (handles string "1" vs int 1)
                        $rankChanged = (int)$orgtrakkerRank !== (int)$scorecardtrakkerRank;
                    }
                    
                    // Detailed logging for name and rank comparison - use error_log for visibility
                    error_log("🔍 ROLE LEVEL NAME/RANK COMPARISON - Level: {$levelUniqueId}");
                    error_log("  Name - Org: '{$orgtrakkerName}' (type: " . gettype($orgtrakkerName) . ") vs Score: '{$scorecardtrakkerName}' (type: " . gettype($scorecardtrakkerName) . ") - Changed: " . ($nameChanged ? 'YES' : 'NO'));
                    error_log("  Rank - Org: " . var_export($orgtrakkerRank, true) . " (type: " . gettype($orgtrakkerRank) . ") vs Score: " . var_export($scorecardtrakkerRank, true) . " (type: " . gettype($scorecardtrakkerRank) . ") - Changed: " . ($rankChanged ? 'YES' : 'NO'));
                    error_log("  Rank normalized - Org int: " . ($orgtrakkerRank !== null ? (int)$orgtrakkerRank : 'NULL') . " vs Score int: " . ($scorecardtrakkerRank !== null ? (int)$scorecardtrakkerRank : 'NULL'));
                    
                    Log::debug("🔍 ROLE LEVEL NAME/RANK COMPARISON", [
                        'level_unique_id' => $levelUniqueId,
                        'orgtrakker_name' => $orgtrakkerName,
                        'scorecardtrakker_name' => $scorecardtrakkerName,
                        'nameChanged' => $nameChanged,
                        'orgtrakker_rank' => $orgtrakkerRank,
                        'scorecardtrakker_rank' => $scorecardtrakkerRank,
                        'rankChanged' => $rankChanged,
                        'rank_normalized_comparison' => [
                            'orgtrakker_int' => $orgtrakkerRank !== null ? (int)$orgtrakkerRank : null,
                            'scorecardtrakker_int' => $scorecardtrakkerRank !== null ? (int)$scorecardtrakkerRank : null,
                            'are_equal' => !$rankChanged,
                        ],
                    ]);
                    
                    if ($needsUpdate || $nameChanged || $rankChanged) {
                        $status = 'needs_update';
                        Log::debug("🔍 ROLE LEVEL STATUS: needs_update", [
                            'level_unique_id' => $levelUniqueId,
                            'needsUpdate' => $needsUpdate,
                            'nameChanged' => $nameChanged,
                            'rankChanged' => $rankChanged,
                            'reason' => $needsUpdate ? 'custom_fields_differ' : ($nameChanged ? 'name_differ' : 'rank_differ'),
                        ]);
                    } else {
                        $status = 'imported';
                    }
                }
                
                $roleLevelsList[] = [
                    'level_unique_id' => $levelUniqueId,
                    'name' => $roleLevel['name'] ?? '',
                    'rank' => $roleLevel['rank'] ?? null,
                    'imported' => $isImported, // Keep for backward compatibility
                    'status' => $status, // New: 'not_imported', 'imported', or 'needs_update'
                ];
            }
            
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'data' => $roleLevelsList,
                    'message' => 'Orgtrakker role levels retrieved successfully',
                ]));
                
        } catch (\Exception $e) {
            Log::error('Error fetching orgtrakker role levels: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error fetching orgtrakker role levels: ' . $e->getMessage(),
                ]));
        }
    }

    /**
     * Get orgtrakker employee reporting relationships with import status
     *
     * @return \Cake\Http\Response
     */
    public function getOrgtrakkerEmployeeReportingRelationships()
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

        $companyId = $this->getCompanyId($authResult);

        try {
            $orgtrakkerCompanyId = $this->getOrgtrakkerCompanyId($companyId);
            $connection = $this->getOrgtrakkerConnection($companyId);
            
            // Get all non-deleted employee reporting relationships from orgtrakker
            $stmt = $connection->execute(
                'SELECT employee_unique_id, report_to_employee_unique_id
                 FROM employee_reporting_relationships 
                 WHERE company_id = :company_id AND deleted = false 
                 ORDER BY employee_unique_id ASC',
                ['company_id' => $orgtrakkerCompanyId]
            );
            
            $orgtrakkerRelationships = $stmt->fetchAll('assoc');
            
            // Get employee names for display
            $employeeStmt = $connection->execute(
                'SELECT employee_unique_id, username, answers
                 FROM employee_template_answers 
                 WHERE company_id = :company_id AND deleted = false',
                ['company_id' => $orgtrakkerCompanyId]
            );
            $employees = $employeeStmt->fetchAll('assoc');
            
            // Get orgtrakker employee template for extracting names
            $orgtrakkerTemplateStmt = $connection->execute(
                'SELECT structure FROM employee_templates WHERE company_id = :company_id AND name = :name AND deleted = false LIMIT 1',
                ['company_id' => $orgtrakkerCompanyId, 'name' => 'employee']
            );
            $orgtrakkerTemplate = $orgtrakkerTemplateStmt->fetch('assoc');
            $orgtrakkerTemplateStructure = $orgtrakkerTemplate ? json_decode($orgtrakkerTemplate['structure'], true) : [];
            
            // Create employee lookup
            $employeeLookup = [];
            foreach ($employees as $emp) {
                $answers = json_decode($emp['answers'] ?? '{}', true);
                $firstName = $this->extractFieldValueFromAnswers($answers, $orgtrakkerTemplateStructure, 'First Name');
                $lastName = $this->extractFieldValueFromAnswers($answers, $orgtrakkerTemplateStructure, 'Last Name');
                $employeeLookup[$emp['employee_unique_id']] = [
                    'username' => $emp['username'] ?? '',
                    'name' => trim(($firstName ?? '') . ' ' . ($lastName ?? '')),
                ];
            }
            
            // Get already imported relationships from current company
            $EmployeeReportingRelationshipsTable = $this->getTable('EmployeeReportingRelationships', $companyId);
            $importedRelationships = $EmployeeReportingRelationshipsTable
                ->find()
                ->select(['employee_unique_id', 'report_to_employee_unique_id'])
                ->where([
                    'company_id' => $companyId,
                    'deleted' => false,
                ])
                ->toArray();
            
            // Create map of imported relationships by employee_unique_id
            $importedRelationshipsMap = [];
            foreach ($importedRelationships as $imported) {
                $empId = $imported->employee_unique_id ?? '';
                if (!empty($empId)) {
                    $importedRelationshipsMap[$empId] = $imported->report_to_employee_unique_id ?? '';
                }
            }
            
            // Process all relationships
            $relationshipsList = [];
            foreach ($orgtrakkerRelationships as $relationship) {
                $employeeUniqueId = $relationship['employee_unique_id'] ?? '';
                $reportingTo = $relationship['report_to_employee_unique_id'] ?? '';
                
                // Check if imported and if relationship matches
                $importedReportingTo = $importedRelationshipsMap[$employeeUniqueId] ?? null;
                $isImported = $importedReportingTo !== null;
                
                // Determine status
                $status = 'not_imported';
                if ($isImported) {
                    // Check if the reporting relationship matches
                    if ($importedReportingTo === $reportingTo) {
                        $status = 'imported';
                    } else {
                        // Relationship exists but points to different manager - needs update
                        $status = 'needs_update';
                    }
                }
                
                $employeeName = $employeeLookup[$employeeUniqueId]['name'] ?? $employeeLookup[$employeeUniqueId]['username'] ?? $employeeUniqueId;
                $reportingToName = $employeeLookup[$reportingTo]['name'] ?? $employeeLookup[$reportingTo]['username'] ?? $reportingTo;
                
                $relationshipsList[] = [
                    'employee_unique_id' => $employeeUniqueId,
                    'employee_name' => $employeeName,
                    'reporting_to' => $reportingTo,
                    'reporting_to_name' => $reportingToName,
                    'imported' => $isImported, // Keep for backward compatibility
                    'status' => $status, // New: 'not_imported', 'imported', or 'needs_update'
                ];
            }
            
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'data' => $relationshipsList,
                    'message' => 'Orgtrakker employee reporting relationships retrieved successfully',
                ]));
                
        } catch (\Exception $e) {
            Log::error('Error fetching orgtrakker employee reporting relationships: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error fetching orgtrakker employee reporting relationships: ' . $e->getMessage(),
                ]));
        }
    }

    /**
     * Get orgtrakker job role reporting relationships with import status
     *
     * @return \Cake\Http\Response
     */
    public function getOrgtrakkerJobRoleReportingRelationships()
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

        $companyId = $this->getCompanyId($authResult);

        try {
            $orgtrakkerCompanyId = $this->getOrgtrakkerCompanyId($companyId);
            $connection = $this->getOrgtrakkerConnection($companyId);
            
            // Get all non-deleted job role reporting relationships from orgtrakker
            $stmt = $connection->execute(
                'SELECT job_role, reporting_to
                 FROM job_role_reporting_relationships 
                 WHERE company_id = :company_id AND deleted = false 
                 ORDER BY job_role ASC',
                ['company_id' => $orgtrakkerCompanyId]
            );
            
            $orgtrakkerRelationships = $stmt->fetchAll('assoc');
            
            // Get job role names for display
            $jobRoleStmt = $connection->execute(
                'SELECT job_role_unique_id, answers
                 FROM job_role_template_answers 
                 WHERE company_id = :company_id AND deleted = false',
                ['company_id' => $orgtrakkerCompanyId]
            );
            $jobRoles = $jobRoleStmt->fetchAll('assoc');
            
            // Get orgtrakker job role template for extracting names
            $orgtrakkerTemplateStmt = $connection->execute(
                'SELECT structure FROM job_role_templates WHERE company_id = :company_id AND deleted = false LIMIT 1',
                ['company_id' => $orgtrakkerCompanyId]
            );
            $orgtrakkerTemplate = $orgtrakkerTemplateStmt->fetch('assoc');
            $orgtrakkerTemplateStructure = $orgtrakkerTemplate ? json_decode($orgtrakkerTemplate['structure'], true) : [];
            
            // Create job role lookup
            $jobRoleLookup = [];
            foreach ($jobRoles as $jr) {
                $answers = json_decode($jr['answers'] ?? '{}', true);
                $jobRoleName = $this->extractFieldValueFromAnswers($answers, $orgtrakkerTemplateStructure, 'Job Role');
                if (!$jobRoleName) {
                    $jobRoleName = $this->extractFieldValueFromAnswers($answers, $orgtrakkerTemplateStructure, 'Official Designation');
                }
                if (!$jobRoleName) {
                    $jobRoleName = $this->extractFieldValueFromAnswers($answers, $orgtrakkerTemplateStructure, 'Job Title');
                }
                $jobRoleLookup[$jr['job_role_unique_id']] = $jobRoleName ?? $jr['job_role_unique_id'];
            }
            
            // Get already imported relationships from current company
            $JobRoleReportingRelationshipsTable = $this->getTable('JobRoleReportingRelationships', $companyId);
            $importedRelationships = $JobRoleReportingRelationshipsTable
                ->find()
                ->select(['job_role', 'reporting_to'])
                ->where([
                    'company_id' => $companyId,
                    'deleted' => false,
                ])
                ->toArray();
            
            // Create map of imported relationships by job_role
            $importedRelationshipsMap = [];
            foreach ($importedRelationships as $imported) {
                $jrId = $imported->job_role ?? '';
                if (!empty($jrId)) {
                    $importedRelationshipsMap[$jrId] = $imported->reporting_to ?? '';
                }
            }
            
            // Process all relationships
            $relationshipsList = [];
            foreach ($orgtrakkerRelationships as $relationship) {
                $jobRole = $relationship['job_role'] ?? '';
                $reportingTo = $relationship['reporting_to'] ?? '';
                
                // Check if imported and if relationship matches
                $importedReportingTo = $importedRelationshipsMap[$jobRole] ?? null;
                $isImported = $importedReportingTo !== null;
                
                // Determine status
                $status = 'not_imported';
                if ($isImported) {
                    // Check if the reporting relationship matches
                    if ($importedReportingTo === $reportingTo) {
                        $status = 'imported';
                    } else {
                        // Relationship exists but points to different job role - needs update
                        $status = 'needs_update';
                    }
                }
                
                $jobRoleName = $jobRoleLookup[$jobRole] ?? $jobRole;
                $reportingToName = $jobRoleLookup[$reportingTo] ?? $reportingTo;
                
                $relationshipsList[] = [
                    'job_role' => $jobRole,
                    'job_role_name' => $jobRoleName,
                    'reporting_to' => $reportingTo,
                    'reporting_to_name' => $reportingToName,
                    'imported' => $isImported, // Keep for backward compatibility
                    'status' => $status, // New: 'not_imported', 'imported', or 'needs_update'
                ];
            }
            
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'data' => $relationshipsList,
                    'message' => 'Orgtrakker job role reporting relationships retrieved successfully',
                ]));
                
        } catch (\Exception $e) {
            Log::error('Error fetching orgtrakker job role reporting relationships: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error fetching orgtrakker job role reporting relationships: ' . $e->getMessage(),
                ]));
        }
    }

    /**
     * Get default job role template for company
     *
     * @param string $companyId
     * @return \App\Model\Entity\JobRoleTemplate
     * @throws Exception
     */
    private function getDefaultJobRoleTemplate($companyId)
    {
        $JobRoleTemplatesTable = $this->getTable('JobRoleTemplates', $companyId);
        $template = $JobRoleTemplatesTable
            ->find()
            ->where([
                'company_id' => $companyId,
                'deleted' => false,
            ])
            ->first();

        if (!$template) {
            throw new Exception('Default job role template not found. Please create a job role template first.');
        }
        return $template;
    }

    /**
     * Get default role level template for company
     *
     * @param string $companyId
     * @return \App\Model\Entity\LevelTemplate
     * @throws Exception
     */
    private function getDefaultRoleLevelTemplate($companyId)
    {
        $LevelTemplatesTable = $this->getTable('LevelTemplates', $companyId);
        $template = $LevelTemplatesTable
            ->find()
            ->where([
                'company_id' => $companyId,
                'deleted' => false,
            ])
            ->first();

        if (!$template) {
            throw new Exception('Default role level template not found. Please create a role level template first.');
        }
        return $template;
    }

    /**
     * Map field values from orgtrakker answers to scorecardtrakker template structure by matching field labels
     *
     * @param array $orgtrakkerAnswers
     * @param array $orgtrakkerTemplate
     * @param array $scorecardtrakkerTemplate
     * @param string|null $debugIdentifier Optional identifier for debugging
     * @return array
     */
    private function mapFieldValuesByLabel($orgtrakkerAnswers, $orgtrakkerTemplate, $scorecardtrakkerTemplate, $debugIdentifier = null)
    {
        // Build answer structure based on scorecardtrakker template (target structure)
        $mappedAnswers = [];
        
        // Create a flat mapping of orgtrakker fields: label -> value (from answers)
        $orgtrakkerFieldMap = [];
        $orgtrakkerFieldDetails = []; // For debugging: store full details
        
        foreach ($orgtrakkerTemplate as $group) {
            $groupId = $group['id'] ?? null;
            if (!$groupId) continue;
            
            // Map regular fields
            if (isset($group['fields']) && is_array($group['fields'])) {
                foreach ($group['fields'] as $field) {
                    $fieldId = $field['id'] ?? null;
                    if (!$fieldId) continue;
                    
                    // Use 'label' ONLY for mapping (not 'customize_field_label')
                    // customize_field_label is a custom name that can be anything and has no relation to field matching
                    $label = $field['label'] ?? '';
                    if (empty($label)) continue;
                    
                    // Use extractFieldValueFromAnswers to find value by label (more robust than direct ID access)
                    // This searches by label ONLY, ensuring consistent matching across templates
                    $value = $this->extractFieldValueFromAnswers($orgtrakkerAnswers, $orgtrakkerTemplate, $label);
                    
                    // Special debug logging for rank/order related fields
                    $isRankOrderField = (
                        stripos($label, 'rank') !== false || 
                        stripos($label, 'order') !== false ||
                        stripos($label, 'rank/order') !== false ||
                        stripos($label, 'rank order') !== false
                    );
                    
                    // Special debug logging for Reports To field
                    $isReportsToField = (strcasecmp($label, 'Reports To') === 0);
                    
                    if ($isRankOrderField) {
                        Log::debug("🔍 ROLE LEVEL MAPPING - Rank/Order field extraction from orgtrakker", [
                            'debug_id' => $debugIdentifier,
                            'field_label' => $label,
                            'field_id' => $fieldId,
                            'field_type' => $field['type'] ?? 'unknown',
                            'extracted_value' => $value,
                            'extracted_value_type' => gettype($value),
                            'extracted_value_is_null' => $value === null,
                        ]);
                    }
                    
                    if ($isReportsToField) {
                        Log::debug("🔍 EMPLOYEE IMPORT - Reports To field extraction from orgtrakker", [
                            'debug_id' => $debugIdentifier,
                            'field_label' => $label,
                            'field_id' => $fieldId,
                            'field_type' => $field['type'] ?? 'unknown',
                            'extracted_value' => $value,
                            'extracted_value_type' => gettype($value),
                            'extracted_value_is_null' => $value === null,
                            'extracted_value_is_empty_string' => $value === '',
                            'extracted_value_string' => (string)$value,
                            'orgtrakker_answers_sample' => is_array($orgtrakkerAnswers) ? array_keys($orgtrakkerAnswers) : 'not_array',
                        ]);
                        // Also use error_log for visibility
                        error_log("🔍 REPORTS TO EXTRACTION - Employee: {$debugIdentifier}, Label: {$label}, Value: " . ($value !== null ? (string)$value : 'NULL') . ", Type: " . gettype($value));
                    }
                    
                    // Include value in map even if it's an empty string (empty string is a valid value)
                    // Only skip if value is null (field not found)
                    if ($value !== null) {
                        $labelLower = strtolower($label);
                        // Store details for debugging
                        $orgtrakkerFieldDetails[] = [
                            'label' => $label,
                            'label_lower' => $labelLower,
                            'field_id' => $fieldId,
                            'group_id' => $groupId,
                            'value' => $value,
                            'value_type' => gettype($value),
                        ];
                        
                        // WARNING: If duplicate labels exist, last one wins
                        if (isset($orgtrakkerFieldMap[$labelLower])) {
                            Log::warning("🔍 ROLE LEVEL MAPPING - Duplicate label found in orgtrakker", [
                                'debug_id' => $debugIdentifier,
                                'label' => $label,
                                'label_lower' => $labelLower,
                                'old_value' => $orgtrakkerFieldMap[$labelLower],
                                'new_value' => $value,
                                'old_field_id' => $orgtrakkerFieldDetails[count($orgtrakkerFieldDetails) - 2]['field_id'] ?? 'unknown',
                                'new_field_id' => $fieldId,
                            ]);
                        }
                        $orgtrakkerFieldMap[$labelLower] = $value;
                    }
                }
            }
            
            // Map subGroup fields (ignore subGroup structure, just map individual fields)
            if (isset($group['subGroups']) && is_array($group['subGroups'])) {
                foreach ($group['subGroups'] as $subGroup) {
                    $subGroupId = $subGroup['id'] ?? null;
                    if (!$subGroupId) continue;
                    
                    if (isset($subGroup['fields']) && is_array($subGroup['fields'])) {
                        foreach ($subGroup['fields'] as $field) {
                            $fieldId = $field['id'] ?? null;
                            if (!$fieldId) continue;
                            
                            // Use 'label' ONLY for mapping (not 'customize_field_label')
                            // customize_field_label is a custom name that can be anything and has no relation to field matching
                            $label = $field['label'] ?? '';
                            if (empty($label)) continue;
                            
                            // Use extractFieldValueFromAnswers to find value by label (more robust than direct ID access)
                            // This handles subgroups automatically by searching through the template structure
                            // This searches by label ONLY, ensuring consistent matching across templates
                            $value = $this->extractFieldValueFromAnswers($orgtrakkerAnswers, $orgtrakkerTemplate, $label);
                            
                            if ($value !== null) {
                                $labelLower = strtolower($label);
                                // Store details for debugging
                                $orgtrakkerFieldDetails[] = [
                                    'label' => $label,
                                    'label_lower' => $labelLower,
                                    'field_id' => $fieldId,
                                    'subgroup_id' => $subGroupId,
                                    'value' => $value,
                                ];
                                
                                // WARNING: If duplicate labels exist, last one wins
                                if (isset($orgtrakkerFieldMap[$labelLower])) {
                                    Log::warning("🔍 ROLE LEVEL MAPPING - Duplicate label found in orgtrakker (subgroup)", [
                                        'debug_id' => $debugIdentifier,
                                        'label' => $label,
                                        'label_lower' => $labelLower,
                                        'old_value' => $orgtrakkerFieldMap[$labelLower],
                                        'new_value' => $value,
                                    ]);
                                }
                                $orgtrakkerFieldMap[$labelLower] = $value;
                            }
                        }
                    }
                }
            }
        }
        
        Log::debug("🔍 ROLE LEVEL MAPPING - Orgtrakker field map built", [
            'debug_id' => $debugIdentifier,
            'orgtrakker_field_map' => $orgtrakkerFieldMap,
            'orgtrakker_field_details' => $orgtrakkerFieldDetails,
        ]);
        
        // Now build scorecardtrakker answer structure and map values
        $scorecardtrakkerFieldDetails = []; // For debugging
        
        foreach ($scorecardtrakkerTemplate as $group) {
            $groupId = $group['id'] ?? null;
            if (!$groupId) continue;
            
            $mappedAnswers[$groupId] = [];
            
            // Map regular fields
            if (isset($group['fields']) && is_array($group['fields'])) {
                foreach ($group['fields'] as $field) {
                    $fieldId = $field['id'] ?? null;
                    if (!$fieldId) continue;
                    
                    // Use 'label' ONLY for mapping (not 'customize_field_label')
                    // customize_field_label is a custom name that can be anything and has no relation to field matching
                    $label = $field['label'] ?? '';
                    if (empty($label)) {
                        $mappedAnswers[$groupId][$fieldId] = '';
                        continue;
                    }
                    
                    $labelLower = strtolower($label);
                    // Find matching value from orgtrakker
                    $value = $orgtrakkerFieldMap[$labelLower] ?? '';
                    $mappedAnswers[$groupId][$fieldId] = $value;
                    
                    // Special debug logging for rank/order related fields
                    $isRankOrderField = (
                        stripos($label, 'rank') !== false || 
                        stripos($label, 'order') !== false ||
                        stripos($label, 'rank/order') !== false ||
                        stripos($label, 'rank order') !== false
                    );
                    
                    // Special debug logging for Reports To field
                    $isReportsToField = (strcasecmp($label, 'Reports To') === 0);
                    
                    if ($isRankOrderField) {
                        Log::debug("🔍 ROLE LEVEL MAPPING - Rank/Order field mapping", [
                            'debug_id' => $debugIdentifier,
                            'field_label' => $label,
                            'field_label_lower' => $labelLower,
                            'field_id' => $fieldId,
                            'field_type' => $field['type'] ?? 'unknown',
                            'found_in_orgtrakker' => isset($orgtrakkerFieldMap[$labelLower]),
                            'mapped_value' => $value,
                            'mapped_value_type' => gettype($value),
                            'available_orgtrakker_labels' => array_keys($orgtrakkerFieldMap),
                        ]);
                    }
                    
                    if ($isReportsToField) {
                        Log::debug("🔍 EMPLOYEE IMPORT - Reports To field mapping to scorecardtrakker", [
                            'debug_id' => $debugIdentifier,
                            'field_label' => $label,
                            'field_label_lower' => $labelLower,
                            'field_id' => $fieldId,
                            'field_type' => $field['type'] ?? 'unknown',
                            'found_in_orgtrakker' => isset($orgtrakkerFieldMap[$labelLower]),
                            'mapped_value' => $value,
                            'mapped_value_type' => gettype($value),
                            'mapped_value_string' => (string)$value,
                            'mapped_value_is_empty' => empty($value),
                            'available_orgtrakker_labels' => array_keys($orgtrakkerFieldMap),
                            'orgtrakker_field_map_reports_to' => $orgtrakkerFieldMap[$labelLower] ?? 'NOT_FOUND',
                        ]);
                        // Also use error_log for visibility
                        $foundInMap = isset($orgtrakkerFieldMap[$labelLower]);
                        $mapValue = $orgtrakkerFieldMap[$labelLower] ?? 'NOT_IN_MAP';
                        error_log("🔍 REPORTS TO MAPPING - Employee: {$debugIdentifier}, Label: {$label} (lower: {$labelLower}), Found in map: " . ($foundInMap ? 'YES' : 'NO') . ", Map value: {$mapValue}, Final mapped value: " . (string)$value . ", Available labels: " . implode(', ', array_keys($orgtrakkerFieldMap)));
                    }
                    
                    // Store details for debugging
                    $scorecardtrakkerFieldDetails[] = [
                        'label' => $label,
                        'label_lower' => $labelLower,
                        'field_id' => $fieldId,
                        'group_id' => $groupId,
                        'mapped_value' => $value,
                        'found_in_orgtrakker' => isset($orgtrakkerFieldMap[$labelLower]),
                    ];
                }
            }
            
            // Map subGroup fields (preserve structure but map values)
            if (isset($group['subGroups']) && is_array($group['subGroups'])) {
                foreach ($group['subGroups'] as $index => $subGroup) {
                    $subGroupId = $subGroup['id'] ?? null;
                    if (!$subGroupId) continue;
                    
                    // Use pattern {groupLabel}_{index} for subGroups
                    $groupLabel = $group['label'] ?? $group['id'];
                    $subGroupLabel = "{$groupLabel}_{$index}";
                    $mappedAnswers[$subGroupLabel] = [];
                    
                    if (isset($subGroup['fields']) && is_array($subGroup['fields'])) {
                        foreach ($subGroup['fields'] as $field) {
                            $fieldId = $field['id'] ?? null;
                            if (!$fieldId) continue;
                            
                            // Use 'label' ONLY for mapping (not 'customize_field_label')
                            // customize_field_label is a custom name that can be anything and has no relation to field matching
                            $label = $field['label'] ?? '';
                            if (empty($label)) {
                                $mappedAnswers[$subGroupLabel][$fieldId] = '';
                                continue;
                            }
                            
                            $labelLower = strtolower($label);
                            // Find matching value from orgtrakker
                            $value = $orgtrakkerFieldMap[$labelLower] ?? '';
                            $mappedAnswers[$subGroupLabel][$fieldId] = $value;
                            
                            // Store details for debugging
                            $scorecardtrakkerFieldDetails[] = [
                                'label' => $label,
                                'label_lower' => $labelLower,
                                'field_id' => $fieldId,
                                'subgroup_id' => $subGroupId,
                                'subgroup_label' => $subGroupLabel,
                                'mapped_value' => $value,
                                'found_in_orgtrakker' => isset($orgtrakkerFieldMap[$labelLower]),
                            ];
                        }
                    }
                }
            }
        }
        
        Log::debug("🔍 ROLE LEVEL MAPPING - Scorecardtrakker mapping complete", [
            'debug_id' => $debugIdentifier,
            'scorecardtrakker_field_details' => $scorecardtrakkerFieldDetails,
            'final_mapped_structure' => $mappedAnswers,
        ]);
        
        // SPECIAL HANDLING: If this is an employee import and we have a Reports To value in the orgtrakker field map,
        // ensure it's in the mapped answers even if mapping failed
        // This is a safety net in case the field wasn't found during normal mapping
        if ($debugIdentifier && isset($orgtrakkerFieldMap['reports to'])) {
            $reportsToValue = $orgtrakkerFieldMap['reports to'];
            if ($reportsToValue !== null && $reportsToValue !== '') {
                // Try to find Reports To field in scorecardtrakker template and set it
                foreach ($scorecardtrakkerTemplate as $group) {
                    // Check regular fields
                    foreach ($group['fields'] ?? [] as $field) {
                        $fieldLabel = $field['label'] ?? '';
                        if (!empty($fieldLabel) && strcasecmp($fieldLabel, 'Reports To') === 0) {
                            $groupId = $group['id'];
                            $fieldId = $field['id'];
                            if (!isset($mappedAnswers[$groupId])) {
                                $mappedAnswers[$groupId] = [];
                            }
                            // Only set if current value is empty
                            if (empty($mappedAnswers[$groupId][$fieldId])) {
                                $mappedAnswers[$groupId][$fieldId] = $reportsToValue;
                                error_log("🔍 MAP FIELD VALUES - Reports To value set in mapped answers (safety net) - Group: {$groupId}, Field: {$fieldId}, Value: {$reportsToValue}");
                            }
                            break 2;
                        }
                    }
                    
                    // Check subgroups
                    if (isset($group['subGroups']) && is_array($group['subGroups'])) {
                        $groupLabel = $group['label'] ?? $group['id'];
                        foreach ($group['subGroups'] as $index => $subGroup) {
                            foreach ($subGroup['fields'] ?? [] as $field) {
                                $fieldLabel = $field['label'] ?? '';
                                if (!empty($fieldLabel) && strcasecmp($fieldLabel, 'Reports To') === 0) {
                                    $subGroupLabel = "{$groupLabel}_{$index}";
                                    $fieldId = $field['id'];
                                    if (!isset($mappedAnswers[$subGroupLabel])) {
                                        $mappedAnswers[$subGroupLabel] = [];
                                    }
                                    // Only set if current value is empty
                                    if (empty($mappedAnswers[$subGroupLabel][$fieldId])) {
                                        $mappedAnswers[$subGroupLabel][$fieldId] = $reportsToValue;
                                        error_log("🔍 MAP FIELD VALUES - Reports To value set in mapped answers (safety net, subgroup) - SubGroup: {$subGroupLabel}, Field: {$fieldId}, Value: {$reportsToValue}");
                                    }
                                    break 3;
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return $mappedAnswers;
    }

    /**
     * Ensure rank/order field is mapped from rank column if not found in custom_fields
     *
     * @param array &$customFields Reference to the custom fields array to update
     * @param array $scorecardtrakkerTemplateStructure
     * @param mixed $rankColumnValue The rank value from the rank column
     * @param string $debugIdentifier For logging
     * @param bool $forceOverwrite If true, always use rank column value even if field exists
     * @return void
     */
    private function ensureRankOrderFieldMapped(&$customFields, $scorecardtrakkerTemplateStructure, $rankColumnValue, $debugIdentifier = null, $forceOverwrite = false)
    {
        if ($rankColumnValue === null || empty($scorecardtrakkerTemplateStructure)) {
            return;
        }
        
        // Search for rank/order field in scorecardtrakker template
        foreach ($scorecardtrakkerTemplateStructure as $group) {
            $groupId = $group['id'] ?? null;
            if (!$groupId) continue;
            
            // Check regular fields
            if (isset($group['fields']) && is_array($group['fields'])) {
                foreach ($group['fields'] as $field) {
                    $fieldId = $field['id'] ?? null;
                    if (!$fieldId) continue;
                    
                    // Use 'label' ONLY (not 'customize_field_label')
                    // customize_field_label is a custom name that can be anything and has no relation to field matching
                    $label = $field['label'] ?? '';
                    $fieldType = $field['type'] ?? 'text';
                    
                    // Check if this is a rank/order field
                    $isRankOrderField = (
                        stripos($label, 'rank') !== false && 
                        (stripos($label, 'order') !== false || stripos($label, '/') !== false)
                    ) || (
                        stripos($label, 'rank/order') !== false ||
                        stripos($label, 'rank order') !== false
                    );
                    
                    if ($isRankOrderField) {
                        // Check if field is already mapped and has a value
                        $currentValue = $customFields[$groupId][$fieldId] ?? null;
                        
                        // If not mapped or empty, or if forceOverwrite is true, use rank column value
                        if ($forceOverwrite || $currentValue === null || $currentValue === '') {
                            // Convert type if needed
                            $value = $rankColumnValue;
                            if ($fieldType === 'text' && is_numeric($value)) {
                                $value = (string)$value;
                            } elseif ($fieldType === 'number' && !is_numeric($value)) {
                                $value = is_numeric($value) ? (int)$value : null;
                            }
                            
                            if ($value !== null) {
                                if (!isset($customFields[$groupId])) {
                                    $customFields[$groupId] = [];
                                }
                                $customFields[$groupId][$fieldId] = $value;
                                
                                Log::debug("🔍 ROLE LEVEL MAPPING - Rank/Order field mapped from rank column", [
                                    'debug_id' => $debugIdentifier,
                                    'field_label' => $label,
                                    'field_id' => $fieldId,
                                    'field_type' => $fieldType,
                                    'rank_column_value' => $rankColumnValue,
                                    'mapped_value' => $value,
                                    'mapped_value_type' => gettype($value),
                                ]);
                            }
                        } else {
                            Log::debug("🔍 ROLE LEVEL MAPPING - Rank/Order field already has value", [
                                'debug_id' => $debugIdentifier,
                                'field_label' => $label,
                                'field_id' => $fieldId,
                                'current_value' => $currentValue,
                                'rank_column_value' => $rankColumnValue,
                            ]);
                        }
                    }
                }
            }
            
            // Check subGroup fields
            if (isset($group['subGroups']) && is_array($group['subGroups'])) {
                foreach ($group['subGroups'] as $index => $subGroup) {
                    $subGroupId = $subGroup['id'] ?? null;
                    if (!$subGroupId) continue;
                    
                    $groupLabel = $group['label'] ?? $group['id'];
                    $subGroupLabel = "{$groupLabel}_{$index}";
                    
                    if (isset($subGroup['fields']) && is_array($subGroup['fields'])) {
                        foreach ($subGroup['fields'] as $field) {
                            $fieldId = $field['id'] ?? null;
                            if (!$fieldId) continue;
                            
                            // Use 'label' ONLY (not 'customize_field_label')
                            // customize_field_label is a custom name that can be anything and has no relation to field matching
                            $label = $field['label'] ?? '';
                            $fieldType = $field['type'] ?? 'text';
                            
                            // Check if this is a rank/order field
                            $isRankOrderField = (
                                stripos($label, 'rank') !== false && 
                                (stripos($label, 'order') !== false || stripos($label, '/') !== false)
                            ) || (
                                stripos($label, 'rank/order') !== false ||
                                stripos($label, 'rank order') !== false
                            );
                            
                            if ($isRankOrderField) {
                                // Check if field is already mapped and has a value
                                $currentValue = $customFields[$subGroupLabel][$fieldId] ?? null;
                                
                                // If not mapped or empty, or if forceOverwrite is true, use rank column value
                                if ($forceOverwrite || $currentValue === null || $currentValue === '') {
                                    // Convert type if needed
                                    $value = $rankColumnValue;
                                    if ($fieldType === 'text' && is_numeric($value)) {
                                        $value = (string)$value;
                                    } elseif ($fieldType === 'number' && !is_numeric($value)) {
                                        $value = is_numeric($value) ? (int)$value : null;
                                    }
                                    
                                    if ($value !== null) {
                                        if (!isset($customFields[$subGroupLabel])) {
                                            $customFields[$subGroupLabel] = [];
                                        }
                                        $customFields[$subGroupLabel][$fieldId] = $value;
                                        
                                        Log::debug("🔍 ROLE LEVEL MAPPING - Rank/Order field mapped from rank column (subgroup)", [
                                            'debug_id' => $debugIdentifier,
                                            'field_label' => $label,
                                            'field_id' => $fieldId,
                                            'field_type' => $fieldType,
                                            'subgroup_label' => $subGroupLabel,
                                            'rank_column_value' => $rankColumnValue,
                                            'mapped_value' => $value,
                                            'mapped_value_type' => gettype($value),
                                        ]);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Compare mapped answers between orgtrakker and scorecardtrakker to detect inconsistencies
     *
     * @param array $orgtrakkerMappedAnswers Mapped answers from orgtrakker
     * @param array $scorecardtrakkerMappedAnswers Mapped answers from scorecardtrakker
     * @param array $scorecardtrakkerTemplate Template structure from scorecardtrakker
     * @param array $orgtrakkerTemplate Template structure from orgtrakker (for label matching)
     * @return bool True if any field values differ, false if all match
     */
    private function compareMappedAnswers($orgtrakkerMappedAnswers, $scorecardtrakkerMappedAnswers, $scorecardtrakkerTemplate, $orgtrakkerTemplate, &$differences = null)
    {
        $differences = [];
        
        if (empty($orgtrakkerMappedAnswers) && empty($scorecardtrakkerMappedAnswers)) {
            return false; // Both empty, no difference
        }
        
        // Validate inputs
        if (!is_array($orgtrakkerTemplate) || !is_array($scorecardtrakkerTemplate)) {
            return false; // Can't compare without templates
        }
        
        if (!is_array($orgtrakkerMappedAnswers)) {
            $orgtrakkerMappedAnswers = [];
        }
        if (!is_array($scorecardtrakkerMappedAnswers)) {
            $scorecardtrakkerMappedAnswers = [];
        }
        
        // Create a map of orgtrakker labels for quick lookup
        $orgtrakkerLabelMap = [];
        foreach ($orgtrakkerTemplate as $group) {
            if (isset($group['fields']) && is_array($group['fields'])) {
                foreach ($group['fields'] as $field) {
                    $label = $field['label'] ?? '';
                    if (!empty($label)) {
                        $orgtrakkerLabelMap[strtolower($label)] = true;
                    }
                }
            }
            // Also check subgroups
            if (isset($group['subGroups']) && is_array($group['subGroups'])) {
                foreach ($group['subGroups'] as $subGroup) {
                    if (isset($subGroup['fields']) && is_array($subGroup['fields'])) {
                        foreach ($subGroup['fields'] as $field) {
                            $label = $field['label'] ?? '';
                            if (!empty($label)) {
                                $orgtrakkerLabelMap[strtolower($label)] = true;
                            }
                        }
                    }
                }
            }
        }
        
        // Iterate through scorecardtrakker template and compare values
        foreach ($scorecardtrakkerTemplate as $group) {
            $groupId = $group['id'] ?? null;
            if (!$groupId) continue;
            
            // Check regular fields
            if (isset($group['fields']) && is_array($group['fields'])) {
                foreach ($group['fields'] as $field) {
                    $fieldId = $field['id'] ?? null;
                    $label = $field['label'] ?? '';
                    
                    if (!$fieldId || empty($label)) continue;
                    
                    // Only compare if this label exists in orgtrakker template
                    if (!isset($orgtrakkerLabelMap[strtolower($label)])) {
                        continue; // Skip fields that don't exist in orgtrakker
                    }
                    
                    // Get values from both mapped answers
                    $orgtrakkerValue = $orgtrakkerMappedAnswers[$groupId][$fieldId] ?? null;
                    $scorecardtrakkerValue = $scorecardtrakkerMappedAnswers[$groupId][$fieldId] ?? null;
                    
                    // Normalize values for comparison
                    $orgtrakkerNormalized = $this->normalizeValueForComparison($orgtrakkerValue);
                    $scorecardtrakkerNormalized = $this->normalizeValueForComparison($scorecardtrakkerValue);
                    
                    // Compare values
                    if ($orgtrakkerNormalized !== $scorecardtrakkerNormalized) {
                        $differences[] = [
                            'location' => 'group',
                            'groupId' => $groupId,
                            'fieldId' => $fieldId,
                            'label' => $label,
                            'orgtrakker_value' => $orgtrakkerValue,
                            'orgtrakker_normalized' => $orgtrakkerNormalized,
                            'scorecardtrakker_value' => $scorecardtrakkerValue,
                            'scorecardtrakker_normalized' => $scorecardtrakkerNormalized,
                        ];
                    }
                }
            }
            
            // Check subgroups
            if (isset($group['subGroups']) && is_array($group['subGroups'])) {
                $groupLabel = $group['label'] ?? $group['id'];
                foreach ($group['subGroups'] as $index => $subGroup) {
                    $subGroupLabel = "{$groupLabel}_{$index}";
                    
                    if (isset($subGroup['fields']) && is_array($subGroup['fields'])) {
                        foreach ($subGroup['fields'] as $field) {
                            $fieldId = $field['id'] ?? null;
                            $label = $field['label'] ?? '';
                            
                            if (!$fieldId || empty($label)) continue;
                            
                            // Only compare if this label exists in orgtrakker template
                            if (!isset($orgtrakkerLabelMap[strtolower($label)])) {
                                continue;
                            }
                            
                            // Get values from both mapped answers
                            $orgtrakkerValue = $orgtrakkerMappedAnswers[$subGroupLabel][$fieldId] ?? null;
                            $scorecardtrakkerValue = $scorecardtrakkerMappedAnswers[$subGroupLabel][$fieldId] ?? null;
                            
                            // Normalize values for comparison
                            $orgtrakkerNormalized = $this->normalizeValueForComparison($orgtrakkerValue);
                            $scorecardtrakkerNormalized = $this->normalizeValueForComparison($scorecardtrakkerValue);
                            
                            // Compare values
                            if ($orgtrakkerNormalized !== $scorecardtrakkerNormalized) {
                                $differences[] = [
                                    'location' => 'subgroup',
                                    'subGroupLabel' => $subGroupLabel,
                                    'fieldId' => $fieldId,
                                    'label' => $label,
                                    'orgtrakker_value' => $orgtrakkerValue,
                                    'orgtrakker_normalized' => $orgtrakkerNormalized,
                                    'scorecardtrakker_value' => $scorecardtrakkerValue,
                                    'scorecardtrakker_normalized' => $scorecardtrakkerNormalized,
                                ];
                            }
                        }
                    }
                }
            }
        }
        
        return !empty($differences); // Return true if differences found
    }
    
    /**
     * Normalize value for comparison (trim, handle null/empty, type conversion)
     *
     * @param mixed $value
     * @return string
     */
    private function normalizeValueForComparison($value)
    {
        if ($value === null) {
            return '';
        }
        
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        
        if (is_numeric($value)) {
            return (string)$value;
        }
        
        if (is_string($value)) {
            return trim($value);
        }
        
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        
        return (string)$value;
    }

    /**
     * Extract field value from answers JSON by searching for field label
     *
     * @param array $answers
     * @param array $templateStructure
     * @param string $fieldLabel
     * @return string|null
     */
    private function extractFieldValueFromAnswers($answers, $templateStructure, $fieldLabel)
    {
        if (empty($answers) || empty($templateStructure)) {
            return null;
        }
        
        // Search through groups and subGroups
        foreach ($templateStructure as $group) {
            // Check fields in group
            if (isset($group['fields']) && is_array($group['fields'])) {
                foreach ($group['fields'] as $field) {
                    $label = $field['label'] ?? '';
                    $fieldId = $field['id'] ?? null;
                    
                    // Match by label ONLY (case-insensitive)
                    // NEVER use customize_field_label for mapping - it's a custom name that can be anything
                    if (!empty($label) && strcasecmp($label, $fieldLabel) === 0) {
                        // Found matching field, get value from answers
                        $groupId = $group['id'] ?? null;
                        if ($groupId && isset($answers[$groupId][$fieldId])) {
                            return $answers[$groupId][$fieldId];
                        }
                    }
                }
            }
            
            // Check subGroups
            if (isset($group['subGroups']) && is_array($group['subGroups'])) {
                foreach ($group['subGroups'] as $subGroup) {
                    if (isset($subGroup['fields']) && is_array($subGroup['fields'])) {
                        foreach ($subGroup['fields'] as $field) {
                            $label = $field['label'] ?? '';
                            $fieldId = $field['id'] ?? null;
                            
                            // Match by label ONLY (case-insensitive)
                            // NEVER use customize_field_label for mapping - it's a custom name that can be anything
                            if (!empty($label) && strcasecmp($label, $fieldLabel) === 0) {
                                // Found matching field in subGroup
                                $subGroupId = $subGroup['id'] ?? null;
                                if ($subGroupId && isset($answers[$subGroupId][$fieldId])) {
                                    return $answers[$subGroupId][$fieldId];
                                }
                                // Also check for pattern {groupLabel}_{index}
                                $groupLabel = $group['label'] ?? $group['id'];
                                for ($i = 0; $i < 10; $i++) {
                                    $subGroupLabel = "{$groupLabel}_{$i}";
                                    if (isset($answers[$subGroupLabel][$fieldId])) {
                                        return $answers[$subGroupLabel][$fieldId];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return null;
    }

    /**
     * Import all role levels from orgtrakker
     *
     * @param string $companyId
     * @return int Count of imported role levels
     */
    private function importAllRoleLevelsFromOrgtrakker($companyId)
    {
        $orgtrakkerCompanyId = $this->getOrgtrakkerCompanyId($companyId);
        $connection = $this->getOrgtrakkerConnection($companyId);
        $importedCount = 0;
        
        // Fetch ALL role levels from orgtrakker
        $stmt = $connection->execute(
            'SELECT level_unique_id, name, rank, custom_fields, template_id
             FROM role_levels 
             WHERE company_id = :company_id AND deleted = false',
            ['company_id' => $orgtrakkerCompanyId]
        );
        $orgtrakkerRoleLevels = $stmt->fetchAll('assoc');
        
        if (empty($orgtrakkerRoleLevels)) {
            return 0;
        }
        
        // Get templates
        $scorecardtrakkerTemplate = $this->getDefaultRoleLevelTemplate($companyId);
        $scorecardtrakkerTemplateStructure = is_string($scorecardtrakkerTemplate->structure) 
            ? json_decode($scorecardtrakkerTemplate->structure, true) 
            : $scorecardtrakkerTemplate->structure;
        
        // Get orgtrakker template
        $orgtrakkerTemplateStmt = $connection->execute(
            'SELECT structure FROM level_templates WHERE company_id = :company_id AND deleted = false LIMIT 1',
            ['company_id' => $orgtrakkerCompanyId]
        );
        $orgtrakkerTemplate = $orgtrakkerTemplateStmt->fetch('assoc');
        $orgtrakkerTemplateStructure = $orgtrakkerTemplate ? json_decode($orgtrakkerTemplate['structure'], true) : [];
        
        $RoleLevelsTable = $this->getTable('RoleLevels', $companyId);
        $updatedCount = 0;
        
        // Get existing role levels with full data for updates (including soft-deleted)
        $existingRoleLevels = $RoleLevelsTable->find()
            ->select(['id', 'level_unique_id', 'name', 'rank', 'custom_fields', 'template_id', 'deleted', 'created_by'])
                ->where([
                    'company_id' => $companyId,
            ])
            ->toArray();
        
        $existingRoleLevelsMap = [];
        $softDeletedRoleLevelsMap = [];
        foreach ($existingRoleLevels as $rl) {
            $key = $rl->level_unique_id ?? '';
            if (!empty($key)) {
                if ($rl->deleted) {
                    // Store soft-deleted role levels separately
                    $softDeletedRoleLevelsMap[$key] = $rl;
                } else {
                    // Store non-deleted role levels
                    $existingRoleLevelsMap[$key] = $rl;
                }
            }
        }
        
        foreach ($orgtrakkerRoleLevels as $roleLevel) {
            $levelUniqueId = $roleLevel['level_unique_id'];
            
            // Check if already exists (non-deleted)
            $existing = $existingRoleLevelsMap[$levelUniqueId] ?? null;
            $isUpdate = $existing !== null;
            
            // Check if soft-deleted role level exists
            $softDeletedRoleLevel = $softDeletedRoleLevelsMap[$levelUniqueId] ?? null;
            $isRestore = $softDeletedRoleLevel !== null && $softDeletedRoleLevel->deleted;
            
            // Map custom_fields - always create structure based on scorecardtrakker template
            $customFields = [];
            if (!empty($roleLevel['custom_fields'])) {
                $orgtrakkerCustomFields = json_decode($roleLevel['custom_fields'], true);
                
                Log::debug("🔍 ROLE LEVEL IMPORT DEBUG - Level: {$levelUniqueId}", [
                    'orgtrakker_custom_fields' => $orgtrakkerCustomFields,
                    'orgtrakker_rank_column' => $roleLevel['rank'] ?? null,
                    'orgtrakker_template_structure_exists' => !empty($orgtrakkerTemplateStructure),
                    'scorecardtrakker_template_structure_exists' => !empty($scorecardtrakkerTemplateStructure),
                ]);
                
                if (is_array($orgtrakkerCustomFields) && !empty($orgtrakkerTemplateStructure) && !empty($scorecardtrakkerTemplateStructure)) {
                    // Map fields from orgtrakker to scorecardtrakker structure
                    // Treat rank/order like any other field - no special handling
                    $customFields = $this->mapFieldValuesByLabel($orgtrakkerCustomFields, $orgtrakkerTemplateStructure, $scorecardtrakkerTemplateStructure, $levelUniqueId);
                    
                    Log::debug("🔍 ROLE LEVEL IMPORT DEBUG - After mapping", [
                        'level_unique_id' => $levelUniqueId,
                        'mapped_custom_fields' => $customFields,
                    ]);
                } elseif (!empty($scorecardtrakkerTemplateStructure)) {
                    // If orgtrakker has no structure or mapping fails, create empty structure based on scorecardtrakker template
                    $customFields = $this->mapFieldValuesByLabel([], [], $scorecardtrakkerTemplateStructure, $levelUniqueId);
                } else {
                    // Fallback: use orgtrakker custom_fields as-is if no scorecardtrakker structure
                    $customFields = $orgtrakkerCustomFields ?? [];
                }
            } elseif (!empty($scorecardtrakkerTemplateStructure)) {
                // If orgtrakker has no custom_fields, create empty structure based on scorecardtrakker template
                $customFields = $this->mapFieldValuesByLabel([], [], $scorecardtrakkerTemplateStructure, $levelUniqueId);
            }
            
            // Create, update, or restore role level
            if ($isUpdate) {
                // Check if data actually changed before counting as update
                $existingCustomFields = is_string($existing->custom_fields) 
                    ? json_decode($existing->custom_fields, true) 
                    : $existing->custom_fields;
                $customFieldsChanged = json_encode($existingCustomFields) !== json_encode($customFields);
                $nameChanged = $existing->name !== $roleLevel['name'];
                $rankChanged = $existing->rank != $roleLevel['rank'];
                
                $hasChanges = $nameChanged || $rankChanged || $customFieldsChanged;
                
                // Update existing role level
                $existing->name = $roleLevel['name'];
                $existing->rank = $roleLevel['rank'];
                $existing->custom_fields = $customFields;
                $existing->modified = date('Y-m-d H:i:s');
                if ($RoleLevelsTable->save($existing)) {
                    // Only count as updated if data actually changed
                    if ($hasChanges) {
                        $updatedCount++;
                    }
                }
            } elseif ($isRestore) {
                // Restore soft-deleted role level
                $softDeletedRoleLevel->deleted = false;
                $softDeletedRoleLevel->name = $roleLevel['name'];
                $softDeletedRoleLevel->rank = $roleLevel['rank'];
                $softDeletedRoleLevel->custom_fields = $customFields;
                $softDeletedRoleLevel->template_id = $scorecardtrakkerTemplate->id;
                $softDeletedRoleLevel->modified = date('Y-m-d H:i:s');
                if ($RoleLevelsTable->save($softDeletedRoleLevel)) {
                    $importedCount++;
                }
            } else {
                // Import new role level
                $entity = $RoleLevelsTable->newEntity([
                    'company_id' => $companyId,
                    'level_unique_id' => $levelUniqueId,
                    'name' => $roleLevel['name'],
                    'rank' => $roleLevel['rank'],
                    'custom_fields' => $customFields,
                    'template_id' => $scorecardtrakkerTemplate->id,
                    'created_by' => $this->Authentication->getIdentity()->get('username') ?? 'system',
                    'created' => date('Y-m-d H:i:s'),
                    'modified' => date('Y-m-d H:i:s'),
                    'deleted' => false,
                ]);
                
                if ($RoleLevelsTable->save($entity)) {
                    $importedCount++;
                }
            }
        }
        
        return ['imported' => $importedCount, 'updated' => $updatedCount];
    }

    /**
     * Import all job roles from orgtrakker
     *
     * @param string $companyId
     * @return int Count of imported job roles
     */
    private function importAllJobRolesFromOrgtrakker($companyId)
    {
        $orgtrakkerCompanyId = $this->getOrgtrakkerCompanyId($companyId);
        $connection = $this->getOrgtrakkerConnection($companyId);
        $importedCount = 0;
        
        // Fetch ALL job roles from orgtrakker
        $stmt = $connection->execute(
            'SELECT job_role_unique_id, answers, template_id
             FROM job_role_template_answers 
             WHERE company_id = :company_id AND deleted = false',
            ['company_id' => $orgtrakkerCompanyId]
        );
        $orgtrakkerJobRoles = $stmt->fetchAll('assoc');
        
        if (empty($orgtrakkerJobRoles)) {
            return 0;
        }
        
        // Get templates
        $scorecardtrakkerTemplate = $this->getDefaultJobRoleTemplate($companyId);
        $scorecardtrakkerTemplateStructure = is_string($scorecardtrakkerTemplate->structure) 
            ? json_decode($scorecardtrakkerTemplate->structure, true) 
            : $scorecardtrakkerTemplate->structure;
        
        // Get orgtrakker template
        $orgtrakkerTemplateStmt = $connection->execute(
            'SELECT structure FROM job_role_templates WHERE company_id = :company_id AND deleted = false LIMIT 1',
            ['company_id' => $orgtrakkerCompanyId]
        );
        $orgtrakkerTemplate = $orgtrakkerTemplateStmt->fetch('assoc');
        $orgtrakkerTemplateStructure = $orgtrakkerTemplate ? json_decode($orgtrakkerTemplate['structure'], true) : [];
        
        $JobRoleTemplateAnswersTable = $this->getTable('JobRoleTemplateAnswers', $companyId);
        $updatedCount = 0;
        
        // Get existing job roles with full data for updates (including soft-deleted)
        $existingJobRoles = $JobRoleTemplateAnswersTable->find()
            ->select(['id', 'job_role_unique_id', 'answers', 'template_id', 'deleted'])
                ->where([
                    'company_id' => $companyId,
            ])
            ->toArray();
        
        $existingJobRolesMap = [];
        $softDeletedJobRolesMap = [];
        foreach ($existingJobRoles as $jr) {
            $key = $jr->job_role_unique_id ?? '';
            if (!empty($key)) {
                if ($jr->deleted) {
                    // Store soft-deleted job roles separately
                    $softDeletedJobRolesMap[$key] = $jr;
                } else {
                    // Store non-deleted job roles
                    $existingJobRolesMap[$key] = $jr;
                }
            }
        }
        
        foreach ($orgtrakkerJobRoles as $jobRole) {
            $jobRoleUniqueId = $jobRole['job_role_unique_id'];
            
            // Check if already exists (non-deleted)
            $existing = $existingJobRolesMap[$jobRoleUniqueId] ?? null;
            
            // Check if soft-deleted job role exists
            $softDeletedJobRole = $softDeletedJobRolesMap[$jobRoleUniqueId] ?? null;
            $isRestore = $softDeletedJobRole !== null && $softDeletedJobRole->deleted;
            
            // Map answers
            $orgtrakkerAnswers = json_decode($jobRole['answers'], true);
            $mappedAnswers = $this->mapFieldValuesByLabel($orgtrakkerAnswers, $orgtrakkerTemplateStructure, $scorecardtrakkerTemplateStructure);
            
            if ($existing) {
                // Check if data actually changed before counting as update
                $existingAnswers = is_string($existing->answers) 
                    ? json_decode($existing->answers, true) 
                    : $existing->answers;
                $answersChanged = json_encode($existingAnswers) !== json_encode($mappedAnswers);
                
                // Update existing job role
                $existing->answers = $mappedAnswers;
                $existing->modified = date('Y-m-d H:i:s');
                if ($JobRoleTemplateAnswersTable->save($existing)) {
                    // Only count as updated if data actually changed
                    if ($answersChanged) {
                        $updatedCount++;
                    }
                }
            } elseif ($isRestore) {
                // Restore soft-deleted job role
                $softDeletedJobRole->deleted = false;
                $softDeletedJobRole->answers = $mappedAnswers;
                $softDeletedJobRole->template_id = $scorecardtrakkerTemplate->id;
                $softDeletedJobRole->modified = date('Y-m-d H:i:s');
                if ($JobRoleTemplateAnswersTable->save($softDeletedJobRole)) {
                    $importedCount++;
                }
            } else {
                // Import new job role
                $entity = $JobRoleTemplateAnswersTable->newEntity([
                    'company_id' => $companyId,
                    'job_role_unique_id' => $jobRoleUniqueId,
                    'template_id' => $scorecardtrakkerTemplate->id,
                    'answers' => $mappedAnswers,
                    'created' => date('Y-m-d H:i:s'),
                    'modified' => date('Y-m-d H:i:s'),
                    'deleted' => false,
                ]);
                
                if ($JobRoleTemplateAnswersTable->save($entity)) {
                    $importedCount++;
                }
            }
        }
        
        return ['imported' => $importedCount, 'updated' => $updatedCount];
    }

    /**
     * Import all job role reporting relationships from orgtrakker
     *
     * @param string $companyId
     * @return int Count of imported relationships
     */
    private function importAllJobRoleReportingRelationships($companyId)
    {
        $orgtrakkerCompanyId = $this->getOrgtrakkerCompanyId($companyId);
        $connection = $this->getOrgtrakkerConnection($companyId);
        $importedCount = 0;
        
        // Fetch ALL job role reporting relationships from orgtrakker
        $stmt = $connection->execute(
            'SELECT job_role, reporting_to
             FROM job_role_reporting_relationships 
             WHERE company_id = :company_id AND deleted = false',
            ['company_id' => $orgtrakkerCompanyId]
        );
        $orgtrakkerRelationships = $stmt->fetchAll('assoc');
        
        if (empty($orgtrakkerRelationships)) {
            return 0;
        }
        
        // Get existing job roles in scorecardtrakker
        $JobRoleTemplateAnswersTable = $this->getTable('JobRoleTemplateAnswers', $companyId);
        $existingJobRoles = $JobRoleTemplateAnswersTable->find()
            ->select(['job_role_unique_id'])
            ->where([
                'company_id' => $companyId,
                'deleted' => false,
            ])
            ->toArray();
        
        $existingJobRoleIds = [];
        foreach ($existingJobRoles as $jr) {
            $existingJobRoleIds[$jr->job_role_unique_id] = true;
        }
        
        // Get existing relationships
        $JobRoleReportingRelationshipsTable = $this->getTable('JobRoleReportingRelationships', $companyId);
        $existingRelationships = $JobRoleReportingRelationshipsTable->find()
            ->select(['id', 'job_role', 'reporting_to'])
            ->where([
                'company_id' => $companyId,
                'deleted' => false,
            ])
            ->toArray();
        
        // Create map by job_role for quick lookup
        $existingRelationshipsMap = [];
        foreach ($existingRelationships as $rel) {
            $existingRelationshipsMap[$rel->job_role] = $rel;
        }
        
        $updatedCount = 0;
        foreach ($orgtrakkerRelationships as $relationship) {
            $jobRole = $relationship['job_role'];
            $reportingTo = $relationship['reporting_to'];
            
            // Check if both job roles exist
            if (!isset($existingJobRoleIds[$jobRole]) || !isset($existingJobRoleIds[$reportingTo])) {
                continue; // Skip if job roles don't exist
            }
            
            // Check if relationship already exists
            $existingRelationship = $existingRelationshipsMap[$jobRole] ?? null;
            
            if ($existingRelationship) {
                // Update if reporting_to changed
                if ($existingRelationship->reporting_to !== $reportingTo) {
                    $existingRelationship->reporting_to = $reportingTo;
                    $existingRelationship->modified = date('Y-m-d H:i:s');
                    if ($JobRoleReportingRelationshipsTable->save($existingRelationship)) {
                        $updatedCount++;
                    }
                }
            } else {
                // Import new relationship
                $entity = $JobRoleReportingRelationshipsTable->newEntity([
                    'company_id' => $companyId,
                    'job_role' => $jobRole,
                    'reporting_to' => $reportingTo,
                    'created' => date('Y-m-d H:i:s'),
                    'modified' => date('Y-m-d H:i:s'),
                    'deleted' => false,
                ]);
                
                if ($JobRoleReportingRelationshipsTable->save($entity)) {
                    $importedCount++;
                    $existingRelationshipsMap[$jobRole] = $entity; // Track newly imported
                }
            }
        }
        
        return ['imported' => $importedCount, 'updated' => $updatedCount];
    }

    /**
     * Import all employees from orgtrakker
     *
     * @param string $companyId
     * @return int Count of imported employees
     */
    private function importAllEmployeesFromOrgtrakker($companyId)
    {
        $orgtrakkerCompanyId = $this->getOrgtrakkerCompanyId($companyId);
        $connection = $this->getOrgtrakkerConnection($companyId);
        $importedCount = 0;
        
        // Fetch ALL employees from orgtrakker
        $stmt = $connection->execute(
            'SELECT company_id, employee_unique_id, employee_id, username, answers, template_id
             FROM employee_template_answers 
             WHERE company_id = :company_id AND deleted = false',
            ['company_id' => $orgtrakkerCompanyId]
        );
        $orgtrakkerEmployees = $stmt->fetchAll('assoc');
        
        if (empty($orgtrakkerEmployees)) {
            return 0;
        }
        
        // Get templates
        $scorecardtrakkerTemplate = $this->getDefaultEmployeeTemplate($companyId);
        $scorecardtrakkerTemplateStructure = is_string($scorecardtrakkerTemplate->structure) 
            ? json_decode($scorecardtrakkerTemplate->structure, true) 
            : $scorecardtrakkerTemplate->structure;
        
        // Get orgtrakker template
        $orgtrakkerTemplateStmt = $connection->execute(
            'SELECT structure FROM employee_templates WHERE company_id = :company_id AND name = :name AND deleted = false LIMIT 1',
            ['company_id' => $orgtrakkerCompanyId, 'name' => 'employee']
        );
        $orgtrakkerTemplate = $orgtrakkerTemplateStmt->fetch('assoc');
        $orgtrakkerTemplateStructure = $orgtrakkerTemplate ? json_decode($orgtrakkerTemplate['structure'], true) : [];
        
        // Get existing employees with full data for updates (including soft-deleted)
        $EmployeeTemplateAnswersTable = $this->getTable('EmployeeTemplateAnswers', $companyId);
        $existingEmployees = $EmployeeTemplateAnswersTable->find()
            ->select(['id', 'username', 'employee_unique_id', 'answers', 'deleted', 'employee_id', 'template_id', 'created_by'])
            ->where([
                'company_id' => $companyId,
            ])
            ->toArray();
        
        $existingEmployeesMap = [];
        $softDeletedEmployeesMap = [];
        foreach ($existingEmployees as $emp) {
            $key = $emp->employee_unique_id ?? $emp->username ?? '';
            if (!empty($key)) {
                if ($emp->deleted) {
                    // Store soft-deleted employees separately
                    $softDeletedEmployeesMap[$key] = $emp;
                } else {
                    // Store non-deleted employees
                    $existingEmployeesMap[$key] = $emp;
                }
            }
        }
        
        // Get existing job roles
        $JobRoleTemplateAnswersTable = $this->getTable('JobRoleTemplateAnswers', $companyId);
        $existingJobRoles = $JobRoleTemplateAnswersTable->find()
            ->select(['job_role_unique_id'])
            ->where([
                'company_id' => $companyId,
                'deleted' => false,
            ])
            ->toArray();
        
        $existingJobRoleIds = [];
        foreach ($existingJobRoles as $jr) {
            $existingJobRoleIds[$jr->job_role_unique_id] = true;
        }
        
        $currentUser = $this->Authentication->getIdentity();
        $createdBy = $currentUser ? ($currentUser->get('username') ?? 'system') : 'system';
        $updatedCount = 0;
        
        foreach ($orgtrakkerEmployees as $employee) {
            $username = $employee['username'] ?? '';
            $employeeUniqueId = $employee['employee_unique_id'] ?? '';
            
            // Check if already exists (non-deleted)
            $existingEmployee = $existingEmployeesMap[$employeeUniqueId] ?? $existingEmployeesMap[$username] ?? null;
            $isUpdate = $existingEmployee !== null;
            
            // Check if soft-deleted employee exists
            $softDeletedEmployee = $softDeletedEmployeesMap[$employeeUniqueId] ?? $softDeletedEmployeesMap[$username] ?? null;
            $isRestore = $softDeletedEmployee !== null && $softDeletedEmployee->deleted;
            
            // Map answers
            $orgtrakkerAnswers = json_decode($employee['answers'], true);
            
            // Extract report_to_employee_unique_id FIRST, before mapping
            // This ensures we have the value even if mapping fails
            $reportToEmployeeUniqueId = $this->extractFieldValueFromAnswers($orgtrakkerAnswers, $orgtrakkerTemplateStructure, 'Reports To');
            error_log("🔍 EMPLOYEE IMPORT - Pre-mapping extraction - Employee: {$employeeUniqueId}, Reports To value: " . ($reportToEmployeeUniqueId !== null ? (string)$reportToEmployeeUniqueId : 'NULL'));
            
            // Pass employee unique ID as debug identifier to track Reports To field mapping
            $mappedAnswers = $this->mapFieldValuesByLabel($orgtrakkerAnswers, $orgtrakkerTemplateStructure, $scorecardtrakkerTemplateStructure, $employeeUniqueId);
            
            // Extract job_role_unique_id from orgtrakker answers
            // report_to_employee_unique_id was already extracted above
            $jobRoleUniqueId = $this->extractFieldValueFromAnswers($orgtrakkerAnswers, $orgtrakkerTemplateStructure, 'Job Role');
            
            // If job_role_unique_id doesn't exist in scorecardtrakker, set to empty in mapped answers
            if ($jobRoleUniqueId && !isset($existingJobRoleIds[$jobRoleUniqueId])) {
                // Find the field in scorecardtrakker template and set to empty
                foreach ($scorecardtrakkerTemplateStructure as $group) {
                    foreach ($group['fields'] ?? [] as $field) {
                        // Use 'label' ONLY (not 'customize_field_label') for mapping
                        $fieldLabel = $field['label'] ?? '';
                        if (!empty($fieldLabel) && strcasecmp($fieldLabel, 'Job Role') === 0) {
                            $groupId = $group['id'];
                            $fieldId = $field['id'];
                            if (isset($mappedAnswers[$groupId][$fieldId])) {
                                $mappedAnswers[$groupId][$fieldId] = '';
                            }
                            break 2;
                        }
                    }
                }
                $jobRoleUniqueId = null;
            }
            
            // Verify and ensure "Reports To" field value is in mapped answers
            // This is a safety check in case the mapping didn't work for some reason
            error_log("🔍 REPORTS TO VERIFICATION - Employee: {$employeeUniqueId}, Extracted value: " . ($reportToEmployeeUniqueId !== null ? (string)$reportToEmployeeUniqueId : 'NULL'));
            if ($reportToEmployeeUniqueId !== null && $reportToEmployeeUniqueId !== '') {
                $reportsToFoundInMappedAnswers = false;
                foreach ($scorecardtrakkerTemplateStructure as $group) {
                    // Check regular fields
                    foreach ($group['fields'] ?? [] as $field) {
                        $fieldLabel = $field['label'] ?? '';
                        if (!empty($fieldLabel) && strcasecmp($fieldLabel, 'Reports To') === 0) {
                            $groupId = $group['id'];
                            $fieldId = $field['id'];
                            $currentValue = $mappedAnswers[$groupId][$fieldId] ?? null;
                            
                            error_log("🔍 REPORTS TO VERIFICATION - Found field in group {$groupId}, field {$fieldId}, current value: " . ($currentValue !== null ? (string)$currentValue : 'NULL'));
                            
                            // ALWAYS set the value if we have an extracted value, even if current value exists
                            // This ensures the Reports To field is always populated from orgtrakker
                            if (!isset($mappedAnswers[$groupId])) {
                                $mappedAnswers[$groupId] = [];
                            }
                            $mappedAnswers[$groupId][$fieldId] = $reportToEmployeeUniqueId;
                            Log::debug("🔍 EMPLOYEE IMPORT - Reports To value explicitly set in mapped answers", [
                                'employee_unique_id' => $employeeUniqueId,
                                'group_id' => $groupId,
                                'field_id' => $fieldId,
                                'value' => $reportToEmployeeUniqueId,
                                'previous_value' => $currentValue,
                            ]);
                            error_log("🔍 REPORTS TO VERIFICATION - SET VALUE in group {$groupId}, field {$fieldId} to: {$reportToEmployeeUniqueId} (previous: " . ($currentValue !== null ? (string)$currentValue : 'NULL') . ")");
                            $reportsToFoundInMappedAnswers = true;
                            break 2;
                        }
                    }
                    
                    // Check subgroups
                    if (!$reportsToFoundInMappedAnswers && isset($group['subGroups']) && is_array($group['subGroups'])) {
                        $groupLabel = $group['label'] ?? $group['id'];
                        foreach ($group['subGroups'] as $index => $subGroup) {
                            foreach ($subGroup['fields'] ?? [] as $field) {
                                $fieldLabel = $field['label'] ?? '';
                                if (!empty($fieldLabel) && strcasecmp($fieldLabel, 'Reports To') === 0) {
                                    $subGroupLabel = "{$groupLabel}_{$index}";
                                    $fieldId = $field['id'];
                                    $currentValue = $mappedAnswers[$subGroupLabel][$fieldId] ?? null;
                                    
                                    error_log("🔍 REPORTS TO VERIFICATION - Found field in subgroup {$subGroupLabel}, field {$fieldId}, current value: " . ($currentValue !== null ? (string)$currentValue : 'NULL'));
                                    
                                    // ALWAYS set the value if we have an extracted value, even if current value exists
                                    if (!isset($mappedAnswers[$subGroupLabel])) {
                                        $mappedAnswers[$subGroupLabel] = [];
                                    }
                                    $mappedAnswers[$subGroupLabel][$fieldId] = $reportToEmployeeUniqueId;
                                    Log::debug("🔍 EMPLOYEE IMPORT - Reports To value explicitly set in mapped answers (subgroup)", [
                                        'employee_unique_id' => $employeeUniqueId,
                                        'subgroup_label' => $subGroupLabel,
                                        'field_id' => $fieldId,
                                        'value' => $reportToEmployeeUniqueId,
                                        'previous_value' => $currentValue,
                                    ]);
                                    error_log("🔍 REPORTS TO VERIFICATION - SET VALUE in subgroup {$subGroupLabel}, field {$fieldId} to: {$reportToEmployeeUniqueId} (previous: " . ($currentValue !== null ? (string)$currentValue : 'NULL') . ")");
                                    $reportsToFoundInMappedAnswers = true;
                                    break 3;
                                }
                            }
                        }
                    }
                }
                
                if (!$reportsToFoundInMappedAnswers) {
                    error_log("🔍 REPORTS TO VERIFICATION - WARNING: Reports To field not found in scorecardtrakker template structure!");
                }
            } else {
                error_log("🔍 REPORTS TO VERIFICATION - Extracted value is null or empty, skipping verification");
            }
            
            // Log mapped answers before saving
            error_log("🔍 EMPLOYEE IMPORT - Before save - Employee: {$employeeUniqueId}, Mapped answers: " . json_encode($mappedAnswers));
            
            // Check if Reports To is in mapped answers
            $reportsToInMappedAnswers = false;
            $reportsToValue = null;
            foreach ($mappedAnswers as $groupId => $groupData) {
                if (is_array($groupData)) {
                    foreach ($groupData as $fieldId => $fieldValue) {
                        // We need to check if this field ID corresponds to Reports To
                        // For now, just log all field values
                        if ($fieldId && is_string($fieldValue) && strlen($fieldValue) > 0) {
                            error_log("🔍 EMPLOYEE IMPORT - Field in mapped answers - Group: {$groupId}, Field: {$fieldId}, Value: {$fieldValue}");
                        }
                    }
                }
            }
            
            // Create, update, or restore employee
            if ($isRestore) {
                // Restore soft-deleted employee
                $entity = $EmployeeTemplateAnswersTable->get($softDeletedEmployee->id);
                $entity->deleted = false;
                $entity->answers = $mappedAnswers;
                $entity->employee_id = $employee['employee_id'] ?? $entity->employee_id;
                $entity->username = $username;
                $entity->template_id = $scorecardtrakkerTemplate->id;
                $entity->report_to_employee_unique_id = $reportToEmployeeUniqueId ?: null;
                $entity->created_by = $createdBy;
                $entity->modified = date('Y-m-d H:i:s');
                
                if ($EmployeeTemplateAnswersTable->save($entity)) {
                    $importedCount++;
                }
            } elseif ($isUpdate) {
                // Check if data actually changed before counting as update
                $existingAnswers = is_string($existingEmployee->answers) 
                    ? json_decode($existingEmployee->answers, true) 
                    : $existingEmployee->answers;
                $answersChanged = json_encode($existingAnswers) !== json_encode($mappedAnswers);
                $reportToChanged = ($existingEmployee->report_to_employee_unique_id ?: null) !== ($reportToEmployeeUniqueId ?: null);
                
                $hasChanges = $answersChanged || $reportToChanged;
                
                // Update existing employee
                $entity = $EmployeeTemplateAnswersTable->get($existingEmployee->id);
                $entity->answers = $mappedAnswers;
                $entity->report_to_employee_unique_id = $reportToEmployeeUniqueId ?: null;
                $entity->modified = date('Y-m-d H:i:s');
                
                if ($EmployeeTemplateAnswersTable->save($entity)) {
                    // Only count as updated if data actually changed
                    if ($hasChanges) {
                        $updatedCount++;
                    }
                }
            } else {
                // Create new employee
                $entity = $EmployeeTemplateAnswersTable->newEntity([
                    'company_id' => $companyId,
                    'employee_unique_id' => $employeeUniqueId,
                    'employee_id' => $employee['employee_id'] ?? '',
                    'username' => $username,
                    'template_id' => $scorecardtrakkerTemplate->id,
                    'answers' => $mappedAnswers,
                    'report_to_employee_unique_id' => $reportToEmployeeUniqueId ?: null,
                    'created_by' => $createdBy,
                    'created' => date('Y-m-d H:i:s'),
                    'modified' => date('Y-m-d H:i:s'),
                    'deleted' => false,
                ]);
                
                if ($EmployeeTemplateAnswersTable->save($entity)) {
                    $importedCount++;
                }
            }
        }
        
        // Create user company mappings for all imported/updated employees
        try {
            $mappingService = $this->getCompanyMappingService();
            $orgtrakkerCompanyId = $this->getOrgtrakkerCompanyId($companyId);
            
            // Get all employees that were just imported/updated
            $importedEmployees = $EmployeeTemplateAnswersTable->find()
                ->select(['username', 'employee_unique_id'])
                ->where([
                    'company_id' => $companyId,
                    'deleted' => false
                ])
                ->toArray();
            
            $mappingsCreated = 0;
            $mappingsSkipped = 0;
            $usersNotFound = 0;
            
            // Get Users table from workmatica database (default connection)
            $UsersTable = $this->getTable('Users');
            
            foreach ($importedEmployees as $employee) {
                $username = $employee->username ?? '';
                if (empty($username)) {
                    continue;
                }
                
                // Get user from workmatica Users table (users are stored centrally)
                try {
                    $orgtrakkerUser = $UsersTable->find()
                        ->where([
                            'username' => $username,
                            'company_id' => $orgtrakkerCompanyId,
                            'deleted' => false
                        ])
                        ->first();
                    
                    if ($orgtrakkerUser) {
                        $userId = (int)$orgtrakkerUser->id;
                        $sourceCompanyId = (int)$orgtrakkerUser->company_id;
                        
                        // Create user company mapping
                        if ($mappingService->createUserCompanyMapping(
                            $userId,
                            $username,
                            $sourceCompanyId,
                            (int)$companyId,
                            'scorecardtrakker'
                        )) {
                            $mappingsCreated++;
                            Log::debug('User company mapping created', [
                                'user_id' => $userId,
                                'username' => $username,
                                'source_company_id' => $sourceCompanyId,
                                'mapped_company_id' => $companyId
                            ]);
                        } else {
                            $mappingsSkipped++;
                            Log::warning('Failed to create user company mapping', [
                                'user_id' => $userId,
                                'username' => $username,
                                'source_company_id' => $sourceCompanyId,
                                'mapped_company_id' => $companyId
                            ]);
                        }
                    } else {
                        $usersNotFound++;
                        Log::debug('User not found in workmatica database for mapping creation', [
                            'username' => $username,
                            'orgtrakker_company_id' => $orgtrakkerCompanyId
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Error creating user company mapping during import: ' . $e->getMessage(), [
                        'username' => $username,
                        'company_id' => $companyId,
                        'orgtrakker_company_id' => $orgtrakkerCompanyId,
                        'trace' => $e->getTraceAsString()
                    ]);
                    // Continue with next employee even if mapping fails
                }
            }
            
            Log::info('User company mappings created during employee import', [
                'company_id' => $companyId,
                'mappings_created' => $mappingsCreated,
                'mappings_skipped' => $mappingsSkipped,
                'users_not_found' => $usersNotFound,
                'total_employees' => count($importedEmployees)
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail the import
            Log::error('Error creating user company mappings during employee import: ' . $e->getMessage(), [
                'company_id' => $companyId,
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        return ['imported' => $importedCount, 'updated' => $updatedCount];
    }

    /**
     * Import all employee reporting relationships from orgtrakker
     *
     * @param string $companyId
     * @return int Count of imported relationships
     */
    private function importAllEmployeeReportingRelationships($companyId)
    {
        $orgtrakkerCompanyId = $this->getOrgtrakkerCompanyId($companyId);
        $connection = $this->getOrgtrakkerConnection($companyId);
        $importedCount = 0;
        
        // Fetch ALL employee reporting relationships from orgtrakker
        $stmt = $connection->execute(
            'SELECT employee_unique_id, report_to_employee_unique_id, employee_first_name, employee_last_name, 
                    reporting_manager_first_name, reporting_manager_last_name, start_date, end_date, created_by
             FROM employee_reporting_relationships 
             WHERE company_id = :company_id AND deleted = false',
            ['company_id' => $orgtrakkerCompanyId]
        );
        $orgtrakkerRelationships = $stmt->fetchAll('assoc');
        
        if (empty($orgtrakkerRelationships)) {
            return 0;
        }
        
        // Get existing employees in scorecardtrakker
        $EmployeeTemplateAnswersTable = $this->getTable('EmployeeTemplateAnswers', $companyId);
        $existingEmployees = $EmployeeTemplateAnswersTable->find()
            ->select(['employee_unique_id'])
            ->where([
                'company_id' => $companyId,
                'deleted' => false,
            ])
            ->toArray();
        
        $existingEmployeeIds = [];
        foreach ($existingEmployees as $emp) {
            $existingEmployeeIds[$emp->employee_unique_id] = true;
        }
        
        // Get existing relationships
        $EmployeeReportingRelationshipsTable = $this->getTable('EmployeeReportingRelationships', $companyId);
        $existingRelationships = $EmployeeReportingRelationshipsTable->find()
            ->select(['id', 'employee_unique_id', 'report_to_employee_unique_id'])
            ->where([
                'company_id' => $companyId,
                'deleted' => false,
            ])
            ->toArray();
        
        // Create map by employee_unique_id for quick lookup
        $existingRelationshipsMap = [];
        foreach ($existingRelationships as $rel) {
            $existingRelationshipsMap[$rel->employee_unique_id] = $rel;
        }
        
        $currentUser = $this->Authentication->getIdentity();
        $createdBy = $currentUser ? ($currentUser->get('username') ?? 'system') : 'system';
        $updatedCount = 0;
        
        foreach ($orgtrakkerRelationships as $relationship) {
            $employeeUniqueId = $relationship['employee_unique_id'];
            $reportToEmployeeUniqueId = $relationship['report_to_employee_unique_id'];
            
            // Check if both employees exist
            if (!isset($existingEmployeeIds[$employeeUniqueId]) || 
                ($reportToEmployeeUniqueId && !isset($existingEmployeeIds[$reportToEmployeeUniqueId]))) {
                continue; // Skip if employees don't exist
            }
            
            // Check if relationship already exists
            $existingRelationship = $existingRelationshipsMap[$employeeUniqueId] ?? null;
            
            if ($existingRelationship) {
                // Check if any data actually changed before counting as update
                $reportToChanged = ($existingRelationship->report_to_employee_unique_id ?: null) !== ($reportToEmployeeUniqueId ?: null);
                $employeeFirstNameChanged = ($existingRelationship->employee_first_name ?? '') !== ($relationship['employee_first_name'] ?? '');
                $employeeLastNameChanged = ($existingRelationship->employee_last_name ?? '') !== ($relationship['employee_last_name'] ?? '');
                $managerFirstNameChanged = ($existingRelationship->reporting_manager_first_name ?: null) !== ($relationship['reporting_manager_first_name'] ?: null);
                $managerLastNameChanged = ($existingRelationship->reporting_manager_last_name ?: null) !== ($relationship['reporting_manager_last_name'] ?: null);
                $startDateChanged = ($existingRelationship->start_date ?: null) !== ($relationship['start_date'] ?: null);
                $endDateChanged = ($existingRelationship->end_date ?: null) !== ($relationship['end_date'] ?: null);
                
                $hasChanges = $reportToChanged || $employeeFirstNameChanged || $employeeLastNameChanged || 
                              $managerFirstNameChanged || $managerLastNameChanged || $startDateChanged || $endDateChanged;
                
                // Update if any field changed
                if ($hasChanges) {
                    $existingRelationship->report_to_employee_unique_id = $reportToEmployeeUniqueId ?: null;
                    $existingRelationship->employee_first_name = $relationship['employee_first_name'] ?? '';
                    $existingRelationship->employee_last_name = $relationship['employee_last_name'] ?? '';
                    $existingRelationship->reporting_manager_first_name = $relationship['reporting_manager_first_name'] ?? null;
                    $existingRelationship->reporting_manager_last_name = $relationship['reporting_manager_last_name'] ?? null;
                    $existingRelationship->start_date = $relationship['start_date'] ?? null;
                    $existingRelationship->end_date = $relationship['end_date'] ?? null;
                    $existingRelationship->modified = date('Y-m-d H:i:s');
                    if ($EmployeeReportingRelationshipsTable->save($existingRelationship)) {
                        // Only count as updated if data actually changed
                        $updatedCount++;
                        
                        // Also update report_to_employee_unique_id in employee_template_answers if it changed
                        if ($reportToChanged) {
                            $employee = $EmployeeTemplateAnswersTable->find()
                                ->where([
                                    'company_id' => $companyId,
                                    'employee_unique_id' => $employeeUniqueId,
                                    'deleted' => false,
                                ])
                                ->first();
                            
                            if ($employee) {
                                $employee->report_to_employee_unique_id = $reportToEmployeeUniqueId ?: null;
                                $employee->modified = date('Y-m-d H:i:s');
                                $EmployeeTemplateAnswersTable->save($employee);
                            }
                        }
                    }
                }
            } else {
                // Import new relationship
                $entity = $EmployeeReportingRelationshipsTable->newEntity([
                    'company_id' => $companyId,
                    'employee_unique_id' => $employeeUniqueId,
                    'report_to_employee_unique_id' => $reportToEmployeeUniqueId ?: null,
                    'employee_first_name' => $relationship['employee_first_name'] ?? '',
                    'employee_last_name' => $relationship['employee_last_name'] ?? '',
                    'reporting_manager_first_name' => $relationship['reporting_manager_first_name'] ?? null,
                    'reporting_manager_last_name' => $relationship['reporting_manager_last_name'] ?? null,
                    'start_date' => $relationship['start_date'] ?? null,
                    'end_date' => $relationship['end_date'] ?? null,
                    'created_by' => $createdBy,
                    'created' => date('Y-m-d H:i:s'),
                    'modified' => date('Y-m-d H:i:s'),
                    'deleted' => false,
                ]);
                
                if ($EmployeeReportingRelationshipsTable->save($entity)) {
                    $importedCount++;
                    $existingRelationshipsMap[$employeeUniqueId] = $entity; // Track newly imported
                    
                    // Also update report_to_employee_unique_id in employee_template_answers
                    $employee = $EmployeeTemplateAnswersTable->find()
                        ->where([
                            'company_id' => $companyId,
                            'employee_unique_id' => $employeeUniqueId,
                            'deleted' => false,
                        ])
                        ->first();
                    
                    if ($employee) {
                        $employee->report_to_employee_unique_id = $reportToEmployeeUniqueId ?: null;
                        $employee->modified = date('Y-m-d H:i:s');
                        $EmployeeTemplateAnswersTable->save($employee);
                    }
                }
            }
        }
        
        return ['imported' => $importedCount, 'updated' => $updatedCount];
    }

    /**
     * Import employees from orgtrakker
     *
     * @return \Cake\Http\Response
     */
    public function importOrgtrakkerEmployees()
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

        // Require admin access for importing employees
        $adminCheck = $this->requireAdmin();
        if ($adminCheck !== null) {
            return $adminCheck;
        }

        $companyId = $this->getCompanyId($authResult);
        $data = $this->request->getData();
        $employeeUniqueIds = $data['employee_ids'] ?? [];

        if (empty($employeeUniqueIds) || !is_array($employeeUniqueIds)) {
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Employee IDs are required',
                ]));
        }

        try {
            // Get default employee template
            $template = $this->getDefaultEmployeeTemplate($companyId);
            $templateStructure = $template->structure;
            $emptyAnswers = $this->generateEmptyAnswersFromTemplate($templateStructure);
            
            $EmployeeTemplateAnswersTable = $this->getTable('EmployeeTemplateAnswers', $companyId);
            $connection = $EmployeeTemplateAnswersTable->getConnection();
            
            $importedCount = 0;
            $failedEmployees = [];
            $currentUser = $this->Authentication->getIdentity();
            $createdBy = $currentUser ? ($currentUser->get('username') ?? 'system') : 'system';
            
            foreach ($employeeUniqueIds as $employeeUniqueId) {
                try {
                    // Fetch employee from orgtrakker
                    $orgtrakkerEmployee = $this->getOrgtrakkerEmployee($employeeUniqueId, (string)$companyId);
                    
                    if (!$orgtrakkerEmployee) {
                        $failedEmployees[] = [
                            'employee_unique_id' => $employeeUniqueId,
                            'reason' => 'Employee not found in orgtrakker database',
                        ];
                        continue;
                    }
                    
                    $username = $orgtrakkerEmployee['username'] ?? '';
                    $employeeId = $orgtrakkerEmployee['employee_id'] ?? '';
                    
                    // Check if already imported (non-deleted)
                    if ($this->checkEmployeeAlreadyImported($companyId, $username, $employeeUniqueId)) {
                        $failedEmployees[] = [
                            'employee_unique_id' => $employeeUniqueId,
                            'username' => $username,
                            'reason' => 'Employee already imported',
                        ];
                        continue;
                    }
                    
                    // Check if soft-deleted employee exists
                    $softDeletedEmployee = $this->findEmployeeIncludingDeleted($companyId, $username, $employeeUniqueId);
                    
                    // Start transaction for this employee
                    $connection->begin();
                    
                    try {
                        if ($softDeletedEmployee && $softDeletedEmployee->deleted) {
                            // Restore soft-deleted employee
                            $softDeletedEmployee->deleted = false;
                            $softDeletedEmployee->employee_id = $employeeId;
                            $softDeletedEmployee->username = $username;
                            $softDeletedEmployee->template_id = $template->id;
                            $softDeletedEmployee->answers = $emptyAnswers;
                            $softDeletedEmployee->report_to_employee_unique_id = null;
                            $softDeletedEmployee->created_by = $createdBy;
                            $softDeletedEmployee->modified = date('Y-m-d H:i:s');
                            
                            if (!$EmployeeTemplateAnswersTable->save($softDeletedEmployee)) {
                                throw new Exception('Failed to restore employee record');
                            }
                        } else {
                            // Create new employee record
                            $answerEntity = $EmployeeTemplateAnswersTable->newEntity([
                                'company_id' => $companyId,
                                'employee_unique_id' => $employeeUniqueId,
                                'employee_id' => $employeeId,
                                'username' => $username,
                                'template_id' => $template->id,
                                'answers' => $emptyAnswers,
                                'report_to_employee_unique_id' => null,
                                'created_by' => $createdBy,
                                'created' => date('Y-m-d H:i:s'),
                                'modified' => date('Y-m-d H:i:s'),
                                'deleted' => false,
                            ]);
                            
                            if (!$EmployeeTemplateAnswersTable->save($answerEntity)) {
                                throw new Exception('Failed to save employee record');
                            }
                        }
                        
                        $connection->commit();
                        $importedCount++;
                        
                    } catch (\Exception $e) {
                        $connection->rollback();
                        throw $e;
                    }
                    
                } catch (\Exception $e) {
                    Log::error('Error importing employee: ' . $e->getMessage(), [
                        'employee_unique_id' => $employeeUniqueId,
                        'trace' => $e->getTraceAsString()
                    ]);
                    $failedEmployees[] = [
                        'employee_unique_id' => $employeeUniqueId,
                        'reason' => $e->getMessage(),
                    ];
                }
            }
            
            // Log bulk import action
            if ($importedCount > 0) {
                $auditUserData = AuditHelper::extractUserData($authResult);
                
                // Override company_id and username with the correct values from controller
                $authData = $authResult->getData();
                $auditUsername = null;
                if ($authData instanceof \ArrayObject || is_array($authData)) {
                    $auditUsername = $authData['username'] ?? $authData['sub'] ?? null;
                } elseif (is_object($authData)) {
                    $auditUsername = $authData->username ?? $authData->sub ?? null;
                }
                
                $auditUserData['company_id'] = (string)$companyId;
                $auditUserData['username'] = $auditUsername ?? $auditUserData['username'] ?? 'system';
                $auditUserData['user_id'] = $authData->id ?? $authData['id'] ?? $authData->sub ?? $authData['sub'] ?? $auditUserData['user_id'] ?? 0;
                
                // If we now have a user_id but full_name wasn't fetched, fetch it now
                if (!empty($auditUserData['user_id']) && (empty($auditUserData['full_name']) || $auditUserData['full_name'] === 'Unknown')) {
                    try {
                        $usersTable = TableRegistry::getTableLocator()->get('Users', [
                            'connection' => ConnectionManager::get('default')
                        ]);
                        
                        $user = $usersTable->find()
                            ->select(['first_name', 'last_name'])
                            ->where(['id' => $auditUserData['user_id']])
                            ->first();
                        
                        if ($user) {
                            $firstName = $user->first_name ?? '';
                            $lastName = $user->last_name ?? '';
                            $fullName = trim($firstName . ' ' . $lastName);
                            if (!empty($fullName)) {
                                $auditUserData['full_name'] = $fullName;
                                $auditUserData['employee_name'] = $fullName;
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Error fetching user full name in controller: ' . $e->getMessage());
                    }
                }
                
                // Ensure full_name is preserved (don't overwrite it if it was already fetched)
                if (empty($auditUserData['full_name']) && !empty($auditUserData['employee_name'])) {
                    $auditUserData['full_name'] = $auditUserData['employee_name'];
                }
                
                // Try to log audit action, but don't fail the import if it fails
                $auditDebug = [];
                $auditDebug['audit_service_created'] = false;
                $auditDebug['audit_log_attempted'] = false;
                $auditDebug['audit_log_success'] = false;
                $auditDebug['audit_log_error'] = null;
                $auditDebug['audit_log_id'] = null;
                $auditDebug['company_id'] = (string)$companyId;
                $auditDebug['company_id_type'] = gettype($companyId);
                
                try {
                    // Ensure companyId is a valid string (handle null, int, etc.)
                    if ($companyId === null || $companyId === '') {
                        $auditCompanyId = 'default';
                    } else {
                        $auditCompanyId = (string)$companyId;
                    }
                    $auditDebug['audit_company_id_used'] = $auditCompanyId;
                    $auditService = new \App\Service\AuditService($auditCompanyId);
                    $auditDebug['audit_service_created'] = true;
                } catch (\Throwable $e) {
                    // Log error creating audit service but don't fail the import
                    $auditDebug['audit_service_error'] = $e->getMessage();
                    $auditDebug['audit_service_error_file'] = $e->getFile();
                    $auditDebug['audit_service_error_line'] = $e->getLine();
                    $auditDebug['audit_service_error_trace'] = substr($e->getTraceAsString(), 0, 2000); // Limit trace length
                    Log::error('Error creating AuditService for bulk import audit: ' . $e->getMessage(), [
                        'trace' => $e->getTraceAsString(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'company_id' => $companyId,
                        'company_id_type' => gettype($companyId)
                    ]);
                    $auditService = null;
                }
                
                if ($auditService !== null && $importedCount > 0) {
                    $auditDebug['audit_log_attempted'] = true;
                    $auditDebug['imported_count'] = $importedCount;
                    try {
                        $result = $auditService->logBulkImportAction(
                            'employee',
                            $importedCount,
                            $auditUserData,
                            $this->request->getParsedBody() ?? [],
                            ['imported_count' => $importedCount, 'failed_count' => count($failedEmployees)]
                        );
                        
                        if ($result !== null) {
                            $auditDebug['audit_log_success'] = true;
                            $auditDebug['audit_log_id'] = $result->id;
                        } else {
                            $auditDebug['audit_log_success'] = false;
                            $auditDebug['audit_log_error'] = 'logBulkImportAction returned null';
                        }
                    } catch (\Throwable $e) {
                        // Log audit error but don't fail the import
                        $auditDebug['audit_log_error'] = $e->getMessage();
                        $auditDebug['audit_log_error_file'] = $e->getFile();
                        $auditDebug['audit_log_error_line'] = $e->getLine();
                        Log::error('Error logging bulk import audit: ' . $e->getMessage(), [
                            'trace' => $e->getTraceAsString(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine()
                        ]);
                    }
                } else {
                    $auditDebug['skip_reason'] = $auditService === null ? 'audit_service_is_null' : 'imported_count_is_zero';
                    $auditDebug['imported_count'] = $importedCount;
                }
            } else {
                $auditDebug['skip_reason'] = 'imported_count_is_zero';
                $auditDebug['imported_count'] = $importedCount;
            }
            
            $message = "Successfully imported {$importedCount} employee(s)";
            if (!empty($failedEmployees)) {
                $message .= ". " . count($failedEmployees) . " employee(s) failed to import.";
            }
            
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'message' => $message,
                    'imported_count' => $importedCount,
                    'failed_count' => count($failedEmployees),
                    'failed_employees' => $failedEmployees,
                    'debug' => $auditDebug ?? null,
                ]));
                
        } catch (\Exception $e) {
            Log::error('Error importing orgtrakker employees: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error importing employees: ' . $e->getMessage(),
                ]));
        }
    }

    /**
     * Import all employees and related data from orgtrakker
     *
     * @return \Cake\Http\Response
     */
    public function importAllOrgtrakkerEmployees()
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

        // Require admin access for importing all employees
        $adminCheck = $this->requireAdmin();
        if ($adminCheck !== null) {
            return $adminCheck;
        }

        $companyId = $this->getCompanyId($authResult);

        try {
            // Get connection for transaction
            $EmployeeTemplateAnswersTable = $this->getTable('EmployeeTemplateAnswers', $companyId);
            $connection = $EmployeeTemplateAnswersTable->getConnection();
            
            // Drop unique constraint on rank if it exists (to allow duplicate ranks)
            try {
                $connection->execute('ALTER TABLE role_levels DROP CONSTRAINT IF EXISTS role_levels_rank_key');
                Log::info('Dropped role_levels_rank_key constraint to allow duplicate ranks');
            } catch (\Exception $e) {
                // Constraint might not exist, that's okay
                Log::debug('Could not drop role_levels_rank_key constraint: ' . $e->getMessage());
            }
            
            // Start transaction
            $connection->begin();
            
            try {
                // Import order: role levels → job roles → job role relationships → employees → employee relationships
                $roleLevelsResult = $this->importAllRoleLevelsFromOrgtrakker($companyId);
                $jobRolesResult = $this->importAllJobRolesFromOrgtrakker($companyId);
                $jobRoleRelationshipsResult = $this->importAllJobRoleReportingRelationships($companyId);
                $employeesResult = $this->importAllEmployeesFromOrgtrakker($companyId);
                $employeeRelationshipsResult = $this->importAllEmployeeReportingRelationships($companyId);
                
                // Normalize results to arrays
                $roleLevelsImported = is_array($roleLevelsResult) ? ($roleLevelsResult['imported'] ?? $roleLevelsResult) : $roleLevelsResult;
                $roleLevelsUpdated = is_array($roleLevelsResult) ? ($roleLevelsResult['updated'] ?? 0) : 0;
                $jobRolesImported = is_array($jobRolesResult) ? ($jobRolesResult['imported'] ?? $jobRolesResult) : $jobRolesResult;
                $jobRolesUpdated = is_array($jobRolesResult) ? ($jobRolesResult['updated'] ?? 0) : 0;
                $jobRoleRelationshipsImported = is_array($jobRoleRelationshipsResult) ? ($jobRoleRelationshipsResult['imported'] ?? $jobRoleRelationshipsResult) : $jobRoleRelationshipsResult;
                $jobRoleRelationshipsUpdated = is_array($jobRoleRelationshipsResult) ? ($jobRoleRelationshipsResult['updated'] ?? 0) : 0;
                $employeesImported = is_array($employeesResult) ? ($employeesResult['imported'] ?? 0) : 0;
                $employeesUpdated = is_array($employeesResult) ? ($employeesResult['updated'] ?? 0) : 0;
                $employeeRelationshipsImported = is_array($employeeRelationshipsResult) ? ($employeeRelationshipsResult['imported'] ?? $employeeRelationshipsResult) : $employeeRelationshipsResult;
                $employeeRelationshipsUpdated = is_array($employeeRelationshipsResult) ? ($employeeRelationshipsResult['updated'] ?? 0) : 0;
                
                // Commit transaction
                $connection->commit();
                
                // Log bulk import actions
                $auditUserData = AuditHelper::extractUserData($authResult);
                
                // Override company_id and username with the correct values from controller
                $authData = $authResult->getData();
                $auditUsername = null;
                if ($authData instanceof \ArrayObject || is_array($authData)) {
                    $auditUsername = $authData['username'] ?? $authData['sub'] ?? null;
                } elseif (is_object($authData)) {
                    $auditUsername = $authData->username ?? $authData->sub ?? null;
                }
                
                $auditUserData['company_id'] = (string)$companyId;
                $auditUserData['username'] = $auditUsername ?? $auditUserData['username'] ?? 'system';
                $auditUserData['user_id'] = $authData->id ?? $authData['id'] ?? $authData->sub ?? $authData['sub'] ?? $auditUserData['user_id'] ?? 0;
                
                // If we now have a user_id but full_name wasn't fetched, fetch it now
                if (!empty($auditUserData['user_id']) && (empty($auditUserData['full_name']) || $auditUserData['full_name'] === 'Unknown')) {
                    try {
                        $usersTable = TableRegistry::getTableLocator()->get('Users', [
                            'connection' => ConnectionManager::get('default')
                        ]);
                        
                        $user = $usersTable->find()
                            ->select(['first_name', 'last_name'])
                            ->where(['id' => $auditUserData['user_id']])
                            ->first();
                        
                        if ($user) {
                            $firstName = $user->first_name ?? '';
                            $lastName = $user->last_name ?? '';
                            $fullName = trim($firstName . ' ' . $lastName);
                            if (!empty($fullName)) {
                                $auditUserData['full_name'] = $fullName;
                                $auditUserData['employee_name'] = $fullName;
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Error fetching user full name in controller: ' . $e->getMessage());
                    }
                }
                
                // Ensure full_name is preserved (don't overwrite it if it was already fetched)
                if (empty($auditUserData['full_name']) && !empty($auditUserData['employee_name'])) {
                    $auditUserData['full_name'] = $auditUserData['employee_name'];
                }
                
                // Try to create audit service, but don't fail the import if it fails
                $auditDebug = [];
                $auditDebug['audit_service_created'] = false;
                $auditDebug['role_levels'] = [
                    'audit_log_attempted' => false,
                    'audit_log_success' => false,
                    'audit_log_error' => null,
                    'audit_log_id' => null,
                ];
                $auditDebug['job_roles'] = [
                    'audit_log_attempted' => false,
                    'audit_log_success' => false,
                    'audit_log_error' => null,
                    'audit_log_id' => null,
                ];
                $auditDebug['job_role_relationships'] = [
                    'audit_log_attempted' => false,
                    'audit_log_success' => false,
                    'audit_log_error' => null,
                    'audit_log_id' => null,
                ];
                $auditDebug['employees'] = [
                    'audit_log_attempted' => false,
                    'audit_log_success' => false,
                    'audit_log_error' => null,
                    'audit_log_id' => null,
                ];
                $auditDebug['employee_relationships'] = [
                    'audit_log_attempted' => false,
                    'audit_log_success' => false,
                    'audit_log_error' => null,
                    'audit_log_id' => null,
                ];
                $auditDebug['company_id'] = (string)$companyId;
                $auditDebug['company_id_type'] = gettype($companyId);
                
                try {
                    // Ensure companyId is a valid string (handle null, int, etc.)
                    if ($companyId === null || $companyId === '') {
                        $auditCompanyId = 'default';
                    } else {
                        $auditCompanyId = (string)$companyId;
                    }
                    $auditDebug['audit_company_id_used'] = $auditCompanyId;
                    $auditService = new \App\Service\AuditService($auditCompanyId);
                    $auditDebug['audit_service_created'] = true;
                } catch (\Throwable $e) {
                    // Log error creating audit service but don't fail the import
                    $auditDebug['audit_service_error'] = $e->getMessage();
                    $auditDebug['audit_service_error_file'] = $e->getFile();
                    $auditDebug['audit_service_error_line'] = $e->getLine();
                    $auditDebug['audit_service_error_trace'] = substr($e->getTraceAsString(), 0, 2000); // Limit trace length
                    Log::error('Error creating AuditService for bulk import audit: ' . $e->getMessage(), [
                        'trace' => $e->getTraceAsString(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'company_id' => $companyId,
                        'company_id_type' => gettype($companyId)
                    ]);
                    $auditService = null;
                }
                
                // Log bulk import for role levels
                if ($auditService !== null && $roleLevelsImported > 0) {
                    $auditDebug['role_levels']['audit_log_attempted'] = true;
                    $auditDebug['role_levels']['imported_count'] = $roleLevelsImported;
                    try {
                        $result = $auditService->logBulkImportAction(
                            'role_level',
                            $roleLevelsImported,
                            $auditUserData,
                            $this->request->getParsedBody() ?? [],
                            ['imported_count' => $roleLevelsImported, 'updated_count' => $roleLevelsUpdated]
                        );
                        
                        if ($result !== null) {
                            $auditDebug['role_levels']['audit_log_success'] = true;
                            $auditDebug['role_levels']['audit_log_id'] = $result->id;
                        } else {
                            $auditDebug['role_levels']['audit_log_success'] = false;
                            $auditDebug['role_levels']['audit_log_error'] = 'logBulkImportAction returned null';
                        }
                    } catch (\Throwable $e) {
                        // Log audit error but don't fail the import
                        $auditDebug['role_levels']['audit_log_error'] = $e->getMessage();
                        $auditDebug['role_levels']['audit_log_error_file'] = $e->getFile();
                        $auditDebug['role_levels']['audit_log_error_line'] = $e->getLine();
                        Log::error('Error logging bulk import audit for role levels: ' . $e->getMessage(), [
                            'trace' => $e->getTraceAsString(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine()
                        ]);
                    }
                } else {
                    $auditDebug['role_levels']['skip_reason'] = $auditService === null ? 'audit_service_is_null' : 'role_levels_imported_is_zero';
                    $auditDebug['role_levels']['imported_count'] = $roleLevelsImported;
                }
                
                // Log bulk import for employees
                if ($auditService !== null && $employeesImported > 0) {
                    $auditDebug['employees']['audit_log_attempted'] = true;
                    $auditDebug['employees']['imported_count'] = $employeesImported;
                    try {
                        $result = $auditService->logBulkImportAction(
                            'employee',
                            $employeesImported,
                            $auditUserData,
                            $this->request->getParsedBody() ?? [],
                            ['imported_count' => $employeesImported, 'updated_count' => $employeesUpdated]
                        );
                        
                        if ($result !== null) {
                            $auditDebug['employees']['audit_log_success'] = true;
                            $auditDebug['employees']['audit_log_id'] = $result->id;
                        } else {
                            $auditDebug['employees']['audit_log_success'] = false;
                            $auditDebug['employees']['audit_log_error'] = 'logBulkImportAction returned null';
                        }
                    } catch (\Throwable $e) {
                        // Log audit error but don't fail the import
                        $auditDebug['employees']['audit_log_error'] = $e->getMessage();
                        $auditDebug['employees']['audit_log_error_file'] = $e->getFile();
                        $auditDebug['employees']['audit_log_error_line'] = $e->getLine();
                        Log::error('Error logging bulk import audit for employees: ' . $e->getMessage(), [
                            'trace' => $e->getTraceAsString(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine()
                        ]);
                    }
                } else {
                    $auditDebug['employees']['skip_reason'] = $auditService === null ? 'audit_service_is_null' : 'employees_imported_is_zero';
                    $auditDebug['employees']['imported_count'] = $employeesImported;
                }
                
                // Log bulk import for job roles
                if ($auditService !== null && $jobRolesImported > 0) {
                    $auditDebug['job_roles']['audit_log_attempted'] = true;
                    $auditDebug['job_roles']['imported_count'] = $jobRolesImported;
                    try {
                        $result = $auditService->logBulkImportAction(
                            'job_role',
                            $jobRolesImported,
                            $auditUserData,
                            $this->request->getParsedBody() ?? [],
                            ['imported_count' => $jobRolesImported, 'updated_count' => $jobRolesUpdated]
                        );
                        
                        if ($result !== null) {
                            $auditDebug['job_roles']['audit_log_success'] = true;
                            $auditDebug['job_roles']['audit_log_id'] = $result->id;
                        } else {
                            $auditDebug['job_roles']['audit_log_success'] = false;
                            $auditDebug['job_roles']['audit_log_error'] = 'logBulkImportAction returned null';
                        }
                    } catch (\Throwable $e) {
                        // Log audit error but don't fail the import
                        $auditDebug['job_roles']['audit_log_error'] = $e->getMessage();
                        $auditDebug['job_roles']['audit_log_error_file'] = $e->getFile();
                        $auditDebug['job_roles']['audit_log_error_line'] = $e->getLine();
                        Log::error('Error logging bulk import audit for job roles: ' . $e->getMessage(), [
                            'trace' => $e->getTraceAsString(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine()
                        ]);
                    }
                } else {
                    $auditDebug['job_roles']['skip_reason'] = $auditService === null ? 'audit_service_is_null' : 'job_roles_imported_is_zero';
                    $auditDebug['job_roles']['imported_count'] = $jobRolesImported;
                }
                
                // Log bulk import for job role relationships
                if ($auditService !== null && $jobRoleRelationshipsImported > 0) {
                    $auditDebug['job_role_relationships']['audit_log_attempted'] = true;
                    $auditDebug['job_role_relationships']['imported_count'] = $jobRoleRelationshipsImported;
                    try {
                        $result = $auditService->logBulkImportAction(
                            'job_role_relationship',
                            $jobRoleRelationshipsImported,
                            $auditUserData,
                            $this->request->getParsedBody() ?? [],
                            ['imported_count' => $jobRoleRelationshipsImported, 'updated_count' => $jobRoleRelationshipsUpdated]
                        );
                        
                        if ($result !== null) {
                            $auditDebug['job_role_relationships']['audit_log_success'] = true;
                            $auditDebug['job_role_relationships']['audit_log_id'] = $result->id;
                        } else {
                            $auditDebug['job_role_relationships']['audit_log_success'] = false;
                            $auditDebug['job_role_relationships']['audit_log_error'] = 'logBulkImportAction returned null';
                        }
                    } catch (\Throwable $e) {
                        // Log audit error but don't fail the import
                        $auditDebug['job_role_relationships']['audit_log_error'] = $e->getMessage();
                        $auditDebug['job_role_relationships']['audit_log_error_file'] = $e->getFile();
                        $auditDebug['job_role_relationships']['audit_log_error_line'] = $e->getLine();
                        Log::error('Error logging bulk import audit for job role relationships: ' . $e->getMessage(), [
                            'trace' => $e->getTraceAsString(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine()
                        ]);
                    }
                } else {
                    $auditDebug['job_role_relationships']['skip_reason'] = $auditService === null ? 'audit_service_is_null' : 'job_role_relationships_imported_is_zero';
                    $auditDebug['job_role_relationships']['imported_count'] = $jobRoleRelationshipsImported;
                }
                
                // Log bulk import for employee relationships
                if ($auditService !== null && $employeeRelationshipsImported > 0) {
                    $auditDebug['employee_relationships']['audit_log_attempted'] = true;
                    $auditDebug['employee_relationships']['imported_count'] = $employeeRelationshipsImported;
                    try {
                        $result = $auditService->logBulkImportAction(
                            'employee_relationship',
                            $employeeRelationshipsImported,
                            $auditUserData,
                            $this->request->getParsedBody() ?? [],
                            ['imported_count' => $employeeRelationshipsImported, 'updated_count' => $employeeRelationshipsUpdated]
                        );
                        
                        if ($result !== null) {
                            $auditDebug['employee_relationships']['audit_log_success'] = true;
                            $auditDebug['employee_relationships']['audit_log_id'] = $result->id;
                        } else {
                            $auditDebug['employee_relationships']['audit_log_success'] = false;
                            $auditDebug['employee_relationships']['audit_log_error'] = 'logBulkImportAction returned null';
                        }
                    } catch (\Throwable $e) {
                        // Log audit error but don't fail the import
                        $auditDebug['employee_relationships']['audit_log_error'] = $e->getMessage();
                        $auditDebug['employee_relationships']['audit_log_error_file'] = $e->getFile();
                        $auditDebug['employee_relationships']['audit_log_error_line'] = $e->getLine();
                        Log::error('Error logging bulk import audit for employee relationships: ' . $e->getMessage(), [
                            'trace' => $e->getTraceAsString(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine()
                        ]);
                    }
                } else {
                    $auditDebug['employee_relationships']['skip_reason'] = $auditService === null ? 'audit_service_is_null' : 'employee_relationships_imported_is_zero';
                    $auditDebug['employee_relationships']['imported_count'] = $employeeRelationshipsImported;
                }
                
                return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'message' => 'Import completed successfully',
                    'debug' => $auditDebug ?? null,
                        'role_levels' => ['imported' => $roleLevelsImported, 'updated' => $roleLevelsUpdated],
                        'job_roles' => ['imported' => $jobRolesImported, 'updated' => $jobRolesUpdated],
                        'job_role_relationships' => ['imported' => $jobRoleRelationshipsImported, 'updated' => $jobRoleRelationshipsUpdated],
                        'employees' => ['imported' => $employeesImported, 'updated' => $employeesUpdated],
                        'employee_relationships' => ['imported' => $employeeRelationshipsImported, 'updated' => $employeeRelationshipsUpdated],
                    ]));
                    
            } catch (\Exception $e) {
                // Rollback on any failure
                $connection->rollback();
                throw $e;
            }
            
        } catch (\Exception $e) {
            Log::error('Error importing all orgtrakker employees: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error importing employees: ' . $e->getMessage(),
                ]));
        }
    }
}
