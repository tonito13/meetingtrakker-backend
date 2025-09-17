<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\Api\ApiController;
use App\Helper\AuditHelper;
use Cake\Core\Configure;
use Cake\Utility\Text;
use Exception;
use Cake\Log\Log;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;

class EmployeesController extends ApiController
{
    public function initialize(): void
    {
        parent::initialize();
    }

    public function getEmployees()
    {
        $this->request->allowMethod(['get']);

        // Authentication check
        $authResult = $this->Authentication->getResult();
        if (!$authResult || !$authResult->isValid() || !isset($authResult->getData()->company_id)) {
            return $this->response
                ->withStatus(401)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Unauthorized access or invalid company ID',
                    'data' => []
                ]));
        }

        $company_id = $authResult->getData()->company_id;

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
                    foreach ($structure as $section) {
                        foreach ($section['fields'] as $field) {
                            $fieldMap[$field['id']] = $field['customize_field_label'];
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
        Configure::write('debug', 1);
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

        $companyId = $authResult->getData()->company_id;
        $logged_username = $authResult->getData()->username;
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
            $employeeName = AuditHelper::extractEmployeeName($answerData);
            
            // Extract user data for audit logging
            $auditUserData = AuditHelper::extractUserData($authResult);
            
            // Log employee creation
            AuditHelper::logEmployeeAction(
                'CREATE',
                $employeeUniqueId,
                $employeeName,
                $auditUserData,
                $this->request
            );

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'message' => 'Employee data saved successfully. Please upload files.',
                    'employee_id' => $employeeUniqueId,
                    'answer_id' => $answerEntity->id,
                    'user_id' => $userEntity->id,
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
        Configure::write('debug', 1);
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

        $companyId = $authResult->getData()->company_id;
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

            // Start transaction
            $connection = $EmployeeTemplateAnswersTable->getConnection();
            $connection->begin();

            // Validate answer_id exists
            $answer = $EmployeeTemplateAnswersTable
                ->find()
                ->where([
                    'id' => $answerId,
                    'company_id' => $companyId,
                    'employee_unique_id' => $employeeUniqueId,
                    'deleted' => 0,
                ])
                ->first();
            if (!$answer) {
                throw new Exception('Invalid answer ID or employee unique ID.');
            }

            // Get template to validate required file fields
            $template = $this->validateTemplate($companyId, $answer->template_id);
            $requiredFileFields = $this->getRequiredFileFields($template->structure);

            $files = $this->request->getUploadedFiles();
            $fileMap = [];
            $uploadedFields = [];

            $targetFiles = isset($files['files']) && is_array($files['files']) ? $files['files'] : $files;

            foreach ($targetFiles as $key => $file) {
                if (preg_match('/^(\d+)_([0-9_]+)$/', $key, $matches)) {
                    $groupId = $matches[1];
                    $fieldId = $matches[2];
                    $this->validateFile($file, "File for {$groupId}_{$fieldId}");

                    $fileName = $file->getClientFilename();
                    $file_name =  $companyId . DS . $employeeUniqueId . DS . $fieldId . '_' . $fileName;
                    $filePath = WWW_ROOT . 'Uploads' . DS . $file_name;
                    $fileDir = dirname($filePath);

                    if (!file_exists($fileDir) && !mkdir($fileDir, 0777, true)) {
                        throw new Exception("Failed to create directory for file upload: {$fieldId}");
                    }

                    $file->moveTo($filePath);
                    if (!file_exists($filePath)) {
                        throw new Exception("Failed to upload file for {$fieldId}.");
                    }

                    $fileEntity = $EmployeeAnswerFilesTable->newEntity([
                        'answer_id' => $answerId,
                        'file_name' => $fileName,
                        'file_path' => 'Uploads/' . $file_name,
                        'file_type' => $file->getClientMediaType(),
                        'file_size' => $file->getSize(),
                        'group_id' => $groupId,
                        'field_id' => $fieldId,
                        'company_id' => $companyId,
                        'deleted' => 0,
                        'created' => date('Y-m-d H:i:s'),
                        'modified' => date('Y-m-d H:i:s'),
                    ]);

                    if (!$EmployeeAnswerFilesTable->save($fileEntity)) {
                        throw new Exception("Failed to save file metadata for {$fieldId}.");
                    }

                    if (!isset($fileMap[$groupId])) {
                        $fileMap[$groupId] = [];
                    }
                    $fileMap[$groupId][$fieldId] = $filePath;
                    $uploadedFields[] = "{$groupId}_{$fieldId}";
                }
            }

            // Validate required file fields
            foreach ($requiredFileFields as $requiredField) {
                if (!in_array("{$requiredField['group_id']}_{$requiredField['field_id']}", $uploadedFields)) {
                    throw new Exception("Required file field {$requiredField['label']} is missing.");
                }
            }

            // Update answers with file paths
            $answerData = $answer->answers;
            foreach ($fileMap as $groupId => $fields) {
                foreach ($fields as $fieldId => $filePath) {
                    if (!isset($answerData[$groupId])) {
                        $answerData[$groupId] = [];
                    }
                    $answerData[$groupId][$fieldId] = $filePath;
                }
            }
            $answer->answers = $answerData;
            if (!$EmployeeTemplateAnswersTable->save($answer)) {
                throw new Exception('Failed to update employee answers with file paths.');
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
                'deleted' => 0,
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

        foreach ($answers as $groupId => $groupAnswers) {
            $answerData[$groupId] = [];
            foreach ($groupAnswers as $fieldId => $value) {
                $field = $this->findField($template->structure, $groupId, $fieldId);
                $displayLabel = $this->getDisplayLabel($field, $fieldId);
                
                Log::debug("Processing field: groupId={$groupId}, fieldId={$fieldId}, displayLabel='{$displayLabel}', value=" . json_encode($value));

                Log::debug("Processing field: groupId=$groupId, fieldId=$fieldId, value=" . ($field && $field['type'] === 'file' ? '[File]' : json_encode($value)) . ", displayLabel=$displayLabel");

                // Skip file validation (handled in uploadFiles)
                if ($field && $field['type'] === 'file') {
                    $answerData[$groupId][$fieldId] = null; // Placeholder
                    continue;
                }

                if ($field && $field['is_required'] && (is_null($value) || $value === '')) {
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
                } elseif ($field && $field['type'] === 'job_role') {
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
        if (empty($userData['blood_type'])) {
            $userData['blood_type'] = 'Not Specified'; // Default blood type
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
        foreach ($structure as $group) {
            if ($group['id'] == $groupId) {
                foreach ($group['fields'] as $f) {
                    if ($f['id'] == $fieldId || $fieldId === 'reports_to') {
                        return $f;
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

        $company_id = $authResult->getData()->company_id;
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
            foreach ($structure as $group) {
                foreach ($group['fields'] as $field) {
                    $label = $field['label'];
                    if (in_array($label, ['Employee ID', 'First Name', 'Last Name', 'Job Role', 'Reports To'])) {
                        $headers[] = [
                            'id' => $this->getFieldId($label),
                            'label' => !empty($field['customize_field_label']) ? $field['customize_field_label'] : $field['label']
                        ];
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

    public function getEmployeesData()
    {
        Configure::write('debug', 1);
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

        $company_id = $authResult->getData()->company_id;

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
                $jobRoleAnswers = json_decode($jobRole->job_role_answer, true);
                $jobRoleStructure = $jobRole->job_role_structure;
                $jobRoleUniqueId = $jobRole->job_role_unique_id;

                $designation = null;
                if (is_array($jobRoleStructure) && is_array($jobRoleAnswers)) {
                    foreach ($jobRoleStructure as $group) {
                        foreach ($group['fields'] as $field) {
                            $fieldLabel = $field['customize_field_label'] ?? $field['label'];
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
                $answers = is_array($employee->answers) ? $employee->answers : json_decode($employee->answers, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Invalid answers JSON format for employee_unique_id: ' . $employee->employee_unique_id);
                }

                $jobRoleUniqueId = null;

                if (is_array($structure)) {
                    foreach ($structure as $group) {
                        if (!isset($group['fields']) || !is_array($group['fields'])) {
                            continue;
                        }

                        foreach ($group['fields'] as $field) {
                            $fieldId = $field['id'];
                            $fieldLabel = $field['label'];

                            if (isset($fieldMapping[$fieldLabel])) {
                                $dataKey = $fieldMapping[$fieldLabel]['dataKey'];

                                foreach ($answers as $groupAnswers) {
                                    if (isset($groupAnswers[$fieldId])) {
                                        $answerValue = $groupAnswers[$fieldId];
                                        
                                        if ($dataKey === 'job_role') {
                                            $jobRoleUniqueId = $answerValue; // Store job_role_unique_id
                                        } else {
                                            $result[$dataKey] = $answerValue;
                                        }
                                        break;
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
                }

                // Debug logging for final result
                Log::debug('🔍 DEBUG: Final employee result in getEmployeesData', [
                    'result' => $result,
                    'username_in_result' => $result['username'] ?? 'NOT_SET',
                    'username_type' => gettype($result['username'] ?? null)
                ]);

                return $result;
            }, $allEmployeeAnswers);

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

    $companyId = $authResult->getData()->company_id;
    $connection = ConnectionManager::get('client_' . $companyId);
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
        Configure::write('debug', 1);
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

        $companyId = $authResult->getData()->company_id;
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
                ->find()
                ->select([
                    'answer_id' => 'answer_id',
                    'file_path' => 'file_path',
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

            foreach ($get_employee_attached as $file) {
                $group_id = $file['group_id'];
                $field_id = $file['field_id'];
                if (!isset($answers[$group_id])) {
                    $answers[$group_id] = [];
                }
                $answers[$group_id][$field_id] = $file['file_path'];
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
                        $flatAnswers = array_merge($flatAnswers, $groupAnswers);
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
        Configure::write('debug', 1); // Consider removing in production
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

        $data = $this->request->getData();
        $companyId = $authResult->getData()->company_id;
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
            $employeeName = AuditHelper::extractEmployeeName($employee->answers ?? []);

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
            
            // Log employee deletion
            AuditHelper::logEmployeeAction(
                'DELETE',
                $employeeUniqueId,
                $employeeName,
                $auditUserData,
                $this->request
            );

            return $this->response
                ->withStatus(200)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'message' => 'Employee deleted successfully'
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
        Configure::write('debug', 1); // Disable debug in production
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

        $companyId = $authResult->getData()->company_id;
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
                ->find()
                ->select([
                    'answer_id',
                    'file_path',
                    'group_id',
                    'field_id',
                ])
                ->where([
                    'EmployeeAnswerFiles.company_id' => $companyId,
                    'EmployeeAnswerFiles.deleted' => 0,
                    'EmployeeAnswerFiles.answer_id' => $employeeDetail->answer_id,
                ])
                ->toArray();

            // Merge file paths into answers, skipping Password fields
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
                    $answers[$groupId][$fieldId] = $file->file_path;
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
        Configure::write('debug', 1);
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

        $companyId = $authResult->getData()->company_id;
        $logged_username = $authResult->getData()->username;
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
            $oldAnswers = is_array($existingAnswer->answers) ? $existingAnswer->answers : (json_decode($existingAnswer->answers, true) ?? []);
            
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
            $existingAnswer->answers = array_replace_recursive($existingAnswer->answers, $answerData);
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
            $employeeName = AuditHelper::extractEmployeeName($answerData);
            
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
            
            AuditHelper::logEmployeeAction(
                'UPDATE',
                $employeeUniqueId,
                $employeeName,
                $userData,
                $this->request,
                $fieldChanges
            );

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'message' => 'Employee data updated successfully. Please upload files if needed.',
                    'employee_id' => $employeeUniqueId,
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

        // Find the user by username or employee_unique_id (assuming a link exists)
        $user = $UsersTable
            ->find()
            ->where([
                'company_id' => $companyId,
                'username' => $username,
                'deleted' => false,
            ])
            ->first();

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
        Configure::write('debug', 1);
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

        $companyId = $authResult->getData()->company_id;
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

            // Start transaction
            $connection = $EmployeeTemplateAnswersTable->getConnection();
            $connection->begin();

            // Validate answer_id exists
            $answer = $EmployeeTemplateAnswersTable
                ->find()
                ->where([
                    'id' => $answerId,
                    'company_id' => $companyId,
                    'employee_unique_id' => $employeeUniqueId,
                    'deleted' => 0,
                ])
                ->first();
            if (!$answer) {
                throw new Exception('Invalid answer ID or employee unique ID.');
            }

            // Get template to validate required file fields
            $template = $this->validateTemplate($companyId, $answer->template_id);
            $requiredFileFields = $this->getRequiredFileFields($template->structure);

            $files = $this->request->getUploadedFiles();
            $fileMap = [];
            $uploadedFields = [];

            $targetFiles = isset($files['files']) && is_array($files['files']) ? $files['files'] : $files;

            // Process new or updated files
            foreach ($targetFiles as $key => $file) {
                if (preg_match('/^(\d+)_([0-9_]+)$/', $key, $matches)) {
                    $groupId = $matches[1];
                    $fieldId = $matches[2];
                    $this->validateFile($file, "File for {$groupId}_{$fieldId}");

                    $fileName = $file->getClientFilename();
                    $file_name = $companyId . DS . $employeeUniqueId . DS . $fieldId . '_' . $fileName;
                    $filePath = WWW_ROOT . 'Uploads' . DS . $file_name;
                    $fileDir = dirname($filePath);

                    if (!file_exists($fileDir) && !mkdir($fileDir, 0777, true)) {
                        throw new Exception("Failed to create directory for file upload: {$fieldId}");
                    }

                    $file->moveTo($filePath);
                    if (!file_exists($filePath)) {
                        throw new Exception("Failed to upload file for {$fieldId}.");
                    }

                    // Check if file already exists for this answer_id, group_id, and field_id
                    $existingFile = $EmployeeAnswerFilesTable
                        ->find()
                        ->where([
                            'answer_id' => $answerId,
                            'group_id' => $groupId,
                            'field_id' => $fieldId,
                            'company_id' => $companyId,
                            'deleted' => 0,
                        ])
                        ->first();

                    if ($existingFile) {
                        // Delete old file from storage
                        $oldFilePath = WWW_ROOT . $existingFile->file_path;
                        if (file_exists($oldFilePath)) {
                            unlink($oldFilePath);
                        }
                        // Update existing file record
                        $existingFile->file_name = $fileName;
                        $existingFile->file_path = 'Uploads/' . $file_name;
                        $existingFile->file_type = $file->getClientMediaType();
                        $existingFile->file_size = $file->getSize();
                        $existingFile->modified = date('Y-m-d H:i:s');
                        if (!$EmployeeAnswerFilesTable->save($existingFile)) {
                            throw new Exception("Failed to update file metadata for {$fieldId}.");
                        }
                    } else {
                        // Create new file record
                        $fileEntity = $EmployeeAnswerFilesTable->newEntity([
                            'answer_id' => $answerId,
                            'file_name' => $fileName,
                            'file_path' => 'Uploads/' . $file_name,
                            'file_type' => $file->getClientMediaType(),
                            'file_size' => $file->getSize(),
                            'group_id' => $groupId,
                            'field_id' => $fieldId,
                            'company_id' => $companyId,
                            'deleted' => 0,
                            'created' => date('Y-m-d H:i:s'),
                            'modified' => date('Y-m-d H:i:s'),
                        ]);
                        if (!$EmployeeAnswerFilesTable->save($fileEntity)) {
                            throw new Exception("Failed to save file metadata for {$fieldId}.");
                        }
                    }

                    if (!isset($fileMap[$groupId])) {
                        $fileMap[$groupId] = [];
                    }
                    $fileMap[$groupId][$fieldId] = 'Uploads/' . $file_name;
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

            // Update answers with new file paths
            $answerData = $answer->answers;
            foreach ($fileMap as $groupId => $fields) {
                foreach ($fields as $fieldId => $filePath) {
                    if (!isset($answerData[$groupId])) {
                        $answerData[$groupId] = [];
                    }
                    $answerData[$groupId][$fieldId] = $filePath;
                }
            }
            $answer->answers = $answerData;
            $answer->modified = date('Y-m-d H:i:s');
            if (!$EmployeeTemplateAnswersTable->save($answer)) {
                throw new Exception('Failed to update employee answers with file paths.');
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
        Configure::write('debug', 0); // Disable debug in production
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

        $companyId = $authResult->getData()->company_id;
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
                    'structure' => 'EmployeeTemplates.structure',
                ])
                ->join([
                    'EmployeeTemplates' => [
                        'table' => $employeeTemplatesTable->getTable(),
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

            // Parse structure (JSON in database)
            $structure = is_string($employeeDetail->structure)
                ? json_decode($employeeDetail->structure, true)
                : $employeeDetail->structure;
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid structure JSON format');
            }

            // Parse answers (array from JSONB)
            $answers = is_array($employeeDetail->answers) ? $employeeDetail->answers : [];

            $formattedData = [
                'employee_unique_id' => $employeeDetail->employee_unique_id,
                'template_id' => $employeeDetail->template_id,
                'answer_id' => $employeeDetail->answer_id,
            ];

            // Map answers using group and field IDs
            foreach ($structure as $group) {
                $groupId = $group['id'];
                $groupLabel = $group['customize_group_label'] ?? $group['label'];
                if (isset($answers[$groupId])) {
                    foreach ($group['fields'] as $field) {
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
                if (!empty($group['subGroups'])) {
                    foreach ($group['subGroups'] as $index => $subGroup) {
                        $subGroupId = $subGroup['id'];
                        $subGroupLabel = "{$groupLabel}_{$index}";
                        if (isset($answers[$subGroupLabel])) {
                            foreach ($subGroup['fields'] as $field) {
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
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error fetching employee data',
                ]));
        }
    }

    public function changePassword()
    {
        Configure::write('debug', 1);
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

        $companyId = $authResult->getData()->company_id;
        $logged_username = $authResult->getData()->username;
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

        $companyId = $authResult->getData()->company_id;

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
}
