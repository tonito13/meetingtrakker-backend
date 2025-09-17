<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * EmployeeAnswerFiles Model
 *
 * @property \App\Model\Table\EmployeeTemplateAnswersTable&\Cake\ORM\Association\BelongsTo $Answers
 *
 * @method \App\Model\Entity\EmployeeAnswerFile newEmptyEntity()
 * @method \App\Model\Entity\EmployeeAnswerFile newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\EmployeeAnswerFile> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\EmployeeAnswerFile get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\EmployeeAnswerFile findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\EmployeeAnswerFile patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\EmployeeAnswerFile> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\EmployeeAnswerFile|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\EmployeeAnswerFile saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\EmployeeAnswerFile>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\EmployeeAnswerFile>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\EmployeeAnswerFile>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\EmployeeAnswerFile> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\EmployeeAnswerFile>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\EmployeeAnswerFile>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\EmployeeAnswerFile>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\EmployeeAnswerFile> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class EmployeeAnswerFilesTable extends Table
{
    /**
     * Initialize method
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('employee_answer_files');
        $this->setDisplayField('file_name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Answers', [
            'foreignKey' => 'answer_id',
            'className' => 'EmployeeTemplateAnswers',
            'joinType' => 'INNER',
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
         $validator
            ->integer('company_id')
            ->notEmptyString('company_id');

        $validator
            ->integer('answer_id')
            ->notEmptyString('answer_id');

        $validator
            ->scalar('file_name')
            ->maxLength('file_name', 255)
            ->requirePresence('file_name', 'create')
            ->notEmptyString('file_name');

        $validator
            ->scalar('file_path')
            ->requirePresence('file_path', 'create')
            ->notEmptyString('file_path');

        $validator
            ->scalar('file_type')
            ->maxLength('file_type', 50)
            ->requirePresence('file_type', 'create')
            ->notEmptyString('file_type');

        $validator
            ->requirePresence('file_size', 'create')
            ->notEmptyString('file_size');
        
        $validator
            ->integer('group_id')
            ->notEmptyString('group_id', 'create');

        $validator
            ->integer('field_id')
            ->notEmptyString('field_id', 'create');


        $validator
            ->boolean('deleted')
            ->allowEmptyString('deleted');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    // public function buildRules(RulesChecker $rules): RulesChecker
    // {
    //     $rules->add($rules->existsIn(['answer_id'], 'Answers'), ['errorField' => 'answer_id']);

    //     return $rules;
    // }
}
