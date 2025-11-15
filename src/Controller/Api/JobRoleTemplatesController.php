<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\Api\ApiController;
use Cake\Core\Configure;

class JobRoleTemplatesController extends ApiController
{
    public function initialize(): void
    {
        parent::initialize();
        // $this->loadComponent('Authentication.Authentication');
        // $this->loadComponent('Authorization.Authorization');
    }

    public function addJobRoleForm()
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
        $jobRoleTemplatesTable = $this->getTable('JobRoleTemplates', $company_id);
        
        $jobRoleTemplate = $jobRoleTemplatesTable->newEntity([
            'name' => $data['name'] ?? 'Untitled',
            'structure' => $data['structure'] ?? [],
            'company_id' => $company_id
        ]);

        if ($jobRoleTemplatesTable->save($jobRoleTemplate)) {
            return $this->response->withType('application/json')->withStringBody(json_encode([
                'success' => true,
                'id' => $jobRoleTemplate->id,
            ]));
            
        } else {
            return $this->response->withStatus(422)->withType('application/json')->withStringBody(json_encode([
                'success' => false,
                'errors' => $jobRoleTemplate->getErrors(),
            ]));
        }
    }

    public function updateJobRoleForm()
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

            // Get the JobRoleTemplates table for the company
            $jobRoleTemplatesTable = $this->getTable('JobRoleTemplates', $company_id);

            // Fetch the existing template
            $template = $jobRoleTemplatesTable->find()
                ->where(['id' => $data['id'], 'company_id' => $company_id])
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
            $template = $jobRoleTemplatesTable->patchEntity($template, [
                'name' => $data['name'],
                'structure' => $data['structure'], // Store structure as JSON
            ]);

            if ($jobRoleTemplatesTable->save($template)) {
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

    public function getJobRoleForm()
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
            $table = $this->getTable('JobRoleTemplates', $companyId);

            // You can apply more logic here if you want to support selecting by ID
            $template = $this->JobRoleTemplates->find()
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
                    'message' => 'No job role template found.',
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
                'message' => 'Error fetching job role template: ' . $e->getMessage(),
            ]));
        }
    }

    public function getJobRoleTemplate()
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
            $JobRoleTemplatesTable = $this->getTable('JobRoleTemplates', $companyId);

            $template = $JobRoleTemplatesTable->find()
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
                'message' => 'Error fetching job role template: ' . $e->getMessage(),
            ]));
        }
    }

    public function getJobRoleFields()
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
            $table = $this->getTable('JobRoleTemplates', $companyId);

            // You can apply more logic here if you want to support selecting by ID
            $template = $table->find()->select(['template_id' => 'id', 'structure'])->where(['company_id' => $companyId, 'deleted' => 0])->first();

            if (!$template) {
                return $this->response->withType('application/json')->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'No job role template found.',
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

    public function getJobRoleFieldsAndAnswers()
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

    $companyId = $this->getCompanyId($authResult);
    $data = $this->request->getData();
    $job_role_unique_id = $data['job_role_unique_id'] ?? null;

    // Validate input
    if (empty($job_role_unique_id)) {
        return $this->response
            ->withStatus(400)
            ->withType('application/json')
            ->withStringBody(json_encode([
                'success' => false,
                'message' => 'Missing job_role_unique_id',
            ]));
    }

    try {
        // Get tenant-specific table
        $JobRoleTemplatesTable = $this->getTable('JobRoleTemplates', $companyId);

        // Fetch job role details
        $get_job_role_detail = $JobRoleTemplatesTable
            ->find()
            ->select([
                'structure' => 'structure',
                'job_role_unique_id' => 'job_role_template_answers.job_role_unique_id',
                'template_id' => 'job_role_template_answers.template_id',
                'answer_id' => 'job_role_template_answers.id',
                'answers' => 'job_role_template_answers.answers',
            ])
            ->join([
                'job_role_template_answers' => [
                    'table' => 'job_role_template_answers',
                    'type' => 'LEFT',
                    'conditions' => [
                        'job_role_template_answers.company_id = JobRoleTemplates.company_id',
                        'job_role_template_answers.deleted' => 0,
                    ],
                ],
            ])
            ->where([
                'JobRoleTemplates.company_id' => $companyId,
                'JobRoleTemplates.deleted' => 0,
                'job_role_template_answers.job_role_unique_id' => $job_role_unique_id,
            ])
            ->first();
            

        if (!$get_job_role_detail) {
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'No job role found for the provided job_role_unique_id.',
                ]));
        }

        // Parse answers JSON
        $answers = json_decode($get_job_role_detail->answers, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid answers JSON format');
        }

        // Prepare response
        $response = [
            'success' => true,
            'data' => [
                'structure' => $get_job_role_detail->structure,
                'template_id' => $get_job_role_detail->template_id,
                'answers' => $answers,
            ],
        ];

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($response));

    } catch (\Throwable $e) {
        return $this->response
            ->withStatus(500)
            ->withType('application/json')
            ->withStringBody(json_encode([
                'success' => false,
                'message' => 'Error fetching job role: ' . $e->getMessage(),
            ]));
    }
}

    public function getRoleLevels()
{
    $this->request->allowMethod(['get']);

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

            $company_id = $this->getCompanyId($authResult);
    try {
        $LevelTemplatesTable = $this->getTable('LevelTemplates', $company_id);

        $role_levels = $LevelTemplatesTable
            ->find()
            ->select([
                'structure' => 'LevelTemplates.structure',
                'template_id' => 'role_levels.template_id',
                'answers' => 'role_levels.custom_fields',
                'answer_id' => 'role_levels.id',
                'level_unique_id' => 'role_levels.level_unique_id',
                'name' => 'role_levels.name',
            ])
            ->join([
                'role_levels' => [
                    'table' => 'role_levels',
                    'type' => 'INNER',
                    'conditions' => [
                        'role_levels.company_id = LevelTemplates.company_id',
                        'role_levels.template_id = LevelTemplates.id',
                        'role_levels.deleted' => 0,
                    ],
                ],
            ])
            ->where([
                'role_levels.company_id' => $company_id,
                'LevelTemplates.deleted' => 0,
            ])
            ->all()
            ->toArray();

        if (empty($role_levels)) {
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'No role levels found.',
                ]));
        }

        // Define mapping of template field labels to output keys
        $fieldMapping = [
            'Name' => ['dataKey' => 'name'],
            'Rank/Order' => ['dataKey' => 'rank'],
            'Description' => ['dataKey' => 'description'],
        ];

        // Process role levels to extract fields from custom_fields JSON
        $processedLevels = array_map(function ($level) use ($fieldMapping) {
            $result = [
                'role_level_unique_id' => $level->level_unique_id,
                'name' => $level->name,
                'rank' => null,
                'description' => null,
            ];

            // Parse custom_fields JSON
            $answers = json_decode($level->answers, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Log error but continue processing
                error_log('Invalid answers JSON for level_unique_id: ' . $level->level_unique_id);
                return $result;
            }

            // Extract fields based on template structure
            $structure = $level->structure;
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('Invalid structure JSON for template_id: ' . $level->template_id);
                return $result;
            }

            foreach ($structure as $group) {
                foreach ($group['fields'] as $field) {
                    $fieldId = $field['id'];
                    $fieldLabel = $field['label'];

                    if (isset($fieldMapping[$fieldLabel])) {
                        $dataKey = $fieldMapping[$fieldLabel]['dataKey'];
                        foreach ($answers as $groupId => $groupAnswers) {
                            if (isset($groupAnswers[$fieldId])) {
                                $result[$dataKey] = $groupAnswers[$fieldId];
                                break;
                            }
                        }
                    }
                }
            }

            return $result;
        }, $role_levels);

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode([
                'success' => true,
                'data' => $processedLevels,
            ]));
    } catch (\Throwable $e) {
        error_log('Error fetching role levels: ' . $e->getMessage());
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error fetching role levels: ' . $e->getMessage(),
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
