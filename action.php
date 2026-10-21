<?php
require __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$allowedTables = ['users','devices','security_analysis','threat_types','vulnerabilities','security_logs','alerts','incidents','incident_assignments','incident_updates','responses','audit_logs'];
$table = $_POST['table'] ?? '';
if (!in_array($table, $allowedTables, true) || !tableExists($con, $table)) exit('Invalid or unavailable table.');
$columns = columnsFor($con, $table);
$pk = primaryKeyFor($columns);
if (!$pk) exit('This table has no primary key and is read-only in this interface.');
$op = $_POST['operation'] ?? '';
$safeTable = '`' . str_replace('`','``',$table) . '`';
try {
    if ($op === 'delete') {
        $id = (string)($_POST['id'] ?? '');
        $stmt = $con->prepare("DELETE FROM $safeTable WHERE `" . str_replace('`','``',$pk) . "` = ?");
        $stmt->bind_param('s', $id); $stmt->execute(); $stmt->close();
        $_SESSION['tdts_message'] = 'Record deleted.';
    } elseif ($op === 'insert' || $op === 'update') {
        $posted = $_POST['fields'] ?? [];
        if (!is_array($posted)) throw new RuntimeException('Invalid form data.');
        $known = [];
        foreach ($columns as $column) {
            $name = $column['Field'];
            if (isAutoIncrement($column) || !array_key_exists($name, $posted)) continue;
            // An empty value is NULL only for nullable columns; otherwise it stays an empty string.
            $value = $posted[$name];
            if ($value === '' && $column['Null'] === 'YES') $value = null;
            if (is_string($value) && str_contains(strtolower($column['Type']), 'datetime')) $value = str_replace('T',' ',$value) . (strlen($value)===16 ? ':00' : '');
            $known[$name] = $value;
        }
        if (!$known) throw new RuntimeException('No editable fields were submitted.');
        if ($op === 'insert') {
            $names = array_keys($known); $quoted = array_map(fn($n)=>'`'.str_replace('`','``',$n).'`',$names);
            $placeholders = implode(',', array_fill(0,count($names),'?'));
            $stmt = $con->prepare("INSERT INTO $safeTable (".implode(',',$quoted).") VALUES ($placeholders)");
            $values = array_values($known); $types = str_repeat('s',count($values)); $stmt->bind_param($types, ...$values); $stmt->execute(); $stmt->close();
            $_SESSION['tdts_message'] = 'Record added successfully.';
        } else {
            $id = (string)($_POST['original_id'] ?? '');
            if ($id === '') throw new RuntimeException('Missing record ID for update.');
            $sets=[]; foreach(array_keys($known) as $name) $sets[]='`'.str_replace('`','``',$name).'` = ?';
            $values=array_values($known); $types=str_repeat('s',count($values)); $values[]=$id; $types.='s';
            $stmt=$con->prepare("UPDATE $safeTable SET ".implode(',',$sets)." WHERE `".str_replace('`','``',$pk)."` = ?");
            $stmt->bind_param($types,...$values); $stmt->execute(); $stmt->close();
            $_SESSION['tdts_message'] = 'Record updated successfully.';
        }
    } else throw new RuntimeException('Unsupported operation.');
} catch (Throwable $e) {
    $_SESSION['tdts_message'] = 'Database operation failed: ' . $e->getMessage();
}
header('Location: index.php?table=' . rawurlencode($table) . '#records');
exit;
