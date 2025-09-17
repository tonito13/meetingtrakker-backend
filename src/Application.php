<?php

declare(strict_types=1);

namespace App;

use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Datasource\FactoryLocator;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Http\BaseApplication;
use Cake\Http\Middleware\BodyParserMiddleware;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\Middleware\AssetMiddleware;
use Cake\Routing\Middleware\RoutingMiddleware;
use Authentication\AuthenticationService;
use Authentication\AuthenticationServiceProviderInterface;
use Authentication\Middleware\AuthenticationMiddleware;
use Psr\Http\Message\ServerRequestInterface;
use Cake\Routing\Router;
use Authorization\Middleware\AuthorizationMiddleware;
use Authentication\Authenticator\JwtAuthenticator;
use Authentication\Authenticator\AuthenticationRequiredException;
// use App\Middleware\CorsMiddleware; 

use Authentication\AuthenticationServiceInterface;
use Authentication\Identifier\AbstractIdentifier;
use Authentication\Identifier\IdentifierInterface;
use Authentication\Identifier\PasswordIdentifier;
use Cake\Http\Middleware\CsrfProtectionMiddleware;

class Application extends BaseApplication implements AuthenticationServiceProviderInterface
{
    public function bootstrap(): void
    {
        parent::bootstrap();

        $this->addPlugin('Authorization');
        $this->addPlugin('Authentication');

        if (PHP_SAPI !== 'cli') {
            FactoryLocator::add(
                'Table',
                (new \Cake\ORM\Locator\TableLocator())->allowFallbackClass(false)
            );
        }
    }

    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        $middlewareQueue
            // Catch any exceptions in the lower layers,
            // and make an error page/response
            ->add(new ErrorHandlerMiddleware(Configure::read('Error'), $this))

            // Handle plugin/theme assets like CakePHP normally does.
            ->add(new AssetMiddleware([
                'cacheTime' => Configure::read('Asset.cacheTime'),
            ]))

            // Add routing middleware.
            // If you have a large number of routes connected, turning on routes
            // caching in production could improve performance.
            // See https://github.com/CakeDC/cakephp-cached-routing
            ->add(new RoutingMiddleware($this))

            // Parse various types of encoded request bodies so that they are
            // available as array through $request->getData()
            // https://book.cakephp.org/4/en/controllers/middleware.html#body-parser-middleware
            ->add(new BodyParserMiddleware())
            ->add(new AuthenticationMiddleware($this));

            // Cross Site Request Forgery (CSRF) Protection Middleware
            // https://book.cakephp.org/4/en/security/csrf.html#cross-site-request-forgery-csrf-middleware
            // ->add(new CsrfProtectionMiddleware([
            //     'httponly' => true,
            // ]));

            $csrf = new CsrfProtectionMiddleware();
            $csrf->skipCheckCallback(function($request){
                if($request->getParam('prefix') === 'Api'){
                    return true;
                }

            });

        return $middlewareQueue;
    }
    

    public function getAuthenticationService(ServerRequestInterface $request): AuthenticationServiceInterface
    {
        $service = new AuthenticationService();
        

        $fields = [
            AbstractIdentifier::CREDENTIAL_USERNAME => 'username',
            AbstractIdentifier::CREDENTIAL_PASSWORD => 'password'
        ];

        
        /**
         * see code at routes.php for Api prefix configuration
         */
        if($request->getParam('prefix') === 'Api'){
            $service->loadAuthenticator('Authentication.Form', [
                'fields' => $fields,
                'loginUrl' => 
                    [
                        Router::url([
                            'prefix' => 'Api',
                            'plugin' => null,
                            'controller' => 'Users',
                            'action' => 'login',
                            'unauthenticatedRedirect' => false,
                            'authError' => 'Did you really think you are allowed to see that?',
                        ]),
                        Router::url([
                            'prefix' => 'Api',
                            'plugin' => null,
                            'controller' => 'Users',
                            'action' => 'login',
                            '_ext' => 'json', // or specify the desired extension
                            'unauthenticatedRedirect' => false,
                            'authError' => 'Did you really think you are allowed to see that?',
                        ]),
                    ]
                   
            ]);

            $service->loadIdentifier('Authentication.JwtSubject');
            $service->loadAuthenticator('Authentication.Jwt', [
                'secretKey' => file_get_contents(CONFIG . '/jwt.pem'),
                'algorithm' => 'RS256',
                'returnPayload' => false,
                'authError' => 'Did you really think you are allowed to see that?',
            ]);

        }else{

            // Define where users should be redirected to when they are not authenticated
            $service->setConfig([
                'unauthenticatedRedirect' => Router::url([
                        'prefix' => false,
                        'plugin' => null,
                        'controller' => 'Users',
                        'action' => 'login',
                ]),
                'queryParam' => 'redirect',
            ]);

            // Load the authenticators. Session should be first.
            $service->loadAuthenticator('Authentication.Session');
            $service->loadAuthenticator('Authentication.Form', [
            'fields' => $fields,
            'loginUrl' => Router::url([
                'prefix' => false,
                'plugin' => null,
                'controller' => 'Users',
                'action' => 'login',
            ]),
        ]);
            
        }
        

        // Load identifiers
        $service->loadIdentifier('Authentication.Password', compact('fields'));

        return $service;
    }
}