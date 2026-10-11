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

namespace Fusio\Impl\Command\System;

use Fusio\Impl\Installation\MongoDataSyncronizer;
use MongoDB\Database;
use PSX\Sql\MongoDB\MongoStorage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * MongoInstallCommand
 *
 * Seeds a fresh MongoDB-backed Fusio installation with the default data
 * (config, categories, scopes, operations, actions, roles, plans, apps).
 * DDL/schema steps are intentionally skipped — collections are implicit.
 *
 * Usage: bin/fusio system:install:mongo
 *
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    https://www.fusio-project.org/
 */
class MongoInstallCommand extends Command
{
    public function __construct(private \PSX\Sql\TableManagerInterface $tableManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('system:install:mongo')
            ->setDescription('Seed a MongoDB-backed Fusio installation (replaces migration:migrate)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->tableManager instanceof \PSX\Sql\Storage\StorageAwareTableManagerInterface) {
            $output->writeln('<error>MongoDB backend is not active — set FUSIO_CONNECTION=mongodb://...</error>');
            return self::FAILURE;
        }

        $storage = $this->tableManager->getStorage();
        if (!$storage instanceof MongoStorage) {
            $output->writeln('<error>Storage backend is not MongoStorage</error>');
            return self::FAILURE;
        }

        $output->writeln('Seeding MongoDB installation data...');
        MongoDataSyncronizer::sync($storage);
        $output->writeln('<info>Done — Fusio seed data written to MongoDB.</info>');

        return self::SUCCESS;
    }
}
