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

namespace Fusio\Impl\Table\Schema;

use Fusio\Impl\Table\Generated;

/**
 * Commit
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org
 */
class Commit extends Generated\SchemaCommitTable
{
    /**
     * @return array{string|null, string|null}
     */
    public function findCurrentHash(int $schemaId): array
    {
        $condition = \PSX\Sql\Condition::withAnd();
        $condition->equals(self::COLUMN_SCHEMA_ID, $schemaId);
        $rows = $this->findAll($condition, 0, 1, Generated\SchemaCommitColumn::ID, \PSX\Sql\OrderBy::DESC);
        $row = $rows[0] ?? null;

        if ($row === null) {
            return [null, null];
        }

        return [$row->getCommitHash(), $row->getSourceHash()];
    }

    /**
     * @return array<mixed>
     */
    public function findAllLatestCommitIds(): array
    {
        $latest = [];
        foreach ($this->findAll(null, 0, 0, Generated\SchemaCommitColumn::ID, \PSX\Sql\OrderBy::ASC) as $row) {
            $latest[$row->getSchemaId()] = $row->getId();
        }
        return array_values($latest);
    }
}
