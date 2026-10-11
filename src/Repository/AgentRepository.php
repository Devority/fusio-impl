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
use Fusio\Impl\Service;
use Fusio\Impl\Table;
use PSX\Sql\Condition;
use stdClass;

/**
 * AgentRepository
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org
 */
class AgentRepository implements Repository\AgentInterface
{
    private Connection $connection;
    private Service\System\FrameworkConfig $frameworkConfig;
    private Table\Agent $agentTable;

    public function __construct(Connection $connection, Service\System\FrameworkConfig $frameworkConfig, Table\Agent $agentTable)
    {
        $this->connection = $connection;
        $this->frameworkConfig = $frameworkConfig;
        $this->agentTable = $agentTable;
    }

    public function getAll(): array
    {
        $condition = Condition::withAnd();
        $condition->equals(Table\Generated\AgentTable::COLUMN_TENANT_ID, $this->frameworkConfig->getTenantId());
        $condition->equals(Table\Generated\AgentTable::COLUMN_STATUS, Table\Agent::STATUS_ACTIVE);

        $actions = [];
        foreach ($this->agentTable->findAll($condition, 0, 0, Table\Generated\AgentColumn::NAME, \PSX\Sql\OrderBy::ASC) as $row) {
            $actions[] = $this->newAgent($row->toRecord()->getAll());
        }

        return $actions;
    }

    public function get(string|int $id): ?Model\AgentInterface
    {
        if (empty($id)) {
            return null;
        }

        if (is_numeric($id)) {
            $column = Table\Generated\AgentTable::COLUMN_ID;
        } else {
            $column = Table\Generated\AgentTable::COLUMN_NAME;
        }

        $condition = Condition::withAnd();
        $condition->equals(Table\Generated\ActionTable::COLUMN_TENANT_ID, $this->frameworkConfig->getTenantId());
        $condition->equals($column, $id);

        $agent = $this->agentTable->findOneBy($condition);
        if ($agent !== null) {
            return $this->newAgent($agent->toRecord()->getAll());
        } else {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function newAgent(array $row): Model\AgentInterface
    {
        $metadata = null;
        if (!empty($row[Table\Generated\AgentTable::COLUMN_METADATA])) {
            $metadata = json_decode($row[Table\Generated\AgentTable::COLUMN_METADATA]);
            if (!$metadata instanceof stdClass) {
                $metadata = null;
            }
        }

        return new Model\Agent(
            $row[Table\Generated\AgentTable::COLUMN_ID],
            $row[Table\Generated\AgentTable::COLUMN_NAME],
            $row[Table\Generated\AgentTable::COLUMN_DESCRIPTION],
            $metadata
        );
    }
}
