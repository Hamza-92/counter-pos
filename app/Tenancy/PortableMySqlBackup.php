<?php

namespace App\Tenancy;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Streaming MySQL backup/restore implementation for shared hosts that disable
 * proc_open. The generated file is ordinary SQL and can also be imported by
 * the mysql client or phpMyAdmin.
 */
final class PortableMySqlBackup
{
    private const ROWS_PER_INSERT = 200;

    public function dump(string $path, string $expectedDatabase): void
    {
        $connection = DB::connection('tenant');
        $actualDatabase = (string) ($connection->selectOne('SELECT DATABASE() AS db')->db ?? '');
        if ($actualDatabase === '' || ! hash_equals($expectedDatabase, $actualDatabase)) {
            throw new RuntimeException('Portable backup database identity verification failed.');
        }

        $stream = fopen($path, 'xb');
        if ($stream === false) {
            throw new RuntimeException('Unable to create the portable tenant backup file.');
        }
        @chmod($path, 0600);

        $snapshotStarted = false;
        try {
            $connection->statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $connection->beginTransaction();
            $snapshotStarted = true;

            $this->write($stream, "-- Counter POS portable tenant backup\n");
            $this->write($stream, '-- Database: '.$expectedDatabase."\n");
            $this->write($stream, '-- Generated: '.now()->toIso8601String()."\n\n");
            $this->write($stream, "SET NAMES utf8mb4;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\nSET FOREIGN_KEY_CHECKS=0;\n\n");

            foreach ($this->objects($connection, 'BASE TABLE') as $table) {
                $this->dumpTable($connection, $stream, $table);
            }

            foreach ($this->objects($connection, 'VIEW') as $view) {
                $row = (array) $connection->selectOne('SHOW CREATE VIEW '.$this->identifier($view));
                $create = $this->withoutDefiner((string) ($row['Create View'] ?? array_values($row)[1] ?? ''));
                if ($create !== '') {
                    $this->write($stream, 'DROP VIEW IF EXISTS '.$this->identifier($view).";\n".$create.";\n\n");
                }
            }

            $this->dumpTriggers($connection, $stream);
            $this->dumpRoutines($connection, $stream, $expectedDatabase);
            $this->dumpEvents($connection, $stream);
            $this->write($stream, "SET FOREIGN_KEY_CHECKS=1;\n");

            $connection->commit();
            $snapshotStarted = false;
        } catch (Throwable $exception) {
            if ($snapshotStarted && $connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
            fclose($stream);
            @unlink($path);
            throw $exception;
        }

        if (! fclose($stream)) {
            @unlink($path);
            throw new RuntimeException('Unable to finalize the portable tenant backup.');
        }
    }

    public function restore(string $path, string $expectedDatabase): void
    {
        $connection = DB::connection('tenant');
        $actualDatabase = (string) ($connection->selectOne('SELECT DATABASE() AS db')->db ?? '');
        if ($actualDatabase === '' || ! hash_equals($expectedDatabase, $actualDatabase)) {
            throw new RuntimeException('Portable restore database identity verification failed.');
        }

        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Unable to read the portable tenant backup.');
        }

        $delimiter = ';';
        $statement = '';
        try {
            while (($line = fgets($stream)) !== false) {
                $trimmed = trim($line);
                if (str_starts_with(strtoupper($trimmed), 'DELIMITER ')) {
                    $delimiter = trim(substr($trimmed, 10));
                    continue;
                }
                if ($statement === '' && ($trimmed === '' || str_starts_with($trimmed, '--'))) {
                    continue;
                }

                $statement .= $line;
                $withoutWhitespace = rtrim($statement);
                if ($delimiter !== '' && str_ends_with($withoutWhitespace, $delimiter)) {
                    $sql = trim(substr($withoutWhitespace, 0, -strlen($delimiter)));
                    if ($sql !== '') {
                        $connection->unprepared($sql);
                    }
                    $statement = '';
                }
            }

            if (trim($statement) !== '') {
                throw new RuntimeException('Portable backup ended with an incomplete SQL statement.');
            }
        } finally {
            fclose($stream);
        }
    }

    private function dumpTable(ConnectionInterface $connection, $stream, string $table): void
    {
        $quotedTable = $this->identifier($table);
        $row = (array) $connection->selectOne('SHOW CREATE TABLE '.$quotedTable);
        $create = (string) ($row['Create Table'] ?? array_values($row)[1] ?? '');
        if ($create === '') {
            throw new RuntimeException('Unable to read the schema for table '.$table.'.');
        }

        $this->write($stream, '-- Table '.$quotedTable."\nDROP TABLE IF EXISTS ".$quotedTable.";\n".$create.";\n\n");

        $columns = $connection->select('SHOW COLUMNS FROM '.$quotedTable);
        $columnNames = [];
        $binaryColumns = [];
        foreach ($columns as $column) {
            $columnNames[] = (string) $column->Field;
            if (preg_match('/(?:binary|blob|bit)/i', (string) $column->Type)) {
                $binaryColumns[(string) $column->Field] = true;
            }
        }
        if ($columnNames === []) {
            return;
        }

        $offset = 0;
        do {
            $rows = $connection->select(
                'SELECT * FROM '.$quotedTable.' LIMIT '.self::ROWS_PER_INSERT.' OFFSET '.$offset
            );
            if ($rows === []) {
                break;
            }

            $values = [];
            foreach ($rows as $dataRow) {
                $data = (array) $dataRow;
                $values[] = '('.implode(', ', array_map(
                    fn (string $column): string => $this->literal($data[$column] ?? null, isset($binaryColumns[$column])),
                    $columnNames,
                )).')';
            }

            $this->write(
                $stream,
                'INSERT INTO '.$quotedTable.' ('.implode(', ', array_map($this->identifier(...), $columnNames)).") VALUES\n".
                implode(",\n", $values).";\n"
            );
            $offset += count($rows);
        } while (count($rows) === self::ROWS_PER_INSERT);

        $this->write($stream, "\n");
    }

    private function dumpTriggers(ConnectionInterface $connection, $stream): void
    {
        foreach ($this->optionalSelect($connection, 'SHOW TRIGGERS') as $trigger) {
            $name = (string) ($trigger->Trigger ?? '');
            if ($name === '') {
                continue;
            }
            $row = (array) $connection->selectOne('SHOW CREATE TRIGGER '.$this->identifier($name));
            $create = $this->withoutDefiner((string) ($row['SQL Original Statement'] ?? array_values($row)[2] ?? ''));
            $this->writeDelimitedObject($stream, 'TRIGGER', $name, $create);
        }
    }

    private function dumpRoutines(ConnectionInterface $connection, $stream, string $database): void
    {
        foreach (['PROCEDURE', 'FUNCTION'] as $type) {
            foreach ($this->optionalSelect($connection, 'SHOW '.$type.' STATUS WHERE Db = ?', [$database]) as $routine) {
                $name = (string) ($routine->Name ?? '');
                if ($name === '') {
                    continue;
                }
                $row = (array) $connection->selectOne('SHOW CREATE '.$type.' '.$this->identifier($name));
                $createKey = $type === 'PROCEDURE' ? 'Create Procedure' : 'Create Function';
                $create = $this->withoutDefiner((string) ($row[$createKey] ?? ''));
                $this->writeDelimitedObject($stream, $type, $name, $create);
            }
        }
    }

    private function dumpEvents(ConnectionInterface $connection, $stream): void
    {
        foreach ($this->optionalSelect($connection, 'SHOW EVENTS') as $event) {
            $name = (string) ($event->Name ?? '');
            if ($name === '') {
                continue;
            }
            $row = (array) $connection->selectOne('SHOW CREATE EVENT '.$this->identifier($name));
            $create = $this->withoutDefiner((string) ($row['Create Event'] ?? ''));
            $this->writeDelimitedObject($stream, 'EVENT', $name, $create);
        }
    }

    private function writeDelimitedObject($stream, string $type, string $name, string $create): void
    {
        if ($create === '') {
            return;
        }
        $this->write(
            $stream,
            "DELIMITER $$\nDROP ".$type.' IF EXISTS '.$this->identifier($name)."$$\n".$create."$$\nDELIMITER ;\n\n"
        );
    }

    private function objects(ConnectionInterface $connection, string $type): array
    {
        $names = [];
        foreach ($connection->select("SHOW FULL TABLES WHERE Table_type = '".$type."'") as $row) {
            $values = array_values((array) $row);
            if (isset($values[0])) {
                $names[] = (string) $values[0];
            }
        }

        return $names;
    }

    private function optionalSelect(ConnectionInterface $connection, string $query, array $bindings = []): array
    {
        try {
            return $connection->select($query, $bindings);
        } catch (Throwable) {
            // Some shared-hosting runtime users cannot inspect global routine
            // or event metadata. Tables and their data remain mandatory.
            return [];
        }
    }

    private function literal(mixed $value, bool $binary): string
    {
        if ($value === null) {
            return 'NULL';
        }
        $value = (string) $value;
        if ($binary) {
            return "X'".bin2hex($value)."'";
        }

        return "'".str_replace(
            ["\\", "\0", "\n", "\r", "\x1a", "'"],
            ["\\\\", "\\0", "\\n", "\\r", "\\Z", "\\'"],
            $value,
        )."'";
    }

    private function identifier(string $value): string
    {
        return '`'.str_replace('`', '``', $value).'`';
    }

    private function withoutDefiner(string $sql): string
    {
        return preg_replace('/\sDEFINER\s*=\s*`[^`]*`@`[^`]*`/i', '', $sql) ?? $sql;
    }

    private function write($stream, string $contents): void
    {
        if (fwrite($stream, $contents) === false) {
            throw new RuntimeException('Unable to write the portable tenant backup.');
        }
    }
}
