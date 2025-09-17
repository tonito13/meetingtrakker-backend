<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\Api\ApiController;
use App\Helper\AuditHelper;
use Cake\Core\Configure;
use Exception;
use Cake\Log\Log;

class ScorecardEvaluationsController extends ApiController
{
    public function initialize(): void
    {
        parent::initialize();
    }

    /**
     * Get evaluations for a specific scorecard
     */
    public function getScorecardEvaluations()
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

        $companyId = $authResult->getData()->company_id;
        $scorecardUniqueId = $this->request->getQuery('scorecard_unique_id');

        if (empty($scorecardUniqueId)) {
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Scorecard unique ID is required',
                ]));
        }

        try {
            $evaluationsTable = $this->getTable('ScorecardEvaluations', $companyId);

            $evaluations = $evaluationsTable->find()
                ->where([
                    'scorecard_unique_id' => $scorecardUniqueId,
                    'deleted' => false
                ])
                ->order(['evaluation_date' => 'DESC', 'created' => 'DESC'])
                ->toArray();

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'data' => $evaluations,
                ]));

        } catch (\Exception $e) {
            Log::error('Error fetching scorecard evaluations: ' . $e->getMessage());
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error fetching evaluations: ' . $e->getMessage(),
                ]));
        }
    }

    /**
     * Create a new scorecard evaluation
     */
    public function createScorecardEvaluation()
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

        $companyId = $authResult->getData()->company_id;
        $data = $this->request->getData();
        $evaluatorUsername = $authResult->getData()->username ?? 'system';

        // Validate required fields
        $requiredFields = [
            'scorecard_unique_id',
            'evaluation_date'
        ];

        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return $this->response
                    ->withStatus(400)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => "Field '{$field}' is required",
                    ]));
            }
        }

        try {
            $evaluationsTable = $this->getTable('ScorecardEvaluations', $companyId);

            // Get the assigned employee username from the scorecard
            $scorecardsTable = $this->getTable('ScorecardTemplateAnswers', $companyId);
            $scorecard = $scorecardsTable->find()
                ->where(['scorecard_unique_id' => $data['scorecard_unique_id']])
                ->first();

            if (!$scorecard) {
                return $this->response
                    ->withStatus(404)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Scorecard not found',
                    ]));
            }

            // Create new evaluation using the same pattern as scorecard creation
            $evaluation = $evaluationsTable->newEmptyEntity();
            $evaluation->scorecard_unique_id = $data['scorecard_unique_id'];
            $evaluation->evaluator_username = $evaluatorUsername;
            $evaluation->evaluated_employee_username = $scorecard->assigned_employee_username;
            $evaluation->grade = $data['grade'] ?? null;
            $evaluation->notes = $data['notes'] ?? null;
            $evaluation->evaluation_date = $data['evaluation_date'];
            $evaluation->status = $data['status'] ?? 'draft';
            $evaluation->deleted = false;

            if ($evaluationsTable->save($evaluation)) {
                // Log audit action
                $userData = AuditHelper::extractUserData($authResult);
                // Handle answers - it might be an array or JSON string
                $answers = is_array($scorecard->answers) ? $scorecard->answers : (json_decode($scorecard->answers, true) ?? []);
                $scorecardName = AuditHelper::extractScorecardCode($answers);
                
                AuditHelper::logScorecardAction(
                    'EVALUATE',
                    $data['scorecard_unique_id'],
                    $scorecardName,
                    $userData,
                    $this->request
                );
                
                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => true,
                        'message' => 'Evaluation created successfully',
                        'data' => $evaluation,
                    ]));
            } else {
                return $this->response
                    ->withStatus(422)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Failed to create evaluation',
                        'errors' => $evaluation->getErrors(),
                    ]));
            }

        } catch (\Exception $e) {
            Log::error('Error creating scorecard evaluation: ' . $e->getMessage());
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error creating evaluation: ' . $e->getMessage(),
                ]));
        }
    }

    /**
     * Delete a scorecard evaluation (soft delete) - API endpoint
     */
    public function deleteScorecardEvaluation()
    {
        $this->request->allowMethod(['delete']);

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
        $evaluationId = $data['id'] ?? null;

        if (empty($evaluationId)) {
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Evaluation ID is required',
                ]));
        }

        try {
            $evaluationsTable = $this->getTable('ScorecardEvaluations', $companyId);

            $evaluation = $evaluationsTable->find()
                ->where([
                    'id' => $evaluationId,
                    'deleted' => false
                ])
                ->first();

            if (!$evaluation) {
                return $this->response
                    ->withStatus(404)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Evaluation not found',
                    ]));
            }

            // Soft delete using the same pattern as scorecard deletion
            $evaluation->deleted = true;
            if ($evaluationsTable->save($evaluation)) {
                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => true,
                        'message' => 'Evaluation deleted successfully',
                    ]));
            } else {
                return $this->response
                    ->withStatus(500)
                    ->withType('application/json')
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Failed to delete evaluation',
                    ]));
            }

        } catch (\Exception $e) {
            Log::error('Error deleting scorecard evaluation: ' . $e->getMessage());
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error deleting evaluation: ' . $e->getMessage(),
                ]));
        }
    }

    /**
     * Get evaluation statistics for a scorecard
     */
    public function getEvaluationStats()
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

        $companyId = $authResult->getData()->company_id;
        $scorecardUniqueId = $this->request->getQuery('scorecard_unique_id');

        if (empty($scorecardUniqueId)) {
            return $this->response
                ->withStatus(400)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Scorecard unique ID is required',
                ]));
        }

        try {
            $evaluationsTable = $this->getTable('ScorecardEvaluations', $companyId);

            $evaluations = $evaluationsTable->find()
                ->where([
                    'scorecard_unique_id' => $scorecardUniqueId,
                    'deleted' => false
                ])
                ->toArray();

            $stats = [
                'total_evaluations' => count($evaluations),
                'completed_evaluations' => count(array_filter($evaluations, fn($e) => $e->status === 'submitted' || $e->status === 'approved')),
                'draft_evaluations' => count(array_filter($evaluations, fn($e) => $e->status === 'draft')),
                'average_grade' => null,
                'period_breakdown' => []
            ];

            // Calculate average grade
            $grades = array_filter(array_column($evaluations, 'grade'), fn($g) => $g !== null);
            if (!empty($grades)) {
                $stats['average_grade'] = round(array_sum($grades) / count($grades), 2);
            }

            // Remove period breakdown since we no longer use periods
            $stats['period_breakdown'] = [];

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'data' => $stats,
                ]));

        } catch (\Exception $e) {
            Log::error('Error fetching evaluation stats: ' . $e->getMessage());
            return $this->response
                ->withStatus(500)
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => false,
                    'message' => 'Error fetching evaluation statistics: ' . $e->getMessage(),
                ]));
        }
    }
}