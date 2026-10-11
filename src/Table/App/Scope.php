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

namespace Fusio\Impl\Table\App;

use Fusio\Impl\Table;
use Fusio\Impl\Table\Generated;
use PSX\Sql\Condition;

/**
 * Scope
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org
 */
class Scope extends Generated\AppScopeTable
{
    public function deleteAllFromApp(int $appId): void
    {
        $condition = Condition::withAnd();
        $condition->equals(self::COLUMN_APP_ID, $appId);
        $this->doDeleteBy($condition);
    }

    /**
     * @param list<string> $scopes
     * @return list<array{id: int, name: string, description: string}>
     */
    public function getValidScopes(?string $tenantId, int $appId, array $scopes): array
    {
        $result = $this->getAvailableScopes($tenantId, $appId, true);
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
    public function getAvailableScopes(?string $tenantId, int $appId, bool $includePlanScopes = false): array
    {
        $assignedScopes = $this->getScopesForApp($tenantId, $appId);

        // get scopes from plan
        if ($includePlanScopes) {
            $assignedScopes = array_merge($assignedScopes, $this->getScopesForPlan($tenantId, $appId));
        }

        $scopes = [];
        foreach ($assignedScopes as $assignedScope) {
            $scopes[$assignedScope['name']] = $assignedScope;

            if (!str_contains((string) $assignedScope['name'], '.')) {
                // load all sub scopes
                $subScopes = $this->getTable(Table\Scope::class)->findSubScopes($tenantId, $assignedScope['name']);
                foreach ($subScopes as $subScope) {
                    $scopes[$subScope['name']] = $subScope;
                }
            }
        }

        return array_values($scopes);
    }

    /**
     * @return list<array{id: int, name: string, description: string}>
     */
    private function getScopesForApp(?string $tenantId, int $appId): array
    {
        $condition = Condition::withAnd();
        $condition->equals(Generated\ScopeTable::COLUMN_TENANT_ID, $tenantId);
        $condition->equals(Generated\ScopeTable::COLUMN_STATUS, Table\Scope::STATUS_ACTIVE);
        $condition->equals(self::COLUMN_APP_ID, $appId);

        $scopeIds = [];
        $linkCondition = Condition::withAnd();
        $linkCondition->equals(self::COLUMN_APP_ID, $appId);
        foreach ($this->findAll($linkCondition) as $link) {
            $scopeIds[] = $link->getScopeId();
        }
        if (empty($scopeIds)) {
            return [];
        }

        $result = [];
        $scopeCondition = Condition::withAnd();
        $scopeCondition->equals(Generated\ScopeTable::COLUMN_TENANT_ID, $tenantId);
        $scopeCondition->equals(Generated\ScopeTable::COLUMN_STATUS, Table\Scope::STATUS_ACTIVE);
        $scopeCondition->in(Generated\ScopeTable::COLUMN_ID, $scopeIds);
        foreach ($this->getTable(Table\Scope::class)->findAll($scopeCondition, 0, PHP_INT_MAX, Generated\ScopeColumn::ID, \PSX\Sql\OrderBy::ASC) as $scope) {
            $result[] = ['id' => $scope->getId(), 'name' => $scope->getName(), 'description' => $scope->getDescription()];
        }
        return $result;
    }

    /**
     * @return list<array{id: int, name: string, description: string}>
     */
    private function getScopesForPlan(?string $tenantId, int $appId): array
    {
        $userId = $this->getTable(Table\App::class)->getUserId($tenantId, $appId);
        if (empty($userId)) {
            return [];
        }

        $planId = $this->getTable(Table\User::class)->getPlanId($tenantId, $appId);
        if (empty($planId)) {
            return [];
        }

        $condition = Condition::withAnd();
        $condition->equals(Generated\ScopeTable::COLUMN_TENANT_ID, $tenantId);
        $condition->equals(Generated\ScopeTable::COLUMN_STATUS, Table\Scope::STATUS_ACTIVE);
        $condition->equals(Generated\PlanScopeTable::COLUMN_PLAN_ID, $planId);

        $scopeIds = [];
        $linkCondition = Condition::withAnd();
        $linkCondition->equals(Generated\PlanScopeTable::COLUMN_PLAN_ID, $planId);
        foreach ($this->getTable(Table\Plan\Scope::class)->findAll($linkCondition) as $link) {
            $scopeIds[] = $link->getScopeId();
        }
        if (empty($scopeIds)) {
            return [];
        }

        $result = [];
        $scopeCondition = Condition::withAnd();
        $scopeCondition->equals(Generated\ScopeTable::COLUMN_TENANT_ID, $tenantId);
        $scopeCondition->equals(Generated\ScopeTable::COLUMN_STATUS, Table\Scope::STATUS_ACTIVE);
        $scopeCondition->in(Generated\ScopeTable::COLUMN_ID, $scopeIds);
        foreach ($this->getTable(Table\Scope::class)->findAll($scopeCondition, 0, PHP_INT_MAX, Generated\ScopeColumn::ID, \PSX\Sql\OrderBy::ASC) as $scope) {
            $result[] = ['id' => $scope->getId(), 'name' => $scope->getName(), 'description' => $scope->getDescription()];
        }
        return $result;
    }
}
