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

namespace Fusio\Impl\Consumer\View\User;

use Fusio\Engine\ContextInterface;
use Fusio\Impl\Backend\Filter\QueryFilter;
use Fusio\Impl\Service;
use Fusio\Impl\Table;
use Fusio\Model;
use PSX\Nested\Builder;
use PSX\Sql\Condition;
use PSX\Sql\ViewAbstract;

/**
 * Grant
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org
 */
class Grant extends ViewAbstract
{
    public function getCollection(QueryFilter $filter, ContextInterface $context): mixed
    {
        $startIndex = $filter->getStartIndex();
        $count = $filter->getCount();

        $condition = Condition::withAnd();
        $condition->equals('user_grant.' . Table\Generated\UserGrantTable::COLUMN_USER_ID, $context->getUser()->getId());
        $condition->equals('app.' . Table\Generated\AppTable::COLUMN_TENANT_ID, $context->getTenantId());
        $condition->equals('app.' . Table\Generated\AppTable::COLUMN_STATUS, Table\App::STATUS_ACTIVE);

        if ($this->storage instanceof \PSX\Sql\MongoDB\MongoStorage) {
            $grantFilter = \PSX\Sql\MongoDB\ConditionTranslator::translateForAlias($condition, 'user_grant');
            $appFilter = \PSX\Sql\MongoDB\ConditionTranslator::translateForAlias($condition, 'app', true) ?? [];
            $pipeline = [
                ['$match' => $grantFilter],
                ['$lookup' => ['from' => 'fusio_app', 'localField' => 'app_id', 'foreignField' => 'id', 'as' => 'app']],
                ['$unwind' => '$app'],
            ];
            if (!empty($appFilter)) {
                $pipeline[] = ['$match' => $appFilter];
            }
            $total = $this->storage->aggregate('fusio_user_grant', [...$pipeline, ['$count' => 'cnt']]);
            $rows = $this->storage->aggregate('fusio_user_grant', [...$pipeline, ['$sort' => ['id' => -1]], ['$skip' => $startIndex], ['$limit' => $count]]);
            $entries = [];
            foreach ($rows as $row) {
                $app = $row['app'] ?? [];
                $entries[] = [
                    'kind' => \Fusio\Impl\Service\TypeSystem\KindBuilder::build(Model\Consumer\Grant::class),
                    'id' => (int) ($row['id'] ?? 0),
                    'allow' => (int) ($row['allow'] ?? 0),
                    'createDate' => isset($row['date']) ? (new \DateTime((string) $row['date']))->format(\DateTime::RFC3339) : null,
                    'app' => [
                        'id' => (int) ($row['app_id'] ?? 0),
                        'name' => $app['name'] ?? null,
                        'url' => $app['url'] ?? null,
                    ],
                ];
            }
            return [
                'kind' => \Fusio\Impl\Service\TypeSystem\KindBuilder::build(Model\Consumer\GrantCollection::class),
                'totalResults' => (int) ($total[0]['cnt'] ?? 0),
                'startIndex' => $startIndex,
                'itemsPerPage' => $count,
                'entry' => $entries,
            ];
        }

        $queryBuilder = $this->connection->createQueryBuilder()
            ->select([
                'user_grant.' . Table\Generated\UserGrantTable::COLUMN_ID,
                'user_grant.' . Table\Generated\UserGrantTable::COLUMN_APP_ID,
                'user_grant.' . Table\Generated\UserGrantTable::COLUMN_ALLOW,
                'user_grant.' . Table\Generated\UserGrantTable::COLUMN_DATE,
                'app.' . Table\Generated\AppTable::COLUMN_NAME,
                'app.' . Table\Generated\AppTable::COLUMN_URL,
            ])
            ->from('fusio_user_grant', 'user_grant')
            ->innerJoin('user_grant', 'fusio_app', 'app', 'user_grant.' . Table\Generated\UserGrantTable::COLUMN_APP_ID . ' = app.' . Table\Generated\AppTable::COLUMN_ID)
            ->orderBy('user_grant.' . Table\Generated\UserGrantTable::COLUMN_ID, 'DESC')
            ->where($condition->getExpression($this->connection->getDatabasePlatform()))
            ->setParameters($condition->getValues())
            ->setFirstResult($startIndex)
            ->setMaxResults($count);

        $countBuilder = $this->connection->createQueryBuilder()
            ->select(['COUNT(*) AS cnt'])
            ->from('fusio_user_grant', 'user_grant')
            ->innerJoin('user_grant', 'fusio_app', 'app', 'user_grant.' . Table\Generated\UserGrantTable::COLUMN_APP_ID . ' = app.' . Table\Generated\AppTable::COLUMN_ID)
            ->where($condition->getExpression($this->connection->getDatabasePlatform()))
            ->setParameters($condition->getValues());

        $builder = new Builder($this->connection);

        $definition = [
            'kind' => $builder->fieldValue(Service\TypeSystem\KindBuilder::build(Model\Consumer\GrantCollection::class)),
            'totalResults' => $builder->doValue($countBuilder->getSQL(), $countBuilder->getParameters(), $builder->fieldInteger('cnt')),
            'startIndex' => $startIndex,
            'itemsPerPage' => $count,
            'entry' => $builder->doCollection($queryBuilder->getSQL(), $queryBuilder->getParameters(), [
                'kind' => $builder->fieldValue(Service\TypeSystem\KindBuilder::build(Model\Consumer\Grant::class)),
                'id' => $builder->fieldInteger(Table\Generated\UserGrantTable::COLUMN_ID),
                'allow' => $builder->fieldInteger(Table\Generated\UserGrantTable::COLUMN_ALLOW),
                'createDate' => $builder->fieldDateTime(Table\Generated\UserGrantTable::COLUMN_DATE),
                'app' => [
                    'id' => $builder->fieldInteger(Table\Generated\UserGrantTable::COLUMN_APP_ID),
                    'name' => Table\Generated\AppTable::COLUMN_NAME,
                    'url' => Table\Generated\AppTable::COLUMN_URL,
                ],
            ]),
        ];

        return $builder->build($definition);
    }
}
