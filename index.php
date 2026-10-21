<?php
require __DIR__ . '/db.php';

$allowedTables = ['users','devices','security_analysis','threat_types','vulnerabilities','security_logs','alerts','incidents','incident_assignments','incident_updates','responses','audit_logs'];
$existingTables = [];
foreach ($allowedTables as $name) if (tableExists($con, $name)) $existingTables[] = $name;
$activeTable = $_GET['table'] ?? 'incidents';
if (!in_array($activeTable, $existingTables, true)) $activeTable = $existingTables[0] ?? '';
if (session_status() === PHP_SESSION_NONE) session_start();
$message = $_SESSION['tdts_message'] ?? '';
unset($_SESSION['tdts_message']);

// Dashboard figures are calculated from actual database records, not demo numbers.
$stats = ['total'=>0,'active'=>0,'resolved'=>0,'critical'=>0];
if (in_array('incidents', $existingTables, true)) {
    $incCols = array_column(columnsFor($con, 'incidents'), 'Field');
    $stats['total'] = (int)$con->query('SELECT COUNT(*) FROM `incidents`')->fetch_row()[0];
    if (in_array('status', $incCols, true)) {
        $statusCol = '`status`';
        $stats['resolved'] = (int)$con->query("SELECT COUNT(*) FROM `incidents` WHERE LOWER(CAST($statusCol AS CHAR)) IN ('resolved','closed','completed')")->fetch_row()[0];
        $stats['active'] = (int)$con->query("SELECT COUNT(*) FROM `incidents` WHERE LOWER(CAST($statusCol AS CHAR)) NOT IN ('resolved','closed','completed')")->fetch_row()[0];
    } else $stats['active'] = $stats['total'];
    if (in_array('severity', $incCols, true)) $stats['critical'] = (int)$con->query("SELECT COUNT(*) FROM `incidents` WHERE LOWER(CAST(`severity` AS CHAR)) IN ('critical','high')")->fetch_row()[0];
}

// Generic table browsing, searching, adding, editing, and deleting uses actual table metadata.
$columns = $activeTable ? columnsFor($con, $activeTable) : [];
$pk = primaryKeyFor($columns);
$search = trim($_GET['q'] ?? '');
$rows = [];
if ($activeTable) {
    $safeTable = str_replace('`','``',$activeTable);
    $sql = "SELECT * FROM `$safeTable`";
    $types = '';
    $params = [];
    if ($search !== '' && $columns) {
        $conditions = [];
        foreach ($columns as $col) {
            $field = str_replace('`','``',$col['Field']);
            $conditions[] = "CAST(`$field` AS CHAR) LIKE ?";
            $types .= 's'; $params[] = '%' . $search . '%';
        }
        $sql .= ' WHERE ' . implode(' OR ', $conditions);
    }
    $sql .= $pk ? ' ORDER BY `' . str_replace('`','``',$pk) . '` DESC LIMIT 200' : ' LIMIT 200';
    if ($params) {
        $stmt = $con->prepare($sql); $stmt->bind_param($types, ...$params); $stmt->execute();
        $res = $stmt->get_result(); while ($r = $res->fetch_assoc()) $rows[] = $r; $stmt->close();
    } else { $res = $con->query($sql); while ($r = $res->fetch_assoc()) $rows[] = $r; }
}

function friendly(string $s): string { return ucwords(str_replace('_', ' ', $s)); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TDTS — Threat Detection & Tracking System</title><link rel="stylesheet" href="assets/style.css">
</head>
<body>
<aside class="sidebar"><h2>🛡 TDTS</h2><p class="subtitle">THREAT INTELLIGENCE</p>
<nav><a href="index.php">Dashboard</a><?php foreach ($existingTables as $t): ?><a class="<?= $activeTable === $t ? 'selected' : '' ?>" href="?table=<?= e($t) ?>#records"><?= e(friendly($t)) ?></a><?php endforeach; ?></nav>
<div class="profile"><div class="avatar">TD</div><div><strong>TDTS Workspace</strong><p>Database dashboard</p></div></div></aside>
<main class="main-content"><header class="topbar"><div><span>Workspace / Database</span><h1>Threat Detection Dashboard</h1></div><span class="secure">● Database-backed</span></header>
<?php if ($message): ?><div class="notice"><?= e($message) ?></div><?php endif; ?>
<section id="dashboard"><div class="welcome"><div><h2>Security Overview</h2><p>Live summary from your <strong>cyber_track</strong> database.</p></div><a href="?table=incidents#records" class="button">Manage Incidents</a></div>
<div class="stats"><div class="stat-card"><p>Total Incidents</p><h2><?= $stats['total'] ?></h2><span>Records in incidents table</span></div><div class="stat-card orange"><p>Active Incidents</p><h2><?= $stats['active'] ?></h2><span>Not resolved or closed</span></div><div class="stat-card green"><p>Resolved Incidents</p><h2><?= $stats['resolved'] ?></h2><span>Resolved, closed, or completed</span></div><div class="stat-card red"><p>Critical / High</p><h2><?= $stats['critical'] ?></h2><span>High-priority incidents</span></div></div></section>
<section class="panel" id="records"><div class="section-heading"><div><h2><?= e(friendly($activeTable ?: 'database')) ?></h2><p>Browse, search, create, update, and delete records in this table.</p></div><form method="get" class="search-form"><input type="hidden" name="table" value="<?= e($activeTable) ?>"><input type="search" name="q" value="<?= e($search) ?>" placeholder="Search this table..."><button class="button" type="submit">Search</button></form></div>
<?php if (!$existingTables): ?><p>No expected TDTS tables were found in database <code>cyber_track</code>. Check the database name and table names in db.php.</p>
<?php elseif (!$columns): ?><p>Could not read columns for this table.</p>
<?php else: ?>
<div class="table-container"><table><thead><tr><?php foreach ($columns as $col): ?><th><?= e(friendly($col['Field'])) ?></th><?php endforeach; ?><th>Actions</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="<?= count($columns)+1 ?>" class="empty">No records found.</td></tr><?php endif; ?>
<?php foreach ($rows as $row): ?><tr><?php foreach ($columns as $col): $v = $row[$col['Field']] ?? ''; ?><td><?= e(substr((string)$v, 0, 100)) ?></td><?php endforeach; ?><td class="actions">
<?php if ($pk): ?><button type="button" class="view-btn" onclick='openEdit(<?= json_encode($row, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>)'>Edit</button>
<form method="post" action="action.php" onsubmit="return confirm('Delete this record? This cannot be undone.');" class="inline"><input type="hidden" name="operation" value="delete"><input type="hidden" name="table" value="<?= e($activeTable) ?>"><input type="hidden" name="id" value="<?= e($row[$pk]) ?>"><button class="delete-btn" type="submit">Delete</button></form><?php else: ?>Read only<?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php if ($pk): ?><div class="form-panel"><h3 id="formTitle">Add a record</h3><form method="post" action="action.php" id="recordForm"><input type="hidden" name="operation" value="insert" id="operation"><input type="hidden" name="table" value="<?= e($activeTable) ?>"><input type="hidden" name="pk" value="<?= e($pk) ?>"><input type="hidden" name="original_id" id="originalId" value="">
<div class="form-grid"><?php foreach ($columns as $col): if (isAutoIncrement($col)) continue; $field=$col['Field']; $type=strtolower($col['Type']); ?><label><?= e(friendly($field)) ?><?= ($col['Null'] === 'NO' && $col['Default'] === null && !isAutoIncrement($col)) ? ' *' : '' ?>
<?php if (str_contains($type,'text')): ?><textarea name="fields[<?= e($field) ?>]" rows="2" <?= ($col['Null']==='NO' && $col['Default']===null)?'required':'' ?>></textarea>
<?php else: ?><input name="fields[<?= e($field) ?>]" type="<?= str_contains($type,'int') || str_contains($type,'decimal') || str_contains($type,'float') ? 'number' : (str_contains($type,'date') && !str_contains($type,'time') ? 'date' : (str_contains($type,'datetime') || str_contains($type,'timestamp') ? 'datetime-local' : 'text')) ?>" <?= (str_contains($type,'int')||str_contains($type,'decimal')||str_contains($type,'float'))?'step="any"':'' ?> <?= ($col['Null']==='NO' && $col['Default']===null)?'required':'' ?>><?php endif; ?></label>
<?php endforeach; ?></div><div class="form-buttons"><button type="submit" class="button">Save Record</button><button type="button" class="view-btn" onclick="resetForm()">Clear</button></div></form></div><?php endif; ?>
<?php endif; ?></section>
<section class="panel"><h2>Database Explorer</h2><p class="muted">Your 12 TDTS tables. Select a table from the sidebar to browse its records.</p><div class="table-chips"><?php foreach ($allowedTables as $t): ?><a href="?table=<?= e($t) ?>#records" class="chip <?= $activeTable===$t?'active':'' ?>"><?= e(friendly($t)) ?><?= tableExists($con,$t)?' ✓':' — missing' ?></a><?php endforeach; ?></div></section>
<footer><span>© 2026 TDTS — Threat Detection & Tracking System</span><span>Connected to cyber_track</span></footer></main>
<script>
function openEdit(row){const form=document.getElementById('recordForm'); if(!form)return; form.reset(); document.getElementById('operation').value='update'; document.getElementById('formTitle').textContent='Edit record'; document.getElementById('originalId').value=row[form.elements.pk.value] ?? ''; for(const [key,value] of Object.entries(row)){const input=form.querySelector(`[name="fields[${CSS.escape(key)}]"]`);if(input){let v=value??'';if(input.type==='datetime-local' && v) v=String(v).replace(' ','T').slice(0,16);input.value=v;}} form.scrollIntoView({behavior:'smooth',block:'start'});}
function resetForm(){const form=document.getElementById('recordForm');if(form){form.reset();document.getElementById('operation').value='insert';document.getElementById('originalId').value='';document.getElementById('formTitle').textContent='Add a record';}}
</script></body></html>
