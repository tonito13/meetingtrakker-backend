<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * AuditLogDetails Model
 *
 * @property \App\Model\Table\AuditLogsTable&\Cake\ORM\Association\BelongsTo $AuditLogs
 *
 * @method \App\Model\Entity\AuditLogDetail newEmptyEntity()
 * @method \App\Model\Entity\AuditLogDetail newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\AuditLogDetail[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\AuditLogDetail get($primaryKey, $options = [])
 * @method \App\Model\Entity\AuditLogDetail findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\AuditLogDetail patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\AuditLogDetail[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\AuditLogDetail|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\AuditLogDetail saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\AuditLogDetail[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\AuditLogDetail[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\AuditLogDetail[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\AuditLogDetail[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 */
class AuditLogDetailsTable extends Table
{
    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('audit_log_details');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('AuditLogs', [
            'foreignKey' => 'audit_log_id',
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
            ->uuid('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->uuid('audit_log_id')
            ->requirePresence('audit_log_id', 'create')
            ->notEmptyString('audit_log_id');

        $validator
            ->scalar('field_name')
            ->maxLength('field_name', 255)
            ->requirePresence('field_name', 'create')
            ->notEmptyString('field_name');

        $validator
            ->scalar('field_label')
            ->maxLength('field_label', 255)
            ->allowEmptyString('field_label');

        $validator
            ->scalar('old_value')
            ->allowEmptyString('old_value');

        $validator
            ->scalar('new_value')
            ->allowEmptyString('new_value');

        $validator
            ->scalar('change_type')
            ->maxLength('change_type', 20)
            ->requirePresence('change_type', 'create')
            ->notEmptyString('change_type');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['audit_log_id'], 'AuditLogs'), ['errorField' => 'audit_log_id']);

        return $rules;
    }

    /**
     * Find details by audit log ID
     *
     * @param \Cake\ORM\Query $query
     * @param array $options
     * @return \Cake\ORM\Query
     */
    public function findByAuditLogId(Query $query, array $options): Query
    {
        if (!empty($options['audit_log_id'])) {
            $query->where(['AuditLogDetails.audit_log_id' => $options['audit_log_id']]);
        }

        return $query->orderAsc('AuditLogDetails.field_name');
    }
}
