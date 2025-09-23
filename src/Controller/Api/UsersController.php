<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use Authentication\Controller\Component\AuthenticationComponent;
use Cake\Event\EventInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Cake\Http\Exception\UnauthorizedException;
use Cake\Utility\Security;
use Cake\ORM\TableRegistry;
use Cake\DataSource\ConnectionManager;
use Cake\Core\Configure;

class UsersController extends ApiController
{
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('Authentication.Authentication');
        $this->loadComponent('Authorization.Authorization');

        // Allow login without authentication
        $this->Authentication->allowUnauthenticated(['login']);
    }

    // public function login()
    // {
    //     // $this->request->allowMethod(['post']);

    //     // $user = $this->Authentication->getIdentity();

    //     // if (!$user) {
    //     //     $this->response = $this->response->withStatus(401);
    //     //     $this->set([
    //     //         'message' => 'Invalid credentials',
    //     //         'success' => false,
    //     //     ]);
    //     //     return;
    //     // }

    //     // $jwt = JWT::encode(
    //     //     [
    //     //         'sub' => $user->id,
    //     //         'exp' => time() + 604800, // 7 days expiration
    //     //     ],
    //     //     env('JWT_SECRET', 'your_secret_key'),
    //     //     'HS256'
    //     // );

    //     // $this->set([
    //     //     'token' => $jwt,
    //     //     'success' => true,
    //     // ]);

    //     return $this->response->withStringBody(json_encode([
    //         'message' => 'Login successful',
    //         'status' => 200
    //     ]));
    // }


    public function login()
    {
        $data = $this->request->getData();
        
        // Validate input
        if (empty($data['username']) || empty($data['password'])) {
            return $this->response->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode(['message' => 'Username and password are required']));
        }

        // Fetch Users Table
        $usersTable = $this->getTable('Users');

        // Find user by username
        $user = $usersTable->find()
            ->where(['username' => $data['username']])
            ->first();

    
        // Check if user exists and password is correct
        if (!$user || !password_verify($data['password'], $user->password)) {
            return $this->response->withStatus(401)
                ->withType('application/json')
                ->withStringBody(json_encode(['message' => 'Invalid username or password']));
        }
       
        $token = $this->generateToken($user);
       


        return $this->response->withType('application/json')->withStringBody(json_encode([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email_address,
                'system_user_role' => $user->system_user_role
            ],
            'success' => true
        ]));
    }

    public function index()
    {
        $this->request->allowMethod(['get']);
        $users = $this->Users->find()->all();
        return $this->response->withType('application/json')
            ->withStringBody(json_encode($users));
    }

    public function unauthorized()
    {
        $this->response = $this->response->withStatus(401);

        $format = $this->request->getParam('_ext'); // Check request type

        if ($format === 'xml') {
            $this->set([
                'status' => 'error',
                'message' => 'Unauthorized access.',
                '_serialize' => ['status', 'message']
            ]);
            $this->viewBuilder()->setOption('serialize', ['status', 'message']);
            return;
        }

        // Default JSON response
        return $this->response->withType('application/json')->withStringBody(json_encode([
            'status' => 'error',
            'message' => 'Unauthorized access.'
        ]));
    }

    public function test()
    {
        $token = $this->Authentication->getResult();
       
        return $this->response->withType('application/json')
            ->withStringBody(json_encode($token));
    }
}
