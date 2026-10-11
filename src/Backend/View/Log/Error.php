<?php
/*
 * Fusio - Self-Hosted API Management for Builders.
 * For the current version and information visit <https://www.fusio-project.org/>
 *
 * Copyright (c) Christoph Kappestein <christoph.kappestein@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Fusio\Impl\Backend\View\Log;

use Fusio\Engine\ContextInterface;
use Fusio\Impl\Backend\Filter\QueryFilter;
use Fusio\Impl\Service;
use Fusio\Impl\Table;
use Fusio\Model;
use PSX\Nested\Builder;
use PSX\Sql\Condition;
use PSX\Sql\ViewAbstract;

/**
 * Error
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org
 */
class Error extends ViewAbstract
{
    public function getCollection(QueryFilter $filter, ContextInterface $context): mixed
    {
        $startIndex = $filter->getStartIndex();
        $count = $filter->getCount();

        $condition = $filter->getCondition($this->getTable(Table\Log\Error::class), [QueryFilter::COLUMN_SEARCH => Table\Generated\LogErrorColumn::MESSAGE], 'error');
        $condition->equals('log.' . Table\Generated\LogTable::COLUMN_TENANT_ID, $context->getTenantId());
        $condition->equals('log.' . Table\Generated\LogTable::COLUMN_CATEGORY_ID, $context->getUser()->getCategoryId());

        if ($this->storage instanceof \PSX\Sql\MongoDB\MongoStorage) {
            $errFilter = \PSX\Sql\MongoDB\ConditionTranslator::translateForAlias($condition, 'error');
            $logFilter = \PSX\Sql\MongoDB\ConditionTranslator::translateForAlias($condition, 'log', true);
            $pipeline = [
                ['$match' => $errFilter],
                ['$lookup' => ['from' => 'fusio_log', 'localField' => 'log_id', 'foreignField' => 'id', 'as' => 'log']],
                ['$unwind' => '$log'],
            ];
            if (!empty($logFilter)) {
                $pipeline[] = ['$match' => $logFilter];
            }
            $total = $this->storage->aggregate('fusio_log_error', [...$pipeline, ['$count' => 'cnt']]);
            $entries = $this->storage->aggregate('fusio_log_error', [...$pipeline, ['$sort' => ['id' => -1]], ['$skip' => $startIndex], ['$limit' => $count]]);
            $result = [
                'kind' => \Fusio\Impl\Service\TypeSystem\KindBuilder::build(Model\Backend\LogErrorCollection::class),
                'totalResults' => (int) ($total[0]['cnt'] ?? 0),
                'startIndex' => $startIndex,
                'itemsPerPage' => $count,
                'entry' => [],
            ];
            foreach ($entries as $row) {
                $result['entry'][] = [
                    'kind' => \Fusio\Impl\Service\TypeSystem\KindBuilder::build(Model\Backend\LogError::class),
                    'id' => (int) ($row['id'] ?? 0),
                    'logId' => (int) ($row['log_id'] ?? 0),
                    'message' => $row['message'] ?? null,
                    'file' => $row['file'] ?? null,
                    'line' => (int) ($row['line'] ?? 0),
                    'insertDate' => isset($row['insert_date']) ? (new \DateTime((string) $row['insert_date']))->format(\DateTime::RFC3339) : null,
                ];
            }
            return $result;
        }

        $queryBuilder = $this->connection->createQueryBuilder()
            ->select([
                'error.' . Table\Generated\LogErrorTable::COLUMN_ID,
                'error.' . Table\Generated\LogErrorTable::COLUMN_LOG_ID,
                'error.' . Table\Generated\LogErrorTable::COLUMN_MESSAGE,
                'error.' . Table\Generated\LogErrorTable::COLUMN_FILE,
                'error.' . Table\Generated\LogErrorTable::COLUMN_LINE,
                'error.' . Table\Generated\LogErrorTable::COLUMN_INSERT_DATE,
            ])
            ->from('fusio_log_error', 'error')
            ->innerJoin('error', 'fusio_log', 'log', 'error.' . Table\Generated\LogErrorTable::COLUMN_LOG_ID . ' = log.' . Table\Generated\LogTable::COLUMN_ID)
            ->orderBy('error.' . Table\Generated\LogErrorTable::COLUMN_ID, 'DESC')
            ->where($condition->getExpression($this->connection->getDatabasePlatform()))
            ->setParameters($condition->getValues())
            ->setFirstResult($startIndex)
            ->setMaxResults($count);

        $countBuilder = $this->connection->createQueryBuilder()
            ->select(['COUNT(*) AS cnt'])
            ->from('fusio_log_error', 'error')
            ->innerJoin('error', 'fusio_log', 'log', 'error.' . Table\Generated\LogErrorTable::COLUMN_LOG_ID . ' = log.' . Table\Generated\LogTable::COLUMN_ID)
            ->where($condition->getExpression($this->connection->getDatabasePlatform()))
            ->setParameters($condition->getValues());

        $builder = new Builder($this->connection);

        $definition = [
            'kind' => $builder->fieldValue(Service\TypeSystem\KindBuilder::build(Model\Backend\LogErrorCollection::class)),
            'totalResults' => $builder->doValue($countBuilder->getSQL(), $countBuilder->getParameters(), $builder->fieldInteger('cnt')),
            'startIndex' => $startIndex,
            'itemsPerPage' => $count,
            'entry' => $builder->doCollection($queryBuilder->getSQL(), $queryBuilder->getParameters(), [
                'kind' => $builder->fieldValue(Service\TypeSystem\KindBuilder::build(Model\Backend\LogError::class)),
                'id' => $builder->fieldInteger(Table\Generated\LogErrorTable::COLUMN_ID),
                'logId' => $builder->fieldInteger(Table\Generated\LogErrorTable::COLUMN_LOG_ID),
                'message' => Table\Generated\LogErrorTable::COLUMN_MESSAGE,
                'file' => Table\Generated\LogErrorTable::COLUMN_FILE,
                'line' => Table\Generated\LogErrorTable::COLUMN_LINE,
                'insertDate' => $builder->fieldDateTime(Table\Generated\LogErrorTable::COLUMN_INSERT_DATE),
            ]),
        ];

        return $builder->build($definition);
    }

    public function getEntity(int $id, ContextInterface $context): mixed
    {
        $condition = Condition::withAnd();
        $condition->equals('error.' . Table\Generated\LogErrorTable::COLUMN_ID, $id);
        $condition->equals('log.' . Table\Generated\LogTable::COLUMN_TENANT_ID, $context->getTenantId());
        $condition->equals('log.' . Table\Generated\LogTable::COLUMN_CATEGORY_ID, $context->getUser()->getCategoryId());

        if ($this->storage instanceof \PSX\Sql\MongoDB\MongoStorage) {
            $pipeline = [
                ['$match' => \PSX\Sql\MongoDB\ConditionTranslator::translateForAlias($condition, 'error')],
                ['$lookup' => ['from' => 'fusio_log', 'localField' => 'log_id', 'foreignField' => 'id', 'as' => 'log']],
                ['$unwind' => '$log'],
                ['$match' => \PSX\Sql\MongoDB\ConditionTranslator::translateForAlias($condition, 'log', true) ?? ['id' => -1]],
                ['$limit' => 1],
            ];
            $rows = $this->storage->aggregate('fusio_log_error', $pipeline);
            $row = $rows[0] ?? null;
            if ($row === null) {
                return null;
            }
            return [
                'kind' => \Fusio\Impl\Service\TypeSystem\KindBuilder::build(Model\Backend\LogError::class),
                'id' => (int) ($row['id'] ?? 0),
                'logId' => (int) ($row['log_id'] ?? 0),
                'message' => $row['message'] ?? null,
                'trace' => $row['trace'] ?? null,
                'file' => $row['file'] ?? null,
                'line' => (int) ($row['line'] ?? 0),
                'insertDate' => isset($row['insert_date']) ? (new \DateTime((string) $row['insert_date']))->format(\DateTime::RFC3339) : null,
            ];
        }

        $queryBuilder = $this->connection->createQueryBuilder()
            ->select([
                'error.' . Table\Generated\LogErrorTable::COLUMN_ID,
                'error.' . Table\Generated\LogErrorTable::COLUMN_LOG_ID,
                'error.' . Table\Generated\LogErrorTable::COLUMN_MESSAGE,
                'error.' . Table\Generated\LogErrorTable::COLUMN_TRACE,
                'error.' . Table\Generated\LogErrorTable::COLUMN_FILE,
                'error.' . Table\Generated\LogErrorTable::COLUMN_LINE,
                'error.' . Table\Generated\LogErrorTable::COLUMN_INSERT_DATE,
            ])
            ->from('fusio_log_error', 'error')
            ->innerJoin('error', 'fusio_log', 'log', 'error.' . Table\Generated\LogErrorTable::COLUMN_LOG_ID . ' = log.' . Table\Generated\LogTable::COLUMN_ID)
            ->where($condition->getExpression($this->connection->getDatabasePlatform()))
            ->setParameters($condition->getValues());

        $builder = new Builder($this->connection);

        $definition = $builder->doEntity($queryBuilder->getSQL(), $queryBuilder->getParameters(), [
            'kind' => $builder->fieldValue(Service\TypeSystem\KindBuilder::build(Model\Backend\LogError::class)),
            'id' => $builder->fieldInteger(Table\Generated\LogErrorTable::COLUMN_ID),
            'logId' => $builder->fieldInteger(Table\Generated\LogErrorTable::COLUMN_LOG_ID),
            'message' => Table\Generated\LogErrorTable::COLUMN_MESSAGE,
            'trace' => Table\Generated\LogErrorTable::COLUMN_TRACE,
            'file' => Table\Generated\LogErrorTable::COLUMN_FILE,
            'line' => Table\Generated\LogErrorTable::COLUMN_LINE,
            'insertDate' => $builder->fieldDateTime(Table\Generated\LogErrorTable::COLUMN_INSERT_DATE),
        ]);

        return $builder->build($definition);
    }
}
