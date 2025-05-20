<?php

namespace Models\Inventory\Services;

use Zephyrus\Database\Core\Database;
use Zephyrus\Application\Configuration;
use PDO;
use PDOException;
use Exception;
use Zephyrus\Database\Core\DatabaseStatement;

class DatabaseService
{

    private static ?self $instance = null;

    private Database $database;

    private function __construct()
    {
        try {
            $config = Configuration::getDatabase();
            $this->database = new Database($config);
        } catch (Exception $e) {
            error_log("Erreur de DB: " . $e->getMessage());
            throw $e;
        }
    }

    public static function getInstance(): self
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getDatabase(): Database
    {
        return $this->database;
    }

    public function getPdo(): PDO
    {
        return $this->database->getPdo();
    }


    public function query(string $query, array $params = []): \Zephyrus\Database\Core\DatabaseStatement
    {
        try {
            $statement = $this->database->query($query, $params);
            return $statement;
        } catch (PDOException $e) {
            error_log("Erreur SQL: " . $e->getMessage() . " - Requête: " . $query);
            throw $e;
        }
    }

    public function fetch(DatabaseStatement $statement): ?array
    {
        $result = $statement->next();
        if ($result === null) {
            return null;
        }
        return (array)$result;
    }

    public function fetchAll(DatabaseStatement $statement): array
    {
        $results = [];
        while ($row = $statement->next()) {
            $results[] = $row;
        }
        return $results;
    }


    public function beginTransaction(): bool
    {
        return $this->database->getPdo()->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->database->getPdo()->commit();
    }

    public function rollback(): bool
    {
        return $this->database->getPdo()->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->database->getPdo()->inTransaction();
    }

    public function getLastInsertedId(?string $sequenceName = null)
    {
        return $this->database->getPdo()->lastInsertId($sequenceName);
    }

    public function querySingle(string $query, array $params = []): ?array
    {
        $statement = $this->query($query, $params);
        return $this->fetch($statement);
    }

    public function queryAll(string $query, array $params = []): array
    {
        $statement = $this->query($query, $params);
        return $this->fetchAll($statement);
    }

    public function insert(string $table, array $data, ?string $sequenceName = null)
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $query = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        $this->query($query, array_values($data));

        return $this->getLastInsertedId($sequenceName);
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $setClauses = [];
        $params = [];

        foreach ($data as $column => $value) {
            $setClauses[] = "$column = ?";
            $params[] = $value;
        }

        $setClause = implode(', ', $setClauses);
        $query = "UPDATE $table SET $setClause WHERE $where";

        $params = array_merge($params, $whereParams);

        $statement = $this->query($query, $params);
        return $statement->rowCount();
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        $query = "DELETE FROM $table WHERE $where";
        $statement = $this->query($query, $params);
        return $statement->rowCount();
    }

    public function exists(string $table, string $column, $value, string $additionalWhere = '', array $additionalParams = []): bool
    {
        $query = "SELECT COUNT(*) as count FROM $table WHERE $column = ?";
        $params = [$value];

        if (!empty($additionalWhere)) {
            $query .= " AND $additionalWhere";
            $params = array_merge($params, $additionalParams);
        }

        $result = $this->querySingle($query, $params);
        return ($result['count'] ?? 0) > 0;
    }

    public function count(string $table, string $where = '', array $params = []): int
    {
        $query = "SELECT COUNT(*) as count FROM $table";

        if (!empty($where)) {
            $query .= " WHERE $where";
        }

        $result = $this->querySingle($query, $params);
        return $result['count'] ?? 0;
    }

    public function createMap(string $query, string $keyColumn, string $valueColumn, array $params = []): array
    {
        $results = $this->queryAll($query, $params);
        $map = [];

        foreach ($results as $row) {
            if (isset($row[$keyColumn]) && isset($row[$valueColumn])) {
                $map[$row[$keyColumn]] = $row[$valueColumn];
            }
        }

        return $map;
    }

    public function escapeValue($value): string
    {
        if (is_string($value)) {
            // Échapper les caractères spéciaux
            return "'" . $this->database->getPdo()->quote($value) . "'";
        } elseif (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        } elseif (is_null($value)) {
            return 'NULL';
        } elseif (is_array($value)) {
            // Pour les tableaux, échapper chaque élément et les joindre
            $escapedValues = array_map([$this, 'escapeValue'], $value);
            return '(' . implode(', ', $escapedValues) . ')';
        }

        // Pour les nombres, les retourner tels quels
        return (string) $value;
    }

}