<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * JobRoleTemplateAnswer Entity
 *
 * @property int $id
 * @property int $company_id
 * @property string $job_role_unique_id
 * @property int $template_id
 * @property array $answers
 * @property bool|null $deleted
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 */
class JobRoleTemplateAnswer extends Entity
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
        'job_role_unique_id' => true,
        'template_id' => true,
        'answers' => true,
        'deleted' => true,
        'created' => true,
        'modified' => true,
    ];
}
