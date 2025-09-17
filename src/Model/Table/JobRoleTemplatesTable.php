<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * JobRoleTemplates Model
 *
 * @method \App\Model\Entity\JobRoleTemplate newEmptyEntity()
 * @method \App\Model\Entity\JobRoleTemplate newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\JobRoleTemplate> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\JobRoleTemplate get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\JobRoleTemplate findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\JobRoleTemplate patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\JobRoleTemplate> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\JobRoleTemplate|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\JobRoleTemplate saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\JobRoleTemplate>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\JobRoleTemplate>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\JobRoleTemplate>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\JobRoleTemplate> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\JobRoleTemplate>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\JobRoleTemplate>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\JobRoleTemplate>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\JobRoleTemplate> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class JobRoleTemplatesTable extends Table
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

        $this->setTable('job_role_templates');
        $this->setDisplayField('name');
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
        ->requirePresence('company_id', 'create')
        ->notEmptyString('company_id');

        $validator
            ->scalar('name')
            ->maxLength('name', 150)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        $validator
            ->requirePresence('structure', 'create')
            ->notEmptyString('structure');
       
        $validator
            ->boolean('deleted')
            ->notEmptyString('deleted');

        return $validator;
    }
}
