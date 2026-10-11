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
use Fusio\Impl\Service\Connection as ConnectionService;
use Fusio\Impl\Service\System\FrameworkConfig;
use Fusio\Impl\Table;
use PSX\Sql\Condition;

/**
 * ConnectionRepository
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org
 */
class ConnectionRepository implements Repository\ConnectionInterface
{
    private Connection $connection;
    private FrameworkConfig $frameworkConfig;

    private Table\Connection $connectionTable;

    public function __construct(Connection $connection, FrameworkConfig $frameworkConfig, Table\Connection $connectionTable)
    {
        $this->connection = $connection;
        $this->frameworkConfig = $frameworkConfig;
        $this->connectionTable = $connectionTable;
    }

    public function getAll(): array
    {
        $condition = Condition::withAnd();
        $condition->equals(Table\Generated\ConnectionTable::COLUMN_TENANT_ID, $this->frameworkConfig->getTenantId());
        $condition->equals(Table\Generated\ConnectionTable::COLUMN_STATUS, Table\Connection::STATUS_ACTIVE);

        $connections = [];
        foreach ($this->connectionTable->findAll($condition, 0, 0, Table\Generated\ConnectionColumn::NAME, \PSX\Sql\OrderBy::ASC) as $row) {
            $connections[] = $this->newConnection($row->toRecord()->getAll());
        }

        return $connections;
    }

    public function get(string|int $id): ?Model\ConnectionInterface
    {
        if (is_numeric($id)) {
            $column = Table\Generated\ConnectionTable::COLUMN_ID;
        } else {
            $column = Table\Generated\ConnectionTable::COLUMN_NAME;
        }

        $condition = Condition::withAnd();
        $condition->equals(Table\Generated\ConnectionTable::COLUMN_TENANT_ID, $this->frameworkConfig->getTenantId());
        $condition->equals($column, $id);

        $conn = $this->connectionTable->findOneBy($condition);
        if ($conn !== null) {
            return $this->newConnection($conn->toRecord()->getAll());
        } else {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function newConnection(array $row): Model\ConnectionInterface
    {
        $config = !empty($row[Table\Generated\ConnectionTable::COLUMN_CONFIG]) ? ConnectionService\Encrypter::decrypt($row[Table\Generated\ConnectionTable::COLUMN_CONFIG], $this->frameworkConfig->getProjectKey()) : [];

        $metadata = null;
        if (!empty($row[Table\Generated\ConnectionTable::COLUMN_METADATA])) {
            $metadata = json_decode($row[Table\Generated\ConnectionTable::COLUMN_METADATA]);
            if (!$metadata instanceof \stdClass) {
                $metadata = null;
            }
        }

        return new Model\Connection(
            $row[Table\Generated\ConnectionTable::COLUMN_ID],
            $row[Table\Generated\ConnectionTable::COLUMN_NAME],
            $row[Table\Generated\ConnectionTable::COLUMN_CLASS],
            $config,
            $metadata
        );
    }
}
