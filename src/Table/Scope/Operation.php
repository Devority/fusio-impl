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

namespace Fusio\Impl\Table\Scope;

use Fusio\Impl\Table\Generated;

/**
 * Operation
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org
 */
class Operation extends Generated\ScopeOperationTable
{
    public function deleteAllFromScope(int $scopeId): void
    {
        $condition = Condition::withAnd();
        $condition->equals(self::COLUMN_SCOPE_ID, $scopeId);
        $this->doDeleteBy($condition);
    }

    public function deleteAllFromOperation(int $operationId): void
    {
        $condition = Condition::withAnd();
        $condition->equals(self::COLUMN_OPERATION_ID, $operationId);
        $this->doDeleteBy($condition);
    }

    /**
     * @return list<array{name: string}>
     */
    public function getScopeNamesForOperation(int $operationId): array
    {
        return array_map(
            fn ($row) => ['name' => $row],
            $this->resolveScopeNames($operationId)
        );
    }

    /**
     * @return list<string>
     */
    public function getScopesForOperation(int $operationId): array
    {
        return $this->resolveScopeNames($operationId);
    }

    /**
     * @return list<string>
     */
    private function resolveScopeNames(int $operationId): array
    {
        $scopeIds = [];
        $condition = Condition::withAnd();
        $condition->equals(self::COLUMN_OPERATION_ID, $operationId);
        $condition->equals(self::COLUMN_ALLOW, 1);
        foreach ($this->findAll($condition, 0, 0, Generated\ScopeOperationColumn::ID, \PSX\Sql\OrderBy::ASC) as $link) {
            $scopeIds[] = $link->getScopeId();
        }
        if (empty($scopeIds)) {
            return [];
        }

        $names = [];
        $condition = Condition::withAnd();
        $condition->in(Generated\ScopeTable::COLUMN_ID, $scopeIds);
        foreach ($this->getTable(\Fusio\Impl\Table\Scope::class)->findAll($condition) as $scope) {
            $names[] = $scope->getName();
        }
        return $names;
    }
}
