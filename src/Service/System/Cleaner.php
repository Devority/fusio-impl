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

namespace Fusio\Impl\Service\System;

use Doctrine\DBAL\Connection;
use Fusio\Impl\Table;

/**
 * Cleaner
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org
 */
class Cleaner
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function cleanUp(): void
    {
        $this->cleanUpExpiredTokens();
        $this->cleanUpIdentityRequests();
        $this->cleanUpWebhookResponses();
        $this->cleanUpCronjobErrors();
        $this->cleanUpLogErrors();
        $this->cleanUpFirewallExpired();
        $this->cleanUpFirewallLogs();
    }

    private function cleanUpExpiredTokens(): void
    {
        $this->purge(Table\Token::class, Table\Generated\TokenTable::COLUMN_EXPIRE, new \DateTime('first day of last month'));
    }

    private function cleanUpIdentityRequests(): void
    {
        $this->purge(Table\Generated\IdentityRequestTable::class, Table\Generated\IdentityRequestTable::COLUMN_INSERT_DATE, new \DateTime('first day of last month'));
    }

    private function cleanUpWebhookResponses(): void
    {
        $this->purge(Table\Webhook\Response::class, Table\Generated\WebhookResponseTable::COLUMN_INSERT_DATE, new \DateTime('first day of last month'));
    }

    private function cleanUpCronjobErrors(): void
    {
        $this->purge(Table\Cronjob\Error::class, Table\Generated\CronjobErrorTable::COLUMN_INSERT_DATE, new \DateTime('first day of -3 months'));
    }

    private function cleanUpLogErrors(): void
    {
        $this->purge(Table\Log\Error::class, Table\Generated\LogErrorTable::COLUMN_INSERT_DATE, new \DateTime('first day of -3 months'));
    }

    private function cleanUpFirewallExpired(): void
    {
        $this->purge(Table\Firewall::class, Table\Generated\FirewallTable::COLUMN_EXPIRE, new \DateTime('first day of last month'));
    }

    private function cleanUpFirewallLogs(): void
    {
        $this->purge(Table\Firewall\Log::class, Table\Generated\FirewallLogTable::COLUMN_INSERT_DATE, new \DateTime('first day of -3 months'));
    }

    private function purge(string $tableClass, string $column, \DateTime $before): void
    {
        $condition = \PSX\Sql\Condition::withAnd();
        $condition->less($column, $before);
        $this->tableManager->getTable($tableClass)->deleteByCondition($condition);
    }
}
