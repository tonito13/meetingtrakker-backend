<?php
declare(strict_types=1);

namespace App\Service;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Cake\Core\Configure;
use Cake\Log\Log;

/**
 * S3 File Service for ScorecardTrakker Application
 * 
 * Handles all file operations with AWS S3 including:
 * - File uploads with company isolation using company ID folders
 * - File downloads and URL generation
 * - File deletion
 * - Single bucket management
 */
class S3FileService
{
    private S3Client $s3Client;
    private string $bucket;
    private array $folders;
    private array $settings;

    public function __construct()
    {
        // Check if AWS SDK classes are available
        if (!class_exists('Aws\S3\S3Client')) {
            // Log detailed information for debugging
            Log::error('AWS SDK S3Client class not found');
            Log::error('Current working directory: ' . getcwd());
            Log::error('Script path: ' . __FILE__);
            Log::error('PHP SAPI: ' . PHP_SAPI);
            
            // Check autoloaders
            $autoloaders = spl_autoload_functions();
            Log::error('Autoloaders registered: ' . count($autoloaders));
            
            throw new \Exception('AWS SDK S3Client class not found. Please ensure aws/aws-sdk-php is properly installed and autoloaded.');
        }
        
        $awsConfig = Configure::read('AWS');
        
        // Handle both nested and flat configuration structures
        if (isset($awsConfig['AWS'])) {
            $awsConfig = $awsConfig['AWS'];
        }
        
        // Debug AWS configuration
        Log::info('AWS Configuration Debug:');
        Log::info('  - Raw config: ' . print_r($awsConfig, true));
        Log::info('  - Region: ' . ($awsConfig['region'] ?? 'NOT SET'));
        Log::info('  - Bucket: ' . ($awsConfig['bucket'] ?? 'NOT SET'));
        Log::info('  - Credentials key: ' . (isset($awsConfig['credentials']['key']) ? 'SET' : 'NOT SET'));
        Log::info('  - Credentials secret: ' . (isset($awsConfig['credentials']['secret']) ? 'SET' : 'NOT SET'));
        
        $this->s3Client = new S3Client([
            'version' => $awsConfig['version'],
            'region' => $awsConfig['region'],
            'credentials' => [
                'key' => $awsConfig['credentials']['key'],
                'secret' => $awsConfig['credentials']['secret'],
            ],
        ]);
        
        $this->bucket = $awsConfig['bucket'];
        $this->folders = $awsConfig['folders'];
        $this->settings = $awsConfig['settings'];
        
        Log::info('S3FileService initialized successfully');
        Log::info('  - Bucket: ' . $this->bucket);
        Log::info('  - Region: ' . $awsConfig['region']);
    }

    /**
     * Get the company folder name for S3 path
     * This method should be called with the company name, not ID
     */
    public function getCompanyFolder(string $companyName): string
    {
        // Sanitize company name for S3 folder
        $sanitized = preg_replace('/[^a-zA-Z0-9_\s]/', '', $companyName);
        $sanitized = str_replace(' ', '_', $sanitized);
        $sanitized = strtolower($sanitized);
        $sanitized = preg_replace('/_+/', '_', $sanitized);
        $sanitized = trim($sanitized, '_');
        
        return $sanitized;
    }

    /**
     * Upload a file to S3
     */
    public function uploadFile(
        string $content,
        string $fileName,
        int $companyId,
        string $folderType = 'employees',
        string $companyName = null,
        string $employeeName = null,
        string $interventionUniqueId = null,
        string $competencyName = null,
        string $levelName = null,
        string $employeeUniqueId = null,
        string $fieldId = null,
        string $competencyUniqueId = null,
        string $levelUniqueId = null,
        string $courseUniqueId = null,
        string $moduleUniqueId = null
    ): array {
        try {
            // Build the S3 key with scorecardtrakker prefix using company ID directly
            if ($folderType === 'employees' && $employeeUniqueId && $fieldId) {
                // For employees: scorecardtrakker/employees/{companyId}/{employeeUniqueId}/{fieldId}/{filename}
                $key = 'scorecardtrakker/' . $this->folders[$folderType] . $companyId . '/' . $employeeUniqueId . '/' . $fieldId . '/' . $fileName;
            } elseif ($folderType === 'interventions' && $employeeUniqueId && $interventionUniqueId) {
                // For interventions: scorecardtrakker/interventions/{companyId}/{employeeUniqueId}/{interventionUniqueId}/{filename}
                $key = 'scorecardtrakker/' . $this->folders[$folderType] . $companyId . '/' . $employeeUniqueId . '/' . $interventionUniqueId . '/' . $fileName;
            } elseif ($folderType === 'rubrics' && $competencyUniqueId && $levelUniqueId) {
                // For rubrics: scorecardtrakker/rubrics/{companyId}/{competencyUniqueId}/{levelUniqueId}/{filename}
                $key = 'scorecardtrakker/' . $this->folders[$folderType] . $companyId . '/' . $competencyUniqueId . '/' . $levelUniqueId . '/' . $fileName;
            } elseif ($folderType === 'training_courses' && $courseUniqueId && $moduleUniqueId) {
                // For training courses: scorecardtrakker/training_courses/{companyId}/{courseUniqueId}/{moduleUniqueId}/{filename}
                $key = 'scorecardtrakker/' . $this->folders[$folderType] . $companyId . '/' . $courseUniqueId . '/' . $moduleUniqueId . '/' . $fileName;
            } elseif ($folderType === 'tests' && $interventionUniqueId) {
                // For tests: scorecardtrakker/tests/{companyId}/{examinationUniqueId}/{filename}
                $key = 'scorecardtrakker/' . $this->folders[$folderType] . $companyId . '/' . $interventionUniqueId . '/' . $fileName;
            } elseif ($folderType === 'questions' && $interventionUniqueId) {
                // For questions: scorecardtrakker/tests/questions/{companyId}/{testId}/{filename}
                $key = 'scorecardtrakker/tests/questions/' . $companyId . '/' . $interventionUniqueId . '/' . $fileName;
            } elseif ($folderType === 'answers' && $interventionUniqueId) {
                // For answers: scorecardtrakker/tests/answers/{companyId}/{assignmentId}/{filename}
                $key = 'scorecardtrakker/tests/answers/' . $companyId . '/' . $interventionUniqueId . '/' . $fileName;
            } else {
                // For other types: scorecardtrakker/{folderType}/{companyId}/{filename}
                $key = 'scorecardtrakker/' . $this->folders[$folderType] . $companyId . '/' . $fileName;
            }
            
            // Debug logging
            Log::info('S3 Upload Debug:');
            Log::info('  - Bucket: ' . $this->bucket);
            Log::info('  - Key: ' . $key);
            Log::info('  - Company ID: ' . $companyId);
            Log::info('  - Folder Type: ' . $folderType);
            Log::info('  - File Name: ' . $fileName);
            Log::info('  - Content Length: ' . strlen($content));
            
            $result = $this->s3Client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'Body' => $content,
                'ACL' => $this->settings['ACL'],
                'StorageClass' => $this->settings['StorageClass'],
                'ServerSideEncryption' => $this->settings['ServerSideEncryption'],
                'Metadata' => [
                    'original-name' => $fileName,
                    'uploaded-by' => 'scorecardtrakker',
                    'company-id' => (string)$companyId
                ]
            ]);

            Log::info('S3 Upload Success:');
            Log::info('  - ETag: ' . $result['ETag']);

            return [
                'success' => true,
                'bucket' => $this->bucket,
                'key' => $key,
                'etag' => $result['ETag']
            ];
        } catch (AwsException $e) {
            Log::error('S3 Upload Error: ' . $e->getMessage());
            Log::error('S3 Upload Error Code: ' . $e->getAwsErrorCode());
            Log::error('S3 Upload Error Details: ' . print_r($e->toArray(), true));
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            Log::error('General Upload Error: ' . $e->getMessage());
            Log::error('Error Trace: ' . $e->getTraceAsString());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Download a file from S3
     */
    public function downloadFile(string $bucket, string $key): ?string
    {
        try {
            Log::info('S3 Download File:');
            Log::info('  - Bucket: ' . $bucket);
            Log::info('  - Key: ' . $key);
            
            $result = $this->s3Client->getObject([
                'Bucket' => $bucket,
                'Key' => $key
            ]);
            
            Log::info('S3 Download Success');
            return $result['Body']->getContents();
        } catch (AwsException $e) {
            Log::error('S3 Download Error: ' . $e->getMessage());
            Log::error('S3 Download Error Code: ' . $e->getAwsErrorCode());
            return null;
        }
    }

    /**
     * Delete a file from S3
     */
    public function deleteFile(string $bucket, string $key): bool
    {
        try {
            $this->s3Client->deleteObject([
                'Bucket' => $bucket,
                'Key' => $key
            ]);
            
            return true;
        } catch (AwsException $e) {
            Log::error('S3 Delete Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate a presigned URL for file access
     */
    public function generatePresignedUrl(string $bucket, string $key, int $expires = 3600): ?string
    {
        try {
            // Determine if this is a PDF file
            $isPdf = strtolower(pathinfo($key, PATHINFO_EXTENSION)) === 'pdf';
            
            $params = [
                'Bucket' => $bucket,
                'Key' => $key
            ];
            
            // For PDFs, set content disposition to inline to display in browser instead of downloading
            if ($isPdf) {
                $params['ResponseContentDisposition'] = 'inline';
                $params['ResponseContentType'] = 'application/pdf';
            }
            
            $cmd = $this->s3Client->getCommand('GetObject', $params);
            
            $request = $this->s3Client->createPresignedRequest($cmd, "+{$expires} seconds");
            return (string) $request->getUri();
        } catch (AwsException $e) {
            Log::error('S3 Presigned URL Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if a file exists in S3
     */
    public function fileExists(string $bucket, string $key): bool
    {
        try {
            Log::info('S3 File Exists Check:');
            Log::info('  - Bucket: ' . $bucket);
            Log::info('  - Key: ' . $key);
            
            $exists = $this->s3Client->doesObjectExist($bucket, $key);
            Log::info('  - Exists: ' . ($exists ? 'YES' : 'NO'));
            
            return $exists;
        } catch (AwsException $e) {
            Log::error('S3 File Exists Check Error: ' . $e->getMessage());
            Log::error('S3 File Exists Check Error Code: ' . $e->getAwsErrorCode());
            return false;
        }
    }

    /**
     * Get the bucket name used by this service
     */
    public function getBucketName(): string
    {
        return $this->bucket;
    }

    /**
     * Get file metadata from S3
     */
    public function getFileMetadata(string $bucket, string $key): ?array
    {
        try {
            $result = $this->s3Client->headObject([
                'Bucket' => $bucket,
                'Key' => $key
            ]);
            
            return [
                'size' => $result['ContentLength'],
                'lastModified' => $result['LastModified'],
                'etag' => $result['ETag'],
                'contentType' => $result['ContentType'] ?? 'application/octet-stream'
            ];
        } catch (AwsException $e) {
            Log::error('S3 Metadata Error: ' . $e->getMessage());
            return null;
        }
    }
}

