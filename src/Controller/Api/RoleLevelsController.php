<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\Api\ApiController;
use App\Helper\AuditHelper;
use Cake\Core\Configure;
use Cake\Utility\Text;
use Exception;
use Cake\Log\Log;
use Cake\Datasource\ConnectionManager;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\UnauthorizedException;

class RoleLevelsController extends ApiController
{
    public function initialize(): void
    {
        parent::initialize();
        // $this->loadComponent('Authentication.Authentication');
        // $this->loadComponent('Authorization.Authorization');
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
        

        $company_id = $this->getCompanyId($authResult);
        try {
            $LevelTemplatesTable = $this->getTable('LevelTemplates', $company_id);

            $template = $LevelTemplatesTable
                ->find()
                ->select(['template_id' => 'id', 'structure'])
                ->where(['company_id' => $company_id, 'deleted' => 0])
                ->first();

            if (!$template) {
                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'No role levels template found.',
                    ]));
            }

            // Process the structure to extract required fields
            $structure = $template->structure;
            if (is_string($structure)) {
                $structure = json_decode($structure, true);
            }
            if (!is_array($structure)) {
                $structure = [];
            }
            $headers = [];

            // Iterate through structure groups to find the fields
            foreach ($structure as $group) {
                foreach ($group['fields'] as $field) {
                    $label = $field['label'];
                    if (in_array($label, ['Level', 'Rank/Order'])) {
                        $headers[] = [
                            'id' => $this->getFieldId($label),
                            'label' => !empty($field['customize_field_label']) && $field['customize_field_label'] !== $field['label']
                                ? $field['customize_field_label']
                                : $field['label']
                        ];
                    }
                }
            }

            // Add Actions column
            // $headers[] = [
            //     'id' => 'actions',
            //     'label' => 'Actions'
            // ];

            // Sort headers to ensure consistent order
            usort($headers, function ($a, $b) {
                $order = ['level', 'rank/order'];
                return array_search($a['id'], $order) - array_search($b['id'], $order);
            });

            // Validate that all required fields are present
            $requiredFields = ['level', 'rank/order'];
            $foundFields = array_column($headers, 'id');
            if (count(array_intersect($requiredFields, $foundFields)) < count($requiredFields)) {
                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Required fields (Level or Rank/Order) not found in template.',
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
                    'message' => 'Error fetching guideline template: ' . $e->getMessage(),
                ]));
        }
    }

    /**
     * Map field labels to consistent IDs
     * @param string $label
     * @return string
     */
    private function getFieldId($label)
    {
        $fieldIds = [
            'Level' => 'level',
            'Rank/Order' => 'rank/order',
        ];
        return $fieldIds[$label] ?? strtolower(str_replace(' ', '', $label));
    }

    public function getRoleLevels()
    {
        Configure::write('debug', true);
        $this->request->allowMethod(['post']);

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
        $data = $this->request->getData();

        try {

            $page = max((int)($data['page'] ?? 1), 1);
            $limit = max((int)($data['limit'] ?? 10), 1);
            $offset = ($page - 1) * $limit;
            $search = strtolower(trim($data['search'] ?? ''));
            $sortField = strtolower($data['sort_field'] ?? 'created');
            $sortOrder = strtolower($data['sort_order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';


            $sortableFields = ['level', 'rank/order'];


            $LevelTemplatesTable = $this->getTable('LevelTemplates', $company_id);

            // Fetch routine records
            $records = $LevelTemplatesTable
                ->find()
                ->select([
                    'structure' => 'LevelTemplates.structure',
                    'template_id' => 'role_levels.template_id',
                    'answers' => 'role_levels.custom_fields',
                    'answer_id' => 'role_levels.id',
                    'level_unique_id' => 'role_levels.level_unique_id',
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
                ->enableHydration(true)
                ->all()
                ->toArray();

            $fieldMap = [
                'Level' => 'level',
                'Rank/Order' => 'rank/order',
            ];

            $results = [];

            foreach ($records as $row) {
                $structure = $row->structure;
                if (is_string($structure)) {
                    $structure = json_decode($structure, true);
                }
                if (!is_array($structure)) {
                    $structure = [];
                }
                
                $answers = $row->answers;
                if (is_string($answers)) {
                    $answers = json_decode($answers, true);
                }
                if (!is_array($answers)) {
                    $answers = [];
                }
                
                $fields = [];

                foreach ($structure as $group) {
                    $groupId = (string)$group['id'];
                    foreach ($group['fields'] as $field) {
                        $label = $field['label'];
                        $customLabel = $field['customize_field_label'] ?? $label;
                        $fieldId = (string)$field['id'];

                        if (!isset($fieldMap[$label])) continue;

                        $key = $fieldMap[$label];
                        $value = $answers[$groupId][$fieldId] ?? null;

                        $fields[$key] = $value;
                    }
                }

                $results[] = [
                    'template_id' => $row->template_id,
                    'fields' => $fields,
                    'answer_id' => $row->answer_id,
                    'level_unique_id' => $row->level_unique_id,
                ];
            }

            // Search
            if (!empty($search)) {
                $results = array_filter($results, function ($item) use ($search) {
                    foreach ($item['fields'] as $val) {
                        if (is_array($val)) {
                            if (
                                stripos($val['value'] ?? '', $search) !== false ||
                                stripos($val['display'] ?? '', $search) !== false
                            ) {
                                return true;
                            }
                        } elseif (stripos((string)$val, $search) !== false) {
                            return true;
                        }
                    }
                    return false;
                });
                $results = array_values($results);
            }

            $sortableFields = ['level', 'rank/order'];
            if (in_array($sortField, $sortableFields)) {
                usort($results, function ($a, $b) use ($sortField, $sortOrder) {
                    $aVal = $a['fields'][$sortField] ?? '';
                    $bVal = $b['fields'][$sortField] ?? '';

                    $aVal = is_array($aVal) ? ($aVal['display'] ?? $aVal['value'] ?? '') : $aVal;
                    $bVal = is_array($bVal) ? ($bVal['display'] ?? $bVal['value'] ?? '') : $bVal;

                    // Force string type to avoid type errors
                    $aVal = (string)$aVal;
                    $bVal = (string)$bVal;

                    return $sortOrder === 'asc'
                        ? strnatcasecmp($aVal, $bVal)
                        : strnatcasecmp($bVal, $aVal);
                });
            }

            $total = count($results);
            $results = array_slice($results, $offset, $limit);

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'data' => $results,
                    'total' => $total,
                ]));
        } catch (BadRequestException $e) {
            Log::warning('Bad request in getRoleLevels: ' . $e->getMessage(), ['company_id' => $company_id]);
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]));
        } catch (\Exception $e) {
            Log::error('Error in getRoleLevels: ' . $e->getMessage(), ['company_id' => $company_id]);
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Server error: ' . $e->getMessage(),
                ]));
        }
    }

    /**
     * Check for rank conflicts in role levels
     * 
     * @return \Cake\Http\Response
     */
    public function checkRankConflicts()
    {
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
            $RoleLevelsTable = $this->getTable('RoleLevels', $companyId);
            $connection = $RoleLevelsTable->getConnection();

            // Find ranks that have duplicates (more than one role level with the same rank)
            $sql = "
                SELECT rank, COUNT(*) as count, 
                       STRING_AGG(level_unique_id, ', ' ORDER BY level_unique_id) as level_unique_ids,
                       STRING_AGG(name, ', ' ORDER BY level_unique_id) as names
                FROM role_levels 
                WHERE company_id = :company_id AND deleted = false AND rank IS NOT NULL
                GROUP BY rank
                HAVING COUNT(*) > 1
                ORDER BY rank
            ";

            $stmt = $connection->execute($sql, ['company_id' => $companyId]);
            $conflicts = [];
            foreach ($stmt->fetchAll('assoc') as $row) {
                $conflicts[] = [
                    'rank' => (int)$row['rank'],
                    'count' => (int)$row['count'],
                    'level_unique_ids' => explode(', ', $row['level_unique_ids']),
                    'names' => explode(', ', $row['names']),
                ];
            }

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'has_conflicts' => count($conflicts) > 0,
                    'conflicts' => $conflicts,
                    'conflict_count' => count($conflicts),
                ]));
        } catch (\Exception $e) {
            Log::error('Error checking rank conflicts: ' . $e->getMessage(), ['company_id' => $companyId]);
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error checking rank conflicts: ' . $e->getMessage(),
                ]));
        }
    }

    public function addRoleLevel()
    {
        Configure::write('debug', true);
        $this->request->allowMethod(['post']);

        $authResult = $this->Authentication->getResult();
        if (!$authResult || !$authResult->isValid()) {
            return $this->response->withStatus(401)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Unauthorized access',
                ]));
        }

        $companyId = $this->getCompanyId($authResult);
        $authData = $authResult->getData();
        $username = null;
        if ($authData instanceof \ArrayObject || is_array($authData)) {
            $username = $authData['username'] ?? $authData['sub'] ?? null;
        } elseif (is_object($authData)) {
            $username = $authData->username ?? $authData->sub ?? null;
        }

        $data = $this->request->getData();

        $templateId = $data['template_id'] ?? null;
        $roleLevelUniqueId = $data['roleLevelUniqueId'] ?? null;
        $answersRaw = $data['answers'] ?? null;
        $answers = is_string($answersRaw) ? json_decode($answersRaw, true) : $answersRaw;

        if (!$templateId || !$roleLevelUniqueId || $answers === false) {

            return $this->response->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Invalid input: template_id, role level unique id and valid answers are required.',
                ]));
        }

        $LevelTemplatesTable = $this->getTable('LevelTemplates', $companyId);
        $RoleLevelsTable = $this->getTable('RoleLevels', $companyId);

        try {
            // Start transaction
            $connection = $RoleLevelsTable->getConnection();
            $connection->begin();

            $template = $LevelTemplatesTable->find()
                ->where([
                    'id' => $templateId,
                    'company_id' => $companyId,
                    'deleted' => 0,
                ])
                ->first();

            if (!$template) {
                throw new BadRequestException('Invalid template ID.');
            }

            // Validate template structure
            $structure = $template->structure;
            if (is_string($structure)) {
                $structure = json_decode($structure, true);
            }
            if (!is_array($structure)) {
                throw new BadRequestException('Invalid template structure.');
            }

            $level = "";
            $rank = "";


            foreach ($structure as $group) {
                $groupId = (string)$group['id']; // Ensure string key match
                foreach ($group['fields'] as $field) {
                    $fieldId = (string)$field['id'];
                    $fieldLabel = $field['label'];

                    // Match by label instead of ID to work with dynamic templates
                    if ($fieldLabel === 'Level') {
                        $submitted = $answers[$groupId][$fieldId] ?? null;
                        if (!empty($submitted) && is_string($submitted)) {
                            $level = $submitted;
                        }
                    }

                    if ($fieldLabel === 'Rank/Order') {
                        $submitted = $answers[$groupId][$fieldId] ?? null;
                        if (!empty($submitted) && (is_numeric($submitted) || is_string($submitted))) {
                            $rank = $submitted;
                        }
                    }
                }
            }

            // Note: Duplicate ranks are now allowed, but we'll warn about conflicts via the frontend

            // Sanitize input data to prevent XSS
            $level = htmlspecialchars($level, ENT_QUOTES, 'UTF-8');
            $answers = $this->sanitizeArray($answers);

            // Create new level
            $level = $RoleLevelsTable->newEntity([
                'company_id' => $companyId,
                'level_unique_id' => $roleLevelUniqueId,
                'template_id' => $templateId,
                'name' => $level,
                'rank' => $rank,
                'custom_fields' => $answers,
                'created_by' => $username,
                'deleted' => 0,
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ]);

            // Debug: Check entity errors
            if ($level->hasErrors()) {
                Log::error('RoleLevel entity validation errors', ['errors' => $level->getErrors()]);
                throw new Exception('Failed to save level.');
            }

            if (!$RoleLevelsTable->save($level)) {
                Log::error('Failed to save RoleLevel', [
                    'entity_errors' => $level->getErrors(),
                    'entity_data' => $level->toArray()
                ]);
                throw new Exception('Failed to save level.');
            }


            // Commit transaction
            $connection->commit();

            // Log audit action
            $userData = AuditHelper::extractUserData($authResult);
            $roleLevelName = AuditHelper::extractRoleLevelName($answers);
            
            Log::debug('🔍 DEBUG: addRoleLevel - Audit logging data', [
                'level_unique_id' => $level->level_unique_id,
                'role_level_name' => $roleLevelName,
                'user_data' => $userData,
                'answers' => $answers
            ]);
            
            AuditHelper::logRoleLevelAction(
                'CREATE',
                $level->level_unique_id,
                $roleLevelName,
                $userData,
                $this->request
            );

            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'message' => 'Special Project data saved successfully.',
                    'data' => [
                        'level_id' => $level->id,
                        'level_unique_id' => $level->level_unique_id,
                        'template_id' => $level->template_id,
                        'answers' => $answers,
                    ],
                ]));
        } catch (\Exception $e) {
            if (isset($connection) && $connection->inTransaction()) {
                $connection->rollback();
            }
            $this->log($e->getMessage(), 'error');

            return $this->response->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]));
        }
    }


    private function getRequiredFileFields($structure)
    {
        $requiredFields = [];
        foreach ($structure as $group) {
            foreach ($group['fields'] as $field) {
                if ($field['type'] === 'file' && $field['is_required']) {
                    $requiredFields[] = [
                        'group_id' => $group['id'],
                        'field_id' => $field['id'],
                        'label' => $field['customize_field_label'] ?? $field['label'],
                    ];
                }
            }
            if (!empty($group['subGroups'])) {
                foreach ($group['subGroups'] as $subGroupIndex => $subGroup) {
                    foreach ($subGroup['fields'] as $field) {
                        if ($field['type'] === 'file' && $field['is_required']) {
                            $requiredFields[] = [
                                'group_id' => $group['id'],
                                'field_id' => $field['id'] . '_' . $subGroupIndex,
                                'label' => $field['customize_field_label'] ?? $field['label'],
                            ];
                        }
                    }
                }
            }
        }
        return $requiredFields;
    }


    public function deleteRoleLevel()
{
    $this->request->allowMethod(['post']);

    // Check authentication
    $authResult = $this->Authentication->getResult();
    if (!$authResult || !$authResult->isValid()) {
        return $this->response->withStatus(401)
            ->withType('application/json')
            ->withStringBody(json_encode([
                'success' => false,
                'message' => 'Unauthorized access'
            ]));
    }

    $data = $this->request->getData();
        $companyId = $this->getCompanyId($authResult);
    $roleLevelId = $data['role_level_id'] ?? null;

    if (empty($roleLevelId)) {
        return $this->response->withStatus(400)
            ->withType('application/json')
            ->withStringBody(json_encode([
                'success' => false,
                'message' => 'Missing role_level_id'
            ]));
    }

    $RoleLevelsTable = $this->getTable('RoleLevels', $companyId);

    try {
                                        $connection = $RoleLevelsTable->getConnection();
            $connection->begin();

            $roleLevel = $RoleLevelsTable->find()
            ->where([
                'company_id' => $companyId,
                'level_unique_id' => $roleLevelId,
                'deleted' => 0
            ])
            ->first();

        if (!$roleLevel) {
            $connection->rollback();
            return $this->response->withStatus(404)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Role level not found'
                ]));
        }

        $timestamp = date('Y-m-d H:i:s');

        $RoleLevelsTable->updateAll(
            ['deleted' => 1, 'modified' => $timestamp],
            [
                'company_id' => $companyId,
                'level_unique_id' => $roleLevelId,
                'deleted' => 0
            ]
        );

        $connection->commit();

        // Log audit action
        $userData = AuditHelper::extractUserData($authResult);
        $roleLevelName = $roleLevel->name ?? 'Unnamed Role Level';
        
        Log::debug('🔍 DEBUG: deleteRoleLevel - Audit logging data', [
            'role_level_id' => $roleLevelId,
            'role_level_name' => $roleLevelName,
            'user_data' => $userData
        ]);
        
        AuditHelper::logRoleLevelAction(
            'DELETE',
            $roleLevelId,
            $roleLevelName,
            $userData,
            $this->request
        );

        return $this->response->withStatus(200)
            ->withType('application/json')
            ->withStringBody(json_encode([
                'success' => true,
                'message' => 'Role level deleted successfully'
            ]));
    } catch (\Exception $e) {
        if (isset($connection) && $connection->inTransaction()) {
            $connection->rollback();
        }
        // Consider logging the exception here instead of exposing it in production
        return $this->response->withStatus(500)
            ->withType('application/json')
            ->withStringBody(json_encode([
                'success' => false,
                'message' => 'An error occurred while deleting the role level',
                'error' => $e->getMessage() // REMOVE in production
            ]));
    }
}


    public function getRoleLevelDetails()
    {
        $this->request->allowMethod(['post']);

        // Authentication check
        $authResult = $this->Authentication->getResult();
        if (!$authResult || !$authResult->isValid()) {
            return $this->response->withType('application/json')
                ->withStatus(401)
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Unauthorized access',
                ]));
        }

        $company_id = $this->getCompanyId($authResult);
        $data = $this->request->getData();

        if (empty($data['level_unique_id'])) {
            return $this->response->withType('application/json')
                ->withStatus(400)
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Missing level unique id',
                ]));
        }

        $role_level_unique_id = $data['level_unique_id'];

        try {
            $LevelTemplatesTable = $this->getTable('LevelTemplates', $company_id);

            $record = $LevelTemplatesTable
                ->find()
               ->select([
                    'structure' => 'LevelTemplates.structure',
                    'template_id' => 'role_levels.template_id',
                    'answers' => 'role_levels.custom_fields',
                    'answer_id' => 'role_levels.id',
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
                    'role_levels.level_unique_id' => $role_level_unique_id,
                ])
                ->first();

            if (!$record) {
                return $this->response->withType('application/json')
                    ->withStatus(404)
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Level not found',
                    ]));
            }

            $structure = $record->structure;
            if (is_string($structure)) {
                $structure = json_decode($structure, true);
            }
            if (!is_array($structure)) {
                $structure = [];
            }
            $answers = json_decode($record->answers, true) ?: [];
            $fields = [];

            // Process all groups in the structure, not just the first one
            foreach ($structure as $group) {
                $groupId = $group['id'] ?? null;
                $groupAnswers = $groupId && isset($answers[$groupId]) ? $answers[$groupId] : [];

                foreach ($group['fields'] as $field) {
                    $label = $field['label'] ?? null;
                    $customLabel = $field['customize_field_label'] ?? null;
                    $fieldKey = $customLabel ?: $label;
                    $fieldId = $field['id'] ?? null;

                    if ($fieldKey) {
                        $fields[$fieldKey] = $fieldId && isset($groupAnswers[$fieldId])
                            ? $groupAnswers[$fieldId]
                            : null;
                    }
                }
            }

            return $this->response->withType('application/json')
                ->withStatus(200)
                ->withStringBody(json_encode([
                    'success' => true,
                    'data' => [
                        'fields' => $fields,
                    ],
                    'message' => 'Level details fetched successfully',
                ]));
        } catch (\Exception $e) {
            return $this->response->withType('application/json')
                ->withStatus(500)
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'An error occurred: ' . $e->getMessage(),
                ]));
        }
    }

    public function getEditRoleLevelDetail()
    {
        Configure::write('debug', true);
        $this->request->allowMethod(['post']);

        $authResult = $this->Authentication->getResult();
        if (!$authResult || !$authResult->isValid()) {
            return $this->response->withType('application/json')
                ->withStatus(401)
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Unauthorized access',
                ]));
        }

        $company_id = $this->getCompanyId($authResult);
        $data = $this->request->getData();

        if (empty($data['level_unique_id'])) {
            return $this->response->withType('application/json')
                ->withStatus(400)
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Missing level unique id',
                ]));
        }

        $level_unique_id = $data['level_unique_id'];

        try {
            $LevelTemplatesTable = $this->getTable('LevelTemplates', $company_id);

            $record = $LevelTemplatesTable
                ->find()
                 ->select([
                    'structure' => 'LevelTemplates.structure',
                    'template_id' => 'role_levels.template_id',
                    'answers' => 'role_levels.custom_fields',
                    'answer_id' => 'role_levels.id',
                    'level_unique_id' => 'role_levels.level_unique_id',
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
                    'role_levels.level_unique_id' => $level_unique_id,
                    'LevelTemplates.deleted' => 0,
                ])
                ->first();

            if (!$record) {
                return $this->response->withType('application/json')
                    ->withStatus(404)
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Routine not found',
                    ]));
            }

            $structure = $record->structure;
            if (is_string($structure)) {
                $structure = json_decode($structure, true);
            }
            if (!is_array($structure)) {
                $structure = [];
            }
            $answers = json_decode($record->answers, true);
            $fieldsById = [];

            foreach ($structure as $group) {
                foreach ($group['fields'] as $field) {
                    $groupId = $group['id'];
                    $fieldId = $field['id'];

                    if (isset($answers[$groupId][$fieldId])) {
                        if (!isset($fieldsById[$groupId])) {
                            $fieldsById[$groupId] = [];
                        }
                        $fieldsById[$groupId][$fieldId] = $answers[$groupId][$fieldId];
                    }
                }
            }


            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'data' => [
                        'structure' => $structure,
                        'fields' => $fieldsById, // frontend expects answers[groupId][fieldId]
                        'template_id' => $record->template_id,
                        'level_unique_id' => $record->level_unique_id,
                        'answer_id' => $record->answer_id,
                    ],
                ]));
        } catch (\Exception $e) {
            return $this->response->withType('application/json')
                ->withStatus(500)
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'An error occurred: ' . $e->getMessage(),
                ]));
        }
    }

    public function updateRoleLevel()
    {
        Configure::write('debug', true);
        $this->request->allowMethod(['post']);

        // Authenticate user
        $authResult = $this->Authentication->getResult();
        if (!$authResult || !$authResult->isValid()) {
            return $this->response->withStatus(401)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Unauthorized access',
                ]));
        }

        $companyId = $this->getCompanyId($authResult);
        $authData = $authResult->getData();
        $username = null;
        if ($authData instanceof \ArrayObject || is_array($authData)) {
            $username = $authData['username'] ?? $authData['sub'] ?? null;
        } elseif (is_object($authData)) {
            $username = $authData->username ?? $authData->sub ?? null;
        }

        $data = $this->request->getData();
        $templateId = $data['template_id'] ?? null;
        $level_unique_id = $data['level_unique_id'] ?? null;
        $answersRaw = $data['answers'] ?? null;
        $answers = is_string($answersRaw) ? json_decode($answersRaw, true) : $answersRaw;

        if (!$templateId || !$level_unique_id || $answers === false) {
            return $this->response->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Invalid input: template_id, level_unique_id, and valid answers are required.',
                ]));
        }

        $LevelTemplatesTable = $this->getTable('LevelTemplates', $companyId);
        $RoleLevelsTable = $this->getTable('RoleLevels', $companyId);
        $connection = ConnectionManager::get('client_' . $companyId);

        try {
            $connection->begin();

            // Verify project exists
            $project = $RoleLevelsTable->find()
                ->where([
                    'level_unique_id' => $level_unique_id,
                    'company_id' => $companyId,
                    'deleted' => 0,
                ])
                ->first();

            if (!$project) {
                return $this->response->withStatus(404)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Level not found',
                    ]));
            }

            // Verify template exists
            $template = $LevelTemplatesTable->find()
                ->where([
                    'id' => $templateId,
                    'company_id' => $companyId,
                    'deleted' => 0,
                ])
                ->first();

            if (!$template) {
                throw new BadRequestException('Invalid template ID.');
            }

            // Validate template structure
            $structure = $template->structure;
            if (is_string($structure)) {
                $structure = json_decode($structure, true);
            }
            if (!is_array($structure)) {
                throw new BadRequestException('Invalid template structure.');
            }

            // Extract level from answers
            $level = $project->name;
            $rank = $project->rank;
            foreach ($structure as $group) {
                $groupId = (string)$group['id'];
                foreach ($group['fields'] as $field) {
                    $fieldId = (string)$field['id'];
                    $fieldLabel = $field['label'];
                    if ($fieldLabel === 'Level' && isset($answers[$groupId][$fieldId])) {
                        $level = $answers[$groupId][$fieldId];
                    }

                    if ($fieldLabel === 'Rank/Order' && isset($answers[$groupId][$fieldId])) {
                        $rank = $answers[$groupId][$fieldId];
                    }
                }
            }

            // Check if rank already exists (excluding current record)
            if (!empty($rank) && $rank != $project->rank) {
                $existingRank = $RoleLevelsTable->find()
                    ->where([
                        'company_id' => $companyId,
                        'rank' => $rank,
                        'deleted' => 0,
                        'level_unique_id !=' => $level_unique_id
                    ])
                    ->first();

                if ($existingRank) {
                    throw new Exception("Rank '{$rank}' already exists. Please choose a different rank.");
                }
            }

            // Store old answers BEFORE any modifications for audit logging
            $oldAnswers = is_array($project->custom_fields) ? $project->custom_fields : (json_decode($project->custom_fields, true) ?? []);
            
            Log::debug('🔍 DEBUG: updateRoleLevel - Old answers from database (BEFORE update)', [
                'project_custom_fields_raw' => $project->custom_fields,
                'old_answers_decoded' => $oldAnswers
            ]);

            // Update project
            $level = $RoleLevelsTable->patchEntity($project, [
                'template_id' => $templateId,
                'name' => $level,
                'rank' => $rank,
                'custom_fields' => $answers,
                'modified' => date('Y-m-d H:i:s'),
                'created_by' => $username,
            ]);

            if (!$RoleLevelsTable->save($level)) {
                throw new Exception('Failed to update role level.');
            }

            $connection->commit();

            // Log audit action with field change tracking
            $userData = AuditHelper::extractUserData($authResult);
            $roleLevelName = AuditHelper::extractRoleLevelName($answers);
            
            Log::debug('🔍 DEBUG: updateRoleLevel - Audit logging data', [
                'level_unique_id' => $level_unique_id,
                'role_level_name' => $roleLevelName,
                'user_data' => $userData,
                'answers' => $answers
            ]);
            
            // Flatten both old and new answers for comparison
            $oldFlatAnswers = $this->flattenAnswers($oldAnswers);
            $newFlatAnswers = $this->flattenAnswers($answers);
            
            Log::debug('🔍 DEBUG: updateRoleLevel - Flattened answers for comparison', [
                'old_flat_answers' => $oldFlatAnswers,
                'new_flat_answers' => $newFlatAnswers
            ]);
            
            // Create field mapping
            $fieldMapping = $this->getRoleLevelFieldMapping($newFlatAnswers);
            
            Log::debug('🔍 DEBUG: updateRoleLevel - Field mapping', [
                'field_mapping' => $fieldMapping,
                'flat_answer_keys' => array_keys($newFlatAnswers)
            ]);
            
            $fieldChanges = AuditHelper::generateFieldChanges(
                $oldFlatAnswers,
                $newFlatAnswers,
                $fieldMapping
            );
            
            Log::debug('🔍 DEBUG: updateRoleLevel - Field changes', [
                'field_changes' => $fieldChanges,
                'field_changes_count' => count($fieldChanges),
                'field_changes_empty' => empty($fieldChanges),
                'old_answers' => $oldAnswers
            ]);
            
            Log::debug('🔍 DEBUG: updateRoleLevel - Calling AuditHelper::logRoleLevelAction', [
                'action' => 'UPDATE',
                'level_unique_id' => $level_unique_id,
                'role_level_name' => $roleLevelName,
                'field_changes' => $fieldChanges,
                'field_changes_count' => count($fieldChanges)
            ]);
            
            AuditHelper::logRoleLevelAction(
                'UPDATE',
                $level_unique_id,
                $roleLevelName,
                $userData,
                $this->request,
                $fieldChanges
            );

            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'message' => 'Role Level updated successfully.',
                    'data' => [
                        'level_id' => $level->id,
                        'level_unique_id' => $level->level_unique_id,
                        'template_id' => $level->template_id,
                        'answers' => $answers,
                    ],
                ]));
        } catch (\Exception $e) {
            if (isset($connection) && $connection->inTransaction()) {
                $connection->rollback();
            }
            $this->log($e->getMessage(), 'error');

            return $this->response->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => $e->getMessage(),
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
     * Get field mapping for role levels
     *
     * @param array $flatAnswers
     * @return array
     */
    private function getRoleLevelFieldMapping($flatAnswers)
    {
        // Define field order based on typical role level structure
        $fieldOrder = ['Level', 'Rank/Order', 'Description', 'Responsibilities', 'Requirements'];
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

    private function getCompanyId($authResult)
    {
        $authData = $authResult->getData();

        // Handle both ArrayObject and stdClass
        if ($authData instanceof \ArrayObject || is_array($authData)) {
            return $authData['company_id'] ?? null;
        } elseif (is_object($authData)) {
            return $authData->company_id ?? null;
        }

        return null;
    }

    /**
     * Recursively sanitize array data to prevent XSS attacks
     */
    private function sanitizeArray($data)
    {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeArray'], $data);
        } elseif (is_string($data)) {
            return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        } elseif (is_object($data)) {
            // Convert object to array and sanitize
            return $this->sanitizeArray((array)$data);
        } elseif (is_numeric($data) || is_bool($data) || is_null($data)) {
            // Keep numeric, boolean, and null values as-is
            return $data;
        }
        // For any other type, convert to string and sanitize
        return htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8');
    }
}
