<?php
// modules/tasks.php + timeline + clients + contracts
function task_full($id) {
  $t = Db::one('SELECT * FROM "Task" WHERE id=?', [$id]);
  if (!$t) return null;
  $t = Util::normRow($t);
  $t['assignees'] = Db::q('SELECT * FROM "TaskAssignee" WHERE "taskId"=?', [$id]);
  $t['children'] = Util::normList(Db::q('SELECT * FROM "Task" WHERE "parentId"=?', [$id]));
  $t['comments'] = Util::normList(Db::q('SELECT * FROM "TaskComment" WHERE "taskId"=?', [$id]));
  $t['time'] = Db::q('SELECT * FROM "TimeEntry" WHERE "taskId"=?', [$id]);
  $t['deps'] = Db::q('SELECT * FROM "TaskDependency" WHERE "srcId"=?', [$id]);
  $t['dependents'] = Db::q('SELECT * FROM "TaskDependency" WHERE "dstId"=?', [$id]);
  return $t;
}
function handle_tasks($method, $parts, $query, $body, $cfg, $user) {
  $uid = $user['id'];
  if ($method === 'GET' && $parts === ['tasks']) {
    $w = ['"archived"=0']; $p = [];
    if (!empty($query['projectId'])) { $w[] = '"projectId"=?'; $p[] = $query['projectId']; }
    if (!empty($query['status'])) { $w[] = 'status=?'; $p[] = $query['status']; }
    if (!empty($query['assigneeId'])) { $w[] = 'EXISTS(SELECT 1 FROM "TaskAssignee" WHERE "taskId"="Task".id AND "userId"=?)'; $p[] = $query['assigneeId']; }
    $take = min(Util::toInt($query['take'] ?? null, 100), 200);
    $rows = Db::q('SELECT * FROM "Task" WHERE ' . implode(' AND ', $w) . ' ORDER BY "createdAt" DESC LIMIT ' . (int)$take, $p);
    foreach ($rows as &$r) { $r = Util::normRow($r); $r['assignees'] = Db::q('SELECT * FROM "TaskAssignee" WHERE "taskId"=?', [$r['id']]); }
    Response::json($rows);
  }
  if ($method === 'GET' && count($parts) === 2 && $parts[0] === 'tasks') {
    $t = task_full($parts[1]);
    Response::json($t);
  }
  if ($method === 'POST' && $parts === ['tasks']) {
    $title = $body['title'] ?? null;
    if (!is_string($title) || trim($title) === '') Response::bad('title required');
    if (empty($body['projectId'])) Response::bad('projectId is required');
    $id = Util::cuid();
    $due = $body['dueDate'] ?? null;
    Db::insert('INSERT INTO "Task" (id,"projectId","sectionId","parentId",title,description,acceptance,status,priority,"dueDate","origDueDate","estHours","reviewerId","createdById","createdAt","updatedAt") VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
      [$id, $body['projectId'], $body['sectionId'] ?? null, $body['parentId'] ?? null, trim($title), $body['description'] ?? null, $body['acceptance'] ?? null,
       'BACKLOG', $body['priority'] ?? 'MEDIUM', $due, $due, $body['estHours'] ?? null, $body['reviewerId'] ?? null, $uid, Util::now(), Util::now()]);
    foreach (($body['assigneeIds'] ?? []) as $i => $aid) {
      if (!Db::one('SELECT * FROM "TaskAssignee" WHERE "taskId"=? AND "userId"=?', [$id, $aid]))
        Db::insert('INSERT INTO "TaskAssignee" ("taskId","userId","isPrimary") VALUES (?,?,?)', [$id, $aid, $i === 0 ? 1 : 0]);
    }
    Util::audit($uid, 'tasks.create', 'Task', $id);
    Response::json(task_full($id));
  }
  if ($method === 'PATCH' && count($parts) === 2 && $parts[0] === 'tasks') {
    if (!empty($body['dueDate']) && empty($body['reason'])) Response::bad('due-date change requires reason');
    $data = [];
    if (!empty($body['title'])) $data['title'] = $body['title'];
    if (array_key_exists('description', $body)) $data['description'] = $body['description'];
    if (!empty($body['status'])) $data['status'] = $body['status'];
    if (!empty($body['priority'])) $data['priority'] = $body['priority'];
    if (!empty($body['dueDate'])) { $data['dueDate'] = $body['dueDate']; $data['reason'] = $body['reason']; }
    if (array_key_exists('progress', $body)) $data['progress'] = $body['progress'];
    if ($data) {
      $sets = []; $p = [];
      foreach ($data as $k => $v) { $sets[] = "\"$k\"=?"; $p[] = $v; }
      $sets[] = '"updatedAt"=?'; $p[] = Util::now(); $p[] = $parts[1];
      Db::exec('UPDATE "Task" SET ' . implode(',', $sets) . ' WHERE id=?', $p);
    }
    Util::audit($uid, 'tasks.edit', 'Task', $parts[1], substr(json_encode($body), 0, 2000));
    Response::json(task_full($parts[1]));
  }
  if ($method === 'POST' && count($parts) === 3 && $parts[0] === 'tasks' && $parts[2] === 'dependencies') {
    $src = $parts[1]; $dst = $body['dependsOnTaskId'] ?? null;
    if ($src === $dst) Response::bad('task cannot depend on itself');
    if (!$dst) Response::bad('dependsOnTaskId is required');
    $edges = Db::q('SELECT "srcId","dstId" FROM "TaskDependency"');
    $adj = [];
    foreach ($edges as $e) $adj[$e['srcId']][] = $e['dstId'];
    $adj[$src][] = $dst;
    $stack = [$dst]; $seen = [];
    while ($stack) {
      $n = array_pop($stack);
      if ($n === $src) Response::bad('dependency cycle rejected');
      if (isset($seen[$n])) continue; $seen[$n] = true;
      foreach (($adj[$n] ?? []) as $m) $stack[] = $m;
    }
    try {
      Db::insert('INSERT INTO "TaskDependency" (id,"srcId","dstId") VALUES (?,?,?)', [Util::cuid(), $src, $dst]);
    } catch (PDOException $e) { Response::conflict('dependency already exists'); }
    Util::audit($uid, 'tasks.add_dependency', 'Task', $src, $dst);
    Response::json(['ok' => true]);
  }
  if ($method === 'POST' && count($parts) === 3 && $parts[0] === 'tasks' && $parts[2] === 'comments') {
    $id = Util::cuid();
    Db::insert('INSERT INTO "TaskComment" (id,"taskId","authorId",body,"createdAt") VALUES (?,?,?,?,?)', [$id, $parts[1], $uid, $body['body'] ?? '', Util::now()]);
    Response::json(Util::normRow(Db::one('SELECT * FROM "TaskComment" WHERE id=?', [$id])));
  }
  if ($method === 'POST' && count($parts) === 3 && $parts[0] === 'tasks' && $parts[2] === 'time-entries') {
    $h = $body['hours'] ?? null;
    if (!is_numeric($h) || $h <= 0) Response::bad('hours must be > 0');
    $id = Util::cuid();
    Db::insert('INSERT INTO "TimeEntry" (id,"taskId","userId",hours,note,date) VALUES (?,?,?,?,?,?)', [$id, $parts[1], $uid, (float)$h, $body['note'] ?? null, Util::now()]);
    Response::json(Db::one('SELECT * FROM "TimeEntry" WHERE id=?', [$id]));
  }
  // timeline
  if ($method === 'GET' && $parts === ['timeline', 'tasks']) {
    $w = ['"archived"=0']; $p = [];
    if (!empty($query['projectId'])) { $w[] = '"projectId"=?'; $p[] = $query['projectId']; }
    $rows = Db::q('SELECT * FROM "Task" WHERE ' . implode(' AND ', $w) . ' ORDER BY "startDate" ASC', $p);
    foreach ($rows as &$r) { $r = Util::normRow($r); $r['assignees'] = Db::q('SELECT * FROM "TaskAssignee" WHERE "taskId"=?', [$r['id']]); }
    Response::json($rows);
  }
  if ($method === 'GET' && $parts === ['timeline', 'overdue']) {
    $rows = Db::q('SELECT * FROM "Task" WHERE "dueDate" < ? AND status NOT IN (?,?) AND "archived"=0 ORDER BY "dueDate" ASC', [Util::now(), 'DONE', 'CANCELLED']);
    foreach ($rows as &$r) {
      $r = Util::normRow($r);
      $r['project'] = Db::one('SELECT code, name FROM "Project" WHERE id=?', [$r['projectId']]);
      $r['assignees'] = Db::q('SELECT * FROM "TaskAssignee" WHERE "taskId"=?', [$r['id']]);
    }
    Response::json($rows);
  }
  if ($method === 'GET' && $parts === ['timeline', 'upcoming']) {
    $days = Util::toInt($query['days'] ?? null, 7);
    $end = gmdate('Y-m-d H:i:s', time() + $days * 86400);
    $rows = Db::q('SELECT * FROM "Task" WHERE "dueDate" BETWEEN ? AND ? AND status NOT IN (?,?) AND "archived"=0 ORDER BY "dueDate" ASC', [Util::now(), $end, 'DONE', 'CANCELLED']);
    foreach ($rows as &$r) { $r = Util::normRow($r); $r['project'] = Db::one('SELECT code, name FROM "Project" WHERE id=?', [$r['projectId']]); }
    Response::json($rows);
  }
  if ($method === 'GET' && $parts === ['timeline', 'workload']) {
    $tasks = Db::q('SELECT * FROM "Task" WHERE status NOT IN (?,?) AND "archived"=0', ['DONE', 'CANCELLED']);
    $by = [];
    foreach ($tasks as $t) {
      foreach (Db::q('SELECT * FROM "TaskAssignee" WHERE "taskId"=?', [$t['id']]) as $a) {
        $u = $a['userId'];
        if (!isset($by[$u])) $by[$u] = ['open' => 0, 'estHours' => 0];
        $by[$u]['open']++;
        $by[$u]['estHours'] += $t['estHours'] ?? 0;
      }
    }
    Response::json($by);
  }
  // clients
  if ($method === 'GET' && $parts === ['clients']) {
    $rows = Db::q('SELECT * FROM "Client" ORDER BY name ASC');
    foreach ($rows as &$r) {
      $r['projects'] = Db::q('SELECT * FROM "Project" WHERE "clientId"=?', [$r['id']]);
      $r['contracts'] = Db::q('SELECT * FROM "Contract" WHERE "clientId"=?', [$r['id']]);
      $r['invoices'] = Db::q('SELECT * FROM "Invoice" WHERE "clientId"=?', [$r['id']]);
    }
    Response::json($rows);
  }
  if ($method === 'POST' && $parts === ['clients']) {
    Auth::requirePerm($uid, 'clients.manage');
    $name = $body['name'] ?? null;
    if (!is_string($name) || trim($name) === '') Response::bad('client name required');
    $id = Util::cuid();
    Db::insert('INSERT INTO "Client" (id, name, contact, notes, "createdAt") VALUES (?,?,?,?,?)', [$id, trim($name), $body['contact'] ?? null, $body['notes'] ?? null, Util::now()]);
    Util::audit($uid, 'clients.manage', 'Client', $id, $name);
    Response::json(Db::one('SELECT * FROM "Client" WHERE id=?', [$id]));
  }
  // contracts
  if ($method === 'GET' && $parts === ['contracts']) {
    $rows = empty($query['projectId'])
      ? Db::q('SELECT * FROM "Contract" ORDER BY reference ASC')
      : Db::q('SELECT * FROM "Contract" WHERE "projectId"=? ORDER BY reference ASC', [$query['projectId']]);
    foreach ($rows as &$r) {
      $r['client'] = Db::one('SELECT name FROM "Client" WHERE id=?', [$r['clientId']]);
      $r['project'] = $r['projectId'] ? Db::one('SELECT code, name FROM "Project" WHERE id=?', [$r['projectId']]) : null;
    }
    Response::json($rows);
  }
  if ($method === 'POST' && $parts === ['contracts']) {
    Auth::requirePerm($uid, 'contracts.manage');
    $ref = $body['reference'] ?? null;
    if (!is_string($ref) || trim($ref) === '') Response::bad('contract reference required');
    if (!isset($body['amount']) || !is_int($body['amount']) || $body['amount'] <= 0) Response::bad('amount must be positive integer (minor units)');
    if (empty($body['clientId'])) Response::bad('clientId is required');
    $id = Util::cuid();
    try {
      Db::insert('INSERT INTO "Contract" (id, reference, "clientId", "projectId", scope, amount, currency, "startDate", "endDate", terms, status) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
        [$id, trim($ref), $body['clientId'], $body['projectId'] ?? null, $body['scope'] ?? null, $body['amount'], $body['currency'] ?? 'EGP', $body['startDate'] ?? null, $body['endDate'] ?? null, $body['terms'] ?? null, 'SIGNED']);
    } catch (PDOException $e) { Response::conflict('contract reference already exists'); }
    if (!empty($body['projectId'])) Db::exec('UPDATE "Project" SET "contractValue"=?, currency=? WHERE id=?', [$body['amount'], $body['currency'] ?? 'EGP', $body['projectId']]);
    Util::audit($uid, 'contracts.manage', 'Contract', $id, "$ref amount={$body['amount']}");
    Response::json(Db::one('SELECT * FROM "Contract" WHERE id=?', [$id]));
  }
  return false;
}
