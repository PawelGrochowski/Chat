<?php
class Database {

    private $connection = null;

    public function __construct() {
        $this->connection = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if (!$this->connection) {
            die('Błąd połączenia: ' . mysqli_connect_error());
        }

        mysqli_set_charset($this->connection, 'utf8mb4');
    }

    
    public function insertRows($table, $data) {

    unset($data['id']);

    $fields = array_keys($data);
    $values = [];

    foreach ($data as $value) {

        if (is_null($value)) {
            $values[] = "NULL";
        } else {
            $values[] = "'" . $this->escape($value) . "'";
        }
    }

    $fields_str = '`' . implode('`, `', $fields) . '`';
    $values_str = implode(", ", $values);

    $query = "INSERT INTO `$table` ($fields_str) VALUES ($values_str)";

    if (!mysqli_query($this->connection, $query)) {
        die('Błąd zapytania: ' . mysqli_error($this->connection) . "\nSQL: " . $query);
    }

    return true;
}

    
    public function getRows($table, $fields = '*', $where = [], $glue = 'AND', $orderBy = null, $limit = null) {

        if (is_array($fields)) {
            $fields = '`' . implode('`, `', $fields) . '`';
        }

        $query = "SELECT $fields FROM `$table`";

        if (!empty($where)) {

            $where_clauses = [];

            foreach ($where as $key => $value) {

                if ($value === null) {
                    $where_clauses[] = "`$key` IS NULL";
                } else {
                    $where_clauses[] = "`$key` = '" . $this->escape($value) . "'";
                }
            }

            $query .= " WHERE " . implode(" $glue ", $where_clauses);
        }

        if ($orderBy) {
            $query .= " ORDER BY $orderBy";
        }

        if ($limit) {
            $query .= " LIMIT $limit";
        }

        $result = mysqli_query($this->connection, $query);

        if (!$result) {
            die('Błąd zapytania: ' . mysqli_error($this->connection) . "\nSQL: " . $query);
        }

        $rows = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }

        mysqli_free_result($result);

        return $rows;
    }

    public function getRow($table, $fields = '*', $where = [], $glue = 'AND', $orderBy = null) {
        $rows = $this->getRows($table, $fields, $where, $glue, $orderBy, 1);
        return $rows[0] ?? [];
    }

    
    public function updateRows($table, $data, $where) {
        $set_clauses = [];
        foreach ($data as $key => $value) {
            if ($value === null) {
                $set_clauses[] = "`$key` = NULL";
            } else {
                $set_clauses[] = "`$key` = '" . $this->escape($value) . "'";
            }
        }
        $set_str = implode(', ', $set_clauses);

        $where_clauses = [];
        foreach ($where as $key => $value) {
            if ($value === null) {
                $where_clauses[] = "`$key` IS NULL";
            } else {
                $where_clauses[] = "`$key` = '" . $this->escape($value) . "'";
            }
        }
        $where_str = implode(' AND ', $where_clauses);

        $query = "UPDATE `$table` SET $set_str WHERE $where_str";

        if (!mysqli_query($this->connection, $query)) {
            die('Błąd zapytania: ' . mysqli_error($this->connection) . "\nSQL: " . $query);
        }

        return mysqli_affected_rows($this->connection);
    }

    public function query($sql) {
        $result = mysqli_query($this->connection, $sql);

        if (!$result) {
            die('Błąd zapytania: ' . mysqli_error($this->connection));
        }

        return $result;
    }

    public function getLastInsertId() {
        return mysqli_insert_id($this->connection);
    }

    public function escape($string) {
        return mysqli_real_escape_string($this->connection, $string);
    }

    public function close() {
        if ($this->connection) {
            mysqli_close($this->connection);
        }
    }

    public function __destruct() {
        $this->close();
    }
}