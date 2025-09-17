<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * EmployeeAnswerFile Entity
 *
 * @property int $id
 * @property int $answer_id
 * @property string $file_name
 * @property string $file_path
 * @property string $file_type
 * @property int $file_size
 * @property bool|null $deleted
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 *
 * @property \App\Model\Entity\EmployeeTemplateAnswer $answer
 */
class EmployeeAnswerFile extends Entity
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
        'answer_id' => true,
        'file_name' => true,
        'file_path' => true,
        'file_type' => true,
        'file_size' => true,
        'group_id' => true,
        'field_id' => true,
        'company_id' => true,
        'deleted' => true,
        'created' => true,
        'modified' => true,
        'answer' => true,
    ];
}
