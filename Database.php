<?php
class Database {
    private $connection;
    private static $instance;

    private function __construct() {
        $this->connect();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function connect() {
        try {
            $this->connection = new mysqli(
                DB_HOST,
                DB_USER,
                DB_PASSWORD,
                DB_NAME
            );

            if ($this->connection->connect_error) {
                throw new Exception('Database connection failed: ' . $this->connection->connect_error);
            }

            $this->connection->set_charset('utf8mb4');
        } catch (Exception $e) {
            error_log($e->getMessage());
            die('Database connection error. Please contact administrator.');
        }
    }

    public function getConnection() {
        return $this->connection;
    }

    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);

            if (!$stmt) {
                throw new Exception('Query preparation failed: ' . $this->connection->error);
            }

            if (!empty($params)) {
                $types = '';
                $values = [];

                foreach ($params as $param) {
                    if (is_int($param)) {
                        $types .= 'i';
                    } elseif (is_float($param)) {
                        $types .= 'd';
                    } elseif (is_bool($param)) {
                        $types .= 'i';
                        $param = $param ? 1 : 0;
                    } else {
                        $types .= 's';
                    }
                    $values[] = $param;
                }

                $stmt->bind_param($types, ...$values);
            }

            if (!$stmt->execute()) {
                throw new Exception('Query execution failed: ' . $stmt->error);
            }

            return $stmt;
        } catch (Exception $e) {
            error_log($e->getMessage());
            throw $e;
        }
    }

    public function insert($table, $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";

        $types = '';
        $values = [];

        foreach ($data as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } elseif (is_bool($value)) {
                $types .= 'i';
                $value = $value ? 1 : 0;
            } else {
                $types .= 's';
            }
            $values[] = $value;
        }

        $stmt = $this->connection->prepare(str_replace('?', '%s', $sql));
        if (!$stmt) {
            throw new Exception('Insert preparation failed: ' . $this->connection->error);
        }

        $stmt = $this->connection->prepare($sql);
        $stmt->bind_param($types, ...$values);

        if (!$stmt->execute()) {
            throw new Exception('Insert failed: ' . $stmt->error);
        }

        return $this->connection->insert_id;
    }

    public function getLastInsertId() {
        return $this->connection->insert_id;
    }

    public function close() {
        if ($this->connection) {
            $this->connection->close();
        }
    }
}
?>
