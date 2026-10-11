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

namespace Fusio\Impl\Table\User;

use Fusio\Impl\Table\Generated;
use PSX\Sql\Condition;

/**
 * Scope
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org
 */
class Scope extends Generated\UserScopeTable
{
    public function deleteAllFromUser(int $userId): void
    {
        $condition = Condition::withAnd();
        $condition->equals(self::COLUMN_USER_ID, $userId);
        $this->doDeleteBy($condition);
    }

    /**
     * @param list<string> $scopes
     * @return list<array{id: int, name: string, description: string}>
     */
    public function getValidScopes(?string $tenantId, int $userId, array $scopes): array
    {
        $result = $this->getAvailableScopes($tenantId, $userId, true);
        $data   = [];

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
    public function getAvailableScopes(?string $tenantId, int $userId, bool $includePlanScopes = false): array
    {
        $assignedScopes = $this->getScopesForUser($userId);

        // get scopes for plan
        if ($includePlanScopes) {
            $assignedScopes = array_merge($assignedScopes, $this->getScopesForPlan($userId));
        }

        $scopes = [];
        foreach ($assignedScopes as $assignedScope) {
            $scopes[$assignedScope['name']] = $assignedScope;

            if (!str_contains((string) $assignedScope['name'], '.')) {
                // load all sub scopes
                $condition = Condition::withAnd();
                $condition->like(Generated\ScopeTable::COLUMN_NAME, $assignedScope['name'] . '.%');
                foreach ($this->getTable(\Fusio\Impl\Table\Scope::class)->findAll($condition, 0, 0, Generated\ScopeColumn::NAME, \PSX\Sql\OrderBy::ASC) as $subScope) {
                    $scopes[$subScope->getName()] = ['id' => $subScope->getId(), 'name' => $subScope->getName(), 'description' => $subScope->getDescription()];
                }
            }
        }

        return array_values($scopes);
    }

    public function hasUserScope(int $userId, int $scopeId): bool
    {
        $condition = Condition::withAnd();
        $condition->equals(self::COLUMN_USER_ID, $userId);
        $condition->equals(self::COLUMN_SCOPE_ID, $scopeId);
        return $this->getCount($condition) > 0;
    }

    /**
     * @return list<array{id: int, name: string, description: string}>
     */
    private function getScopesForUser(int $userId): array
    {
        $condition = Condition::withAnd();
        $condition->equals(self::COLUMN_USER_ID, $userId);

        $scopeIds = [];
        foreach ($this->findAll($condition) as $link) {
            $scopeIds[] = $link->getScopeId();
        }
        if (empty($scopeIds)) {
            return [];
        }

        $result = [];
        $condition = Condition::withAnd();
        $condition->in(Generated\ScopeTable::COLUMN_ID, $scopeIds);
        foreach ($this->getTable(\Fusio\Impl\Table\Scope::class)->findAll($condition, 0, 0, Generated\ScopeColumn::ID, \PSX\Sql\OrderBy::ASC) as $scope) {
            $result[] = ['id' => $scope->getId(), 'name' => $scope->getName(), 'description' => $scope->getDescription()];
        }
        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getScopesForPlan(int $userId): array
    {
        $user = $this->getTable(\Fusio\Impl\Table\User::class)->find($userId);
        $planId = (int) ($user !== null ? ($user->getPlanId() ?? 0) : 0);
        if (empty($planId)) {
            return [];
        }

        $scopeIds = [];
        $condition = Condition::withAnd();
        $condition->equals(Generated\PlanScopeTable::COLUMN_PLAN_ID, $planId);
        foreach ($this->getTable(\Fusio\Impl\Table\Plan\Scope::class)->findAll($condition) as $link) {
            $scopeIds[] = $link->getScopeId();
        }
        if (empty($scopeIds)) {
            return [];
        }

        $result = [];
        $condition = Condition::withAnd();
        $condition->in(Generated\ScopeTable::COLUMN_ID, $scopeIds);
        foreach ($this->getTable(\Fusio\Impl\Table\Scope::class)->findAll($condition, 0, 0, Generated\ScopeColumn::ID, \PSX\Sql\OrderBy::ASC) as $scope) {
            $result[] = ['id' => $scope->getId(), 'name' => $scope->getName(), 'description' => $scope->getDescription()];
        }
        return $result;
    }
}
