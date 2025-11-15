<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\Api\ApiController;
use Cake\Core\Configure;

class LevelTemplatesController extends ApiController
{
    public function initialize(): void
    {
        parent::initialize();
    }

    public function addLevelTemplate()
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

        // Require admin access for template management
        $adminCheck = $this->requireAdminForTemplates();
        if ($adminCheck !== null) {
            return $adminCheck;
        }

        $data = $this->request->getData();
        $company_id = $this->getCompanyId($authResult);
        $authData = $authResult->getData();
        $username = null;
        if ($authData instanceof \ArrayObject || is_array($authData)) {
            $username = $authData['username'] ?? $authData['sub'] ?? null;
        } elseif (is_object($authData)) {
            $username = $authData->username ?? $authData->sub ?? null;
        }

        $LevelTemplatesTable = $this->getTable('LevelTemplates', $company_id);

        $levelsTemplate = $LevelTemplatesTable->newEntity([
            'name' => $data['name'] ?? 'Untitled',
            'structure' => $data['structure'] ?? [],
            'company_id' => $company_id,
            'created_by' => $username,
            'deleted' => 0,
            'created' => date('Y-m-d H:i:s'),
            'modified' => date('Y-m-d H:i:s'),
        ]);

        if ($LevelTemplatesTable->save($levelsTemplate)) {
            return $this->response->withType('application/json')->withStringBody(json_encode([
                'success' => true,
                'id' => $levelsTemplate->id,
            ]));
        } else {
            $this->response = $this->response->withStatus(422);
            return $this->response->withType('application/json')->withStringBody(json_encode([
                'success' => false,
                'errors' => $levelsTemplate->getErrors(),
            ]));
        }
    }

    public function updateLevelTemplate()
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

        // Require admin access for template management
        $adminCheck = $this->requireAdminForTemplates();
        if ($adminCheck !== null) {
            return $adminCheck;
        }

        try {
            $data = $this->request->getData();
            $company_id = $this->getCompanyId($authResult);

            // Validate required fields
            if (empty($data['id'])) {
                return $this->response
                    ->withStatus(400)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Template ID is required for update.',
                    ]));
            }

            // Get the LevelTemplatesTable table for the company
            $LevelTemplatesTable = $this->getTable('LevelTemplates', $company_id);

            // Fetch the existing template
            $template = $LevelTemplatesTable->find()
                ->where(['id' => $data['id'], 'company_id' => $company_id, 'deleted' => 0])
                ->first();

            if (!$template) {
                return $this->response
                    ->withStatus(404)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Template not found or you do not have access.',
                    ]));
            }

            // Update the template with new data
            $template = $LevelTemplatesTable->patchEntity($template, [
                'name' => $data['name'],
                'structure' => $data['structure'], // Store structure as JSON
                'modified' => date('Y-m-d H:i:s'),
            ]);

            if ($LevelTemplatesTable->save($template)) {
                // Return the updated template
                return $this->response->withType('application/json')->withStringBody(json_encode([
                    'success' => true,
                    'data' => [
                        'id' => $template->id,
                        'name' => $template->name,
                        'structure' => $template->structure, // Decode for response
                    ]
                ]));
            } else {
                return $this->response
                    ->withStatus(400)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Failed to update template.',
                        'errors' => $template->getErrors(),
                    ]));
            }
        } catch (\Exception $e) {
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error updating template.',
                    'error' => $e->getMessage(),
                ]));
        }
    }

    public function getRoleLevelForm()
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

        $companyId = $this->getCompanyId($authResult);

        try {
            // Get tenant-specific table
            $LevelTemplatesTable = $this->getTable('LevelTemplates', $companyId);

            // You can apply more logic here if you want to support selecting by ID
            $template = $LevelTemplatesTable->find()
                ->select(['id', 'name', 'structure'])
                ->where([
                    'company_id' => $companyId,
                    'deleted' => 0
                ])
                ->all()
                ->toArray();

            if (!$template) {
                return $this->response->withType('application/json')->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'No template found.',
                ]));
            }

            // Assuming structure is already stored as JSONB in the DB
            // ✅ Force return JSON (don't rely on _serialize)
            return $this->response->withType('application/json')->withStringBody(json_encode([
                'success' => true,
                'data' => $template,
            ]));
        } catch (\Throwable $e) {
            return $this->response->withStatus(500)->withType('application/json')->withStringBody(json_encode([
                'success' => false,
                'message' => 'Error fetching employee template: ' . $e->getMessage(),
            ]));
        }
    }

    public function getLevelTemplate()
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

        $companyId = $this->getCompanyId($authResult);
        $templateId = $this->request->getQuery('id');

        if (!$templateId) {
            return $this->response->withStatus(400)->withType('application/json')->withStringBody(json_encode([
                'success' => false,
                'message' => 'Template ID is required',
            ]));
        }

        try {
            // Get tenant-specific table
            $LevelTemplatesTable = $this->getTable('LevelTemplates', $companyId);

            $template = $LevelTemplatesTable->find()
                ->select(['id', 'name', 'structure'])
                ->where([
                    'id' => $templateId,
                    'company_id' => $companyId,
                    'deleted' => 0
                ])
                ->first();

            if (!$template) {
                return $this->response->withStatus(404)->withType('application/json')->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Template not found',
                ]));
            }

            return $this->response->withType('application/json')->withStringBody(json_encode([
                'success' => true,
                'data' => $template,
            ]));
        } catch (\Throwable $e) {
            return $this->response->withStatus(500)->withType('application/json')->withStringBody(json_encode([
                'success' => false,
                'message' => 'Error fetching level template: ' . $e->getMessage(),
            ]));
        }
    }

    /**
     * Helper method to extract company_id from authentication result
     */
    private function getCompanyId($authResult)
    {
        $authData = $authResult->getData();
        if ($authData instanceof \ArrayObject || is_array($authData)) {
            return $authData['company_id'] ?? null;
        } elseif (is_object($authData)) {
            return $authData->company_id ?? null;
        }
        return null;
    }
}
