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

namespace Fusio\Impl\Repository;

use Doctrine\DBAL\Connection;
use Fusio\Engine\Model;
use Fusio\Engine\Repository;
use Fusio\Impl\Service\System\FrameworkConfig;
use Fusio\Impl\Table;
use PSX\Sql\Condition;

/**
 * AppRepository
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org
 */
class AppRepository implements Repository\AppInterface
{
    private Connection $connection;
    private FrameworkConfig $frameworkConfig;

    private Table\App $appTable;
    private Table\App\Scope $appScopeTable;
    private Table\Scope $scopeTable;

    public function __construct(Connection $connection, FrameworkConfig $frameworkConfig, Table\App $appTable, Table\App\Scope $appScopeTable, Table\Scope $scopeTable)
    {
        $this->connection = $connection;
        $this->frameworkConfig = $frameworkConfig;
        $this->appTable = $appTable;
        $this->appScopeTable = $appScopeTable;
        $this->scopeTable = $scopeTable;
    }

    public function getAll(): array
    {
        $condition = Condition::withAnd();
        $condition->equals(Table\Generated\AppTable::COLUMN_TENANT_ID, $this->frameworkConfig->getTenantId());
        $condition->equals(Table\Generated\AppTable::COLUMN_STATUS, Table\App::STATUS_ACTIVE);

        $apps = [];
        foreach ($this->appTable->findAll($condition, 0, 0, Table\Generated\AppColumn::ID, \PSX\Sql\OrderBy::DESC) as $row) {
            $apps[] = $this->newApp($row->toRecord()->getAll(), []);
        }

        return $apps;
    }

    public function get(string|int $id): ?Model\AppInterface
    {
        if (empty($id)) {
            return null;
        }

        $condition = Condition::withAnd();
        $condition->equals(Table\Generated\AppTable::COLUMN_TENANT_ID, $this->frameworkConfig->getTenantId());
        $condition->equals(Table\Generated\AppTable::COLUMN_ID, $id);

        $app = $this->appTable->findOneBy($condition);
        if ($app !== null) {
            $row = $app->toRecord()->getAll();
            return $this->newApp($row, $this->getScopes($row[Table\Generated\AppTable::COLUMN_ID]));
        } else {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    protected function getScopes(string|int $appId): array
    {
        // resolve scope ids through the join table, then load names —
        // works identically on SQL (via Table methods) and MongoDB
        $scopeIds = [];
        $linkCondition = Condition::withAnd();
        $linkCondition->equals(Table\Generated\AppScopeTable::COLUMN_APP_ID, $appId);
        foreach ($this->appScopeTable->findAll($linkCondition) as $link) {
            $scopeIds[] = $link->getScopeId();
        }

        $names = [];
        if (!empty($scopeIds)) {
            $condition = Condition::withAnd();
            $condition->equals(Table\Generated\ScopeTable::COLUMN_TENANT_ID, $this->frameworkConfig->getTenantId());
            $condition->in(Table\Generated\ScopeTable::COLUMN_ID, $scopeIds);
            foreach ($this->scopeTable->findAll($condition) as $scope) {
                $names[] = $scope->getName();
            }
        }

        return $names;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $scopes
     */
    protected function newApp(array $row, array $scopes): Model\AppInterface
    {
        $parameters = [];
        if (!empty($row[Table\Generated\AppTable::COLUMN_PARAMETERS])) {
            parse_str($row[Table\Generated\AppTable::COLUMN_PARAMETERS], $parameters);
        }

        $metadata = null;
        if (!empty($row[Table\Generated\AppTable::COLUMN_METADATA])) {
            $metadata = json_decode($row[Table\Generated\AppTable::COLUMN_METADATA]);
            if (!$metadata instanceof \stdClass) {
                $metadata = null;
            }
        }

        return new Model\App(
            false,
            $row[Table\Generated\AppTable::COLUMN_ID],
            $row[Table\Generated\AppTable::COLUMN_USER_ID],
            $row[Table\Generated\AppTable::COLUMN_STATUS],
            $row[Table\Generated\AppTable::COLUMN_NAME],
            $row[Table\Generated\AppTable::COLUMN_URL],
            $row[Table\Generated\AppTable::COLUMN_APP_KEY],
            $parameters,
            $scopes,
            $metadata
        );
    }
}
