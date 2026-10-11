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

use Fusio\Impl\Table\App;
use Fusio\Impl\Table\Generated;
use PSX\Sql\Condition;

/**
 * Code
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org
 */
class Code extends Generated\AppCodeTable
{
    /**
     * @return array{id: int, app_id: int, user_id: int, scope: string, date: string}|false
     */
    public function getCodeByRequest(string $appKey, string $appSecret, string $code, ?string $redirectUri, ?string $tenantId = null): array|false
    {
        $condition = Condition::withAnd();
        $condition->equals('app.' . Generated\AppTable::COLUMN_TENANT_ID, $tenantId);
        $condition->equals('app.' . Generated\AppTable::COLUMN_STATUS, App::STATUS_ACTIVE);
        $condition->equals('app.' . Generated\AppTable::COLUMN_APP_KEY, $appKey);
        $condition->equals('app.' . Generated\AppTable::COLUMN_APP_SECRET, $appSecret);
        $condition->equals('app_code.' . self::COLUMN_CODE, $code);
        $condition->equals('app_code.' . self::COLUMN_REDIRECT_URI, $redirectUri ?: '');

        // split: app identity + app_code lookup
        $appCondition = Condition::withAnd();
        $appCondition->equals(Generated\AppTable::COLUMN_TENANT_ID, $tenantId);
        $appCondition->equals(Generated\AppTable::COLUMN_STATUS, App::STATUS_ACTIVE);
        $appCondition->equals(Generated\AppTable::COLUMN_APP_KEY, $appKey);
        $appCondition->equals(Generated\AppTable::COLUMN_APP_SECRET, $appSecret);
        $app = $this->getTable(\Fusio\Impl\Table\App::class)->findOneBy($appCondition);
        if ($app === null) {
            return false;
        }

        $codeCondition = Condition::withAnd();
        $codeCondition->equals(self::COLUMN_APP_ID, $app->getId());
        $codeCondition->equals(self::COLUMN_CODE, $code);
        $codeCondition->equals(self::COLUMN_REDIRECT_URI, $redirectUri ?: '');
        $row = $this->findOneBy($codeCondition);
        if ($row === null) {
            return false;
        }
        return ['id' => $row->getId(), 'app_id' => $row->getAppId(), 'user_id' => $row->getUserId(), 'scope' => $row->getScope(), 'date' => $row->getDate()];
    }
}
