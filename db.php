<?php
// TDTS database connection. Update these values if your local MariaDB/MySQL settings differ.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$server = 'localhost';
$username = 'root';
$password = '';
$database = 'cyber_track';

try {
    $con = new mysqli($server, $username, $password, $database);
    $con->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    exit('Database connection failed. Check db.php settings and make sure MariaDB/MySQL is running. Details: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function tableExists(mysqli $con, string $table): bool {
    $stmt = $con->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_row()[0];
    $stmt->close();
    return $count > 0;
}

function columnsFor(mysqli $con, string $table): array {
    // Table names are checked against information_schema before this function is called.
    $safe = str_replace('`', '``', $table);
    $result = $con->query("SHOW COLUMNS FROM `$safe`");
    $columns = [];
    while ($row = $result->fetch_assoc()) $columns[] = $row;
    return $columns;
}

function primaryKeyFor(array $columns): ?string {
    foreach ($columns as $column) if (($column['Key'] ?? '') === 'PRI') return $column['Field'];
    return null;
}

function isAutoIncrement(array $column): bool {
    return stripos((string)($column['Extra'] ?? ''), 'auto_increment') !== false;
}
