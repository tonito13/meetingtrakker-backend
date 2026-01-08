<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use App\Service\CompanyMappingService;
use Cake\Event\EventInterface;
use Cake\Controller\Controller;
use Cake\DataSource\ConnectionManager;
use Cake\ORM\TableRegistry;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Cake\Core\Configure;
use Cake\Http\Exception\UnauthorizedException;
use Cake\Log\Log;
class ApiController extends Controller
{
    public function initialize(): void
    {
        parent::initialize();
        // $this->loadComponent('RequestHandler');
        $this->loadComponent('Flash');
        
        // Disable layout rendering for API responses



        //  // Check request format (JSON or XML)
        //  $format = $this->request->getParam('_ext');

        //  if ($format === 'json' || $format === 'xml') {
         
        //      // Use JWT Authentication for API requests
        //      $this->loadComponent('Authentication.Authentication', [
        //          'unauthenticatedRedirect' => false,
        //          'queryParam' => 'token',
        //          'storage' => 'Memory'
        //      ]);
        //  } else {
            
        //      // Use Form Authentication for web requests
        //      $this->loadComponent('Authentication.Authentication', [
        //          'authenticators' => [
        //              'Authentication.Form' => [
        //                  'fields' => [
        //                      'username' => 'email',
        //                      'password' => 'password'
        //                  ],
        //                  'loginUrl' => '/api/user/unauthorized'
        //              ]
        //          ],
        //          'identityClass' => 'App\Model\Entity\Users',
        //          'storage' => 'Session'
        //      ]);
 
        //      $this->loadComponent('Authorization.Authorization');
        //  }
        $this->loadComponent('Authorization.Authorization');
        $this->loadComponent('Authentication.Authentication');
        // Set JSON response type globally
        // $this->response = $this->response->withType('application/json');
    }

    public function beforeFilter(EventInterface $event)
    {

        parent::beforeFilter($event);

        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400');
        header('Access-Control-Request-Headers: Content-Type, Authorization, X-Requested-With');
        header('Vary: Origin');


        // Access-Control-Expose-Headers is important to expose Access-Token header in CORS response header
        // the problem see here https://github.com/axios/axios/issues/606
        // see for reference https://stackoverflow.com/questions/37897523/axios-get-access-to-response-header-fields
        header('Access-Control-Expose-Headers: Access-Token');

        if ($this->request->is('options')) {
            return $this->response->withStatus(200);
        }

        if($this->request->getParam('prefix') === 'Api')
        {
            $authentication_result = $this->Authentication->getResult();
            if($authentication_result->isValid()){
                // Only regenerate token if it's a fresh login (Form authentication)
                // JWT authentication already has a valid token, no need to regenerate
                $user_information = $authentication_result->getData();
                
                // Check for company mapping and override company_id if mapping exists
                try {
                    $mappingService = new CompanyMappingService();
                    
                    // Extract user ID and username from user_information (handle both object and array)
                    $userId = null;
                    $username = '';
                    
                    if (is_object($user_information)) {
                        $userId = $user_information->id ?? null;
                        $username = $user_information->username ?? '';
                    } elseif (is_array($user_information) || $user_information instanceof \ArrayObject) {
                        $userId = $user_information['id'] ?? $user_information['sub'] ?? null;
                        $username = $user_information['username'] ?? '';
                    }
                    
                    if ($userId !== null && !empty($username)) {
                        $mappedCompanyId = $mappingService->getMappedCompanyIdForUser(
                            (int)$userId,
                            $username,
                            'meetingtrakker'
                        );
                        
                        if ($mappedCompanyId !== null) {
                            // Override company_id with mapped company_id
                            if (is_object($user_information)) {
                                $user_information->company_id = $mappedCompanyId;
                            } elseif (is_array($user_information) || $user_information instanceof \ArrayObject) {
                                $user_information['company_id'] = $mappedCompanyId;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Log error but don't fail authentication
                    Log::error('Error checking user company mapping in beforeFilter: ' . $e->getMessage(), [
                        'trace' => $e->getTraceAsString()
                    ]);
                }
                
                if (!is_array($user_information) && !($user_information instanceof \ArrayObject)) {
                    // This is a user object from Form authentication, generate a token
                    $token = $this->generateToken($user_information);
                    $this->response = $this->response->withHeader('Authorization', 'Bearer ' . $token);
                }
                // For JWT authentication, the token is already in the Authorization header, no need to regenerate
            }else{
                $this->response = $this->response->withStatus(401)
                ->withType('application/json')
                ->withStringBody(json_encode(['error' => 'Invalid credentials.']));
            }
        }


        // $format = $this->request->getParam('_ext'); 
        
        // if ($format === 'json' || $format === 'xml') {
        //     $authResult = $this->Authentication->getResult();
            
        //     if (!$authResult || !$authResult->isValid()) {
        //         header('HTTP/1.0 401 Unauthorized');
        //         die('Invalid credential.');
        //     }

        //     // Retrieve user data (assumed token is stored in the authentication result)
        //     $userToken = $authResult->getData();
        //     debug($userToken);
        //     exit;
        //     // Maintain original behavior: Set the Access-Token header
        //     header('Access-Token: ' . $userToken['token']);

        //     // Set the authenticated user
        //     $this->Authentication->setIdentity($userToken);
            
        // }
    }

    public function getTable($tableName, $companyId = 'default')
    {
        try {
            if ($companyId == 'default') {
                $connection = ConnectionManager::get('default');
            } else {
                // In test environment, use the test company database connection for company-specific tables
                if (Configure::read('debug') && php_sapi_name() === 'cli') {
                    // Use the test company database connection - this should always exist for company-specific tables
                    // Never fall back to 'test' (workmatica_test) as it's the central database, not company-specific
                    // Try the alias first (test_client_{companyId}), then the direct connection name
                    $testConnectionName = 'test_client_' . $companyId;
                    $directConnectionName = 'client_' . $companyId . '_test';
                    
                    try {
                        // First try the alias (created in tests/bootstrap.php)
                        $connection = ConnectionManager::get($testConnectionName);
                    } catch (\Exception $e1) {
                        try {
                            // Fallback to direct connection name
                            $connection = ConnectionManager::get($directConnectionName);
                        } catch (\Exception $e2) {
                            // If neither connection exists, throw a clear error
                            // Company-specific tables should NEVER use the central 'test' database
                            throw new \Exception(
                                "Test company database connection not found. Tried '{$testConnectionName}' and '{$directConnectionName}'. " .
                                "Company-specific tables should never use the central 'test' database. " .
                                "Error 1: " . $e1->getMessage() . " Error 2: " . $e2->getMessage()
                            );
                        }
                    }
                } else {
                    $connection = ConnectionManager::get('client_' . $companyId);
                }
            }

            $registry = TableRegistry::getTableLocator();
            // Remove the table from registry if it exists to avoid "already exists" errors
            // This allows us to reconfigure it with the correct connection
            // This prevents connection exhaustion while maintaining functionality
            try {
                if ($registry->exists($tableName)) {
                    $registry->remove($tableName);
                }
            } catch (\Exception $e) {
                // If removal fails, try to get existing table and check if connection matches
                try {
                    $existingTable = $registry->get($tableName);
                    $existingConnection = $existingTable->getConnection();
                    // If connections are the same, we can use the existing table
                    if ($existingConnection === $connection) {
                        return $existingTable;
                    }
                    // If different, force remove
                    $registry->remove($tableName);
                } catch (\Exception $e2) {
                    // Table doesn't exist or can't be accessed, continue to create new one
                }
            }
            
            $tableData = $registry->get($tableName, ['connection' => $connection]);

            return $tableData;
        } catch (\Exception $e) {
            // Log the error instead of using debug()
            Log::error('Error getting table ' . $tableName . ' for company ' . $companyId . ': ' . $e->getMessage());
            throw $e; // Re-throw the exception so it can be handled properly
        }
    }

    public function verifyToken($token)
    {
        $jwtKey = Configure::read('JWT.key');

        try {
            $decoded = JWT::decode($token, new Key($jwtKey, 'HS256'));
            return (array) $decoded; // Return user data
        } catch (\Exception $e) {
            throw new UnauthorizedException('Invalid or expired token');
        }
    }

    public function generateToken($user)
    {
        $jwtKey = file_get_contents(CONFIG . '/jwt.key');
        $issuedAt = time();

        // Handle both user objects and JWT payload arrays/objects
        if (is_array($user) || $user instanceof \ArrayObject) {
            $userId = $user['sub'] ?? null;
            $companyId = $user['company_id'] ?? null;
            $systemUserRole = $user['system_user_role'] ?? null;
        } else {
            $userId = $user->id ?? null;
            $companyId = $user->company_id ?? null;
            $systemUserRole = $user->system_user_role ?? null;
        }

        // Check for company mapping for meetingtrakker (only for regular users)
        $companyId = $user->company_id ?? null;
        
        // Handle both object and array formats
        $userId = null;
        $username = '';
        
        if (is_object($user)) {
            $userId = $user->id ?? null;
            $username = $user->username ?? '';
            $companyId = $companyId ?? $user->company_id ?? null;
        } elseif (is_array($user) || $user instanceof \ArrayObject) {
            $userId = $user['id'] ?? $user['sub'] ?? null;
            $username = $user['username'] ?? '';
            $companyId = $companyId ?? $user['company_id'] ?? null;
        }
        
        if ($userId !== null && !empty($username)) {
            try {
                $mappingService = new CompanyMappingService();
                $mappedCompanyId = $mappingService->getMappedCompanyIdForUser(
                    (int)$userId,
                    $username,
                    'meetingtrakker'
                );
            if ($mappedCompanyId !== null) {
                $companyId = $mappedCompanyId;
                }
            } catch (\Exception $e) {
                // Log error but don't fail token generation
                Log::error('Error checking user company mapping in generateToken: ' . $e->getMessage());
            }
        }
        
        $payload = [
            'sub' => $userId,
            'username' => $username, // Include username in JWT payload
            'company_id' => $companyId,
            'system_user_role' => $systemUserRole,
            'exp' => time() + 28800,
            'iat' => $issuedAt,
        ];

        return JWT::encode($payload, $jwtKey, 'RS256');
    }

     /**
     * Get mapped company ID for a user (re-validates from database)
     * 
     * @deprecated Use CompanyMappingService::getMappedCompanyIdForUser() instead
     * @param int $userId User ID
     * @param string $username Username
     * @return int|null Mapped company ID if mapping exists and is active, null otherwise
     */
    private function getMappedCompanyIdForUser($userId, $username)
    {
        try {
            $mappingService = new CompanyMappingService();
            return $mappingService->getMappedCompanyIdForUser(
                (int)$userId,
                $username,
                'meetingtrakker'
            );
        } catch (\Exception $e) {
            // Log error but don't expose details - fail-safe fallback
            Log::error('Error checking user company mapping: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Normalize system user role for access control purposes
     * Only "admin" and "user" are valid roles in meetingtrakker.
     * Any other role (from other systems like orgtrakker) will be treated as "user".
     * The original role data remains unchanged in the database/token.
     * 
     * @param string|null $role The original system user role
     * @return string Normalized role: "admin" or "user"
     */
    protected function normalizeRoleForAccessControl(?string $role): string
    {
        if ($role === null || $role === '') {
            return 'user';
        }
        
        $normalizedRole = strtolower(trim($role));
        
        // Only "admin" is treated as admin, everything else is "user"
        if ($normalizedRole === 'admin') {
            return 'admin';
        }
        
        return 'user';
    }

    /**
     * Check if the authenticated user is an admin
     * 
     * @return bool True if user is admin, false otherwise
     */
    protected function isAdmin(): bool
    {
        $authResult = $this->Authentication->getResult();
        if (!$authResult || !$authResult->isValid()) {
            Log::debug('isAdmin: Authentication result is invalid');
            return false;
        }

        $user = $authResult->getData();
        
        // Convert to array for consistent access (JWT decode returns stdClass)
        if (is_object($user)) {
            // Use get_object_vars for stdClass, or cast to array
            if ($user instanceof \ArrayObject) {
                $userArray = $user->getArrayCopy();
            } else {
                $userArray = get_object_vars($user);
            }
        } else {
            $userArray = $user;
        }

        // Try multiple possible field names for the role
        $userRole = $userArray['system_user_role'] 
                 ?? $userArray['systemUserRole'] 
                 ?? $userArray['user_role']
                 ?? $userArray['role']
                 ?? null;

        // If still null and it's an object, try direct property access
        if ($userRole === null && is_object($user)) {
            $userRole = $user->system_user_role 
                     ?? $user->systemUserRole 
                     ?? $user->user_role
                     ?? $user->role
                     ?? null;
        }

        // Normalize role for access control (treat non-admin/non-user roles as "user")
        $normalizedRole = $this->normalizeRoleForAccessControl($userRole);
        $isAdmin = $normalizedRole === 'admin';
        
        // Log the result for debugging
        Log::debug('isAdmin check result', [
            'original_role' => $userRole,
            'normalized_role' => $normalizedRole,
            'isAdmin' => $isAdmin,
        ]);

        return $isAdmin;
    }

    /**
     * Require admin access - returns error response if user is not admin
     * 
     * @return \Cake\Http\Response|null Response with 403 error if not admin, null if admin
     */
    protected function requireAdmin()
    {
        if (!$this->isAdmin()) {
            return $this->response
                ->withStatus(403)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Admin access required. You do not have permission to perform this action.'
                ]));
        }

        return null;
    }

    /**
     * Require admin access for template management operations
     * 
     * @return \Cake\Http\Response|null Response with 403 error if not admin, null if admin
     */
    protected function requireAdminForTemplates()
    {
        return $this->requireAdmin();
    }
}