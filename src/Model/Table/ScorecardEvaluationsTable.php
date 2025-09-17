<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * ScorecardEvaluations Model
 *
 * @method \App\Model\Entity\ScorecardEvaluation newEmptyEntity()
 * @method \App\Model\Entity\ScorecardEvaluation newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\ScorecardEvaluation> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\ScorecardEvaluation get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\ScorecardEvaluation findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\ScorecardEvaluation patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\ScorecardEvaluation> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\ScorecardEvaluation|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\ScorecardEvaluation saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\ScorecardEvaluation>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\ScorecardEvaluation>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\ScorecardEvaluation>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\ScorecardEvaluation> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\ScorecardEvaluation>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\ScorecardEvaluation>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\ScorecardEvaluation>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\ScorecardEvaluation> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class ScorecardEvaluationsTable extends Table
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

        $this->setTable('scorecard_evaluations');
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
            ->integer('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->scalar('scorecard_unique_id')
            ->maxLength('scorecard_unique_id', 255)
            ->requirePresence('scorecard_unique_id', 'create')
            ->notEmptyString('scorecard_unique_id');

        $validator
            ->scalar('evaluator_username')
            ->maxLength('evaluator_username', 255)
            ->requirePresence('evaluator_username', 'create')
            ->notEmptyString('evaluator_username');

        $validator
            ->scalar('evaluated_employee_username')
            ->maxLength('evaluated_employee_username', 255)
            ->requirePresence('evaluated_employee_username', 'create')
            ->notEmptyString('evaluated_employee_username');


        $validator
            ->decimal('grade')
            ->allowEmptyString('grade')
            ->range('grade', [0, 100], 'Grade must be between 0 and 100');

        $validator
            ->scalar('notes')
            ->allowEmptyString('notes');

        $validator
            ->date('evaluation_date')
            ->requirePresence('evaluation_date', 'create')
            ->notEmptyDate('evaluation_date');

        $validator
            ->scalar('status')
            ->maxLength('status', 20)
            ->notEmptyString('status')
            ->inList('status', ['draft', 'submitted', 'approved', 'rejected']);

        $validator
            ->boolean('deleted')
            ->notEmptyString('deleted');

        return $validator;
    }

    /**
     * Find evaluations for a specific scorecard
     *
     * @param \Cake\ORM\Query\SelectQuery $query
     * @param array $options
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findForScorecard(SelectQuery $query, array $options): SelectQuery
    {
        return $query->where([
            'scorecard_unique_id' => $options['scorecard_unique_id'],
            'deleted' => false
        ]);
    }

    /**
     * Find evaluations by evaluator
     *
     * @param \Cake\ORM\Query\SelectQuery $query
     * @param array $options
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findByEvaluator(SelectQuery $query, array $options): SelectQuery
    {
        return $query->where([
            'evaluator_username' => $options['evaluator_username'],
            'deleted' => false
        ]);
    }

    /**
     * Find evaluations by employee
     *
     * @param \Cake\ORM\Query\SelectQuery $query
     * @param array $options
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findByEmployee(SelectQuery $query, array $options): SelectQuery
    {
        return $query->where([
            'evaluated_employee_username' => $options['employee_username'],
            'deleted' => false
        ]);
    }

    /**
     * Check if evaluation exists for specific criteria
     *
     * @param string $scorecardUniqueId
     * @param string $evaluatorUsername
     * @param string $evaluatedEmployeeUsername
     * @return bool
     */
    public function evaluationExists(
        string $scorecardUniqueId,
        string $evaluatorUsername,
        string $evaluatedEmployeeUsername
    ): bool {
        return $this->exists([
            'scorecard_unique_id' => $scorecardUniqueId,
            'evaluator_username' => $evaluatorUsername,
            'evaluated_employee_username' => $evaluatedEmployeeUsername,
            'deleted' => false
        ]);
    }
}
