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

namespace Fusio\Impl\Table;

use Fusio\Impl\Table\Generated\OperationRow;
use Fusio\Impl\Table\Generated\PageRow;
use Fusio\Impl\Table\Generated\PlanRow;
use PSX\Sql\Condition;

/**
 * Plan
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org
 */
class Plan extends Generated\PlanTable
{
    public const STATUS_ACTIVE  = 1;
    
    public const STATUS_DELETED = 0;

    public function findOneByIdentifier(?string $tenantId, string $id): ?PlanRow
    {
        if (str_starts_with($id, '~')) {
            return $this->findOneByTenantAndName($tenantId, urldecode(substr($id, 1)));
        } else {
            return $this->findOneByTenantAndId($tenantId, (int) $id);
        }
    }

    public function findOneByTenantAndId(?string $tenantId, int $id): ?PlanRow
    {
        $condition = Condition::withAnd();
        $condition->equals(self::COLUMN_TENANT_ID, $tenantId);
        $condition->equals(self::COLUMN_ID, $id);

        return $this->findOneBy($condition);
    }

    public function findOneByTenantAndName(?string $tenantId, string $name): ?PlanRow
    {
        $condition = Condition::withAnd();
        $condition->equals(self::COLUMN_TENANT_ID, $tenantId);
        $condition->equals(self::COLUMN_NAME, $name);

        return $this->findOneBy($condition);
    }

    /**
     * Returns an array of plans which are currently active for the provided user
     *
     * @return PlanRow[]
     */
    public function getActivePlansForUser(?string $tenantId, int $userId): array
    {
        $now = new \DateTime();

        $trxCondition = Condition::withAnd();
        $trxCondition->equals(Generated\TransactionTable::COLUMN_TENANT_ID, $tenantId);
        $trxCondition->equals(Generated\TransactionTable::COLUMN_USER_ID, $userId);
        $trxCondition->lessThan(Generated\TransactionTable::COLUMN_PERIOD_START, $now);
        $trxCondition->greaterThan(Generated\TransactionTable::COLUMN_PERIOD_END, $now);

        $planIds = [];
        foreach ($this->getTable(\Fusio\Impl\Table\Transaction::class)->findAll($trxCondition) as $trx) {
            $planIds[] = $trx->getPlanId();
        }
        if (empty($planIds)) {
            return [];
        }

        $result = [];
        $planCondition = Condition::withAnd();
        $planCondition->in(self::COLUMN_ID, $planIds);
        $planCondition->equals(self::COLUMN_TENANT_ID, $tenantId);
        foreach ($this->findAll($planCondition) as $plan) {
            $result[] = $plan->toRecord()->getAll();
        }

        $plans = [];
        foreach ($result as $row) {
            $plans[] = $this->newRecord($row);
        }
        
        return $plans;
    }
}
