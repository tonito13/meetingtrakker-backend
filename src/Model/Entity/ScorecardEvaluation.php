<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * ScorecardEvaluation Entity
 *
 * @property int $id
 * @property string $scorecard_unique_id
 * @property string $evaluator_username
 * @property string $evaluated_employee_username
 * @property float|null $grade
 * @property string|null $notes
 * @property \Cake\I18n\Date $evaluation_date
 * @property string $status
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 * @property bool $deleted
 */
class ScorecardEvaluation extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'scorecard_unique_id' => true,
        'evaluator_username' => true,
        'evaluated_employee_username' => true,
        'grade' => true,
        'notes' => true,
        'evaluation_date' => true,
        'status' => true,
        'created' => true,
        'modified' => true,
        'deleted' => true,
    ];

    /**
     * Get the grade as a percentage
     *
     * @return string|null
     */
    protected function _getGradePercentage(): ?string
    {
        if ($this->grade === null) {
            return null;
        }
        
        return number_format($this->grade, 1) . '%';
    }

    /**
     * Get the status display text
     *
     * @return string
     */
    protected function _getStatusDisplay(): string
    {
        $statusMap = [
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'approved' => 'Approved',
            'rejected' => 'Rejected'
        ];

        return $statusMap[$this->status] ?? ucfirst($this->status);
    }

    /**
     * Check if the evaluation is completed
     *
     * @return bool
     */
    protected function _getIsCompleted(): bool
    {
        return $this->status === 'submitted' || $this->status === 'approved';
    }
}
