<?php
namespace App\Auth;

use Authentication\Authenticator\AuthenticatorInterface;
use Authentication\Authenticator\Result;
use Authentication\Identifier\IdentifierInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Cake\Core\Configure;
use Laminas\Diactoros\Response\JsonResponse;

class ScorecardTrakkerApiAuthenticate implements AuthenticatorInterface
{
    protected IdentifierInterface $identifier;

    public function __construct(IdentifierInterface $identifier)
    {
        $this->identifier = $identifier;
    }

    public function authenticate(ServerRequestInterface $request): Result
    {
        $authorization = $request->getHeaderLine('Authorization');

        if (empty($authorization) || !preg_match('/^Bearer\s(.+)$/', $authorization, $matches)) {
            return new Result(null, Result::FAILURE_CREDENTIALS_MISSING, ['message' => 'Authorization token is missing or malformed']);
        }

        $token = $matches[1];
        $jwtKey = Configure::read('JWT.key');

        try {
            $decodedToken = JWT::decode($token, new Key($jwtKey, 'HS256'));

            // Generate a new token with refreshed `iat`
            $newToken = JWT::encode([
                'uinf' => $decodedToken->uinf,
                'iat' => time(),
                'oiat' => $decodedToken->oiat
            ], $jwtKey, 'HS256');

            return new Result([
                'token' => $newToken,
                'user' => (array) $decodedToken->uinf
            ], Result::SUCCESS);
        } catch (\Firebase\JWT\ExpiredException $e) {
            return new Result(null, Result::FAILURE_CREDENTIALS_INVALID, ['message' => 'Token has expired']);
        } catch (\Exception $e) {
            return new Result(null, Result::FAILURE_CREDENTIALS_INVALID, ['message' => 'Invalid token']);
        }
    }

    public function unauthorizedChallenge(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse([
            'status' => 'error',
            'message' => 'Unauthorized access. Please provide a valid token.'
        ], 401);
    }
}
