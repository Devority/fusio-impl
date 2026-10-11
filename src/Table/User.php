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

use Fusio\Impl\Table\Generated\ScopeRow;
use Fusio\Impl\Table\Generated\UserRow;
use PSX\Sql\Condition;

/**
 * User
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org
 */
class User extends Generated\UserTable
{
    public const STATUS_DISABLED = 2;
    
    public const STATUS_ACTIVE   = 1;
    
    public const STATUS_DELETED  = 0;

    public function findOneByIdentifier(?string $tenantId, string $id): ?UserRow
    {
        $condition = Condition::withAnd();
        $condition->equals(self::COLUMN_TENANT_ID, $tenantId);

        if (str_starts_with($id, '~')) {
            $condition->equals(self::COLUMN_NAME, urldecode(substr($id, 1)));
        } else {
            $condition->equals(self::COLUMN_ID, (int) $id);
        }

        return $this->findOneBy($condition);
    }

    public function findOneByTenantAndId(?string $tenantId, int $id): ?UserRow
    {
        $condition = Condition::withAnd();
        $condition->equals(self::COLUMN_TENANT_ID, $tenantId);
        $condition->equals(self::COLUMN_ID, $id);

        return $this->findOneBy($condition);
    }

    public function findOneByTenantAndName(?string $tenantId, string $name): ?UserRow
    {
        $condition = Condition::withAnd();
        $condition->equals(self::COLUMN_TENANT_ID, $tenantId);
        $condition->equals(self::COLUMN_NAME, $name);

        return $this->findOneBy($condition);
    }

    public function findOneByTenantAndEmail(?string $tenantId, string $email): ?UserRow
    {
        $condition = Condition::withAnd();
        $condition->equals(self::COLUMN_TENANT_ID, $tenantId);
        $condition->equals(self::COLUMN_EMAIL, $email);

        return $this->findOneBy($condition);
    }

    public function findOneByTenantAndToken(?string $tenantId, string $token): ?UserRow
    {
        $condition = Condition::withAnd();
        $condition->equals(self::COLUMN_TENANT_ID, $tenantId);
        $condition->equals(self::COLUMN_TOKEN, $token);

        return $this->findOneBy($condition);
    }

    public function findRemoteUser(?string $tenantId, int $identityId, string $remoteId): ?UserRow
    {
        $condition = Condition::withAnd();
        $condition->equals(self::COLUMN_TENANT_ID, $tenantId);
        $condition->equals(self::COLUMN_IDENTITY_ID, $identityId);
        $condition->equals(self::COLUMN_REMOTE_ID, $remoteId);

        return $this->findOneBy($condition);
    }

    public function changePassword(?string $tenantId, int $userId, ?string $oldPassword, string $newPassword, bool $verifyOld = true): bool
    {
        $condition = Condition::withAnd();
        $condition->equals(self::COLUMN_TENANT_ID, $tenantId);
        $condition->equals(self::COLUMN_ID, $userId);

        $row = $this->findOneBy($condition);
        $password = $row !== null ? $row->getPassword() : null;
        if (empty($password)) {
            return false;
        }

        if (!$verifyOld || password_verify($oldPassword ?? '', (string) $password)) {
            $this->doUpdateBy($condition, new \PSX\Record\Record([self::COLUMN_PASSWORD => \password_hash($newPassword, PASSWORD_DEFAULT)]));

            return true;
        } else {
            return false;
        }
    }

    public function payPoints(int $userId, int $points): void
    {
        $this->incrementPoints($userId, -$points);
    }

    public function creditPoints(int $userId, int $points): void
    {
        $this->incrementPoints($userId, $points);
    }

    private function incrementPoints(int $userId, int $delta): void
    {
        if ($this->getStorage() instanceof \PSX\Sql\MongoDB\MongoStorage) {
            $this->getStorage()->getDatabase()->selectCollection('fusio_user')->updateOne(
                ['id' => $userId],
                [
                    ['$set' => ['points' => ['$add' => [['$ifNull' => ['$points', 0]], $delta]]]],
                ]
            );
            return;
        }

        $this->connection->executeStatement('UPDATE fusio_user SET points = COALESCE(points, 0) + :delta WHERE id = :id', [
            'id' => $userId,
            'delta' => $delta,
        ]);
    }

    public function getPlanId(?string $tenantId, int $userId): int
    {
        $condition = Condition::withAnd();
        $condition->equals(self::COLUMN_TENANT_ID, $tenantId);
        $condition->equals(self::COLUMN_ID, $userId);

        $row = $this->findOneBy($condition);
        return $row !== null ? (int) ($row->getPlanId() ?? 0) : 0;
    }
}
