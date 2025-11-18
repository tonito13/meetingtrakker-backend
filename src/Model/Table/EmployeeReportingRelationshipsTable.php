<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * EmployeeReportingRelationships Model
 *
 * @method \App\Model\Entity\EmployeeReportingRelationship newEmptyEntity()
 * @method \App\Model\Entity\EmployeeReportingRelationship newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\EmployeeReportingRelationship> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\EmployeeReportingRelationship get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\EmployeeReportingRelationship findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\EmployeeReportingRelationship patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\EmployeeReportingRelationship> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\EmployeeReportingRelationship|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\EmployeeReportingRelationship saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\EmployeeReportingRelationship>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\EmployeeReportingRelationship>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\EmployeeReportingRelationship>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\EmployeeReportingRelationship> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\EmployeeReportingRelationship>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\EmployeeReportingRelationship>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\EmployeeReportingRelationship>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\EmployeeReportingRelationship> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class EmployeeReportingRelationshipsTable extends Table
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

        $this->setTable('employee_reporting_relationships');
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
            ->scalar('employee_unique_id')
            ->maxLength('employee_unique_id', 150)
            ->requirePresence('employee_unique_id', 'create')
            ->notEmptyString('employee_unique_id');

        $validator
            ->scalar('report_to_employee_unique_id')
            ->maxLength('report_to_employee_unique_id', 150)
            ->allowEmptyString('report_to_employee_unique_id');

        $validator
            ->scalar('employee_first_name')
            ->maxLength('employee_first_name', 150)
            ->requirePresence('employee_first_name', 'create')
            ->notEmptyString('employee_first_name');

        $validator
            ->scalar('employee_last_name')
            ->maxLength('employee_last_name', 150)
            ->requirePresence('employee_last_name', 'create')
            ->notEmptyString('employee_last_name');

        $validator
            ->scalar('reporting_manager_first_name')
            ->maxLength('reporting_manager_first_name', 150)
            ->allowEmptyString('reporting_manager_first_name');

        $validator
            ->scalar('reporting_manager_last_name')
            ->maxLength('reporting_manager_last_name', 150)
            ->allowEmptyString('reporting_manager_last_name');

        $validator
            ->date('start_date')
            ->allowEmptyDate('start_date');

        $validator
            ->date('end_date')
            ->allowEmptyDate('end_date');

        $validator
            ->scalar('created_by')
            ->maxLength('created_by', 150)
            ->requirePresence('created_by', 'create')
            ->notEmptyString('created_by');

        $validator
            ->boolean('deleted')
            ->allowEmptyString('deleted');

        return $validator;
    }
}

