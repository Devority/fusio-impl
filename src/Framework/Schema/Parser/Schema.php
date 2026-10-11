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

namespace Fusio\Impl\Framework\Schema\Parser;

use Doctrine\DBAL\Connection;
use Fusio\Impl\Service\System\FrameworkConfig;
use Fusio\Impl\Table;
use PSX\Schema\Exception\ParserException;
use PSX\Schema\Parser\ContextInterface;
use PSX\Schema\Parser\Popo;
use PSX\Schema\Parser\TypeSchema;
use PSX\Schema\ParserInterface;
use PSX\Schema\SchemaInterface;
use PSX\Schema\SchemaManagerInterface;
use PSX\Sql\Condition;

/**
 * Schema
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org
 */
class Schema implements ParserInterface
{
    private Connection $connection;
    private Popo $popo;
    private TypeSchema $typeSchema;
    private FrameworkConfig $frameworkConfig;

    private Table\Schema $schemaTable;
    private Table\Schema\Commit $schemaCommitTable;

    public function __construct(Connection $connection, SchemaManagerInterface $schemaManager, FrameworkConfig $frameworkConfig, Table\Schema $schemaTable, Table\Schema\Commit $schemaCommitTable)
    {
        $this->connection = $connection;
        $this->popo = new Popo();
        $this->typeSchema = new TypeSchema($schemaManager);
        $this->frameworkConfig = $frameworkConfig;
        $this->schemaTable = $schemaTable;
        $this->schemaCommitTable = $schemaCommitTable;
    }

    public function parse(string $schema, ?ContextInterface $context = null): SchemaInterface
    {
        $hash = null;
        if (str_contains($schema, '@')) {
            $hash = substr(strstr($schema, '@'), 1);
            $schema = strstr($schema, '@', true);
        }

        $condition = Condition::withAnd();
        $condition->equals('sm.' . Table\Generated\SchemaTable::COLUMN_TENANT_ID, $this->frameworkConfig->getTenantId());

        if (is_numeric($schema)) {
            $condition->equals('sm.' . Table\Generated\SchemaTable::COLUMN_ID, (int) $schema);
        } else {
            $condition->equals('sm.' . Table\Generated\SchemaTable::COLUMN_NAME, ltrim($schema, '/'));
        }

        $schemaRow = $this->schemaTable->findOneBy($condition);
        $row = $schemaRow !== null ? $schemaRow->toRecord()->getAll() : null;
        if (empty($row)) {
            throw new ParserException('Could not find schema ' . $schema);
        }

        $source = $row[Table\Generated\SchemaTable::COLUMN_SOURCE] ?? throw new ParserException('Could not fetch schema source');
        if ($hash !== null) {
            $source = $this->resolveSourceByHash((int) $row[Table\Generated\SchemaTable::COLUMN_ID], $hash) ?? $source;
        }

        if (!str_contains($source, '{') && class_exists($source)) {
            return $this->popo->parse($source);
        } else {
            return $this->typeSchema->parse($source, $context);
        }
    }

    private function resolveSourceByHash(int $schemaId, string $hash): ?string
    {
        $condition = Condition::withAnd();
        $condition->equals(Table\Generated\SchemaCommitTable::COLUMN_SCHEMA_ID, $schemaId);
        $condition->equals(Table\Generated\SchemaCommitTable::COLUMN_COMMIT_HASH, $hash);

        $commit = $this->schemaCommitTable->findOneBy($condition);
        return $commit !== null ? $commit->getSource() : null;
    }
}
