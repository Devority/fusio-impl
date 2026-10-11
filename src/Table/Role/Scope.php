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

namespace Fusio\Impl\Table\Role;

use Fusio\Impl\Table\Generated;

/**
 * Scope
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org
 */
class Scope extends Generated\RoleScopeTable
{
    public function deleteAllFromRole(int $roleId): void
    {
        $condition = Condition::withAnd();
        $condition->equals(self::COLUMN_ROLE_ID, $roleId);
        $this->doDeleteBy($condition);
    }

    /**
     * @param list<string> $scopes
     * @return list<array{id: int, name: string, description: string}>
     */
    public function getValidScopes(int $roleId, array $scopes): array
    {
        $result = $this->getAvailableScopes($roleId);
        $data = [];

        foreach ($result as $scope) {
            if (in_array($scope['name'], $scopes)) {
                $data[] = $scope;
            }
        }

        return $data;
    }

    /**
     * @return list<array{id: int, name: string, description: string}>
     */
    public function getAvailableScopes(int $roleId): array
    {
        $scopeIds = [];
        $linkCondition = Condition::withAnd();
        $linkCondition->equals(self::COLUMN_ROLE_ID, $roleId);
        foreach ($this->findAll($linkCondition) as $link) {
            $scopeIds[] = $link->getScopeId();
        }

        $assignedScopes = [];
        if (!empty($scopeIds)) {
            $scopeCondition = Condition::withAnd();
            $scopeCondition->in(Generated\ScopeTable::COLUMN_ID, $scopeIds);
            foreach ($this->getTable(\Fusio\Impl\Table\Scope::class)->findAll($scopeCondition, 0, PHP_INT_MAX, Generated\ScopeColumn::ID, \PSX\Sql\OrderBy::ASC) as $scope) {
                $assignedScopes[] = ['id' => $scope->getId(), 'name' => $scope->getName(), 'description' => $scope->getDescription()];
            }
        }

        $scopes = [];
        foreach ($assignedScopes as $assignedScope) {
            $scopes[$assignedScope['name']] = $assignedScope;

            if (!str_contains((string) $assignedScope['name'], '.')) {
                // load all sub scopes
                $subCondition = Condition::withAnd();
                $subCondition->like(Generated\ScopeTable::COLUMN_NAME, $assignedScope['name'] . '.%');
                foreach ($this->getTable(\Fusio\Impl\Table\Scope::class)->findAll($subCondition, 0, PHP_INT_MAX, Generated\ScopeColumn::NAME, \PSX\Sql\OrderBy::ASC) as $subScope) {
                    $scopes[$subScope->getName()] = ['id' => $subScope->getId(), 'name' => $subScope->getName(), 'description' => $subScope->getDescription()];
                }
            }
        }

        return array_values($scopes);
    }
}
