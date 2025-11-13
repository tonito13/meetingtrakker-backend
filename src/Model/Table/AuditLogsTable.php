<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * AuditLogs Model
 *
 * @property \App\Model\Table\AuditLogDetailsTable&\Cake\ORM\Association\HasMany $AuditLogDetails
 *
 * @method \App\Model\Entity\AuditLog newEmptyEntity()
 * @method \App\Model\Entity\AuditLog newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\AuditLog[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\AuditLog get($primaryKey, $options = [])
 * @method \App\Model\Entity\AuditLog findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\AuditLog patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\AuditLog[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\AuditLog|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\AuditLog saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\AuditLog[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\AuditLog[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\AuditLog[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\AuditLog[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 */
class AuditLogsTable extends Table
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

        $this->setTable('audit_logs');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->hasMany('AuditLogDetails', [
            'foreignKey' => 'audit_log_id',
            'dependent' => true,
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
            ->scalar('company_id')
            ->maxLength('company_id', 255)
            ->requirePresence('company_id', 'create')
            ->notEmptyString('company_id');

        $validator
            ->integer('user_id')
            ->requirePresence('user_id', 'create')
            ->notEmptyString('user_id');

        $validator
            ->scalar('username')
            ->maxLength('username', 255)
            ->requirePresence('username', 'create')
            ->notEmptyString('username');

        $validator
            ->scalar('action')
            ->maxLength('action', 100)
            ->requirePresence('action', 'create')
            ->notEmptyString('action');

        $validator
            ->scalar('entity_type')
            ->maxLength('entity_type', 100)
            ->requirePresence('entity_type', 'create')
            ->notEmptyString('entity_type');

        $validator
            ->scalar('entity_id')
            ->maxLength('entity_id', 255)
            ->allowEmptyString('entity_id');

        $validator
            ->scalar('entity_name')
            ->maxLength('entity_name', 500)
            ->allowEmptyString('entity_name');

        $validator
            ->scalar('description')
            ->allowEmptyString('description');

        $validator
            ->scalar('ip_address')
            ->maxLength('ip_address', 45)
            ->allowEmptyString('ip_address');

        $validator
            ->scalar('user_agent')
            ->allowEmptyString('user_agent');

        $validator
            ->scalar('status')
            ->maxLength('status', 20)
            ->notEmptyString('status');

        $validator
            ->scalar('error_message')
            ->allowEmptyString('error_message');

        return $validator;
    }

    /**
     * Find audit logs with filtering and pagination
     *
     * @param \Cake\ORM\Query $query
     * @param array $options
     * @return \Cake\ORM\Query
     */
    public function findFiltered(Query $query, array $options): Query
    {
        // Note: contain() removed to avoid association connection issues
        // Details will be loaded separately if needed

        // Filter by company
        if (!empty($options['company_id'])) {
            $query->where(['AuditLogs.company_id' => $options['company_id']]);
        }

        // Filter by user
        if (!empty($options['user_id'])) {
            $query->where(['AuditLogs.user_id' => $options['user_id']]);
        }

        // Filter by action
        if (!empty($options['action'])) {
            $query->where(['AuditLogs.action' => $options['action']]);
        }

        // Filter by entity type
        if (!empty($options['entity_type'])) {
            $query->where(['AuditLogs.entity_type' => $options['entity_type']]);
        }

        // Filter by status
        if (!empty($options['status'])) {
            $query->where(['AuditLogs.status' => $options['status']]);
        }

        // Filter by date range
        if (!empty($options['date_from'])) {
            $query->where(['AuditLogs.created >=' => $options['date_from']]);
        }

        if (!empty($options['date_to'])) {
            $query->where(['AuditLogs.created <=' => $options['date_to']]);
        }

        // Search in description and entity_name
        if (!empty($options['search'])) {
            $searchTerm = '%' . $options['search'] . '%';
            $query->where([
                'OR' => [
                    ['AuditLogs.description LIKE' => $searchTerm],
                    ['AuditLogs.entity_name LIKE' => $searchTerm],
                    ['AuditLogs.username LIKE' => $searchTerm],
                ]
            ]);
        }

        // Order by created date (newest first)
        $query->orderDesc('AuditLogs.created');

        return $query;
    }

    /**
     * Get audit statistics
     *
     * @param string $companyId
     * @param array $options
     * @return array
     */
    public function getAuditStats(string $companyId, array $options = []): array
    {
        $query = $this->find()
            ->where(['company_id' => $companyId]);

        // Apply date filters
        if (!empty($options['date_from'])) {
            $query->where(['created >=' => $options['date_from']]);
        }

        if (!empty($options['date_to'])) {
            $query->where(['created <=' => $options['date_to']]);
        }

        $stats = [];

        // Total actions
        $stats['total_actions'] = $query->count();

        // Actions by type
        $stats['actions_by_type'] = $query
            ->select(['action', 'count' => $query->func()->count('*')])
            ->group(['action'])
            ->toArray();

        // Actions by entity type
        $stats['actions_by_entity'] = $query
            ->select(['entity_type', 'count' => $query->func()->count('*')])
            ->group(['entity_type'])
            ->toArray();

        // Actions by user
        $stats['actions_by_user'] = $query
            ->select(['username', 'count' => $query->func()->count('*')])
            ->group(['username'])
            ->orderDesc('count')
            ->limit(10)
            ->toArray();

        // Actions by status
        $stats['actions_by_status'] = $query
            ->select(['status', 'count' => $query->func()->count('*')])
            ->group(['status'])
            ->toArray();

        // Recent activity (last 7 days)
        $recentQuery = $this->find()
            ->where([
                'company_id' => $companyId,
                'created >=' => date('Y-m-d H:i:s', strtotime('-7 days'))
            ]);

        $stats['recent_activity'] = $recentQuery
            ->select(['date' => 'DATE(created)', 'count' => $recentQuery->func()->count('*')])
            ->group(['DATE(created)'])
            ->orderAsc('date')
            ->toArray();

        return $stats;
    }
}
