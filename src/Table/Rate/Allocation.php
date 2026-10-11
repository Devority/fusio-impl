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

namespace Fusio\Impl\Table\Rate;

use Fusio\Engine\Model;
use Fusio\Impl\Table\Generated;
use Fusio\Impl\Table\Rate;
use PSX\Sql\Condition;

/**
 * Allocation
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org
 */
class Allocation extends Generated\RateAllocationTable
{
    public function deleteAllFromRate(int $rateId): void
    {
        $condition = Condition::withAnd();
        $condition->equals(self::COLUMN_RATE_ID, $rateId);
        $this->doDeleteBy($condition);
    }

    /**
     * @return array{rate_limit: int, timespan: string}|false
     */
    public function getRateForRequest(?string $tenantId, Generated\OperationRow $operation, Model\AppInterface $app, Model\UserInterface $user): array|false
    {
        // allocation-side predicates (nil/equals on rate_allocation columns)
        $condition = Condition::withAnd();
        $condition->add(Condition::withOr()
            ->nil('rate_allocation.' . self::COLUMN_OPERATION_ID)
            ->equals('rate_allocation.' . self::COLUMN_OPERATION_ID, $operation->getId()));
        $condition->add(Condition::withOr()
            ->nil('rate_allocation.' . self::COLUMN_USER_ID)
            ->equals('rate_allocation.' . self::COLUMN_USER_ID, $user->getId()));
        $condition->add(Condition::withOr()
            ->nil('rate_allocation.' . self::COLUMN_PLAN_ID)
            ->equals('rate_allocation.' . self::COLUMN_PLAN_ID, $user->getPlanId()));
        $condition->add(Condition::withOr()
            ->nil('rate_allocation.' . self::COLUMN_APP_ID)
            ->equals('rate_allocation.' . self::COLUMN_APP_ID, $app->getId()));
        $condition->add(Condition::withOr()
            ->nil('rate_allocation.' . self::COLUMN_AUTHENTICATED)
            ->equals('rate_allocation.' . self::COLUMN_AUTHENTICATED, $user->isAnonymous() ? 0 : 1));

        // resolve best matching rate via priority ordering across candidate allocations
        $allocations = $this->findAll($condition);
        $best = null;
        $bestPriority = null;
        $rateCondition = Condition::withAnd();
        $rateCondition->equals(Generated\RateTable::COLUMN_TENANT_ID, $tenantId);
        $rateCondition->equals(Generated\RateTable::COLUMN_STATUS, Rate::STATUS_ACTIVE);
        foreach ($allocations as $allocation) {
            $candidate = clone $rateCondition;
            $candidate->equals(Generated\RateTable::COLUMN_ID, $allocation->getRateId());
            $rate = $this->getTable(\Fusio\Impl\Table\Rate::class)->findOneBy($candidate);
            if ($rate === null) {
                continue;
            }
            if ($best === null || $rate->getPriority() > $bestPriority) {
                $best = $rate;
                $bestPriority = $rate->getPriority();
            }
        }
        if ($best === null) {
            return false;
        }
        return ['rate_limit' => $best->getRateLimit(), 'timespan' => $best->getTimespan()];
    }
}
