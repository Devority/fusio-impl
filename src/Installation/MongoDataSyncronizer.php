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
        'fusio_user', 'fusio_app',
    ];

    private const COMPOUND_KEYS = [
        'fusio_user_scope'       => ['user_id', 'scope_id'],
        'fusio_role_scope'       => ['role_id', 'scope_id'],
        'fusio_plan_scope'       => ['plan_id', 'scope_id'],
        'fusio_scope_operation'  => ['scope_id', 'operation_id'],
        'fusio_rate_allocation'  => ['rate_id', 'operation_id'],
        'fusio_app_scope'        => ['app_id', 'scope_id'],
        'fusio_user_grant'       => ['user_id', 'app_id'],
        'fusio_webhook'          => ['name'],
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
        if (isset(self::COMPOUND_KEYS[$tableName])) {
            foreach (self::COMPOUND_KEYS[$tableName] as $col) {
                if (!array_key_exists($col, $row)) {
                    return false;
                }
                $filter[$col] = $row[$col];
            }
        } elseif (in_array($tableName, self::NAME_KEYED, true) && isset($row['name'])) {
            $filter = ['name' => $row['name'], 'tenant_id' => $row['tenant_id'] ?? null];
        } else {
            return false; // unknown dedup rule -> insert
        }
        return $db->selectCollection($tableName)->findOne($filter) !== null;
    }

    /**
     * Column defaults which SQL DDL supplies — applied for missing keys only
     */
    private const DEFAULTS = [
        'fusio_action'       => ['status' => 1],
        'fusio_agent'        => ['status' => 1],
        'fusio_app'          => ['status' => 1],
        'fusio_connection'   => ['status' => 1],
        'fusio_cronjob'      => ['status' => 1],
        'fusio_event'        => ['status' => 1],
        'fusio_operation'    => ['status' => 1, 'active' => 1, 'public' => 0, 'stability' => 2, 'costs' => 0],
        'fusio_page'         => ['status' => 1],
        'fusio_plan'         => ['status' => 1],
        'fusio_rate'         => ['status' => 1],
        'fusio_role'         => ['status' => 1],
        'fusio_schema'       => ['status' => 1],
        'fusio_scope'        => ['status' => 1],
        'fusio_user'         => ['status' => 1],
        'fusio_webhook'      => ['status' => 1],
        'fusio_scope_operation' => ['allow' => 1],
    ];

    private static function insert(MongoStorage $storage, string $tableName, array $row): void
    {
        $row += self::DEFAULTS[$tableName] ?? [];

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
