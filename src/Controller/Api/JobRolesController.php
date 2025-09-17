<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\Api\ApiController;
use App\Helper\AuditHelper;
use Cake\Core\Configure;
use Cake\Log\Log;
use Cake\Utility\Text;


class JobRolesController extends ApiController
{
    public function initialize(): void
    {
        parent::initialize();
        // $this->loadComponent('Authentication.Authentication');
        // $this->loadComponent('Authorization.Authorization');
    }

    public function addJobRole()
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
        $companyId = $authResult->getData()->company_id;
        $answers = $data['answers'] ?? null;
        $templateId = $data['template_id'] ?? null;

        // Validation
        if (!$answers || !is_array($answers)) {
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Invalid or missing answers.',
                ]));
        }

        if (!$templateId || !is_numeric($templateId)) {
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Missing or invalid template_id.',
                ]));
        }

        try {
            $JobRoleTemplateAnswersTable = $this->getTable('JobRoleTemplateAnswers', $companyId);

            $entity = $JobRoleTemplateAnswersTable->newEmptyEntity();
            $entity->company_id = $companyId;
            $entity->job_role_unique_id = 'jr-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(5)));
            $entity->template_id = (int)$templateId;
            $entity->answers = $answers; // JSONB accepts array directly if field is jsonb in schema
            $entity->deleted = false;

            if ($JobRoleTemplateAnswersTable->save($entity)) {
                // Log audit action
                $userData = AuditHelper::extractUserData($authResult);
                $jobRoleName = AuditHelper::extractJobRoleName($answers);
                
                Log::debug('🔍 DEBUG: addJobRole - Audit logging data', [
                    'job_role_unique_id' => $entity->job_role_unique_id,
                    'job_role_name' => $jobRoleName,
                    'user_data' => $userData,
                    'answers' => $answers
                ]);
                
                AuditHelper::logJobRoleAction(
                    'CREATE',
                    $entity->job_role_unique_id,
                    $jobRoleName,
                    $userData,
                    $this->request
                );

                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => true,
                        'message' => 'Job role saved successfully.',
                        'data' => [
                            'id' => $entity->id,
                            'job_role_unique_id' => $entity->job_role_unique_id,
                        ],
                    ]));
            } else {
                return $this->response
                    ->withStatus(422)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Unable to save job role.',
                        'errors' => $entity->getErrors(),
                    ]));
            }
        } catch (\Exception $e) {
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'An unexpected error occurred.',
                    'error' => $e->getMessage(),
                ]));
        }
    }

    public function tableHeaders()
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

        $company_id = $authResult->getData()->company_id;
        try {
            $JobRoleTemplatesTable = $this->getTable('JobRoleTemplates', $company_id);

            $template = $JobRoleTemplatesTable
                ->find()
                ->select(['template_id' => 'id', 'structure'])
                ->where(['company_id' => $company_id, 'deleted' => 0])
                ->first();

            if (!$template) {
                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'No job role table header found.',
                    ]));
            }

            // Process the structure to extract Role Code, Official Designation, and Level
            $structure = json_decode(json_encode($template->structure), true); // Convert to array
            $headers = [];

            // Iterate through structure groups to find the fields
            foreach ($structure as $group) {
                foreach ($group['fields'] as $field) {
                    if ($field['label'] === 'Role Code') {
                        $headers[] = [
                            'id' => 'roleCode',
                            'label' => !empty($field['customize_field_label']) ? $field['customize_field_label'] : $field['label']
                        ];
                    }
                    if ($field['label'] === 'Official Designation') {
                        $headers[] = [
                            'id' => 'officialDesignation',
                            'label' => !empty($field['customize_field_label']) ? $field['customize_field_label'] : $field['label']
                        ];
                    }
                    if ($field['label'] === 'Level') {
                        $headers[] = [
                            'id' => 'level',
                            'label' => !empty($field['customize_field_label']) ? $field['customize_field_label'] : $field['label']
                        ];
                    }
                }
            }

            // Add Actions column

            // Sort headers to ensure consistent order (Role Code, Official Designation, Level, Actions)
            usort($headers, function ($a, $b) {
                $order = ['roleCode', 'officialDesignation', 'level'];
                return array_search($a['id'], $order) - array_search($b['id'], $order);
            });

            // Validate that all required fields are present
            $requiredFields = ['roleCode', 'officialDesignation', 'level'];
            $foundFields = array_column($headers, 'id');
            if (count(array_intersect($requiredFields, $foundFields)) < count($requiredFields)) {
                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Required fields (Role Code, Official Designation, or Level) not found in template.',
                    ]));
            }

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'data' => $headers
                ]));
        } catch (\Throwable $e) {
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error fetching job role template: ' . $e->getMessage(),
                ]));
        }
    }

    public function getJobRolesData()
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

        $company_id = $authResult->getData()->company_id;

        // Get pagination, search, and sorting parameters
        $page = (int)($this->request->getQuery('page') ?? 1);
        $limit = (int)($this->request->getQuery('limit') ?? 10);
        $search = $this->request->getQuery('search') ?? '';
        $sortField = $this->request->getQuery('sortField') ?? '';
        $sortOrder = $this->request->getQuery('sortOrder') ?? 'asc';

        // Validate parameters
        if ($page < 1) $page = 1;
        if ($limit < 1) $limit = 10;
        if ($limit > 100) $limit = 100; // Prevent excessive data retrieval
        $sortOrder = strtolower($sortOrder) === 'desc' ? 'desc' : 'asc';

        try {
            // Step 1: Build lookup of level_unique_id => rank and name
            $RoleLevelsTable = $this->getTable('RoleLevels', $company_id);
            $roleLevelsData = $RoleLevelsTable
                ->find()
                ->select(['level_unique_id', 'name', 'rank'])
                ->where([
                    'company_id' => $company_id,
                    'deleted' => 0,
                    'level_unique_id IS NOT' => null,
                ])
                ->all()
                ->toArray();

            // Lookup map: both ID and rank per level_unique_id
            $levelIdToInfo = [];
            foreach ($roleLevelsData as $level) {
                $levelIdToInfo[$level->level_unique_id] = [
                    'name' => $level->name,
                    'rank' => $level->rank
                ];
            }

            // Step 2: Fetch job role answers with pagination
            $JobRoleTemplatesTable = $this->getTable('JobRoleTemplates', $company_id);
            
            // Build the base query
            $query = $JobRoleTemplatesTable
                ->find()
                ->select([
                    'structure' => 'structure',
                    'job_role_unique_id' => 'job_role_template_answers.job_role_unique_id',
                    'template_id' => 'job_role_template_answers.template_id',
                    'answer_id' => 'job_role_template_answers.id',
                    'answers' => 'job_role_template_answers.answers'
                ])
                ->join([
                    'job_role_template_answers' => [
                        'table' => 'job_role_template_answers',
                        'type' => 'LEFT',
                        'conditions' => [
                            'job_role_template_answers.company_id = JobRoleTemplates.company_id',
                            'job_role_template_answers.deleted' => 0,
                        ]
                    ]
                ])
                ->where([
                    'JobRoleTemplates.company_id' => $company_id,
                    'JobRoleTemplates.deleted' => 0,
                    'job_role_template_answers.job_role_unique_id IS NOT' => null,
                ]);

            // Get all data first for search and sorting (since we need to process JSON)
            $allJobRoleAnswers = $query->all()->toArray();

            if (empty($allJobRoleAnswers)) {
                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => true,
                        'data' => [
                            'records' => [],
                            'total' => 0
                        ],
                    ]));
            }

            $fieldMapping = [
                'Role Code' => ['id' => 'roleCode', 'dataKey' => 'role_code'],
                'Official Designation' => ['id' => 'officialDesignation', 'dataKey' => 'official_designation'],
                'Level' => ['id' => 'level', 'dataKey' => 'level'],
            ];

            $processedRoles = array_map(function ($role) use ($fieldMapping, $levelIdToInfo) {
                $result = [
                    'id' => $role->answer_id,
                    'job_role_unique_id' => $role->job_role_unique_id,
                ];

                $structure = is_string($role->structure) ? json_decode($role->structure, true) : $role->structure;
                $answers = json_decode($role->answers, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Invalid answers JSON format for job_role_unique_id: ' . $role->job_role_unique_id);
                }

                $levelValue = null;

                if (is_array($structure)) {
                    foreach ($structure as $group) {
                        if (!isset($group['fields']) || !is_array($group['fields'])) {
                            continue;
                        }

                        foreach ($group['fields'] as $field) {
                            $fieldId = $field['id'];
                            $fieldLabel = $field['label'];

                            if (isset($fieldMapping[$fieldLabel])) {
                                $dataKey = $fieldMapping[$fieldLabel]['dataKey'];

                                foreach ($answers as $groupAnswers) {
                                    if (isset($groupAnswers[$fieldId])) {
                                        $answerValue = $groupAnswers[$fieldId];
                                        $result[$dataKey] = $answerValue;

                                        // Capture level for rank mapping
                                        if ($dataKey === 'level') {
                                            $levelValue = $answerValue;
                                        }

                                        break;
                                    }
                                }
                            }
                        }
                    }
                }

                // Add nulls for missing fields
                foreach ($fieldMapping as $mapping) {
                    $dataKey = $mapping['dataKey'];
                    if (!isset($result[$dataKey])) {
                        $result[$dataKey] = null;
                    }
                }

                // Step 3: Add level_name and rank (if levelValue is found)
                if ($levelValue && isset($levelIdToInfo[$levelValue])) {
                    $levelName = $levelIdToInfo[$levelValue]['name'];
                    $rank = $levelIdToInfo[$levelValue]['rank'];
                    $result['level'] = "{$levelName} (Level {$rank})";
                } else {
                    $result['level'] = null;
                }

                return $result;
            }, $allJobRoleAnswers);

            // Apply search filter if provided
            if (!empty($search)) {
                $processedRoles = array_filter($processedRoles, function ($role) use ($search) {
                    $searchLower = strtolower($search);
                    return (
                        strpos(strtolower($role['role_code'] ?? ''), $searchLower) !== false ||
                        strpos(strtolower($role['official_designation'] ?? ''), $searchLower) !== false ||
                        strpos(strtolower($role['level'] ?? ''), $searchLower) !== false
                    );
                });
            }

            // Apply sorting if provided
            if (!empty($sortField)) {
                usort($processedRoles, function ($a, $b) use ($sortField, $sortOrder) {
                    $aValue = $a[$sortField] ?? '';
                    $bValue = $b[$sortField] ?? '';
                    
                    if ($sortOrder === 'desc') {
                        return strcasecmp($bValue, $aValue);
                    } else {
                        return strcasecmp($aValue, $bValue);
                    }
                });
            }

            // Get total count after search
            $totalCount = count($processedRoles);

            // Apply pagination
            $offset = ($page - 1) * $limit;
            $paginatedRoles = array_slice($processedRoles, $offset, $limit);

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'data' => [
                        'records' => $paginatedRoles,
                        'total' => $totalCount
                    ],
                ]));
        } catch (\Throwable $e) {
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error fetching job roles: ' . $e->getMessage(),
                ]));
        }
    }



    public function getJobRoleDetail()
    {
        Configure::write('debug', 0); // Disable debug in production
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

        $company_id = $authResult->getData()->company_id;
        $data = $this->request->getData();
        $job_role_unique_id = $data['job_role_unique_id'] ?? null;

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
            // Step 1: Build lookup of level_unique_id => rank and name
            $RoleLevelsTable = $this->getTable('RoleLevels', $company_id);
            $roleLevelsData = $RoleLevelsTable
                ->find()
                ->select(['level_unique_id', 'name', 'rank'])
                ->where([
                    'company_id' => $company_id,
                    'deleted' => 0,
                    'level_unique_id IS NOT' => null,
                ])
                ->all()
                ->toArray();

            // Lookup map: both ID and rank per level_unique_id
            $levelIdToInfo = [];
            foreach ($roleLevelsData as $level) {
                $levelIdToInfo[$level->level_unique_id] = [
                    'name' => $level->name,
                    'rank' => $level->rank
                ];
            }

            $JobRoleTemplatesTable = $this->getTable('JobRoleTemplates', $company_id);

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
                    'JobRoleTemplates.company_id' => $company_id,
                    'JobRoleTemplates.deleted' => 0,
                    'job_role_template_answers.job_role_unique_id' => $job_role_unique_id,
                ])
                ->first();

            if (empty($get_job_role_detail)) {
                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'No job role found for the provided job_role_unique_id.',
                    ]));
            }

            // Define mapping of original labels to header IDs and data keys
            $fieldMapping = [
                'Role Code' => ['id' => 'roleCode', 'dataKey' => 'role_code'],
                'Official Designation' => ['id' => 'officialDesignation', 'dataKey' => 'official_designation'],
                'Level' => ['id' => 'level', 'dataKey' => 'level'],
            ];

            // Process the single job role
            $result = [
                'id' => $get_job_role_detail->answer_id,
                'job_role_unique_id' => $get_job_role_detail->job_role_unique_id,
                'groups' => [],
            ];

            // Parse the answers JSON
            $answers = json_decode($get_job_role_detail->answers, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid answers JSON format for job_role_unique_id: ' . $job_role_unique_id);
            }

            // Map answers to field labels based on structure, grouped by group label
            foreach ($get_job_role_detail->structure as $group) {
                $group_label = !empty($group['customize_group_label']) && $group['customize_group_label'] !== $group['label']
                    ? $group['customize_group_label']
                    : $group['label'];
                $group_key = strtolower(str_replace(' ', '_', $group_label));
                $result['groups'][$group_key] = [];

                foreach ($group['fields'] as $field) {
                    $fieldId = $field['id'];
                    $fieldLabel = $field['label'];
                    $customLabel = !empty($field['customize_field_label']) && $field['customize_field_label'] !== $field['label']
                        ? $field['customize_field_label']
                        : $field['label'];

                    // Determine the data key
                    $dataKey = strtolower(str_replace(' ', '_', $customLabel));
                    if (isset($fieldMapping[$fieldLabel])) {
                        $dataKey = $fieldMapping[$fieldLabel]['dataKey'];
                    }

                    // Find the answer for this field
                    $value = null;
                    foreach ($answers as $groupAnswers) {
                        if (isset($groupAnswers[$fieldId])) {
                            $value = $groupAnswers[$fieldId];
                            break;
                        }
                    }

                    // Convert level unique ID to level name if this is a level field
                    if ($fieldLabel === 'Level' && $value && isset($levelIdToInfo[$value])) {
                        $levelInfo = $levelIdToInfo[$value];
                        $value = "{$levelInfo['name']} ({$levelInfo['rank']})";
                    }

                    // Store both value and display label
                    $result['groups'][$group_key][$dataKey] = [
                        'value' => $value,
                        'label' => $customLabel,
                    ];
                }
            }

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'data' => $result,
                ]));
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

    public function editJobRole()
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
        $companyId = $authResult->getData()->company_id;
        $answers = $data['answers'] ?? null;
        $templateId = $data['template_id'] ?? null;
        $job_role_unique_id = $data['job_role_unique_id'] ?? null;

        $JobRoleTemplateAnswersTable = $this->getTable('JobRoleTemplateAnswers', $companyId);

        // Validation
        if (!$job_role_unique_id) {
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Missing or invalid job role unique id.',
                ]));
        }

        if (!$answers || !is_array($answers)) {
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Invalid or missing answers.',
                ]));
        }

        if (!$templateId || !is_numeric($templateId)) {
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Missing or invalid template id.',
                ]));
        }

        // Check if job role exists
        $existing = $JobRoleTemplateAnswersTable->find()
            ->where([
                'company_id' => $companyId,
                'deleted' => 0,
                'job_role_unique_id' => $job_role_unique_id,
                'template_id' => $templateId,
            ])
            ->first();

        try {
            // Store old answers BEFORE any modifications for audit logging
            $oldAnswers = is_array($existing->answers) ? $existing->answers : (json_decode($existing->answers, true) ?? []);
            
            Log::debug('🔍 DEBUG: editJobRole - Old answers from database (BEFORE update)', [
                'existing_answers_raw' => $existing->answers,
                'old_answers_decoded' => $oldAnswers
            ]);
            
            // Begin transaction
            $JobRoleTemplateAnswersTable->getConnection()->begin();

            if ($existing) {
                // Update existing record
                $existing = $JobRoleTemplateAnswersTable->patchEntity($existing, [
                    'answers' => $answers,
                    'template_id' => $templateId,
                    'modified' => date('Y-m-d H:i:s'),
                ]);
            }

            // Save the record
            if (!$JobRoleTemplateAnswersTable->save($existing)) {
                throw new \Exception('Failed to save job role answers.');
            }

            // Commit transaction
            $JobRoleTemplateAnswersTable->getConnection()->commit();

            // Log audit action with field change tracking
            $userData = AuditHelper::extractUserData($authResult);
            $jobRoleName = AuditHelper::extractJobRoleName($answers);
            
            Log::debug('🔍 DEBUG: editJobRole - Audit logging data', [
                'job_role_unique_id' => $job_role_unique_id,
                'job_role_name' => $jobRoleName,
                'user_data' => $userData,
                'answers' => $answers
            ]);
            
            // Flatten both old and new answers for comparison
            $oldFlatAnswers = $this->flattenAnswers($oldAnswers);
            $newFlatAnswers = $this->flattenAnswers($answers);
            
            Log::debug('🔍 DEBUG: editJobRole - Flattened answers for comparison', [
                'old_flat_answers' => $oldFlatAnswers,
                'new_flat_answers' => $newFlatAnswers
            ]);
            
            // Create field mapping
            $fieldMapping = $this->getJobRoleFieldMapping($newFlatAnswers);
            
            Log::debug('🔍 DEBUG: editJobRole - Field mapping', [
                'field_mapping' => $fieldMapping,
                'flat_answer_keys' => array_keys($newFlatAnswers)
            ]);
            
            $fieldChanges = AuditHelper::generateFieldChanges(
                $oldFlatAnswers,
                $newFlatAnswers,
                $fieldMapping
            );
            
            Log::debug('🔍 DEBUG: editJobRole - Field changes', [
                'field_changes' => $fieldChanges,
                'field_changes_count' => count($fieldChanges),
                'field_changes_empty' => empty($fieldChanges),
                'old_answers' => $oldAnswers
            ]);
            
            Log::debug('🔍 DEBUG: editJobRole - Calling AuditHelper::logJobRoleAction', [
                'action' => 'UPDATE',
                'job_role_unique_id' => $job_role_unique_id,
                'job_role_name' => $jobRoleName,
                'field_changes' => $fieldChanges,
                'field_changes_count' => count($fieldChanges)
            ]);
            
            AuditHelper::logJobRoleAction(
                'UPDATE',
                $job_role_unique_id,
                $jobRoleName,
                $userData,
                $this->request,
                $fieldChanges
            );

            return $this->response
                ->withStatus(200)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'message' => 'Job role updated successfully.',
                ]));
        } catch (\Exception $e) {
            // Rollback transaction on error
            $JobRoleTemplateAnswersTable->getConnection()->rollback();

            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'An unexpected error occurred.',
                    'error' => $e->getMessage(),
                ]));
        }
    }

    public function deleteJobRole()
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
        $companyId = $authResult->getData()->company_id;
        $job_role_unique_id = $data['job_role_unique_id'] ?? null;

        $JobRoleTemplateAnswersTable = $this->getTable('JobRoleTemplateAnswers', $companyId);

        // Validation
        if (!$job_role_unique_id) {
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Missing or invalid job role unique id.',
                ]));
        }

        // Check if job role exists
        $existing = $JobRoleTemplateAnswersTable->find()
            ->where([
                'company_id' => $companyId,
                'deleted' => 0,
                'job_role_unique_id' => $job_role_unique_id,
            ])
            ->first();

        if (!$existing) {
            return $this->response
                ->withStatus(404)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Job role not found.',
                ]));
        }

        try {
            // Start transaction
            $connection = $JobRoleTemplateAnswersTable->getConnection();
            $connection->begin();

            // Perform soft delete by updating the 'deleted' field
            $existing->deleted = 1;
            $existing->modified = date('Y-m-d H:i:s');

            if (!$JobRoleTemplateAnswersTable->save($existing)) {
                throw new \Exception('Failed to delete job role.');
            }

            // Commit transaction
            $connection->commit();

            // Log audit action
            $userData = AuditHelper::extractUserData($authResult);
            $jobRoleName = AuditHelper::extractJobRoleName($existing->answers ?? []);
            
            Log::debug('🔍 DEBUG: deleteJobRole - Audit logging data', [
                'job_role_unique_id' => $job_role_unique_id,
                'job_role_name' => $jobRoleName,
                'user_data' => $userData
            ]);
            
            AuditHelper::logJobRoleAction(
                'DELETE',
                $job_role_unique_id,
                $jobRoleName,
                $userData,
                $this->request
            );

            return $this->response
                ->withStatus(200)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'message' => 'Job role deleted successfully.',
                ]));
        } catch (\Exception $e) {
            if (isset($connection) && $connection->inTransaction()) {
                $connection->rollback();
            }
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'An unexpected error occurred.',
                    'error' => $e->getMessage(),
                ]));
        }
    }





    public function getJobRoleLabel()
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
            $table = $this->getTable('JobRoleTemplates', $companyId);

            // Fetch the job role template
            $template = $table->find()
                ->select(['template_id' => 'id', 'structure'])
                ->where(['company_id' => $companyId, 'deleted' => 0])
                ->first();

            if (!$template) {
                return $this->response->withType('application/json')->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'No job role template found.',
                ]));
            }

            // Extract the customize_field_label for the "Role Code" field
            $structure = $template->structure;
            $officialDesignationLabel = 'Official Designation'; // Default label

            foreach ($structure as $group) {
                if (isset($group['fields'])) {
                    foreach ($group['fields'] as $field) {
                        if ($field['label'] === 'Official Designation' || $field['customize_field_label'] === 'Job Title') {
                            $officialDesignationLabel = $field['customize_field_label'] !== $field['label']
                                ? $field['customize_field_label']
                                : $field['label'];
                            break 2; // Exit both loops once found
                        }
                    }
                }
            }

            // Return the label in the expected format
            return $this->response->withType('application/json')->withStringBody(json_encode([
                'success' => true,
                'data' => [
                    'label' => $officialDesignationLabel,
                ],
            ]));
        } catch (\Throwable $e) {
            return $this->response->withStatus(500)->withType('application/json')->withStringBody(json_encode([
                'success' => false,
                'message' => 'Error fetching job role label: ' . $e->getMessage(),
            ]));
        }
    }






    public function getJobRoles()
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

        $company_id = $authResult->getData()->company_id;
        try {
            $JobRoleTemplatesTable = $this->getTable('JobRoleTemplates', $company_id);

            $get_job_roles = $JobRoleTemplatesTable
                ->find()
                ->select([
                    'structure' => 'JobRoleTemplates.structure',
                    'job_role_unique_id' => 'job_role_template_answers.job_role_unique_id',
                    'template_id' => 'job_role_template_answers.template_id',
                    'answer_id' => 'job_role_template_answers.id',
                    'answers' => 'job_role_template_answers.answers',
                ])
                ->join([
                    'job_role_template_answers' => [
                        'table' => 'job_role_template_answers',
                        'type' => 'INNER', // Use INNER to ensure only roles with answers are included
                        'conditions' => [
                            'job_role_template_answers.company_id = JobRoleTemplates.company_id',
                            'job_role_template_answers.deleted' => 0,
                        ],
                    ],
                ])
                ->where([
                    'JobRoleTemplates.company_id' => $company_id,
                    'JobRoleTemplates.deleted' => 0,
                ])
                ->all()
                ->toArray();

            if (empty($get_job_roles)) {
                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'No job roles found without reporting relationships.',
                    ]));
            }

            // Define mapping of original labels to header IDs and data keys
            $fieldMapping = [
                'Role Code' => ['id' => 'roleCode', 'dataKey' => 'role_code'],
                'Official Designation' => ['id' => 'officialDesignation', 'dataKey' => 'official_designation'],
            ];

            // Process the data to flatten answers and map to table headers
            $processedRoles = array_map(function ($role) use ($fieldMapping) {
                $result = [
                    'id' => $role->answer_id,
                    'job_role_unique_id' => $role->job_role_unique_id,
                ];

                // Parse the answers JSON
                $answers = json_decode($role->answers, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Invalid answers JSON format for job_role_unique_id: ' . $role->job_role_unique_id);
                }

                // Map answers to field labels based on structure
                foreach ($role->structure as $group) {
                    foreach ($group['fields'] as $field) {
                        $fieldId = $field['id'];
                        $fieldLabel = $field['label'];
                        $customLabel = !empty($field['customize_field_label']) && $field['customize_field_label'] !== $field['label']
                            ? $field['customize_field_label']
                            : $field['label'];

                        // Check if the original label is in the mapping
                        if (isset($fieldMapping[$fieldLabel])) {
                            $dataKey = $fieldMapping[$fieldLabel]['dataKey'];
                            // Find the answer for this field
                            foreach ($answers as $groupAnswers) {
                                if (isset($groupAnswers[$fieldId])) {
                                    $result[$dataKey] = $groupAnswers[$fieldId];
                                    break;
                                }
                            }
                        }
                    }
                }

                // Ensure all required fields are present, set to null if missing
                foreach ($fieldMapping as $mapping) {
                    $dataKey = $mapping['dataKey'];
                    if (!isset($result[$dataKey])) {
                        $result[$dataKey] = null;
                    }
                }

                return $result;
            }, $get_job_roles);

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'data' => $processedRoles,
                ]));
        } catch (\Throwable $e) {
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error fetching job roles: ' . $e->getMessage(),
                ]));
        }
    }

    /**
     * Flatten answers array for field change comparison
     *
     * @param array $answers
     * @return array
     */
    private function flattenAnswers($answers)
    {
        Log::debug('🔍 DEBUG: flattenAnswers - Input', [
            'answers' => $answers,
            'answers_type' => gettype($answers),
            'answers_empty' => empty($answers)
        ]);

        if (empty($answers)) {
            Log::debug('🔍 DEBUG: flattenAnswers - Empty answers, returning empty array');
            return [];
        }

        $flatAnswers = [];
        $answerKeys = array_keys($answers);

        Log::debug('🔍 DEBUG: flattenAnswers - Answer keys', [
            'answer_keys' => $answerKeys,
            'first_key' => $answerKeys[0] ?? 'none'
        ]);

        if (!empty($answerKeys)) {
            $firstKey = $answerKeys[0];
            if (is_array($answers[$firstKey])) {
                Log::debug('🔍 DEBUG: flattenAnswers - Nested structure detected, flattening');
                // It's nested, flatten it
                foreach ($answers as $groupId => $groupAnswers) {
                    if (is_array($groupAnswers)) {
                        $flatAnswers = array_merge($flatAnswers, $groupAnswers);
                        Log::debug('🔍 DEBUG: flattenAnswers - Flattened group', [
                            'group_id' => $groupId,
                            'group_answers' => $groupAnswers,
                            'flat_answers_so_far' => $flatAnswers
                        ]);
                    }
                }
            } else {
                Log::debug('🔍 DEBUG: flattenAnswers - Flat structure detected');
                // It's already flat
                $flatAnswers = $answers;
            }
        }

        Log::debug('🔍 DEBUG: flattenAnswers - Final result', [
            'flat_answers' => $flatAnswers
        ]);

        return $flatAnswers;
    }

    /**
     * Get field mapping for job roles
     *
     * @param array $flatAnswers
     * @return array
     */
    private function getJobRoleFieldMapping($flatAnswers)
    {
        // Define field order based on typical job role structure
        $fieldOrder = ['Job Title', 'Department', 'Description', 'Responsibilities', 'Requirements', 'Skills', 'Experience Level'];
        $flatAnswerKeys = array_keys($flatAnswers);
        
        $fieldMapping = [];
        foreach ($flatAnswerKeys as $index => $fieldId) {
            if (isset($fieldOrder[$index])) {
                $fieldMapping[$fieldId] = $fieldOrder[$index];
            } else {
                $fieldMapping[$fieldId] = 'Field ' . ($index + 1);
            }
        }
        
        return $fieldMapping;
    }
}
