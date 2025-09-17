<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\Api\ApiController;
use App\Helper\AuditHelper;
use Cake\Core\Configure;
use Cake\Utility\Text;
use Exception;
use Cake\Log\Log;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;

class ScorecardsController extends ApiController
{
    public function initialize(): void
    {
        parent::initialize();
    }

    /**
     * Get table headers for scorecards based on template structure
     */
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
            $ScorecardTemplatesTable = $this->getTable('ScorecardTemplates', $company_id);

            $template = $ScorecardTemplatesTable
                ->find()
                ->select(['template_id' => 'id', 'structure'])
                ->where(['company_id' => $company_id, 'deleted' => 0])
                ->first();

            if (!$template) {
                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'No scorecard template found.',
                    ]));
            }

            // Process the structure to extract required fields
            $structure = [];
            if (is_string($template->structure)) {
                $structure = json_decode($template->structure, true) ?: [];
            } elseif (is_array($template->structure) || is_object($template->structure)) {
                $structure = (array)$template->structure;
            }
            $headers = [];

            // Iterate through structure groups to find the fields
            foreach ($structure as $group) {
                foreach ($group['fields'] as $field) {
                    $label = $field['label'];
                    if (in_array($label, ['Code', 'Strategies/Tactics', 'Measures', 'Deadline', 'Points', 'Weight (%)'])) {
                        $headers[] = [
                            'id' => $this->getFieldId($label),
                            'label' => !empty($field['customize_field_label']) ? $field['customize_field_label'] : $field['label'],
                            'fieldId' => $field['id'] // Include actual field ID for sorting
                        ];
                    }
                }
            }

            // Sort headers to ensure consistent order
            usort($headers, function ($a, $b) {
                $order = ['scorecardCode', 'strategies', 'measures', 'deadline', 'points', 'weight'];
                return array_search($a['id'], $order) - array_search($b['id'], $order);
            });

            // Validate that all required fields are present
            $requiredFields = ['scorecardCode', 'strategies', 'measures', 'deadline', 'points', 'weight'];
            $foundFields = array_column($headers, 'id');
            if (count(array_intersect($requiredFields, $foundFields)) < count($requiredFields)) {
                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Required fields (Code, Strategies/Tactics, Measures, Deadline, Points, Weight) not found in template.',
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
                    'message' => 'Error fetching scorecard template: ' . $e->getMessage(),
                ]));
        }
    }

    /**
     * Get scorecard template for add form
     */
    public function getScorecardTemplate()
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

        $companyId = $authResult->getData()->company_id;

        try {
            // Get tenant-specific table
            $table = $this->getTable('ScorecardTemplates', $companyId);

            // Get the scorecard template
            $template = $table->find()
                ->select(['template_id' => 'id', 'structure'])
                ->where(['company_id' => $companyId, 'deleted' => 0])
                ->first();

            if (!$template) {
                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'No scorecard template found.',
                    ]));
            }

            // Return the template structure
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'structure' => $template,
                ]));
        } catch (\Throwable $e) {
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error fetching scorecard template: ' . $e->getMessage(),
                ]));
        }
    }

    /**
     * Get scorecards data with pagination, search, and sorting
     */
    public function getScorecardsData()
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

        $company_id = $authResult->getData()->company_id;
        $authData = $authResult->getData();
        $currentUsername = $authData->username ?? 'system';

        // Get pagination, search, and sorting parameters
        $page = (int)($this->request->getQuery('page') ?? 1);
        $limit = (int)($this->request->getQuery('limit') ?? 10);
        $search = $this->request->getQuery('search') ?? '';
        $sortField = $this->request->getQuery('sortField') ?? '';
        $sortOrder = $this->request->getQuery('sortOrder') ?? 'asc';

        Log::debug('🔍 DEBUG: getScorecardsData - Sort parameters received', [
            'sortField' => $sortField,
            'sortOrder' => $sortOrder,
            'page' => $page,
            'limit' => $limit,
            'search' => $search
        ]);

        // Validate parameters
        if ($page < 1) $page = 1;
        if ($limit < 1) $limit = 10;
        if ($limit > 100) $limit = 100; // Prevent excessive data retrieval
        $sortOrder = strtolower($sortOrder) === 'desc' ? 'desc' : 'asc';

        try {
            // Fetch scorecard template answers with pagination
            $ScorecardTemplatesTable = $this->getTable('ScorecardTemplates', $company_id);
            
            // Build the base query
            $query = $ScorecardTemplatesTable
                ->find()
                ->select([
                    'structure' => 'structure',
                    'scorecard_unique_id' => 'scorecard_template_answers.scorecard_unique_id',
                    'template_id' => 'scorecard_template_answers.template_id',
                    'answer_id' => 'scorecard_template_answers.id',
                    'answers' => 'scorecard_template_answers.answers',
                    'assigned_employee_username' => 'scorecard_template_answers.assigned_employee_username',
                    'parent_scorecard_id' => 'scorecard_template_answers.parent_scorecard_id',
                    'created_by' => 'scorecard_template_answers.created_by'
                ])
                ->join([
                    'scorecard_template_answers' => [
                        'table' => 'scorecard_template_answers',
                        'type' => 'LEFT',
                        'conditions' => [
                            'scorecard_template_answers.company_id = ScorecardTemplates.company_id',
                            'scorecard_template_answers.deleted' => 0,
                        ]
                    ]
                ])
                ->where([
                    'ScorecardTemplates.company_id' => $company_id,
                    'ScorecardTemplates.deleted' => 0,
                    'scorecard_template_answers.scorecard_unique_id IS NOT' => null,
                ]);

            // Get all data first for search and sorting (since we need to process JSON)
            $allScorecardAnswers = $query->all()->toArray();

            if (empty($allScorecardAnswers)) {
                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => true,
                        'data' => [
                            'records' => [],
                            'total' => 0
                        ]
                    ]));
            }

            // Get template structure for field mapping
            $template = $this->getTable('ScorecardTemplates', $company_id)
                ->find()
                ->where(['company_id' => $company_id, 'deleted' => 0])
                ->first();
            
            $templateStructure = [];
            if ($template && !empty($template->structure)) {
                if (is_string($template->structure)) {
                    $templateStructure = json_decode($template->structure, true) ?: [];
                } elseif (is_array($template->structure) || is_object($template->structure)) {
                    $templateStructure = (array)$template->structure;
                }
            }
            
            // Build field ID to label mapping for search
            $fieldIdToLabel = [];
            foreach ($templateStructure as $group) {
                foreach ($group['fields'] as $field) {
                    $fieldIdToLabel[$field['id']] = $field['label'];
                }
            }

            // Process and filter data
            $processedData = [];
            foreach ($allScorecardAnswers as $scorecard) {
                // Properly decode JSON answers from database
                $answers = [];
                if (!empty($scorecard->answers)) {
                    if (is_string($scorecard->answers)) {
                        $answers = json_decode($scorecard->answers, true) ?: [];
                    } elseif (is_array($scorecard->answers)) {
                        $answers = $scorecard->answers;
                    }
                }
                
                // Extract scorecard code for search using field ID
                $scorecardCode = '';
                if (is_array($answers)) {
                    foreach ($answers as $fieldId => $value) {
                        if (isset($fieldIdToLabel[$fieldId]) && $fieldIdToLabel[$fieldId] === 'Code') {
                            $scorecardCode = $value;
                            break;
                        }
                    }
                }
                
                // Apply search filter
                if (!empty($search)) {
                    $searchLower = strtolower($search);
                    $found = false;
                    
                    Log::debug('🔍 DEBUG: Search filter applied', [
                        'search' => $search,
                        'searchLower' => $searchLower,
                        'scorecardCode' => $scorecardCode,
                        'answers' => $answers
                    ]);
                    
                    // Search in scorecard code
                    if (strpos(strtolower($scorecardCode), $searchLower) !== false) {
                        $found = true;
                        Log::debug('🔍 DEBUG: Found match in scorecard code');
                    }
                    
                    // Search in all answer fields
                    if (is_array($answers)) {
                        foreach ($answers as $fieldId => $value) {
                            if (is_string($value) && strpos(strtolower($value), $searchLower) !== false) {
                                $found = true;
                                Log::debug('🔍 DEBUG: Found match in field', [
                                    'fieldId' => $fieldId,
                                    'value' => $value
                                ]);
                                break;
                            }
                        }
                    }
                    
                    if (!$found) {
                        Log::debug('🔍 DEBUG: No match found, skipping record');
                        continue; // Skip this record if search doesn't match
                    }
                }

                $processedRecord = [
                    'scorecard_unique_id' => $scorecard->scorecard_unique_id,
                    'template_id' => $scorecard->template_id,
                    'answer_id' => $scorecard->answer_id,
                    'answers' => $answers,
                    'scorecard_code' => $scorecardCode,
                    'assigned_employee_username' => $scorecard->assigned_employee_username,
                    'parent_scorecard_id' => $scorecard->parent_scorecard_id,
                    'created_by' => $scorecard->created_by
                ];
                
                $processedData[] = $processedRecord;
            }

            // Apply sorting
            if (!empty($sortField)) {
                Log::debug('🔍 DEBUG: Applying sorting', [
                    'sortField' => $sortField,
                    'sortOrder' => $sortOrder,
                    'dataCount' => count($processedData)
                ]);

                usort($processedData, function ($a, $b) use ($sortField, $sortOrder) {
                    $valueA = $this->getSortValue($a, $sortField);
                    $valueB = $this->getSortValue($b, $sortField);
                    
                    Log::debug('🔍 DEBUG: Comparing values', [
                        'valueA' => $valueA,
                        'valueB' => $valueB,
                        'sortOrder' => $sortOrder
                    ]);
                    
                    if ($sortOrder === 'desc') {
                        return $valueB <=> $valueA;
                    }
                    return $valueA <=> $valueB;
                });

                Log::debug('🔍 DEBUG: Sorting completed', [
                    'firstRecord' => $processedData[0] ?? null,
                    'lastRecord' => end($processedData) ?: null
                ]);
            }

            // Apply pagination
            $total = count($processedData);
            $offset = ($page - 1) * $limit;
            $paginatedData = array_slice($processedData, $offset, $limit);

            Log::debug('🔍 DEBUG: Pagination applied', [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'offset' => $offset,
                'paginatedCount' => count($paginatedData)
            ]);

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'data' => [
                        'records' => $paginatedData,
                        'total' => $total,
                        'page' => $page,
                        'limit' => $limit,
                        'totalPages' => ceil($total / $limit)
                    ]
                ]));

        } catch (\Throwable $e) {
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error fetching scorecards: ' . $e->getMessage(),
                ]));
        }
    }

    /**
     * Add a new scorecard
     */
    public function addScorecard()
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

        $companyId = $authResult->getData()->company_id;
        $data = $this->request->getData();
        
        $scorecardUniqueId = $data['scorecardUniqueId'] ?? null;
        if (empty($scorecardUniqueId)) {
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Scorecard Unique ID is required.',
                ]));
        }

        try {
            $ScorecardTemplateAnswersTable = $this->getTable('ScorecardTemplateAnswers', $companyId);

            // Validate required fields
            if (empty($data['template_id'])) {
                throw new Exception('Template ID is required.');
            }
            if (empty($data['answers'])) {
                throw new Exception('Answers are required.');
            }

            // Parse and validate answers
            $answers = $this->parseAnswers($data['answers']);
            $template = $this->validateTemplate($companyId, $data['template_id']);

            // Check if scorecard code already exists
            $this->checkExistingScorecardCode($companyId, $answers);

            // Start transaction
            $connection = $ScorecardTemplateAnswersTable->getConnection();
            $connection->begin();

            // Get current user's username for assignment and creation tracking
            $authData = $authResult->getData();
            $currentUsername = $authData->username ?? 'system';
            
            // Save answers with assigned employee (current user) and created_by
            $answerEntity = $this->saveScorecardAnswers($companyId, $scorecardUniqueId, $data['template_id'], $answers, $currentUsername, $currentUsername);

            // Commit transaction
            $connection->commit();

            // Log audit action
            $userData = AuditHelper::extractUserData($authResult);
            $scorecardName = AuditHelper::extractScorecardCode($answers);
            
            Log::debug('🔍 DEBUG: addScorecard - Audit logging data', [
                'scorecard_unique_id' => $scorecardUniqueId,
                'scorecard_name' => $scorecardName,
                'user_data' => $userData,
                'answers' => $answers
            ]);
            
            AuditHelper::logScorecardAction(
                'CREATE',
                $scorecardUniqueId,
                $scorecardName,
                $userData,
                $this->request
            );

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'message' => 'Scorecard created successfully.',
                    'scorecard_id' => $scorecardUniqueId,
                    'answer_id' => $answerEntity->id,
                ]));
        } catch (Exception $e) {
            if (isset($connection) && $connection->inTransaction()) {
                $connection->rollback();
            }
            Log::error('AddScorecard Error: ' . $e->getMessage(), ['company_id' => $companyId]);
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]));
        }
    }

    /**
     * Delete a scorecard
     */
    public function deleteScorecard()
    {
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
            $scorecard_unique_id = $data['scorecard_unique_id'] ?? null;

            if (empty($scorecard_unique_id)) {
                return $this->response
                    ->withStatus(400)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Scorecard unique ID is required.',
                    ]));
            }

            $ScorecardTemplateAnswersTable = $this->getTable('ScorecardTemplateAnswers', $company_id);

            // Find the scorecard to delete
            $scorecard = $ScorecardTemplateAnswersTable
                ->find()
                ->where([
                    'scorecard_unique_id' => $scorecard_unique_id,
                    'company_id' => $company_id,
                    'deleted' => 0
                ])
                ->first();

            if (!$scorecard) {
                return $this->response
                    ->withStatus(404)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Scorecard not found.',
                    ]));
            }

            // Get scorecard name for audit logging
            $answers = is_array($scorecard->answers) ? $scorecard->answers : (json_decode($scorecard->answers, true) ?? []);
            $scorecardName = AuditHelper::extractScorecardCode($answers);
            
            // Soft delete
            $scorecard->deleted = 1;
            if ($ScorecardTemplateAnswersTable->save($scorecard)) {
                // Log audit action
                $userData = AuditHelper::extractUserData($authResult);
                AuditHelper::logScorecardAction(
                    'DELETE',
                    $scorecard_unique_id,
                    $scorecardName,
                    $userData,
                    $this->request
                );
                
                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => true,
                        'message' => 'Scorecard deleted successfully'
                    ]));
            } else {
                return $this->response
                    ->withStatus(500)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Failed to delete scorecard',
                    ]));
            }

        } catch (\Exception $e) {
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error deleting scorecard: ' . $e->getMessage(),
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
        $fieldMap = [
            'Code' => 'scorecardCode',
            'Strategies/Tactics' => 'strategies',
            'Measures' => 'measures',
            'Deadline' => 'deadline',
            'Points' => 'points',
            'Weight (%)' => 'weight'
        ];

        return $fieldMap[$label] ?? strtolower(str_replace([' ', '/', '(', ')', '%'], '', $label));
    }

    /**
     * Get field ID to label mapping from template structure
     * @return array
     */
    private function getFieldIdToLabelMapping()
    {
        $authResult = $this->Authentication->getResult();
        if (!$authResult || !$authResult->isValid()) {
            return [];
        }

        $company_id = $authResult->getData()->company_id;
        
        try {
            $template = $this->getTable('ScorecardTemplates', $company_id)
                ->find()
                ->where(['company_id' => $company_id, 'deleted' => 0])
                ->first();
            
            if (!$template || empty($template->structure)) {
                return [];
            }
            
            $templateStructure = [];
            if (is_string($template->structure)) {
                $templateStructure = json_decode($template->structure, true) ?: [];
            } elseif (is_array($template->structure) || is_object($template->structure)) {
                $templateStructure = (array)$template->structure;
            }
            
            $fieldIdToLabel = [];
            foreach ($templateStructure as $group) {
                foreach ($group['fields'] as $field) {
                    $fieldIdToLabel[$field['id']] = $field['label'];
                }
            }
            
            return $fieldIdToLabel;
        } catch (\Exception $e) {
            Log::debug('🔍 DEBUG: Error getting field mapping', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Recursively get all child scorecard IDs in the hierarchy
     * @param object $ScorecardTemplateAnswersTable
     * @param array $parentScorecardIds
     * @return array
     */
    private function getAllChildScorecardIds($ScorecardTemplateAnswersTable, $parentScorecardIds)
    {
        if (empty($parentScorecardIds)) {
            Log::debug('🔍 DEBUG: getAllChildScorecardIds - No parent scorecard IDs provided');
            return [];
        }

        // Get company_id from the table
        $companyId = $parentScorecardIds[0]['company_id'] ?? null;
        if (!$companyId) {
            Log::debug('🔍 DEBUG: getAllChildScorecardIds - No company_id found in parent scorecards');
            return [];
        }

        Log::debug('🔍 DEBUG: getAllChildScorecardIds - Starting with parent IDs', [
            'parentScorecardIds' => $parentScorecardIds,
            'companyId' => $companyId
        ]);

        $allChildIds = [];
        $currentLevelIds = array_column($parentScorecardIds, 'id'); // Extract IDs from the start

        // Keep going until we find no more children
        while (!empty($currentLevelIds)) {
            Log::debug('🔍 DEBUG: getAllChildScorecardIds - Looking for children of', [
                'currentLevelIds' => $currentLevelIds
            ]);

            // Get direct children of current level
            $children = $ScorecardTemplateAnswersTable->find()
                ->where([
                    'company_id' => $companyId,
                    'deleted' => 0,
                    'parent_scorecard_id IN' => $currentLevelIds
                ])
                ->all()
                ->toArray();

            Log::debug('🔍 DEBUG: getAllChildScorecardIds - Found children', [
                'childrenCount' => count($children),
                'children' => $children
            ]);

            if (empty($children)) {
                Log::debug('🔍 DEBUG: getAllChildScorecardIds - No more children found, stopping');
                break; // No more children found
            }

            $childIds = array_column($children, 'id');
            $allChildIds = array_merge($allChildIds, $childIds);
            $currentLevelIds = $childIds; // Next iteration will look for children of these children

            Log::debug('🔍 DEBUG: getAllChildScorecardIds - Updated state', [
                'childIds' => $childIds,
                'allChildIds' => $allChildIds,
                'nextLevelIds' => $currentLevelIds
            ]);
        }

        $result = array_unique($allChildIds);
        Log::debug('🔍 DEBUG: getAllChildScorecardIds - Final result', [
            'allChildIds' => $result
        ]);

        return $result;
    }

    /**
     * Get sort value for a specific field
     * @param array $scorecard
     * @param string $sortField
     * @return mixed
     */
    private function getSortValue($scorecard, $sortField)
    {
        $answers = $scorecard['answers'] ?? [];
        
        Log::debug('🔍 DEBUG: getSortValue called', [
            'sortField' => $sortField,
            'answers' => $answers,
            'isNumeric' => is_numeric($sortField),
            'scorecard_code' => $scorecard['scorecard_code'] ?? 'NOT_SET'
        ]);
        
        // Handle scorecard_code field (stored separately from answers)
        if ($sortField === 'scorecard_code') {
            $value = $scorecard['scorecard_code'] ?? '';
            Log::debug('🔍 DEBUG: Scorecard code sorting result', [
                'rawValue' => $value,
                'processedValue' => (string)$value
            ]);
            return (string)$value;
        }
        
        // Handle field ID-based sorting (new approach)
        if (is_numeric($sortField)) {
            // Map field ID to field label using the template structure
            $fieldIdToLabel = $this->getFieldIdToLabelMapping();
            $fieldLabel = $fieldIdToLabel[$sortField] ?? '';
            
            if (empty($fieldLabel)) {
                Log::debug('🔍 DEBUG: Field ID not found in mapping', [
                    'fieldId' => $sortField,
                    'availableMappings' => $fieldIdToLabel
                ]);
                return '';
            }
            
            $value = $answers[$fieldLabel] ?? '';
            
            // Convert to appropriate type for sorting
            if (is_numeric($value)) {
                $result = (float)$value;
            } else {
                $result = (string)$value;
            }
            
            Log::debug('🔍 DEBUG: Field ID sorting result', [
                'fieldId' => $sortField,
                'fieldLabel' => $fieldLabel,
                'rawValue' => $value,
                'processedValue' => $result
            ]);
            
            return $result;
        }
        
        // Handle legacy field label-based sorting (fallback)
        switch ($sortField) {
            case 'answers->>\'Strategies/Tactics\'':
                return $answers['Strategies/Tactics'] ?? '';
            case 'answers->>\'Measures\'':
                return $answers['Measures'] ?? '';
            case 'answers->>\'Deadline\'':
                return $answers['Deadline'] ?? '';
            case 'answers->>\'Points\'':
                return is_numeric($answers['Points']) ? (int)$answers['Points'] : 0;
            case 'answers->>\'Weight (%)\'':
                return is_numeric($answers['Weight (%)']) ? (int)$answers['Weight (%)'] : 0;
            default:
                Log::debug('🔍 DEBUG: Unknown sort field', ['sortField' => $sortField]);
                return '';
        }
    }

    /**
     * Flatten nested answers structure for comparison
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
     * Parse and validate answers from form data
     * @param array $answers
     * @return array
     */
    private function parseAnswers($answers)
    {
        // Handle both string and array formats for answers
        if (is_string($answers)) {
            $answers = json_decode($answers, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON format for answers.');
            }
        }

        if (!is_array($answers)) {
            throw new Exception('Answers must be an array or valid JSON string.');
        }

        $parsedAnswers = [];
        foreach ($answers as $groupId => $groupAnswers) {
            if (is_array($groupAnswers)) {
                foreach ($groupAnswers as $fieldId => $value) {
                    if ($value !== null && $value !== '') {
                        // For now, we'll store the data with field IDs as keys
                        // The frontend will need to be updated to handle this properly
                        $parsedAnswers[$fieldId] = $value;
                    }
                }
            }
        }

        return $parsedAnswers;
    }



    /**
     * Create child scorecards for a parent scorecard
     * This is an exact replica of addScorecard but with parent_scorecard_id and assigned_employee_username
     */
    public function createChildScorecards()
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

        $companyId = $authResult->getData()->company_id;
        $data = $this->request->getData();

        try {
            // Validate required fields
            if (empty($data['parent_scorecard_id'])) {
                throw new Exception('Parent scorecard ID is required.');
            }

            if (empty($data['child_scorecards']) || !is_array($data['child_scorecards'])) {
                throw new Exception('Child scorecards data is required.');
            }

            $parentScorecardId = $data['parent_scorecard_id'];
            $childScorecards = $data['child_scorecards'];

            // Validate parent scorecard exists and get its template_id
            $parentScorecard = $this->validateParentScorecard($companyId, $parentScorecardId);
            $templateId = $parentScorecard->template_id;

            $createdChildScorecards = [];
            $ScorecardTemplateAnswersTable = $this->getTable('ScorecardTemplateAnswers', $companyId);

            foreach ($childScorecards as $childData) {
                // Debug logging for child scorecard data
                Log::debug('🔍 DEBUG: Processing child scorecard in createChildScorecards');
                Log::debug('🔍 DEBUG: childData: ' . json_encode($childData));
                Log::debug('🔍 DEBUG: assigned_employee_username: ' . ($childData['assigned_employee_username'] ?? 'NOT_SET'));
                Log::debug('🔍 DEBUG: assigned_employee_username_type: ' . gettype($childData['assigned_employee_username'] ?? null));
                Log::debug('🔍 DEBUG: has_assigned_employee_username: ' . (isset($childData['assigned_employee_username']) ? 'true' : 'false'));

                // Start transaction for each child scorecard (same as addScorecard)
                $connection = $ScorecardTemplateAnswersTable->getConnection();
                $connection->begin();

                try {
                    // Validate required fields (same as addScorecard)
                    if (empty($childData['assigned_employee_username'])) {
                        Log::error('🔍 ERROR: Assigned employee username is empty', [
                            'childData' => $childData,
                            'assigned_employee_username' => $childData['assigned_employee_username'] ?? 'NOT_SET'
                        ]);
                        throw new Exception('Assigned employee username is required for each child scorecard.');
                    }

                    if (empty($childData['answers'])) {
                        throw new Exception('Answers are required for each child scorecard.');
                    }

                    // Validate that the employee exists (by username)
                    $this->validateEmployeeExistsByUsername($companyId, $childData['assigned_employee_username']);

                    // Parse and validate answers (EXACT same as addScorecard)
                    $answers = $this->parseAnswers($childData['answers']);
                    $template = $this->validateTemplate($companyId, $templateId);

                    // Check if scorecard code already exists (same as addScorecard)
                    $this->checkExistingScorecardCode($companyId, $answers);

                    // Generate unique scorecard ID (same as addScorecard)
                    $scorecardUniqueId = $this->generateScorecardUniqueId();

                    // Get current user's username for created_by tracking
                    $authData = $authResult->getData();
                    $currentUsername = $authData->username ?? 'system';

                    // Save answers with assigned employee and parent (modified saveScorecardAnswers)
                    $answerEntity = $this->saveScorecardAnswersWithParent(
                        $companyId, 
                        $scorecardUniqueId, 
                        $templateId, 
                        $answers, 
                        $childData['assigned_employee_username'],
                        $parentScorecardId,
                        $currentUsername
                    );

                    // Commit transaction
                    $connection->commit();

                    $createdChildScorecards[] = [
                        'scorecard_id' => $scorecardUniqueId,
                        'answer_id' => $answerEntity->id,
                        'assigned_employee_username' => $childData['assigned_employee_username']
                    ];

                } catch (Exception $e) {
                    if ($connection->inTransaction()) {
                        $connection->rollback();
                    }
                    throw $e;
                }
            }

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'message' => 'Child scorecards created successfully.',
                    'data' => [
                        'parent_scorecard_id' => $parentScorecardId,
                        'child_scorecards' => $createdChildScorecards,
                        'total_created' => count($createdChildScorecards)
                    ]
                ]));

        } catch (Exception $e) {
            Log::error('Error creating child scorecards: ' . $e->getMessage(), [
                'company_id' => $companyId,
                'data' => $data,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => $e->getMessage(),
                ]));
        }
    }

    /**
     * Validate parent scorecard exists and is accessible
     * @param int $companyId
     * @param int $parentScorecardId
     * @return object
     */
    private function validateParentScorecard($companyId, $parentScorecardId)
    {
        $ScorecardTemplateAnswersTable = $this->getTable('ScorecardTemplateAnswers', $companyId);
        $parentScorecard = $ScorecardTemplateAnswersTable->find()
            ->where([
                'id' => $parentScorecardId,
                'company_id' => $companyId,
                'deleted' => 0
                // Removed parent_scorecard_id IS NULL check to allow creating child scorecards of child scorecards
            ])
            ->first();

        if (!$parentScorecard) {
            throw new Exception('Parent scorecard not found or invalid.');
        }

        return $parentScorecard;
    }



    /**
     * Validate that employee exists by unique_id
     * @param int $companyId
     * @param string $employeeUniqueId
     */
    private function validateEmployeeExists($companyId, $employeeUniqueId)
    {
        $EmployeeTemplatesTable = $this->getTable('EmployeeTemplates', $companyId);
        $employee = $EmployeeTemplatesTable
            ->find()
            ->join([
                'employee_template_answers' => [
                    'table' => 'employee_template_answers',
                    'type' => 'INNER',
                    'conditions' => [
                        'employee_template_answers.company_id = EmployeeTemplates.company_id',
                        'employee_template_answers.deleted' => 0,
                    ]
                ]
            ])
            ->where([
                'EmployeeTemplates.company_id' => $companyId,
                'EmployeeTemplates.deleted' => 0,
                'employee_template_answers.employee_unique_id' => $employeeUniqueId
            ])
            ->first();

        if (!$employee) {
            throw new Exception("Employee with unique ID '{$employeeUniqueId}' not found.");
        }
    }

    /**
     * Validate that employee exists by regular ID
     * @param int $companyId
     * @param int $employeeId
     */
    private function validateEmployeeExistsById($companyId, $employeeId)
    {
        $EmployeeTemplatesTable = $this->getTable('EmployeeTemplates', $companyId);
        $employee = $EmployeeTemplatesTable
            ->find()
            ->join([
                'employee_template_answers' => [
                    'table' => 'employee_template_answers',
                    'type' => 'INNER',
                    'conditions' => [
                        'employee_template_answers.company_id = EmployeeTemplates.company_id',
                        'employee_template_answers.deleted' => 0,
                    ]
                ]
            ])
            ->where([
                'EmployeeTemplates.company_id' => $companyId,
                'EmployeeTemplates.deleted' => 0,
                'employee_template_answers.id' => $employeeId
            ])
            ->first();

        if (!$employee) {
            throw new Exception("Employee with ID '{$employeeId}' not found.");
        }
    }

    /**
     * Generate scorecard unique ID with the same structure as frontend
     * @return string
     */
    private function generateScorecardUniqueId()
    {
        $datePart = date('Ymd'); // YYYYMMDD format
        $randomPart = strtolower(substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(6))), 0, 8));
        return "scid-{$datePart}-{$randomPart}";
    }

    /**
     * Validate that employee exists by username
     * @param int $companyId
     * @param string $username
     */
    private function validateEmployeeExistsByUsername($companyId, $username)
    {
        // Look in the employee_template_answers table in the company database
        $EmployeeTemplateAnswersTable = $this->getTable('EmployeeTemplateAnswers', $companyId);
        $employee = $EmployeeTemplateAnswersTable->find()
            ->where([
                'company_id' => $companyId,
                'username' => $username,
                'deleted' => 0
            ])
            ->first();

        if (!$employee) {
            throw new Exception("Employee with username '{$username}' not found.");
        }
    }



    /**
     * Validate template exists and is valid
     * @param int $companyId
     * @param int $templateId
     * @return object
     */
    private function validateTemplate($companyId, $templateId)
    {
        $ScorecardTemplatesTable = $this->getTable('ScorecardTemplates', $companyId);
        $template = $ScorecardTemplatesTable->find()
            ->where([
                'id' => $templateId,
                'company_id' => $companyId,
                'deleted' => 0
            ])
            ->first();

        if (!$template) {
            throw new Exception('Invalid template ID or template not found.');
        }

        return $template;
    }

    /**
     * Check if scorecard code already exists
     * @param int $companyId
     * @param array $answers
     * @param string|null $excludeScorecardId
     */
    private function checkExistingScorecardCode($companyId, $answers, $excludeScorecardId = null)
    {
        $scorecardCode = $answers['Code'] ?? null;
        if (!$scorecardCode) {
            return; // No code to check
        }

        $ScorecardTemplateAnswersTable = $this->getTable('ScorecardTemplateAnswers', $companyId);
        $existingScorecard = $ScorecardTemplateAnswersTable->find()
            ->where([
                'company_id' => $companyId,
                'deleted' => 0
            ])
            ->all()
            ->toArray();

        foreach ($existingScorecard as $scorecard) {
            // Skip the current scorecard when updating
            if ($excludeScorecardId && $scorecard->scorecard_unique_id === $excludeScorecardId) {
                continue;
            }

            $existingAnswers = $scorecard->answers ?? [];
            if (isset($existingAnswers['Code']) && $existingAnswers['Code'] === $scorecardCode) {
                throw new Exception('Scorecard code already exists. Please use a unique code.');
            }
        }
    }

    /**
     * Get employee unique ID from database using user ID
     * @param int $companyId
     * @param int $userId
     * @return string|null
     */
    private function getEmployeeUniqueIdFromUserId($companyId, $userId)
    {
        try {
            // First get the user's username from the Users table
            $UsersTable = $this->getTable('Users');
            $user = $UsersTable->find()
                ->where(['id' => $userId, 'company_id' => $companyId])
                ->first();
            
            if (!$user) {
                return null;
            }
            
            // Now get the employee unique ID from EmployeeTemplateAnswers table using the username
            $EmployeeTemplateAnswersTable = $this->getTable('EmployeeTemplateAnswers', $companyId);
            $employeeRecord = $EmployeeTemplateAnswersTable->find()
                ->where(['username' => $user->username, 'company_id' => $companyId, 'deleted' => false])
                ->first();
            
            if ($employeeRecord && !empty($employeeRecord->employee_unique_id)) {
                return $employeeRecord->employee_unique_id;
            }
            
            return null;
            
        } catch (Exception $e) {
            Log::error('Error fetching employee unique ID from user ID', [
                'error' => $e->getMessage(),
                'userId' => $userId,
                'companyId' => $companyId
            ]);
            return null;
        }
    }

    /**
     * Save scorecard answers to database
     * @param int $companyId
     * @param string $scorecardUniqueId
     * @param int $templateId
     * @param array $answers
     * @param string $assignedEmployeeUsername
     * @param string $createdBy
     * @return object
     */
    private function saveScorecardAnswers($companyId, $scorecardUniqueId, $templateId, $answers, $assignedEmployeeUsername = null, $createdBy = null)
    {
        $ScorecardTemplateAnswersTable = $this->getTable('ScorecardTemplateAnswers', $companyId);
        
        $answerEntity = $ScorecardTemplateAnswersTable->newEmptyEntity();
        $answerEntity->company_id = $companyId;
        $answerEntity->scorecard_unique_id = $scorecardUniqueId;
        $answerEntity->template_id = $templateId;
        $answerEntity->answers = $answers;
        $answerEntity->assigned_employee_username = $assignedEmployeeUsername;
        $answerEntity->created_by = $createdBy;
        $answerEntity->deleted = 0;

        if (!$ScorecardTemplateAnswersTable->save($answerEntity)) {
            throw new Exception('Failed to save scorecard answers.');
        }

        return $answerEntity;
    }

    /**
     * Save scorecard answers to database with parent scorecard (for child scorecards)
     * @param int $companyId
     * @param string $scorecardUniqueId
     * @param int $templateId
     * @param array $answers
     * @param string $assignedEmployeeUsername
     * @param int $parentScorecardId
     * @param string $createdBy
     * @return object
     */
    private function saveScorecardAnswersWithParent($companyId, $scorecardUniqueId, $templateId, $answers, $assignedEmployeeUsername, $parentScorecardId, $createdBy = null)
    {
        Log::debug('🔍 DEBUG: saveScorecardAnswersWithParent called', [
            'companyId' => $companyId,
            'scorecardUniqueId' => $scorecardUniqueId,
            'templateId' => $templateId,
            'answers' => $answers,
            'assignedEmployeeUsername' => $assignedEmployeeUsername,
            'parentScorecardId' => $parentScorecardId,
            'createdBy' => $createdBy
        ]);

        $ScorecardTemplateAnswersTable = $this->getTable('ScorecardTemplateAnswers', $companyId);
        
        $answerEntity = $ScorecardTemplateAnswersTable->newEmptyEntity();
        $answerEntity->company_id = $companyId;
        $answerEntity->scorecard_unique_id = $scorecardUniqueId;
        $answerEntity->template_id = $templateId;
        $answerEntity->answers = $answers;
        $answerEntity->assigned_employee_username = $assignedEmployeeUsername;
        $answerEntity->parent_scorecard_id = $parentScorecardId;
        $answerEntity->created_by = $createdBy;
        $answerEntity->deleted = 0;

        Log::debug('🔍 DEBUG: Entity before save', [
            'entity' => $answerEntity->toArray()
        ]);

        if (!$ScorecardTemplateAnswersTable->save($answerEntity)) {
            $errors = $answerEntity->getErrors();
            Log::error('🔍 DEBUG: Save failed with errors', [
                'errors' => $errors,
                'entity' => $answerEntity->toArray()
            ]);
            throw new Exception('Failed to save child scorecard answers: ' . json_encode($errors));
        }

        Log::debug('🔍 DEBUG: Save successful', [
            'savedEntity' => $answerEntity->toArray()
        ]);

        return $answerEntity;
    }

    /**
     * Get scorecards assigned to the current user
     * @return \Cake\Http\Response
     */
    public function getMyScorecardsData()
    {
        Configure::write('debug', true);
        $this->request->allowMethod(['get']);

        // Authentication check
        $authResult = $this->Authentication->getResult();
        if (!$authResult || !$authResult->isValid()) {
            return $this->response
                ->withStatus(401)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ]));
        }

        $companyId = $authResult->getData()->company_id;
        $authData = $authResult->getData();
        $currentUsername = $authData->username ?? 'system';

        // Get request parameters
        $page = (int)($this->request->getQuery('page', 1));
        $limit = (int)($this->request->getQuery('limit', 10));
        $search = $this->request->getQuery('search', '');
        $sortField = $this->request->getQuery('sortField', '');
        $sortOrder = $this->request->getQuery('sortOrder', 'asc');

        Log::debug('🔍 DEBUG: getMyScorecardsData - Parameters received', [
            'page' => $page,
            'limit' => $limit,
            'search' => $search,
            'sortField' => $sortField,
            'sortOrder' => $sortOrder,
            'currentUsername' => $currentUsername
        ]);

        try {
            $ScorecardTemplateAnswersTable = $this->getTable('ScorecardTemplateAnswers', $companyId);

            // Build query for scorecards assigned to current user (both parent and child scorecards)
            $query = $ScorecardTemplateAnswersTable->find()
                ->where([
                    'company_id' => $companyId,
                    'deleted' => 0,
                    'assigned_employee_username' => $currentUsername
                    // Removed parent_scorecard_id filter to include both parent and child scorecards
                ]);

            $allScorecardAnswers = $query->all()->toArray();

            if (empty($allScorecardAnswers)) {
                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => true,
                        'data' => [
                            'records' => [],
                            'total' => 0
                        ]
                    ]));
            }

            // Get template structure for field mapping
            $template = $this->getTable('ScorecardTemplates', $companyId)
                ->find()
                ->where(['company_id' => $companyId, 'deleted' => 0])
                ->first();
            
            $templateStructure = [];
            if ($template && !empty($template->structure)) {
                if (is_string($template->structure)) {
                    $templateStructure = json_decode($template->structure, true) ?: [];
                } elseif (is_array($template->structure) || is_object($template->structure)) {
                    $templateStructure = (array)$template->structure;
                }
            }
            
            // Build field ID to label mapping for search
            $fieldIdToLabel = [];
            foreach ($templateStructure as $group) {
                foreach ($group['fields'] as $field) {
                    $fieldIdToLabel[$field['id']] = $field['label'];
                }
            }

            // Process and filter data
            $processedData = [];
            foreach ($allScorecardAnswers as $scorecard) {
                // Properly decode JSON answers from database
                $answers = [];
                if (!empty($scorecard->answers)) {
                    if (is_string($scorecard->answers)) {
                        $answers = json_decode($scorecard->answers, true) ?: [];
                    } elseif (is_array($scorecard->answers)) {
                        $answers = $scorecard->answers;
                    }
                }
                
                // Extract scorecard code for search using field ID
                $scorecardCode = '';
                if (is_array($answers)) {
                    foreach ($answers as $fieldId => $value) {
                        if (isset($fieldIdToLabel[$fieldId]) && $fieldIdToLabel[$fieldId] === 'Code') {
                            $scorecardCode = $value;
                            break;
                        }
                    }
                }
                
                // Apply search filter
                if (!empty($search)) {
                    $searchLower = strtolower($search);
                    $found = false;
                    
                    Log::debug('🔍 DEBUG: Search filter applied', [
                        'search' => $search,
                        'searchLower' => $searchLower,
                        'scorecardCode' => $scorecardCode,
                        'answers' => $answers
                    ]);
                    
                    // Search in scorecard code
                    if (strpos(strtolower($scorecardCode), $searchLower) !== false) {
                        $found = true;
                        Log::debug('🔍 DEBUG: Found match in scorecard code');
                    }
                    
                    // Search in all answer fields
                    if (is_array($answers)) {
                        foreach ($answers as $fieldId => $value) {
                            if (is_string($value) && strpos(strtolower($value), $searchLower) !== false) {
                                $found = true;
                                Log::debug('🔍 DEBUG: Found match in field', [
                                    'fieldId' => $fieldId,
                                    'value' => $value
                                ]);
                                break;
                            }
                        }
                    }
                    
                    if (!$found) {
                        Log::debug('🔍 DEBUG: No match found, skipping record');
                        continue; // Skip this record if search doesn't match
                    }
                }

                $processedRecord = [
                    'scorecard_unique_id' => $scorecard->scorecard_unique_id,
                    'template_id' => $scorecard->template_id,
                    'answer_id' => $scorecard->id,
                    'answers' => $answers,
                    'scorecard_code' => $scorecardCode
                ];
                
                $processedData[] = $processedRecord;
            }

            // Apply sorting
            if (!empty($sortField)) {
                Log::debug('🔍 DEBUG: Applying sorting', [
                    'sortField' => $sortField,
                    'sortOrder' => $sortOrder,
                    'dataCount' => count($processedData)
                ]);

                usort($processedData, function ($a, $b) use ($sortField, $sortOrder) {
                    $valueA = $this->getSortValue($a, $sortField);
                    $valueB = $this->getSortValue($b, $sortField);
                    
                    Log::debug('🔍 DEBUG: Comparing values', [
                        'valueA' => $valueA,
                        'valueB' => $valueB,
                        'sortOrder' => $sortOrder
                    ]);
                    
                    if ($sortOrder === 'desc') {
                        return $valueB <=> $valueA;
                    }
                    return $valueA <=> $valueB;
                });

                Log::debug('🔍 DEBUG: Sorting completed', [
                    'firstRecord' => $processedData[0] ?? null,
                    'lastRecord' => end($processedData) ?: null
                ]);
            }

            // Apply pagination
            $total = count($processedData);
            $offset = ($page - 1) * $limit;
            $paginatedData = array_slice($processedData, $offset, $limit);

            Log::debug('🔍 DEBUG: Pagination applied', [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'offset' => $offset,
                'paginatedCount' => count($paginatedData)
            ]);

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'data' => [
                        'records' => $paginatedData,
                        'total' => $total
                    ]
                ]));

        } catch (\Exception $e) {
            Log::error('Error in getMyScorecardsData: ' . $e->getMessage());
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error fetching my scorecards data: ' . $e->getMessage()
                ]));
        }
    }

    /**
     * Get team scorecards (child scorecards of scorecards assigned to current user)
     * @return \Cake\Http\Response
     */
    public function getMyTeamScorecardsData()
    {
        Configure::write('debug', true);
        $this->request->allowMethod(['get']);

        // Authentication check
        $authResult = $this->Authentication->getResult();
        if (!$authResult || !$authResult->isValid()) {
            return $this->response
                ->withStatus(401)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ]));
        }

        $companyId = $authResult->getData()->company_id;
        $authData = $authResult->getData();
        $currentUsername = $authData->username ?? 'system';

        // Get request parameters
        $page = (int)($this->request->getQuery('page', 1));
        $limit = (int)($this->request->getQuery('limit', 10));
        $search = $this->request->getQuery('search', '');
        $sortField = $this->request->getQuery('sortField', '');
        $sortOrder = $this->request->getQuery('sortOrder', 'asc');

        Log::debug('🔍 DEBUG: getMyTeamScorecardsData - Parameters received', [
            'page' => $page,
            'limit' => $limit,
            'search' => $search,
            'sortField' => $sortField,
            'sortOrder' => $sortOrder,
            'currentUsername' => $currentUsername
        ]);

        try {
            $ScorecardTemplateAnswersTable = $this->getTable('ScorecardTemplateAnswers', $companyId);

            // Get all scorecards assigned to current user (both parent and child scorecards)
            $assignedScorecards = $ScorecardTemplateAnswersTable->find()
                ->where([
                    'company_id' => $companyId,
                    'deleted' => 0,
                    'assigned_employee_username' => $currentUsername
                ])
                ->all()
                ->toArray();

            Log::debug('🔍 DEBUG: getMyTeamScorecardsData - Assigned scorecards found', [
                'count' => count($assignedScorecards),
                'assignedScorecards' => $assignedScorecards
            ]);

            if (empty($assignedScorecards)) {
                Log::debug('🔍 DEBUG: getMyTeamScorecardsData - No assigned scorecards found');
                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => true,
                        'data' => [
                            'records' => [],
                            'total' => 0
                        ]
                    ]));
            }

            // Get IDs of all scorecards assigned to user
            $assignedScorecardIds = array_column($assignedScorecards, 'id');
            Log::debug('🔍 DEBUG: getMyTeamScorecardsData - Assigned scorecard IDs', [
                'assignedScorecardIds' => $assignedScorecardIds
            ]);

            // Recursively find all child scorecards in the hierarchy
            $allChildScorecardIds = $this->getAllChildScorecardIds($ScorecardTemplateAnswersTable, $assignedScorecards);

            Log::debug('🔍 DEBUG: getMyTeamScorecardsData - All child scorecard IDs found', [
                'allChildScorecardIds' => $allChildScorecardIds
            ]);

            if (empty($allChildScorecardIds)) {
                Log::debug('🔍 DEBUG: getMyTeamScorecardsData - No child scorecards found in hierarchy, trying fallback logic');
                
                // Fallback: If no children found with new logic, try the original logic
                // Get only top-level parent scorecards assigned to user
                $parentScorecards = array_filter($assignedScorecards, function($scorecard) {
                    return $scorecard['parent_scorecard_id'] === null;
                });
                
                if (empty($parentScorecards)) {
                    Log::debug('🔍 DEBUG: getMyTeamScorecardsData - No parent scorecards found for fallback');
                    return $this->response
                        ->withType('application/json')
                        ->withStringBody(json_encode([
                            'success' => true,
                            'data' => [
                                'records' => [],
                                'total' => 0
                            ]
                        ]));
                }
                
                $parentScorecardIds = array_column($parentScorecards, 'id');
                Log::debug('🔍 DEBUG: getMyTeamScorecardsData - Using fallback with parent IDs', [
                    'parentScorecardIds' => $parentScorecardIds
                ]);
                
                // Get direct children of parent scorecards only
                $query = $ScorecardTemplateAnswersTable->find()
                    ->where([
                        'company_id' => $companyId,
                        'deleted' => 0,
                        'parent_scorecard_id IN' => $parentScorecardIds
                    ]);
            } else {
                // Use the new hierarchical logic
                $query = $ScorecardTemplateAnswersTable->find()
                    ->where([
                        'company_id' => $companyId,
                        'deleted' => 0,
                        'id IN' => $allChildScorecardIds
                    ]);
            }

            $allScorecardAnswers = $query->all()->toArray();

            if (empty($allScorecardAnswers)) {
                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => true,
                        'data' => [
                            'records' => [],
                            'total' => 0
                        ]
                    ]));
            }

            // Get template structure for field mapping
            $template = $this->getTable('ScorecardTemplates', $companyId)
                ->find()
                ->where(['company_id' => $companyId, 'deleted' => 0])
                ->first();
            
            $templateStructure = [];
            if ($template && !empty($template->structure)) {
                if (is_string($template->structure)) {
                    $templateStructure = json_decode($template->structure, true) ?: [];
                } elseif (is_array($template->structure) || is_object($template->structure)) {
                    $templateStructure = (array)$template->structure;
                }
            }
            
            // Build field ID to label mapping for search
            $fieldIdToLabel = [];
            foreach ($templateStructure as $group) {
                foreach ($group['fields'] as $field) {
                    $fieldIdToLabel[$field['id']] = $field['label'];
                }
            }

            // Process and filter data
            $processedData = [];
            foreach ($allScorecardAnswers as $scorecard) {
                // Properly decode JSON answers from database
                $answers = [];
                if (!empty($scorecard->answers)) {
                    if (is_string($scorecard->answers)) {
                        $answers = json_decode($scorecard->answers, true) ?: [];
                    } elseif (is_array($scorecard->answers)) {
                        $answers = $scorecard->answers;
                    }
                }
                
                // Extract scorecard code for search using field ID
                $scorecardCode = '';
                if (is_array($answers)) {
                    foreach ($answers as $fieldId => $value) {
                        if (isset($fieldIdToLabel[$fieldId]) && $fieldIdToLabel[$fieldId] === 'Code') {
                            $scorecardCode = $value;
                            break;
                        }
                    }
                }
                
                // Apply search filter
                if (!empty($search)) {
                    $searchLower = strtolower($search);
                    $found = false;
                    
                    Log::debug('🔍 DEBUG: Team scorecards search filter applied', [
                        'search' => $search,
                        'searchLower' => $searchLower,
                        'scorecardCode' => $scorecardCode,
                        'answers' => $answers
                    ]);
                    
                    // Search in scorecard code
                    if (strpos(strtolower($scorecardCode), $searchLower) !== false) {
                        $found = true;
                        Log::debug('🔍 DEBUG: Found match in team scorecard code');
                    }
                    
                    // Search in all answer fields
                    if (is_array($answers)) {
                        foreach ($answers as $fieldId => $value) {
                            if (is_string($value) && strpos(strtolower($value), $searchLower) !== false) {
                                $found = true;
                                Log::debug('🔍 DEBUG: Found match in team scorecard field', [
                                    'fieldId' => $fieldId,
                                    'value' => $value
                                ]);
                                break;
                            }
                        }
                    }
                    
                    if (!$found) {
                        Log::debug('🔍 DEBUG: No match found in team scorecard, skipping record');
                        continue; // Skip this record if search doesn't match
                    }
                }

                $processedRecord = [
                    'scorecard_unique_id' => $scorecard->scorecard_unique_id,
                    'template_id' => $scorecard->template_id,
                    'answer_id' => $scorecard->id,
                    'answers' => $answers,
                    'scorecard_code' => $scorecardCode,
                    'parent_scorecard_id' => $scorecard->parent_scorecard_id,
                    'assigned_employee_username' => $scorecard->assigned_employee_username
                ];
                
                $processedData[] = $processedRecord;
            }

            // Apply sorting
            if (!empty($sortField)) {
                Log::debug('🔍 DEBUG: Applying team scorecards sorting', [
                    'sortField' => $sortField,
                    'sortOrder' => $sortOrder,
                    'dataCount' => count($processedData)
                ]);

                usort($processedData, function ($a, $b) use ($sortField, $sortOrder) {
                    $valueA = $this->getSortValue($a, $sortField);
                    $valueB = $this->getSortValue($b, $sortField);
                    
                    Log::debug('🔍 DEBUG: Comparing team scorecard values', [
                        'valueA' => $valueA,
                        'valueB' => $valueB,
                        'sortOrder' => $sortOrder
                    ]);
                    
                    if ($sortOrder === 'desc') {
                        return $valueB <=> $valueA;
                    }
                    return $valueA <=> $valueB;
                });

                Log::debug('🔍 DEBUG: Team scorecards sorting completed', [
                    'firstRecord' => $processedData[0] ?? null,
                    'lastRecord' => end($processedData) ?: null
                ]);
            }

            // Apply pagination
            $total = count($processedData);
            $offset = ($page - 1) * $limit;
            $paginatedData = array_slice($processedData, $offset, $limit);

            Log::debug('🔍 DEBUG: Team scorecards pagination applied', [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'offset' => $offset,
                'paginatedCount' => count($paginatedData)
            ]);

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'data' => [
                        'records' => $paginatedData,
                        'total' => $total
                    ]
                ]));

        } catch (\Exception $e) {
            Log::error('Error in getMyTeamScorecardsData: ' . $e->getMessage());
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error fetching team scorecards data: ' . $e->getMessage()
                ]));
        }
    }

    /**
     * Get scorecard data for view/edit
     * @return \Cake\Http\Response
     */
    public function getScorecardData()
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
                    'message' => 'Unauthorized access'
                ]));
        }

        $data = $this->request->getData();
        $companyId = $authResult->getData()->company_id;
        $scorecardUniqueId = $data['scorecard_unique_id'] ?? null;

        // Validation
        if (empty($scorecardUniqueId)) {
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Missing scorecard unique ID'
                ]));
        }

        try {
            $ScorecardTemplateAnswersTable = $this->getTable('ScorecardTemplateAnswers', $companyId);
            $ScorecardTemplatesTable = $this->getTable('ScorecardTemplates', $companyId);

            // Get scorecard data
            $scorecard = $ScorecardTemplateAnswersTable->find()
                ->where([
                    'company_id' => $companyId,
                    'deleted' => 0,
                    'scorecard_unique_id' => $scorecardUniqueId
                ])
                ->first();

            if (!$scorecard) {
                return $this->response
                    ->withStatus(404)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Scorecard not found'
                    ]));
            }

            // Get template structure
            $template = $ScorecardTemplatesTable->find()
                ->where([
                    'id' => $scorecard->template_id,
                    'company_id' => $companyId,
                    'deleted' => 0
                ])
                ->first();

            if (!$template) {
                return $this->response
                    ->withStatus(404)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Template not found'
                    ]));
            }

            // Parse answers
            $rawAnswers = [];
            if (!empty($scorecard->answers)) {
                if (is_string($scorecard->answers)) {
                    $rawAnswers = json_decode($scorecard->answers, true) ?: [];
                } elseif (is_array($scorecard->answers)) {
                    $rawAnswers = $scorecard->answers;
                }
            }

            Log::debug('🔍 DEBUG: getScorecardData - Raw scorecard answers:', [
                'raw_answers' => $scorecard->answers,
                'parsed_raw_answers' => $rawAnswers
            ]);

            // Convert flat structure to nested structure for frontend compatibility
            $answers = [];
            if (!empty($rawAnswers)) {
                // Find the group ID from template structure
                $templateStructure = [];
                if (is_string($template->structure)) {
                    $templateStructure = json_decode($template->structure, true) ?: [];
                } elseif (is_array($template->structure) || is_object($template->structure)) {
                    $templateStructure = (array)$template->structure;
                }

                // Get the first group ID (assuming single group for scorecards)
                $groupId = null;
                if (!empty($templateStructure) && is_array($templateStructure)) {
                    $firstGroup = reset($templateStructure);
                    if (isset($firstGroup['id'])) {
                        $groupId = $firstGroup['id'];
                    }
                }

                if ($groupId) {
                    $answers[$groupId] = $rawAnswers;
                } else {
                    // Fallback: use the raw answers as-is
                    $answers = $rawAnswers;
                }
            }

            Log::debug('🔍 DEBUG: getScorecardData - Converted answers:', [
                'groupId' => $groupId ?? 'not_found',
                'converted_answers' => $answers
            ]);

            // Parse template structure
            $structure = [];
            if (is_string($template->structure)) {
                $structure = json_decode($template->structure, true) ?: [];
            } elseif (is_array($template->structure) || is_object($template->structure)) {
                $structure = (array)$template->structure;
            }

            $responseData = [
                'success' => true,
                'data' => [
                    'scorecard_unique_id' => $scorecard->scorecard_unique_id,
                    'template_id' => $scorecard->template_id,
                    'answer_id' => $scorecard->id,
                    'answers' => $answers,
                    'structure' => $structure,
                    'assigned_employee_username' => $scorecard->assigned_employee_username,
                    'parent_scorecard_id' => $scorecard->parent_scorecard_id,
                    'created' => $scorecard->created,
                    'modified' => $scorecard->modified
                ]
            ];

            Log::debug('🔍 DEBUG: getScorecardData - Raw scorecard record:', [
                'id' => $scorecard->id,
                'scorecard_unique_id' => $scorecard->scorecard_unique_id,
                'assigned_employee_username' => $scorecard->assigned_employee_username,
                'parent_scorecard_id' => $scorecard->parent_scorecard_id,
                'template_id' => $scorecard->template_id
            ]);
            
            Log::debug('🔍 DEBUG: getScorecardData - Response data:', $responseData);

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode($responseData));

        } catch (\Exception $e) {
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error fetching scorecard data: ' . $e->getMessage()
                ]));
        }
    }

    /**
     * Update scorecard
     * @return \Cake\Http\Response
     */
    public function updateScorecard()
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
                    'message' => 'Unauthorized access'
                ]));
        }

        $data = $this->request->getData();
        $companyId = $authResult->getData()->company_id;
        $answers = $data['answers'] ?? null;
        $templateId = $data['template_id'] ?? null;
        $scorecardUniqueId = $data['scorecard_unique_id'] ?? null;

        $ScorecardTemplateAnswersTable = $this->getTable('ScorecardTemplateAnswers', $companyId);

        // Validation
        if (!$scorecardUniqueId) {
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Missing or invalid scorecard unique id.',
                ]));
        }

        if (!$answers) {
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
                    'message' => 'Invalid or missing template ID.',
                ]));
        }

        try {
            // Start transaction
            $connection = $ScorecardTemplateAnswersTable->getConnection();
            $connection->begin();

            // Check if scorecard exists
            $existing = $ScorecardTemplateAnswersTable->find()
                ->where([
                    'company_id' => $companyId,
                    'deleted' => 0,
                    'scorecard_unique_id' => $scorecardUniqueId,
                ])
                ->first();

            if (!$existing) {
                $connection->rollback();
                return $this->response
                    ->withStatus(404)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Scorecard not found.',
                    ]));
            }

            // Store old answers BEFORE any modifications for audit logging
            $oldAnswers = is_array($existing->answers) ? $existing->answers : (json_decode($existing->answers, true) ?? []);
            
            Log::debug('🔍 DEBUG: updateScorecard - Old answers from database (BEFORE update)', [
                'existing_answers_raw' => $existing->answers,
                'old_answers_decoded' => $oldAnswers
            ]);

            // Parse and validate answers
            $parsedAnswers = $this->parseAnswers($answers);

            // Validate template
            $this->validateTemplate($companyId, $templateId);

            // Check for existing scorecard code (if code field exists)
            $this->checkExistingScorecardCode($companyId, $parsedAnswers, $scorecardUniqueId);

            // Update scorecard
            $existing->answers = json_encode($parsedAnswers);
            $existing->template_id = $templateId;
            $existing->modified = date('Y-m-d H:i:s');

            if (!$ScorecardTemplateAnswersTable->save($existing)) {
                $connection->rollback();
                return $this->response
                    ->withStatus(500)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Failed to update scorecard.',
                    ]));
            }

            // Commit transaction
            $connection->commit();

            // Log audit action
            $userData = AuditHelper::extractUserData($authResult);
            $scorecardName = AuditHelper::extractScorecardCode($parsedAnswers);
            
            Log::debug('🔍 DEBUG: updateScorecard - Audit logging data', [
                'scorecard_unique_id' => $scorecardUniqueId,
                'scorecard_name' => $scorecardName,
                'user_data' => $userData,
                'parsed_answers' => $parsedAnswers,
                'old_answers' => $oldAnswers
            ]);
            
            // Generate field changes for detailed audit logging
            // $oldAnswers is already retrieved above
            
            // Flatten both old and new answers for comparison
            $oldFlatAnswers = $this->flattenAnswers($oldAnswers);
            $newFlatAnswers = $this->flattenAnswers($parsedAnswers);
            
            Log::debug('🔍 DEBUG: updateScorecard - Flattened answers for comparison', [
                'old_flat_answers' => $oldFlatAnswers,
                'new_flat_answers' => $newFlatAnswers
            ]);
            
            // Create field mapping based on the order of fields
            $fieldMapping = [];
            $fieldOrder = ['Scorecard Code', 'Strategies/Tactics', 'Measures', 'Deadline', 'Points', 'Weight (%)'];
            $flatAnswerKeys = array_keys($newFlatAnswers);
            
            foreach ($flatAnswerKeys as $index => $fieldId) {
                if (isset($fieldOrder[$index])) {
                    $fieldMapping[$fieldId] = $fieldOrder[$index];
                } else {
                    $fieldMapping[$fieldId] = 'Field ' . ($index + 1);
                }
            }
            
            Log::debug('🔍 DEBUG: updateScorecard - Field mapping', [
                'field_mapping' => $fieldMapping,
                'flat_answer_keys' => $flatAnswerKeys
            ]);
            
            $fieldChanges = AuditHelper::generateFieldChanges(
                $oldFlatAnswers,
                $newFlatAnswers,
                $fieldMapping
            );
            
            Log::debug('🔍 DEBUG: updateScorecard - Field changes', [
                'field_changes' => $fieldChanges,
                'old_answers' => $oldAnswers
            ]);
            
            AuditHelper::logScorecardAction(
                'UPDATE',
                $scorecardUniqueId,
                $scorecardName,
                $userData,
                $this->request,
                $fieldChanges
            );

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'message' => 'Scorecard updated successfully.',
                    'scorecard_id' => $scorecardUniqueId,
                    'answer_id' => $existing->id
                ]));

        } catch (\Exception $e) {
            $connection->rollback();
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
}
