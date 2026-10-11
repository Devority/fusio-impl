# MongoDB storage backend (Devority fork)

This fork adds a pluggable storage backend so Fusio's internal database can
run on **MongoDB** in addition to SQL databases.

## Architecture

Fusio persists everything through generated `Table` classes
(`src/Table/Generated/*Table.php`, ~144 classes) built on `PSX\Sql\TableAbstract`.
All CRUD flows through `doFind*`/`doCreate`/`doUpdate`/`doDelete` methods and
`PSX\Sql\Condition` trees — **no code talks raw SQL for entity data**.

We therefore introduce a storage engine at the `psx/sql` layer:

```
Service → Table\Entity (unchanged) → TableAbstract
                                          │
                          StorageInterface (new)
                          ┌───────────────┴───────────────┐
                     SqlStorage (default)          MongoStorage
                     Doctrine DBAL                 mongodb/mongodb
                          │                              │
                     mysql/pgsql/sqlite              collection per table
```

## Usage

```bash
FUSIO_CONNECTION="mongodb://user:pass@host:27017/fusio"
```

When the DSN scheme is `mongodb://` (or `mongodb+srv://`), the DI container
substitutes `MongoTableManager` (implements `StorageAwareTableManagerInterface`)
for `PSX\Sql\TableManager`. Every Table then persists to a Mongo collection
named after the table (`fusio_user`, `fusio_route`, …).

## Implementation notes

- **IDs**: `psx_sequences` collection + `$inc` counters emulate auto-increment.
- **Transactions**: Mongo sessions (replica set required; degraded no-op on standalone).
- **Conditions**: `Condition` trees translate to BSON filters — `$eq/$ne/$gt/$gte/$lt/$lte/$in/$nin/$regex/$and/$or/$nor`, `LIKE`→anchored regex, `BETWEEN`→`$gte+$lte`, `NIL`→`$eq null`.
- **Raw SQL conditions** (`Condition::raw(...)`) throw on MongoDB — they must be ported to Condition APIs (see tracking issues).
- **Migrations**: SQL schema migrations are skipped; seed data (`DataBag`) inserts documents through the same Table APIs, so installation works unchanged.

## Status / roadmap

See GitHub issues for the tracked tasks:
- [ ] audit & port remaining raw-SQL paths (Views, Statistics, ~15 call sites)
- [ ] secondary index bootstrap matching generated column metadata
- [ ] migration/version bookkeeping collection
- [ ] CI matrix: mysql/pgsql/sqlite/mongodb
