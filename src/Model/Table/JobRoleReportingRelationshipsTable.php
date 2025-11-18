<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * JobRoleReportingRelationships Model
 *
 * @method \App\Model\Entity\JobRoleReportingRelationship newEmptyEntity()
 * @method \App\Model\Entity\JobRoleReportingRelationship newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\JobRoleReportingRelationship> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\JobRoleReportingRelationship get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\JobRoleReportingRelationship findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\JobRoleReportingRelationship patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\JobRoleReportingRelationship> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\JobRoleReportingRelationship|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\JobRoleReportingRelationship saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\JobRoleReportingRelationship>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\JobRoleReportingRelationship>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\JobRoleReportingRelationship>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\JobRoleReportingRelationship> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\JobRoleReportingRelationship>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\JobRoleReportingRelationship>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\JobRoleReportingRelationship>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\JobRoleReportingRelationship> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class JobRoleReportingRelationshipsTable extends Table
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

        $this->setTable('job_role_reporting_relationships');
        $this->setDisplayField('id');
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
            ->scalar('job_role')
            ->maxLength('job_role', 150)
            ->requirePresence('job_role', 'create')
            ->notEmptyString('job_role');

        $validator
            ->scalar('reporting_to')
            ->maxLength('reporting_to', 150)
            ->requirePresence('reporting_to', 'create')
            ->notEmptyString('reporting_to');

        $validator
            ->boolean('deleted')
            ->allowEmptyString('deleted');

        return $validator;
    }
}

