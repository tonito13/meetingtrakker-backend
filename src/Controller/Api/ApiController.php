<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
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
                // In test environment, use the test connection for company-specific tables
                if (Configure::read('debug') && php_sapi_name() === 'cli') {
                    $connection = ConnectionManager::get('test');
                } else {
                    $connection = ConnectionManager::get('client_' . $companyId);
                }
            }

            $registry = TableRegistry::getTableLocator();
            $registry->clear(); // Clear all loaded tables from the locator
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
        } else {
            $userId = $user->id ?? null;
            $companyId = $user->company_id ?? null;
        }

        $payload = [
            'sub' => $userId,
            'company_id' => $companyId,
            'exp' => time() + 28800,
            'iat' => $issuedAt,
        ];

        return JWT::encode($payload, $jwtKey, 'RS256');
    }
}