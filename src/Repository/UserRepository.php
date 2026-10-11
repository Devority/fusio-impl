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
 * UserRepository
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org
 */
class UserRepository implements Repository\UserInterface
{
    private Connection $connection;
    private FrameworkConfig $frameworkConfig;
    private Table\User $userTable;
    private Table\Role $roleTable;

    public function __construct(Connection $connection, FrameworkConfig $frameworkConfig, Table\User $userTable, Table\Role $roleTable)
    {
        $this->connection = $connection;
        $this->frameworkConfig = $frameworkConfig;
        $this->userTable = $userTable;
        $this->roleTable = $roleTable;
    }

    public function getAll(): array
    {
        $condition = Condition::withAnd();
        $condition->equals(Table\Generated\UserTable::COLUMN_TENANT_ID, $this->frameworkConfig->getTenantId());
        $condition->equals(Table\Generated\UserTable::COLUMN_STATUS, Table\User::STATUS_ACTIVE);

        $users = [];
        foreach ($this->userTable->findAll($condition, 0, 0, Table\Generated\UserColumn::ID, \PSX\Sql\OrderBy::DESC) as $row) {
            $users[] = $this->newUser($row->toRecord()->getAll());
        }

        return $users;
    }

    public function get(string|int $id): ?Model\UserInterface
    {
        if (empty($id)) {
            return null;
        }

        $condition = Condition::withAnd();
        $condition->equals(Table\Generated\UserTable::COLUMN_TENANT_ID, $this->frameworkConfig->getTenantId());
        $condition->equals(Table\Generated\UserTable::COLUMN_ID, $id);

        $row = $this->userTable->findOneBy($condition);
        if ($row === null) {
            return null;
        }

        return $this->newUser($row->toRecord()->getAll());
    }

    /**
     * @param array<string, mixed> $row
     */
    private function newUser(array $row): Model\UserInterface
    {
        $metadata = null;
        if (!empty($row[Table\Generated\UserTable::COLUMN_METADATA])) {
            $metadata = json_decode($row[Table\Generated\UserTable::COLUMN_METADATA]);
            if (!$metadata instanceof \stdClass) {
                $metadata = null;
            }
        }

        return new Model\User(
            false,
            $row[Table\Generated\UserTable::COLUMN_ID],
            $row[Table\Generated\UserTable::COLUMN_ROLE_ID],
            $this->getCategoryForRole($row[Table\Generated\UserTable::COLUMN_ROLE_ID]),
            $row[Table\Generated\UserTable::COLUMN_STATUS],
            $row[Table\Generated\UserTable::COLUMN_NAME],
            $row[Table\Generated\UserTable::COLUMN_EMAIL] ?? '',
            $row[Table\Generated\UserTable::COLUMN_POINTS] ?? 0,
            $row[Table\Generated\UserTable::COLUMN_EXTERNAL_ID] ?? null,
            $row[Table\Generated\UserTable::COLUMN_PLAN_ID] ?? null,
            $metadata
        );
    }

    private function getCategoryForRole(int $roleId): int
    {
        $condition = Condition::withAnd();
        $condition->equals(Table\Generated\RoleTable::COLUMN_TENANT_ID, $this->frameworkConfig->getTenantId());
        $condition->equals(Table\Generated\RoleTable::COLUMN_ID, $roleId);

        $row = $this->roleTable->findOneBy($condition);
        if ($row === null) {
            return 1;
        }

        return (int) $row->getCategoryId();
    }
}
