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

namespace Fusio\Impl\Table\Plan;

use Fusio\Impl\Table\Generated;
use Fusio\Impl\Table;
use PSX\Sql\Condition;

/**
 * Scope
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org
 */
class Scope extends Generated\PlanScopeTable
{
    public function deleteAllFromPlan(int $planId): void
    {
        $condition = Condition::withAnd();
        $condition->equals(self::COLUMN_PLAN_ID, $planId);
        $this->doDeleteBy($condition);
    }

    /**
     * @return list<array{id: int, name: string, description: string}>
     */
    public function getAvailableScopes(?string $tenantId, int $planId): array
    {
        $condition = Condition::withAnd();
        $condition->equals(Generated\ScopeTable::COLUMN_TENANT_ID, $tenantId);
        $condition->equals(self::COLUMN_PLAN_ID, $planId);

        $scopeIds = [];
        $linkCondition = Condition::withAnd();
        $linkCondition->equals(self::COLUMN_PLAN_ID, $planId);
        foreach ($this->findAll($linkCondition) as $link) {
            $scopeIds[] = $link->getScopeId();
        }
        if (empty($scopeIds)) {
            return [];
        }

        $result = [];
        $scopeCondition = Condition::withAnd();
        $scopeCondition->equals(Generated\ScopeTable::COLUMN_TENANT_ID, $tenantId);
        $scopeCondition->in(Generated\ScopeTable::COLUMN_ID, $scopeIds);
        foreach ($this->getTable(\Fusio\Impl\Table\Scope::class)->findAll($scopeCondition, 0, PHP_INT_MAX, Generated\ScopeColumn::ID, \PSX\Sql\OrderBy::ASC) as $scope) {
            $result[] = ['id' => $scope->getId(), 'name' => $scope->getName(), 'description' => $scope->getDescription()];
        }
        return $result;
    }
}
