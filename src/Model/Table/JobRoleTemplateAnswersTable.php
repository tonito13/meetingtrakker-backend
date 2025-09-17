<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * JobRoleTemplateAnswers Model
 *
 * @method \App\Model\Entity\JobRoleTemplateAnswer newEmptyEntity()
 * @method \App\Model\Entity\JobRoleTemplateAnswer newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\JobRoleTemplateAnswer> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\JobRoleTemplateAnswer get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\JobRoleTemplateAnswer findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\JobRoleTemplateAnswer patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\JobRoleTemplateAnswer> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\JobRoleTemplateAnswer|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\JobRoleTemplateAnswer saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\JobRoleTemplateAnswer>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\JobRoleTemplateAnswer>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\JobRoleTemplateAnswer>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\JobRoleTemplateAnswer> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\JobRoleTemplateAnswer>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\JobRoleTemplateAnswer>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\JobRoleTemplateAnswer>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\JobRoleTemplateAnswer> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class JobRoleTemplateAnswersTable extends Table
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

        $this->setTable('job_role_template_answers');
        $this->setDisplayField('job_role_unique_id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
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
            ->requirePresence('company_id', 'create')
            ->notEmptyString('company_id');

        $validator
            ->scalar('job_role_unique_id')
            ->maxLength('job_role_unique_id', 150)
            ->requirePresence('job_role_unique_id', 'create')
            ->notEmptyString('job_role_unique_id');

        $validator
            ->integer('template_id')
            ->requirePresence('template_id', 'create')
            ->notEmptyString('template_id');

        $validator
            ->requirePresence('answers', 'create')
            ->notEmptyString('answers');

        $validator
            ->boolean('deleted')
            ->allowEmptyString('deleted');

        return $validator;
    }
}
