<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * ScorecardTemplateAnswer Entity
 *
 * @property int $id
 * @property int $company_id
 * @property string $scorecard_unique_id
 * @property int $template_id
 * @property array $answers
 * @property string|null $assigned_employee_username
 * @property int|null $parent_scorecard_id
 * @property string|null $created_by
 * @property bool|null $deleted
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 */
class ScorecardTemplateAnswer extends Entity
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
        'company_id' => true,
        'scorecard_unique_id' => true,
        'template_id' => true,
        'answers' => true,
        'assigned_employee_username' => true,
        'parent_scorecard_id' => true,
        'created_by' => true,
        'deleted' => true,
        'created' => true,
        'modified' => true,
    ];
}
