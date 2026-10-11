<?php
/*
 * Fusio - Self-Hosted API Management for Builders.
 * For the current version and information visit <https://www.fusio-project.org/>
 *
 * Copyright (c) Christoph Kappestein <christoph.kappestein@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Fusio\Impl\Installation;

use MongoDB\Database;
use PSX\Sql\MongoDB\MongoStorage;

/**
 * MongoDataSyncronizer
 *
 * MongoDB port of DataSyncronizer: inserts missing seed entries from
 * NewInstallation::getData() into mongo collections. Since MongoDB is
 * schemaless, schema-column filtering is not needed; Reference values are
 * resolved through collection lookups.
 *
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
class MongoDataSyncronizer
{
    /**
     * Tables whose entries are keyed by "name" (+ tenant_id) for dedup
     */
    private const NAME_KEYED = [
        'fusio_config', 'fusio_operation', 'fusio_action', 'fusio_schema',
        'fusio_event', 'fusio_cronjob', 'fusio_scope', 'fusio_page',
        'fusio_plan', 'fusio_category', 'fusio_role', 'fusio_connection',
        'fusio_identity', 'fusio_webhook', 'fusio_rate', 'fusio_agent',
    ];

    public static function sync(MongoStorage $storage, ?string $projectKey = null): void
    {
        $db = $storage->getDatabase();
        $data = NewInstallation::getData();

        foreach ($data->toArray() as $tableName => $rows) {
            foreach ($rows as $row) {
                $row = self::resolveReferences($row, $db);

                if (self::exists($tableName, $row, $db)) {
                    continue;
                }

                $row = array_filter($row, fn ($v) => $v !== null);
                self::insert($storage, $tableName, $row);
            }
        }

        self::patchSystemConnection($storage, $projectKey);
    }

    /**
     * The seeded "System" connection uses the DBAL-backed System class which can
     * not work under MongoDB. Rewire it to the mongodb adapter so health checks
     * and mongo-based actions resolve to the same backend storage uses.
     */
    private static function patchSystemConnection(MongoStorage $storage, ?string $projectKey): void
    {
        $dsn = $storage->getDsn();
        if ($dsn === null) {
            return;
        }

        $db = $storage->getDatabase();
        $collection = $db->selectCollection('fusio_connection');
        $row = $collection->findOne(['name' => 'System']);
        if ($row === null) {
            return;
        }

        $config = [
            'url'      => $dsn,
            'database' => $db->getDatabaseName(),
        ];
        $config = $projectKey !== null
            ? \Fusio\Impl\Service\Connection\Encrypter::encrypt($config, $projectKey)
            : \PSX\Json\Parser::encode($config);

        $collection->updateOne(
            ['_id' => $row->_id],
            ['$set' => [
                'class'  => \Fusio\Engine\Inflection\ClassName::serialize(\Fusio\Adapter\Mongodb\Connection\MongoDB::class),
                'config' => $config,
            ]]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function resolveReferences(array $row, Database $db): array
    {
        foreach ($row as $key => $value) {
            if ($value instanceof Reference) {
                $row[$key] = $value->resolveMongo($db);
            }
        }
        return $row;
    }

    private static function exists(string $tableName, array $row, Database $db): bool
    {
        $filter = [];
        if (in_array($tableName, self::NAME_KEYED, true) && isset($row['name'])) {
            $filter = ['name' => $row['name'], 'tenant_id' => $row['tenant_id'] ?? null];
        } elseif (isset($row['scope_id'], $row['operation_id'])) {
            $filter = ['scope_id' => $row['scope_id'], 'operation_id' => $row['operation_id']];
        } elseif (isset($row['app_id'], $row['scope_id'])) {
            $filter = ['app_id' => $row['app_id'], 'scope_id' => $row['scope_id']];
        } elseif (isset($row['route_id'], $row['operation_id'])) {
            $filter = ['route_id' => $row['route_id'], 'operation_id' => $row['operation_id']];
        } else {
            return false; // unknown dedup rule -> insert
        }
        return $db->selectCollection($tableName)->findOne($filter) !== null;
    }

    private static function insert(MongoStorage $storage, string $tableName, array $row): void
    {
        $db = $storage->getDatabase();
        if (!isset($row['id'])) {
            // sequence counter for the integer PK consumers expect
            $seq = $db->selectCollection('psx_sequences')->findOneAndUpdate(
                ['_id' => $tableName],
                ['$inc' => ['seq' => 1]],
                ['upsert' => true, 'returnDocument' => \MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER]
            );
            $row['id'] = (int) ($seq['seq'] ?? 1);
        }
        $db->selectCollection($tableName)->insertOne($row);
    }
}
