<?php

/**
 *  ◤━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◥
 *      Core\Blueprint
 *      นิยามคอลัมน์ ดัชนี และ foreign key ของตาราง
 *      ใช้กับ Schema::create() และ Schema::table()
 *
 *      @author   (deawx) Tirapong Chaiyakun <msdos43@gmail.com>
 *      @license  MIT  https://cyberthai.net
 * ◣━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━◢
 */

declare(strict_types=1);

namespace Core;

use InvalidArgumentException;

final class Blueprint
{
    /** @var array<string, list<string>> */
    private array $columns = [];

    /** @var list<string> */
    private array $constraints = [];

    /** @var list<string> */
    private array $dropped = [];

    private ?string $idMarker = null;

    private ?string $idComment = null;

    private bool $expectIdComment = false;

    private ?string $tableComment = null;

    /** @var array{column: string, onTable: string, references: string, onDelete: string}|null */
    private ?array $pendingForeign = null;

    /** @var array{name: string, type: string, nullable: bool, unique: bool, index: bool, default: mixed, comment: ?string}|null */
    private ?array $pending = null;

    public function id(string $name = 'id'): static
    {
        $this->flush();
        self::ident($name);
        $this->idMarker = $name === 'id' ? '@id' : '@' . $name;
        $this->idComment = null;
        $this->expectIdComment = true;

        return $this;
    }

    /** ส่ง SQL type เอง (อนุญาตเฉพาะอักขระปลอดภัย) */
    public function column(string $name, string $type): static
    {
        $type = trim($type);
        if ($type === '' || preg_match('/^[A-Za-z0-9_(),.\s]+$/', $type) !== 1) {
            throw new InvalidArgumentException('Invalid column type');
        }

        return $this->addColumn($name, preg_replace('/\s+/', ' ', $type) ?? $type);
    }

    public function string(string $name, int $length = 255): static
    {
        $length = max(1, min(16383, $length));

        return $this->addColumn($name, 'VARCHAR(' . $length . ')');
    }

    public function char(string $name, int $length = 255): static
    {
        $length = max(1, min(255, $length));

        return $this->addColumn($name, 'CHAR(' . $length . ')');
    }

    public function text(string $name): static
    {
        return $this->addColumn($name, 'TEXT');
    }

    public function mediumText(string $name): static
    {
        return $this->addColumn($name, 'MEDIUMTEXT');
    }

    public function longText(string $name): static
    {
        return $this->addColumn($name, 'LONGTEXT');
    }

    public function tinyText(string $name): static
    {
        return $this->addColumn($name, 'TINYTEXT');
    }

    public function json(string $name): static
    {
        return $this->addColumn($name, 'JSON');
    }

    public function integer(string $name): static
    {
        return $this->addColumn($name, 'INT');
    }

    public function tinyInteger(string $name): static
    {
        return $this->addColumn($name, 'TINYINT');
    }

    public function smallInteger(string $name): static
    {
        return $this->addColumn($name, 'SMALLINT');
    }

    public function mediumInteger(string $name): static
    {
        return $this->addColumn($name, 'MEDIUMINT');
    }

    public function bigInteger(string $name): static
    {
        return $this->addColumn($name, 'BIGINT');
    }

    public function unsignedInteger(string $name): static
    {
        return $this->addColumn($name, 'INT UNSIGNED');
    }

    public function unsignedTinyInteger(string $name): static
    {
        return $this->addColumn($name, 'TINYINT UNSIGNED');
    }

    public function unsignedSmallInteger(string $name): static
    {
        return $this->addColumn($name, 'SMALLINT UNSIGNED');
    }

    public function unsignedMediumInteger(string $name): static
    {
        return $this->addColumn($name, 'MEDIUMINT UNSIGNED');
    }

    public function unsignedBigInteger(string $name): static
    {
        return $this->addColumn($name, 'BIGINT UNSIGNED');
    }

    public function boolean(string $name): static
    {
        return $this->addColumn($name, 'TINYINT(1)');
    }

    public function decimal(string $name, int $precision = 10, int $scale = 2): static
    {
        $precision = max(1, min(65, $precision));
        $scale = max(0, min($precision, $scale));

        return $this->addColumn($name, 'DECIMAL(' . $precision . ',' . $scale . ')');
    }

    public function float(string $name, int $precision = 8, int $scale = 2): static
    {
        $precision = max(1, min(53, $precision));
        $scale = max(0, min($precision, $scale));

        return $this->addColumn($name, 'FLOAT(' . $precision . ',' . $scale . ')');
    }

    public function double(string $name, ?int $precision = null, ?int $scale = null): static
    {
        if ($precision === null) {
            return $this->addColumn($name, 'DOUBLE');
        }
        $precision = max(1, min(53, $precision));
        $scale = max(0, min($precision, $scale ?? 0));

        return $this->addColumn($name, 'DOUBLE(' . $precision . ',' . $scale . ')');
    }

    public function date(string $name): static
    {
        return $this->addColumn($name, 'DATE');
    }

    public function dateTime(string $name, int $precision = 0): static
    {
        $precision = max(0, min(6, $precision));

        return $this->addColumn($name, $precision > 0 ? 'DATETIME(' . $precision . ')' : 'DATETIME');
    }

    public function time(string $name, int $precision = 0): static
    {
        $precision = max(0, min(6, $precision));

        return $this->addColumn($name, $precision > 0 ? 'TIME(' . $precision . ')' : 'TIME');
    }

    public function year(string $name): static
    {
        return $this->addColumn($name, 'YEAR');
    }

    public function timestamp(string $name, int $precision = 0): static
    {
        $precision = max(0, min(6, $precision));

        return $this->addColumn($name, $precision > 0 ? 'TIMESTAMP(' . $precision . ')' : 'TIMESTAMP');
    }

    public function timestamps(int $precision = 0): static
    {
        $this->flush();
        $this->expectIdComment = false;
        $type = $precision > 0 ? 'TIMESTAMP(' . max(0, min(6, $precision)) . ')' : 'TIMESTAMP';
        $this->columns['created_at'] = [$type, 'NULL'];
        $this->columns['updated_at'] = [$type, 'NULL'];

        return $this;
    }

    public function softDeletes(string $column = 'deleted_at', int $precision = 0): static
    {
        return $this->timestamp($column, $precision)->nullable();
    }

    public function binary(string $name, int $length = 255): static
    {
        $length = max(1, min(8000, $length));

        return $this->addColumn($name, 'BINARY(' . $length . ')');
    }

    public function varbinary(string $name, int $length = 255): static
    {
        $length = max(1, min(65535, $length));

        return $this->addColumn($name, 'VARBINARY(' . $length . ')');
    }

    public function blob(string $name): static
    {
        return $this->addColumn($name, 'BLOB');
    }

    public function tinyBlob(string $name): static
    {
        return $this->addColumn($name, 'TINYBLOB');
    }

    public function mediumBlob(string $name): static
    {
        return $this->addColumn($name, 'MEDIUMBLOB');
    }

    public function longBlob(string $name): static
    {
        return $this->addColumn($name, 'LONGBLOB');
    }

    /**
     * @param list<string|int> $allowed
     */
    public function enum(string $name, array $allowed): static
    {
        return $this->addColumn($name, 'ENUM(' . self::quoteList($allowed) . ')');
    }

    /**
     * @param list<string|int> $allowed
     */
    public function set(string $name, array $allowed): static
    {
        return $this->addColumn($name, 'SET(' . self::quoteList($allowed) . ')');
    }

    public function uuid(string $name = 'uuid'): static
    {
        return $this->addColumn($name, 'CHAR(36)');
    }

    public function ulid(string $name = 'ulid'): static
    {
        return $this->addColumn($name, 'CHAR(26)');
    }

    public function ipAddress(string $name = 'ip_address'): static
    {
        return $this->addColumn($name, 'VARCHAR(45)');
    }

    public function macAddress(string $name = 'mac_address'): static
    {
        return $this->addColumn($name, 'VARCHAR(17)');
    }

    public function rememberToken(): static
    {
        return $this->string('remember_token', 100)->nullable();
    }

    public function unsigned(): static
    {
        if ($this->pending !== null && !str_contains(strtoupper($this->pending['type']), 'UNSIGNED')) {
            $this->pending['type'] .= ' UNSIGNED';
        }

        return $this;
    }

    public function nullable(): static
    {
        if ($this->pending !== null) {
            $this->pending['nullable'] = true;
        }

        return $this;
    }

    public function unique(): static
    {
        if ($this->pending !== null) {
            $this->pending['unique'] = true;
        }

        return $this;
    }

    /**
     * Comment ของคอลัมน์ล่าสุด หรือของตาราง
     * `$table->id()->comment('...')` = comment ของ PK
     * `$table->string('email')->comment('...')` = comment ของคอลัมน์
     * `$table->comment('...')` เมื่อไม่มีคอลัมน์ค้าง = comment ของตาราง
     */
    public function comment(string $text): static
    {
        $text = self::sanitizeComment($text);

        if ($this->pending !== null) {
            $this->pending['comment'] = $text;

            return $this;
        }

        if ($this->expectIdComment) {
            $this->idComment = $text;
            $this->expectIdComment = false;

            return $this;
        }

        $this->tableComment = $text;

        return $this;
    }

    public function tableComment(): ?string
    {
        return $this->tableComment;
    }

    /**
     * @param string|list<string>|null $columns
     */
    public function index(string|array|null $columns = null): static
    {
        if ($columns === null) {
            if ($this->pending !== null) {
                $this->pending['index'] = true;
            }

            return $this;
        }

        $this->addIndex($columns, false);

        return $this;
    }

    /**
     * @param string|list<string> $columns
     */
    public function uniqueIndex(string|array $columns): static
    {
        $this->addIndex($columns, true);

        return $this;
    }

    public function dropColumn(string $name): static
    {
        $this->flush();
        $this->dropped[] = self::ident($name);

        return $this;
    }

    public function default(mixed $value): static
    {
        if ($this->pending !== null) {
            $this->pending['default'] = $value;
        }

        return $this;
    }

    public function foreignId(string $column, string $onTable, string $onDelete = 'CASCADE'): static
    {
        $this->bigInteger($column);
        $action = strtoupper($onDelete);
        if (!in_array($action, ['CASCADE', 'SET NULL', 'RESTRICT', 'NO ACTION'], true)) {
            throw new InvalidArgumentException('Invalid ON DELETE action');
        }
        self::ident($onTable);
        $this->pendingForeign = [
            'column' => $column,
            'onTable' => $onTable,
            'references' => 'id',
            'onDelete' => $action,
        ];

        return $this;
    }

    public function foreign(string $column, string $onTable, string $references = 'id', string $onDelete = 'CASCADE'): static
    {
        $this->flush();
        self::ident($column);
        self::ident($onTable);
        self::ident($references);

        $action = strtoupper($onDelete);
        if (!in_array($action, ['CASCADE', 'SET NULL', 'RESTRICT', 'NO ACTION'], true)) {
            throw new InvalidArgumentException('Invalid ON DELETE action');
        }

        $this->constraints[] = 'FOREIGN KEY (<' . $column . '>) REFERENCES <' . $onTable . '> (<' . $references . '>) ON DELETE ' . $action;

        return $this;
    }

    /**
     * คอลัมน์ในรูปแบบที่ Medoo create() ใช้
     *
     * @return array<int|string, string|list<string>>
     */
    public function toColumns(): array
    {
        $this->flush();
        $columns = [];

        if ($this->idMarker !== null) {
            if ($this->idComment !== null) {
                $name = $this->idMarker === '@id' ? 'id' : ltrim($this->idMarker, '@');
                $columns[$name] = [
                    'BIGINT',
                    'NOT NULL',
                    'AUTO_INCREMENT',
                    'PRIMARY KEY',
                    self::quoteComment($this->idComment),
                ];
            } else {
                $columns[] = $this->idMarker;
            }
        }

        foreach ($this->columns as $name => $definition) {
            $columns[$name] = $definition;
        }

        foreach ($this->constraints as $constraint) {
            $columns[] = $constraint;
        }

        return $columns;
    }

    /**
     * @return list<string>
     */
    public function addedColumns(): array
    {
        $this->flush();

        return array_map(static fn(string|int $name): string => (string) $name, array_keys($this->columns));
    }

    /**
     * @return list<string>
     */
    public function toAlterStatements(string $table): array
    {
        $this->flush();
        $ident = '"' . self::ident($table) . '"';
        $sql = [];

        foreach ($this->columns as $name => $parts) {
            $sql[] = 'ALTER TABLE ' . $ident . ' ADD COLUMN "' . self::ident((string) $name) . '" ' . implode(' ', $parts);
        }

        foreach ($this->constraints as $constraint) {
            $sql[] = 'ALTER TABLE ' . $ident . ' ADD ' . self::toMysqlConstraint($constraint);
        }

        foreach ($this->dropped as $name) {
            $sql[] = 'ALTER TABLE ' . $ident . ' DROP COLUMN "' . $name . '"';
        }

        return $sql;
    }

    public static function ident(string $name): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new InvalidArgumentException('Invalid SQL identifier');
        }

        return $name;
    }

    private function addColumn(string $name, string $type): static
    {
        $this->flush();
        $this->expectIdComment = false;
        self::ident($name);
        $this->pending = [
            'name' => $name,
            'type' => $type,
            'nullable' => false,
            'unique' => false,
            'index' => false,
            'default' => null,
            'comment' => null,
        ];

        return $this;
    }

    private function flush(): void
    {
        if ($this->pending === null) {
            return;
        }

        $parts = [$this->pending['type']];
        $parts[] = $this->pending['nullable'] ? 'NULL' : 'NOT NULL';

        if ($this->pending['default'] !== null) {
            $parts[] = 'DEFAULT ' . $this->quoteDefault($this->pending['default']);
        }

        if ($this->pending['unique']) {
            $parts[] = 'UNIQUE';
        }

        if ($this->pending['comment'] !== null && $this->pending['comment'] !== '') {
            $parts[] = self::quoteComment($this->pending['comment']);
        }

        $name = $this->pending['name'];
        $this->columns[$name] = $parts;

        if ($this->pending['index']) {
            $this->constraints[] = 'INDEX (<' . $name . '>)';
        }

        $fk = $this->pendingForeign;
        $this->pendingForeign = null;
        $this->pending = null;

        if ($fk !== null && $fk['column'] === $name) {
            $this->constraints[] = 'FOREIGN KEY (<' . $fk['column'] . '>) REFERENCES <' . $fk['onTable'] . '> (<' . $fk['references'] . '>) ON DELETE ' . $fk['onDelete'];
        }
    }

    /**
     * @param string|list<string> $columns
     */
    private function addIndex(string|array $columns, bool $unique): void
    {
        $this->flush();
        $names = is_array($columns) ? $columns : [$columns];
        $quoted = [];
        foreach ($names as $name) {
            $quoted[] = '<' . self::ident($name) . '>';
        }

        $this->constraints[] = ($unique ? 'UNIQUE INDEX' : 'INDEX') . ' (' . implode(', ', $quoted) . ')';
    }

    private function quoteDefault(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return "'" . str_replace("'", "''", (string) $value) . "'";
    }

    /**
     * @param list<string|int> $allowed
     */
    private static function quoteList(array $allowed): string
    {
        if ($allowed === []) {
            throw new InvalidArgumentException('ENUM/SET ต้องมีอย่างน้อย 1 ค่า');
        }

        $parts = [];
        foreach ($allowed as $value) {
            if (!is_string($value) && !is_int($value)) {
                throw new InvalidArgumentException('ENUM/SET รับได้แค่ string หรือ int');
            }
            $parts[] = "'" . str_replace("'", "''", (string) $value) . "'";
        }

        return implode(',', $parts);
    }

    private static function sanitizeComment(string $text): string
    {
        return trim(str_replace(["\0", "\r", "\n"], ' ', $text));
    }

    private static function quoteComment(string $text): string
    {
        return "COMMENT '" . str_replace("'", "''", self::sanitizeComment($text)) . "'";
    }

    private static function toMysqlConstraint(string $constraint): string
    {
        return preg_replace_callback(
            '/<([A-Za-z_][A-Za-z0-9_]*)>/',
            static fn(array $match): string => '"' . $match[1] . '"',
            $constraint,
        ) ?? $constraint;
    }
}
