<?php
declare(strict_types=1);

namespace App\Controller\Api;

use Cake\Http\Response;
use App\Service\S3FileService;

/**
 * EmployeeAnswerFiles Controller
 * 
 * Handles employee answer file operations using AWS S3 for storage
 */
class EmployeeAnswerFilesController extends ApiController
{
    private S3FileService $s3Service;

    public function initialize(): void
    {
        parent::initialize();
        $this->s3Service = new S3FileService();
    }

    /**
     * Get employee files
     *
     * @param string $employeeUniqueId Employee Unique ID
     * @return \Cake\Http\Response
     */
    public function getEmployeeFiles(string $employeeUniqueId): Response
    {
        $this->request->allowMethod(['GET']);
        
        try {
            // Authentication check
            $authResult = $this->Authentication->getResult();
            if (!$authResult || !$authResult->isValid()) {
                return $this->response->withStatus(401)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Unauthorized access',
                    ]));
            }

            $companyId = $this->getCompanyId($authResult);
            
            $employeeAnswerFilesTable = $this->getTable('EmployeeAnswerFiles', $companyId);
            
            $files = $employeeAnswerFilesTable->find('active')
                ->where([
                    'EmployeeAnswerFiles.employee_unique_id' => $employeeUniqueId,
                    'EmployeeAnswerFiles.company_id' => $companyId
                ])
                ->order(['EmployeeAnswerFiles.created' => 'DESC'])
                ->toArray();

            // Generate presigned URLs for each file
            foreach ($files as $file) {
                if ($file->s3_bucket && $file->s3_key) {
                    $file->download_url = $this->s3Service->generatePresignedUrl(
                        $file->s3_bucket, 
                        $file->s3_key, 
                        3600 // 1 hour expiry
                    );
                }
            }
            
            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'data' => $files
                ]));
        } catch (\Exception $e) {
            return $this->response->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Failed to get employee files',
                    'error' => $e->getMessage()
                ]));
        }
    }

    /**
     * Get uploaded file
     *
     * @param int $fileId File ID from employee_answer_files table
     * @return \Cake\Http\Response
     */
    public function getEmployeeFile(int $fileId): Response
    {
        $this->request->allowMethod(['GET']);
        
        try {
            // Authentication check
            $authResult = $this->Authentication->getResult();
            if (!$authResult || !$authResult->isValid()) {
                return $this->response->withStatus(401)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Unauthorized access',
                    ]));
            }

            $companyId = $this->getCompanyId($authResult);
            
            $employeeAnswerFilesTable = $this->getTable('EmployeeAnswerFiles', $companyId);
            
            $file = $employeeAnswerFilesTable->find('active')
                ->where([
                    'EmployeeAnswerFiles.id' => $fileId,
                    'EmployeeAnswerFiles.company_id' => $companyId
                ])
                ->first();
            
            if (!$file) {
                return $this->response
                    ->withStatus(404)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'File not found',
                    ]));
            }

            // Check if file exists in S3
            if (!$file->s3_bucket || !$file->s3_key) {
                return $this->response
                    ->withStatus(404)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'File not found in S3',
                    ]));
            }

            if (!$this->s3Service->fileExists($file->s3_bucket, $file->s3_key)) {
                return $this->response
                    ->withStatus(404)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'File not found on S3',
                    ]));
            }

            // Download file from S3
            $fileContent = $this->s3Service->downloadFile($file->s3_bucket, $file->s3_key);
            
            if ($fileContent === null) {
                return $this->response
                    ->withStatus(500)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Failed to download file from S3',
                    ]));
            }

            // Determine content type based on file extension
            $fileExtension = strtolower(pathinfo($file->file_name, PATHINFO_EXTENSION));
            $isVideo = in_array($fileExtension, ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm']);
            
            if ($isVideo) {
                // Serve video files without forced download
                $response = $this->response->withType($this->getVideoContentType($fileExtension));
                $response = $response->withHeader('Content-Length', strlen($fileContent));
                return $response->withStringBody($fileContent);
            } else {
                // Serve other files with download
                $response = $this->response->withType($file->file_type);
                $response = $response->withHeader('Content-Disposition', 'attachment; filename="' . $file->file_name . '"');
                $response = $response->withHeader('Content-Length', strlen($fileContent));
                
                return $response->withStringBody($fileContent);
            }
        } catch (\Exception $e) {
            return $this->response->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Failed to download file',
                    'error' => $e->getMessage()
                ]));
        }
    }

    /**
     * Upload employee file
     *
     * @return \Cake\Http\Response
     */
    public function uploadFile(): Response
    {
        $this->request->allowMethod(['POST']);
        
        try {
            // Authentication check
            $authResult = $this->Authentication->getResult();
            if (!$authResult || !$authResult->isValid()) {
                return $this->response->withStatus(401)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Unauthorized access',
                    ]));
            }

            $companyId = $this->getCompanyId($authResult);
            
            // For multipart/form-data, get data from request body or POST
            $data = [];
            
            // Try to get from request data first
            $requestData = $this->request->getData();
            if (!empty($requestData)) {
                $data = $requestData;
            }
            
            // If empty, try to get from parsed body (for multipart/form-data)
            if (empty($data)) {
                $parsedBody = $this->request->getParsedBody();
                if (!empty($parsedBody) && is_array($parsedBody)) {
                    $data = $parsedBody;
                }
            }
            
            // Fallback to $_POST for multipart/form-data
            if (empty($data) && !empty($_POST)) {
                $data = $_POST;
            }
            
            // Also try to get from query params as last resort
            if (empty($data)) {
                $queryParams = $this->request->getQueryParams();
                if (!empty($queryParams)) {
                    $data = $queryParams;
                }
            }
            
            // Validate required fields
            if (empty($data['employee_unique_id']) || empty($data['field_id']) || empty($data['answer_id'])) {
                return $this->response->withStatus(400)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Employee unique ID, field ID, and answer ID are required',
                    ]));
            }

            $employeeUniqueId = $data['employee_unique_id'];
            $fieldId = $data['field_id'];
            $groupId = $data['group_id'] ?? '';
            $answerId = $data['answer_id'];

            // Verify employee exists and belongs to company
            $employeeTemplateAnswersTable = $this->getTable('EmployeeTemplateAnswers', $companyId);
            $answer = $employeeTemplateAnswersTable->find()
                ->where([
                    'EmployeeTemplateAnswers.id' => $answerId,
                    'EmployeeTemplateAnswers.employee_unique_id' => $employeeUniqueId,
                    'EmployeeTemplateAnswers.company_id' => $companyId,
                    'EmployeeTemplateAnswers.deleted' => false
                ])
                ->first();
            
            if (!$answer) {
                return $this->response->withStatus(404)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Answer record not found'
                    ]));
            }

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

            // Check if there's an existing file for this field and soft delete it
            $employeeAnswerFilesTable = $this->getTable('EmployeeAnswerFiles', $companyId);
            $existingFile = $employeeAnswerFilesTable->find('active')
                ->where([
                    'EmployeeAnswerFiles.answer_id' => $answerId,
                    'EmployeeAnswerFiles.employee_unique_id' => $employeeUniqueId,
                    'EmployeeAnswerFiles.field_id' => $fieldId,
                    'EmployeeAnswerFiles.company_id' => $companyId
                ])
                ->first();
            
            if ($existingFile) {
                // Soft delete the existing file
                $existingFile->deleted = true;
                $employeeAnswerFilesTable->save($existingFile);
                
                // Also delete the file from S3 if it exists
                if ($existingFile->s3_bucket && $existingFile->s3_key) {
                    $this->s3Service->deleteFile($existingFile->s3_bucket, $existingFile->s3_key);
                }
            }
            
            // Process file upload to S3
            $uploadedFile = $this->processSingleFileUpload($employeeUniqueId, $fieldId, $groupId, $companyId);
            
            if ($uploadedFile) {
                // Check if there was an S3 error
                if (isset($uploadedFile['error']) && $uploadedFile['error']) {
                    return $this->response->withStatus(500)
                        ->withType('application/json')
                        ->withStringBody(json_encode([
                            'success' => false,
                            'message' => 'Failed to upload file to S3: ' . ($uploadedFile['s3_error'] ?? 'Unknown error'),
                            'debug_info' => $uploadedFile['s3_result'] ?? null
                        ]));
                }
                
                // Save file metadata to employee_answer_files table
                $savedFile = $this->saveSingleEmployeeFile($uploadedFile, $companyId, $employeeUniqueId, $employeeId, $answerId, $groupId, $fieldId);
                
                if ($savedFile) {
                    return $this->response->withStatus(201)
                        ->withType('application/json')
                        ->withStringBody(json_encode([
                            'success' => true,
                            'message' => 'File uploaded successfully to S3',
                            'data' => [
                                'file_id' => $savedFile->id,
                                'file_name' => $savedFile->file_name,
                                's3_bucket' => $savedFile->s3_bucket,
                                's3_key' => $savedFile->s3_key,
                                'download_url' => $this->s3Service->generatePresignedUrl(
                                    $savedFile->s3_bucket, 
                                    $savedFile->s3_key, 
                                    3600
                                )
                            ]
                        ]));
                } else {
                    return $this->response->withStatus(500)
                        ->withType('application/json')
                        ->withStringBody(json_encode([
                            'success' => false,
                            'message' => 'Failed to save file metadata to database'
                        ]));
                }
            } else {
                return $this->response->withStatus(400)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Failed to upload file to S3',
                        'debug' => [
                            'files_received' => !empty($_FILES),
                            'files_keys' => array_keys($_FILES),
                            'content_type' => $this->request->getHeaderLine('Content-Type')
                        ]
                    ]));
            }
            
        } catch (\Exception $e) {
            return $this->response->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'An error occurred while uploading the file',
                    'error' => $e->getMessage()
                ]));
        }
    }

    /**
     * Process a single file upload to S3
     *
     * @param string $employeeUniqueId Employee Unique ID
     * @param string $fieldId Field ID
     * @param string $groupId Group ID
     * @param int $companyId Company ID
     * @return array|null Uploaded file data or null if failed
     */
    private function processSingleFileUpload(string $employeeUniqueId, string $fieldId, string $groupId, int $companyId): ?array
    {
        // Check if file was uploaded
        if (!isset($_FILES['file'])) {
            return null;
        }
        
        $uploadedFile = $_FILES['file']['tmp_name'];
        $originalName = $_FILES['file']['name'];
        $fileError = $_FILES['file']['error'] ?? 'unknown';
        
        if ($fileError !== UPLOAD_ERR_OK) {
            return null;
        }
        
        if (!is_uploaded_file($uploadedFile)) {
            return null;
        }
        
        // Generate unique filename using the same convention as Skiltrakker
        $fileExtension = pathinfo($originalName, PATHINFO_EXTENSION);
        $baseFileName = pathinfo($originalName, PATHINFO_FILENAME);
        $microtime = microtime(true);
        $randomSuffix = mt_rand(1000, 9999);
        $uniqueFileName = $baseFileName . '_' . number_format($microtime, 4, '', '') . '_' . $randomSuffix . '.' . $fileExtension;
        
        // Read file content
        $fileContent = file_get_contents($uploadedFile);
        if ($fileContent === false) {
            return null;
        }
        
        // Upload to S3 with proper folder structure: scorecardtrakker/employees/{companyId}/{employeeUniqueId}/{fieldId}/{filename}
        $s3Result = $this->s3Service->uploadFile(
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
        
        if ($s3Result['success']) {
            return [
                'field_id' => $fieldId,
                'group_id' => $groupId,
                'file_name' => $originalName,
                'file_path' => $uniqueFileName, // Keep for backward compatibility
                'file_type' => $fileExtension,
                'file_size' => strlen($fileContent),
                'original_filename' => $originalName,
                'unique_filename' => $uniqueFileName,
                's3_bucket' => $s3Result['bucket'],
                's3_key' => $s3Result['key'],
                // Note: s3_url is not returned by S3FileService, it's optional and can be null
            ];
        } else {
            // Return error information for debugging
            return [
                'error' => true,
                's3_error' => $s3Result['error'] ?? 'Unknown S3 error',
                's3_result' => $s3Result
            ];
        }
    }

    /**
     * Save a single employee file to employee_answer_files table
     *
     * @param array $fileData File data
     * @param int $companyId Company ID
     * @param string $employeeUniqueId Employee Unique ID
     * @param int|null $employeeId Employee ID
     * @param int $answerId Answer ID
     * @param string $groupId Group ID
     * @param string $fieldId Field ID
     * @return object|null Saved file entity or null if failed
     */
    private function saveSingleEmployeeFile(array $fileData, int $companyId, string $employeeUniqueId, ?int $employeeId, int $answerId, string $groupId, string $fieldId): ?object
    {
        $employeeAnswerFilesTable = $this->getTable('EmployeeAnswerFiles', $companyId);
        
        $employeeFile = $employeeAnswerFilesTable->newEntity([
            'company_id' => $companyId,
            'answer_id' => $answerId,
            'employee_id' => $employeeId,
            'employee_unique_id' => $employeeUniqueId,
            'group_id' => $groupId,
            'field_id' => $fieldId,
            'file_name' => $fileData['file_name'],
            'file_path' => $fileData['file_path'],
            'file_type' => $fileData['file_type'],
            'file_size' => $fileData['file_size'],
            's3_bucket' => $fileData['s3_bucket'],
            's3_key' => $fileData['s3_key'],
            'deleted' => false
        ]);
        
        if ($employeeAnswerFilesTable->save($employeeFile)) {
            return $employeeFile;
        } else {
            // Log validation errors
            $errors = $employeeFile->getErrors();
            return null;
        }
    }

    /**
     * Delete employee file
     *
     * @param int $fileId File ID
     * @return \Cake\Http\Response
     */
    public function deleteFile(int $fileId): Response
    {
        $this->request->allowMethod(['DELETE']);
        
        try {
            // Authentication check
            $authResult = $this->Authentication->getResult();
            if (!$authResult || !$authResult->isValid()) {
                return $this->response->withStatus(401)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Unauthorized access',
                    ]));
            }

            $companyId = $this->getCompanyId($authResult);
            
            $employeeAnswerFilesTable = $this->getTable('EmployeeAnswerFiles', $companyId);
            
            $file = $employeeAnswerFilesTable->find('active')
                ->where([
                    'EmployeeAnswerFiles.id' => $fileId,
                    'EmployeeAnswerFiles.company_id' => $companyId
                ])
                ->first();
            
            if (!$file) {
                return $this->response
                    ->withStatus(404)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'File not found',
                    ]));
            }

            // Delete from S3
            if ($file->s3_bucket && $file->s3_key) {
                $this->s3Service->deleteFile($file->s3_bucket, $file->s3_key);
            }

            // Soft delete from database
            $file->deleted = true;
            $employeeAnswerFilesTable->save($file);
            
            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'message' => 'File deleted successfully'
                ]));
                
        } catch (\Exception $e) {
            return $this->response->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Failed to delete file',
                    'error' => $e->getMessage()
                ]));
        }
    }

    /**
     * Get company ID from authentication result
     *
     * @param object $authResult Authentication result
     * @return int|null Company ID
     */
    private function getCompanyId($authResult): ?int
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
        
        return null;
    }

    /**
     * Get video content type
     */
    private function getVideoContentType($extension): string
    {
        $contentTypes = [
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
            'mov' => 'video/quicktime',
            'wmv' => 'video/x-ms-wmv',
            'flv' => 'video/x-flv',
            'webm' => 'video/webm'
        ];
        
        return $contentTypes[$extension] ?? 'application/octet-stream';
    }
}

