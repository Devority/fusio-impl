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

use DateTime;
use Fusio\Impl\Table\Generated\TokenRow;
use PSX\Http\Exception as StatusCode;
use PSX\Sql\Condition;

/**
 * Token
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org
 */
class Token extends Generated\TokenTable
{
    const STATUS_ACTIVE  = 0x1;
    
    const STATUS_DELETED = 0x2;

    public function findOneByTenantAndId(?string $tenantId, int $id, ?int $userId = null): ?TokenRow
    {
        $condition = Condition::withAnd();
        $condition->equals(self::COLUMN_TENANT_ID, $tenantId);
        $condition->equals(self::COLUMN_ID, $id);
        if ($userId !== null) {
            $condition->equals(self::COLUMN_USER_ID, $userId);
        }

        return $this->findOneBy($condition);
    }

    /**
     * @return array{id: int, app_id: int, user_id: int, token: string, scope: string, expire: string, date: string}|false
     */
    public function findByAccessToken(?string $tenantId, string $token): array|false
    {
        $now = new \DateTime();

        $condition = Condition::withAnd();
        $condition->equals(self::COLUMN_TENANT_ID, $tenantId);
        $condition->equals(self::COLUMN_STATUS, self::STATUS_ACTIVE);
        $condition->equals(self::COLUMN_TOKEN, $token);
        $condition->add(Condition::withOr()
            ->nil(self::COLUMN_EXPIRE)
            ->greater(self::COLUMN_EXPIRE, $now)
        );

        $row = $this->findOneBy($condition);
        if ($row === null) {
            return false;
        }
        $data = $row->toRecord()->getAll();
        foreach (['expire', 'date'] as $key) {
            if (($data[$key] ?? null) instanceof \DateTimeInterface) {
                $data[$key] = $data[$key]->format('Y-m-d H:i:s');
            }
        }
        return $data;
    }

    /**
     * @return list<TokenRow>
     */
    public function getTokensByApp(?string $tenantId, int $appId): array
    {
        $con = Condition::withAnd();
        $con->equals(self::COLUMN_TENANT_ID, $tenantId);
        $con->equals(self::COLUMN_APP_ID, $appId);
        $con->equals(self::COLUMN_STATUS, self::STATUS_ACTIVE);
        $con->greater(self::COLUMN_EXPIRE, new DateTime()->format('Y-m-d H:i:s'));

        return $this->findBy($con);
    }

    public function findOneByTenantAndRefreshToken(?string $tenantId, string $refreshToken): ?Generated\TokenRow
    {
        $con = Condition::withAnd();
        $con->equals(self::COLUMN_TENANT_ID, $tenantId);
        $con->equals(self::COLUMN_REFRESH, $refreshToken);

        return $this->findOneBy($con);
    }

    public function getTokenByToken(?string $tenantId, string $token): ?Generated\TokenRow
    {
        $con = Condition::withAnd();
        $con->equals(self::COLUMN_TENANT_ID, $tenantId);
        $con->equals(self::COLUMN_STATUS, self::STATUS_ACTIVE);
        $con->greater(self::COLUMN_EXPIRE, new DateTime()->format('Y-m-d H:i:s'));
        $con->equals(self::COLUMN_TOKEN, $token);

        return $this->findOneBy($con);
    }

    public function removeToken(?string $tenantId, int $tokenId): void
    {
        $condition = Condition::withAnd();
        $condition->equals(self::COLUMN_TENANT_ID, $tenantId);
        $condition->equals(self::COLUMN_ID, $tokenId);

        $affectedRows = $this->doUpdateBy($condition, new \PSX\Record\Record([self::COLUMN_STATUS => self::STATUS_DELETED]));

        if ($affectedRows == 0) {
            throw new StatusCode\NotFoundException('Invalid token');
        }
    }

    public function removeAllTokensFromAppAndUser(?string $tenantId, int $appId, int $userId): void
    {
        $condition = Condition::withAnd();
        $condition->equals(self::COLUMN_TENANT_ID, $tenantId);
        $condition->equals(self::COLUMN_APP_ID, $appId);
        $condition->equals(self::COLUMN_USER_ID, $userId);

        $this->doUpdateBy($condition, new \PSX\Record\Record([self::COLUMN_STATUS => self::STATUS_DELETED]));
    }

    public function getCountForUser(?string $tenantId, int $categoryId, int $userId): int
    {
        $condition = Condition::withAnd();
        $condition->equals(self::COLUMN_TENANT_ID, $tenantId);
        $condition->equals(self::COLUMN_CATEGORY_ID, $categoryId);
        $condition->equals(self::COLUMN_USER_ID, $userId);
        $condition->equals(self::COLUMN_STATUS, self::STATUS_ACTIVE);

        return $this->getCount($condition);
    }
}
