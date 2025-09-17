<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Service\AuditService;
use Cake\Log\Log;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Audit Middleware
 * 
 * Automatically logs API requests and responses for audit purposes
 */
class AuditMiddleware implements MiddlewareInterface
{
    /**
     * Process the request and response
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Server\RequestHandlerInterface $handler
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $startTime = microtime(true);
        
        // Process the request
        $response = $handler->handle($request);
        
        // Only log API requests
        if ($request->getAttribute('prefix') === 'Api') {
            $this->logApiRequest($request, $response, $startTime);
        }
        
        return $response;
    }

    /**
     * Log API request and response
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface $response
     * @param float $startTime
     * @return void
     */
    private function logApiRequest(ServerRequestInterface $request, ResponseInterface $response, float $startTime): void
    {
        try {
            // Skip certain endpoints that don't need audit logging
            $path = $request->getUri()->getPath();
            $skipPaths = [
                '/api/audit-logs/getAuditLogs.json',
                '/api/audit-logs/getAuditStats.json',
                '/api/audit-logs/getFilterOptions.json',
            ];

            if (in_array($path, $skipPaths)) {
                return;
            }

            // Extract user information from request attributes
            $user = $request->getAttribute('identity');
            $companyId = $this->extractCompanyId($request, $user);
            
            if (!$companyId) {
                return; // Skip if no company ID
            }

            $auditService = new AuditService($companyId);

            // Determine action and entity type from request
            $action = $this->determineAction($request);
            $entityType = $this->determineEntityType($request);
            $entityId = $this->extractEntityId($request);
            $entityName = $this->extractEntityName($request);

            // Prepare audit data
            $auditData = [
                'user_id' => $user->id ?? 0,
                'username' => $user->username ?? 'system',
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'entity_name' => $entityName,
                'description' => $this->generateDescription($action, $entityType, $entityName),
                'ip_address' => $request->clientIp(),
                'user_agent' => $request->getHeaderLine('User-Agent'),
                'request_data' => $this->sanitizeRequestData($request),
                'response_data' => $this->sanitizeResponseData($response),
                'status' => $this->determineStatus($response),
                'error_message' => $this->extractErrorMessage($response),
            ];

            // Log the audit entry
            $auditService->logAction($auditData);

        } catch (\Exception $e) {
            // Don't let audit logging break the main request
            Log::error('Error in audit middleware: ' . $e->getMessage(), [
                'path' => $request->getUri()->getPath(),
                'method' => $request->getMethod(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Extract company ID from request or user
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param mixed $user
     * @return string|null
     */
    private function extractCompanyId(ServerRequestInterface $request, $user): ?string
    {
        // Try to get from user data
        if ($user && isset($user->company_id)) {
            return $user->company_id;
        }

        // Try to get from request data
        $data = $request->getParsedBody();
        if (isset($data['company_id'])) {
            return $data['company_id'];
        }

        // Try to get from query parameters
        $queryParams = $request->getQueryParams();
        if (isset($queryParams['company_id'])) {
            return $queryParams['company_id'];
        }

        return null;
    }

    /**
     * Determine action from request
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return string
     */
    private function determineAction(ServerRequestInterface $request): string
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();

        // Map HTTP methods to actions
        $actionMap = [
            'POST' => 'CREATE',
            'PUT' => 'UPDATE',
            'PATCH' => 'UPDATE',
            'DELETE' => 'DELETE',
            'GET' => 'VIEW',
        ];

        $baseAction = $actionMap[$method] ?? 'UNKNOWN';

        // Special cases for specific endpoints
        if (strpos($path, '/login') !== false) {
            return 'LOGIN';
        }
        if (strpos($path, '/logout') !== false) {
            return 'LOGOUT';
        }
        if (strpos($path, '/evaluate') !== false) {
            return 'EVALUATE';
        }
        if (strpos($path, '/assign') !== false) {
            return 'ASSIGN';
        }

        return $baseAction;
    }

    /**
     * Determine entity type from request path
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return string
     */
    private function determineEntityType(ServerRequestInterface $request): string
    {
        $path = $request->getUri()->getPath();

        // Extract entity type from path
        if (preg_match('/\/api\/([^\/]+)\//', $path, $matches)) {
            $entityType = $matches[1];
            
            // Map API endpoints to entity types
            $entityMap = [
                'scorecards' => 'scorecard',
                'scorecard-evaluations' => 'evaluation',
                'employees' => 'employee',
                'job-roles' => 'job_role',
                'role-levels' => 'role_level',
                'users' => 'user',
                'templates' => 'template',
            ];

            return $entityMap[$entityType] ?? $entityType;
        }

        return 'unknown';
    }

    /**
     * Extract entity ID from request
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return string|null
     */
    private function extractEntityId(ServerRequestInterface $request): ?string
    {
        $path = $request->getUri()->getPath();
        
        // Try to extract ID from path parameters
        if (preg_match('/\/([a-zA-Z0-9_-]+)\.json$/', $path, $matches)) {
            return $matches[1];
        }

        // Try to extract from request data
        $data = $request->getParsedBody();
        if (isset($data['id'])) {
            return $data['id'];
        }

        return null;
    }

    /**
     * Extract entity name from request
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return string|null
     */
    private function extractEntityName(ServerRequestInterface $request): ?string
    {
        $data = $request->getParsedBody();
        
        // Try common name fields
        $nameFields = ['name', 'title', 'entity_name', 'scorecard_name', 'employee_name'];
        
        foreach ($nameFields as $field) {
            if (isset($data[$field])) {
                return $data[$field];
            }
        }

        return null;
    }

    /**
     * Generate human-readable description
     *
     * @param string $action
     * @param string $entityType
     * @param string|null $entityName
     * @return string
     */
    private function generateDescription(string $action, string $entityType, ?string $entityName): string
    {
        $entityName = $entityName ? ": {$entityName}" : '';
        return ucfirst(strtolower($action)) . " {$entityType}{$entityName}";
    }

    /**
     * Sanitize request data for audit logging
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @return array
     */
    private function sanitizeRequestData(ServerRequestInterface $request): array
    {
        $data = $request->getParsedBody() ?? [];
        
        // Remove sensitive fields
        $sensitiveFields = ['password', 'token', 'secret', 'key', 'ssn', 'social_security'];
        
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[REDACTED]';
            }
        }

        return $data;
    }

    /**
     * Sanitize response data for audit logging
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return array
     */
    private function sanitizeResponseData(ResponseInterface $response): array
    {
        $body = $response->getBody()->getContents();
        $data = json_decode($body, true) ?? [];

        // Only log success/error status, not full response data
        return [
            'status_code' => $response->getStatusCode(),
            'success' => $data['success'] ?? null,
            'message' => $data['message'] ?? null,
        ];
    }

    /**
     * Determine status from response
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return string
     */
    private function determineStatus(ResponseInterface $response): string
    {
        $statusCode = $response->getStatusCode();
        
        if ($statusCode >= 200 && $statusCode < 300) {
            return 'success';
        } elseif ($statusCode >= 400 && $statusCode < 500) {
            return 'error';
        } elseif ($statusCode >= 500) {
            return 'error';
        }

        return 'warning';
    }

    /**
     * Extract error message from response
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return string|null
     */
    private function extractErrorMessage(ResponseInterface $response): ?string
    {
        if ($response->getStatusCode() >= 400) {
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);
            
            return $data['message'] ?? 'HTTP ' . $response->getStatusCode();
        }

        return null;
    }
}
