<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\Api\ApiController;
use Cake\Core\Configure;

class ScorecardTemplatesController extends ApiController
{
    public function initialize(): void
    {
        parent::initialize();
    }

    public function addScorecardForm()
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

        try {
            $data = $this->request->getData();
            $company_id = $authResult->getData()->company_id;
            $username = $authResult->getData()->username ?? 'system';

            // Validate required fields
            if (empty($data['structure'])) {
                return $this->response
                    ->withStatus(400)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Template structure is required.',
                    ]));
            }

            $ScorecardTemplatesTable = $this->getTable('ScorecardTemplates', $company_id);

            $scorecardTemplate = $ScorecardTemplatesTable->newEntity([
                'name' => $data['name'] ?? 'Scorecard Template',
                'structure' => $data['structure'] ?? [],
                'company_id' => $company_id,
                'created_by' => $username,
                'deleted' => 0
            ]);

            if ($ScorecardTemplatesTable->save($scorecardTemplate)) {
                return $this->response->withType('application/json')->withStringBody(json_encode([
                    'success' => true,
                    'id' => $scorecardTemplate->id,
                    'message' => 'Scorecard template created successfully'
                ]));
                
            } else {
                $this->response = $this->response->withStatus(422);
                return $this->response->withType('application/json')->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Failed to save template',
                    'errors' => $scorecardTemplate->getErrors(),
                ]));
            }
        } catch (\Exception $e) {
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error creating scorecard template: ' . $e->getMessage(),
                ]));
        }
    }

    public function updateScorecardForm()
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

        try {
            $data = $this->request->getData();
            $company_id = $authResult->getData()->company_id;

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

            // Get the ScorecardTemplates table for the company
            $ScorecardTemplatesTable = $this->getTable('ScorecardTemplates', $company_id);

            // Fetch the existing template
            $template = $ScorecardTemplatesTable->find()
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
            $template = $ScorecardTemplatesTable->patchEntity($template, [
                'name' => $data['name'],
                'structure' => $data['structure'], // Store structure as JSON
            ]);

            if ($ScorecardTemplatesTable->save($template)) {
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

    public function getScorecardForm()
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

        $companyId = $authResult->getData()->company_id;

        try {
            // Get tenant-specific table
            $table = $this->getTable('ScorecardTemplates', $companyId);

            // You can apply more logic here if you want to support selecting by ID
            $template = $table->find()
                ->select(['id', 'name', 'structure'])
                ->where([
                    'company_id' => $companyId,
                    'deleted' => 0
                ])
                ->all()
                ->toArray();

            // Assuming structure is already stored as JSONB in the DB
            // ✅ Force return JSON (don't rely on _serialize)
            return $this->response->withType('application/json')->withStringBody(json_encode([
                'success' => true,
                'data' => $template,
            ]));
        } catch (\Throwable $e) {
            return $this->response->withStatus(500)->withType('application/json')->withStringBody(json_encode([
                'success' => false,
                'message' => 'Error fetching scorecard template: ' . $e->getMessage(),
            ]));
        }
    }

    public function getScorecardTemplate()
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

        $companyId = $authResult->getData()->company_id;

        try {
            // Get tenant-specific table
            $table = $this->getTable('ScorecardTemplates', $companyId);

            // You can apply more logic here if you want to support selecting by ID
            $template = $table->find()->select(['template_id' => 'id', 'structure'])->where(['company_id' => $companyId, 'deleted' => 0])->first();

            if (!$template) {
                return $this->response->withType('application/json')->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'No scorecard template found.',
                ]));
            }

            // Assuming structure is already stored as JSONB in the DB
            // ✅ Force return JSON (don't rely on _serialize)
            return $this->response->withType('application/json')->withStringBody(json_encode([
                'success' => true,
                'structure' => $template,
            ]));
        } catch (\Throwable $e) {
            return $this->response->withStatus(500)->withType('application/json')->withStringBody(json_encode([
                'success' => false,
                'message' => 'Error fetching scorecard template: ' . $e->getMessage(),
            ]));
        }
    }
}
