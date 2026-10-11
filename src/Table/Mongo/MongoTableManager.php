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

namespace Fusio\Impl\Table\Mongo;

use Doctrine\DBAL\Connection;
use PSX\Sql\InvalidTableException;
use PSX\Sql\MongoDB\MongoStorage;
use PSX\Sql\Storage\StorageAwareTableManagerInterface;
use PSX\Sql\Storage\StorageInterface;
use PSX\Sql\ViewInterface;

/**
 * MongoTableManager
 *
 * TableManager backed by a MongoDB storage engine. Activated by pointing
 * FUSIO_CONNECTION at a "mongodb://" DSN. All generated Table classes keep
 * working unchanged because TableAbstract delegates to the storage engine.
 *
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
class MongoTableManager implements StorageAwareTableManagerInterface
{
    private MongoStorage $storage;
    private array $container = [];

    public function __construct(MongoStorage $storage)
    {
        $this->storage = $storage;
    }

    public static function fromDsn(string $dsn): self
    {
        return new self(MongoStorage::fromDsn($dsn));
    }

    public function getStorage(): StorageInterface
    {
        return $this->storage;
    }

    public function getConnection(): Connection
    {
        // No DBAL connection exists under a MongoDB backend. Code paths that
        // still use raw SQL must be ported to Table APIs or the storage engine.
        throw new \LogicException('No DBAL connection available when the MongoDB storage backend is active');
    }

    public function getTable(string $tableClass): ViewInterface
    {
        if (isset($this->container[$tableClass])) {
            return $this->container[$tableClass];
        }

        if (!class_exists($tableClass)) {
            throw new InvalidTableException('Provided table ' . $tableClass . ' does not exist');
        }

        return $this->container[$tableClass] = new $tableClass($this);
    }
}
