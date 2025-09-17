<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\Api\ApiController;
use Cake\Core\Configure;

class EmployeeTemplatesController extends ApiController
{
    public function initialize(): void
    {
        parent::initialize();
        // $this->loadComponent('Authentication.Authentication');
        // $this->loadComponent('Authorization.Authorization');
    }

    public function createTemplate()
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

        $data = $this->request->getData();
        $company_id = $authResult->getData()->company_id;
        $username = $authResult->getData()->username;
        // debug($company_id);
        $EmployeeTemplatesTable = $this->getTable('EmployeeTemplates', $company_id);
        // debug($data);exit;
        $jobRoleTemplate = $EmployeeTemplatesTable->newEntity([
            'name' => $data['name'] ?? 'Untitled',
            'structure' => $data['structure'] ?? [],
            'company_id' => $company_id,
            'created_by' => $username
        ]);

        if ($EmployeeTemplatesTable->save($jobRoleTemplate)) {
            return $this->response->withType('application/json')->withStringBody(json_encode([
                'success' => true,
                'id' => $jobRoleTemplate->id,
            ]));
            
        } else {
            $this->response = $this->response->withStatus(422);
            return $this->response->withType('application/json')->withStringBody(json_encode([
                'success' => false,
                'errors' => $jobRoleTemplate->getErrors(),
            ]));
        }
    }

    public function updateTemplate()
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

            // Get the JobRoleTemplates table for the company
            $EmployeeTemplatesTable = $this->getTable('EmployeeTemplates', $company_id);

            // Fetch the existing template
            $template = $EmployeeTemplatesTable->find()
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
            $template = $EmployeeTemplatesTable->patchEntity($template, [
                'name' => $data['name'],
                'structure' => $data['structure'], // Store structure as JSON
            ]);

            if ($EmployeeTemplatesTable->save($template)) {
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

    public function getEmployeeTemplateFields()
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
            $table = $this->getTable('EmployeeTemplates', $companyId);

            // You can apply more logic here if you want to support selecting by ID
            $template = $table->find()
                ->select(['id', 'name', 'structure'])
                ->where([
                    'company_id' => $companyId,
                    'deleted' => 0
                ])
                ->all()
                ->toArray();

            // if (!$template) {
            //     return $this->response->withType('application/json')->withStringBody(json_encode([
            //         'success' => false,
            //         'message' => 'No job role template found.',
            //     ]));
            // }

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

    public function getEmployeeTemplate()
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
            $table = $this->getTable('EmployeeTemplates', $companyId);

            // You can apply more logic here if you want to support selecting by ID
            $template = $table->find()->select(['template_id' => 'id', 'structure'])->where(['company_id' => $companyId, 'deleted' => 0])->first();

            if (!$template) {
                return $this->response->withType('application/json')->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'No employee template found.',
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
                'message' => 'Error fetching job role template: ' . $e->getMessage(),
            ]));
        }
    }
}