<?php
// modules/projects.php + sections
function handle_projects($method, $parts, $query, $body, $cfg, $user) {
  $uid = $user['id'];
  if ($method === 'GET' && $parts === ['projects']) {
    Response::json(Util::normList(Db::q('SELECT * FROM "Project" ORDER BY "createdAt" DESC LIMIT 100')));
  }
  if ($method === 'GET' && count($parts) === 2 && $parts[0] === 'projects') {
    $p = Db::one('SELECT * FROM "Project" WHERE id=?', [$parts[1]]);
    if (!$p) Response::json(null);
    $p = Util::normRow($p);
    $p['sections'] = Util::normList(Db::q('SELECT * FROM "ProjectSection" WHERE "projectId"=?', [$p['id']]));
    $p['members'] = Db::q('SELECT * FROM "ProjectMember" WHERE "projectId"=?', [$p['id']]);
    $p['tasks'] = Util::normList(Db::q('SELECT * FROM "Task" WHERE "projectId"=? LIMIT 50', [$p['id']]));
    Response::json($p);
  }
  if ($method === 'POST' && $parts === ['projects']) {
    $code = $body['code'] ?? null; $name = $body['name'] ?? null;
    if (!is_string($code) || trim($code) === '') Response::bad('code is required');
    if (!is_string($name) || trim($name) === '') Response::bad('name is required');
    $id = Util::cuid();
    try {
      Db::insert('INSERT INTO "Project" (id, code, name, description, "clientId", status, "plannedDate", "contractValue", currency, "createdAt", "updatedAt") VALUES (?,?,?,?,?,?,?,?,?,?,?)',
        [$id, $code, $name, $body['description'] ?? null, $body['clientId'] ?? null, 'DRAFT',
         $body['plannedDate'] ?? null, (int)($body['contractValue'] ?? 0), $body['currency'] ?? 'EGP', Util::now(), Util::now()]);
    } catch (PDOException $e) { if (strpos($e->getMessage(), 'UNIQUE') !== false || $e->getCode() == '23000') Response::conflict('project code already exists'); throw $e; }
    Util::audit($uid, 'projects.create', 'Project', $id);
    Response::json(Util::normRow(Db::one('SELECT * FROM "Project" WHERE id=?', [$id])));
  }
  if ($method === 'PATCH' && count($parts) === 2 && $parts[0] === 'projects') {
    $id = $parts[1]; $data = [];
    if (!empty($body['name'])) $data['name'] = $body['name'];
    if (array_key_exists('description', $body)) $data['description'] = $body['description'];
    if (!empty($body['status'])) $data['status'] = $body['status'];
    if (!empty($body['plannedDate'])) $data['plannedDate'] = $body['plannedDate'];
    if ($data) {
      $sets = []; $p = [];
      foreach ($data as $k => $v) { $sets[] = "\"$k\"=?"; $p[] = $v; }
      $sets[] = '"updatedAt"=?'; $p[] = Util::now(); $p[] = $id;
      Db::exec('UPDATE "Project" SET ' . implode(',', $sets) . ' WHERE id=?', $p);
    }
    Util::audit($uid, 'projects.edit', 'Project', $id, substr(json_encode($body), 0, 2000));
    Response::json(Util::normRow(Db::one('SELECT * FROM "Project" WHERE id=?', [$id])));
  }
  if ($method === 'POST' && count($parts) === 3 && $parts[0] === 'projects' && $parts[2] === 'archive') {
    Db::exec('UPDATE "Project" SET status=? WHERE id=?', ['CLOSED', $parts[1]]);
    Util::audit($uid, 'projects.archive', 'Project', $parts[1]);
    Response::json(Util::normRow(Db::one('SELECT * FROM "Project" WHERE id=?', [$parts[1]])));
  }
  if ($method === 'GET' && count($parts) === 3 && $parts[0] === 'projects' && $parts[2] === 'members') {
    Response::json(Db::q('SELECT * FROM "ProjectMember" WHERE "projectId"=?', [$parts[1]]));
  }
  if ($method === 'POST' && count($parts) === 3 && $parts[0] === 'projects' && $parts[2] === 'members') {
    $mid = $body['userId'] ?? null; $role = $body['role'] ?? 'MEMBER';
    if (!$mid) Response::bad('userId is required');
    $ex = Db::one('SELECT * FROM "ProjectMember" WHERE "projectId"=? AND "userId"=?', [$parts[1], $mid]);
    if ($ex) Db::exec('UPDATE "ProjectMember" SET role=? WHERE "projectId"=? AND "userId"=?', [$role, $parts[1], $mid]);
    else Db::insert('INSERT INTO "ProjectMember" ("projectId","userId",role) VALUES (?,?,?)', [$parts[1], $mid, $role]);
    Util::audit($uid, 'projects.manage_members', 'Project', $parts[1], $mid);
    Response::json(['ok' => true]);
  }
  // sections
  if ($method === 'GET' && count($parts) === 3 && $parts[0] === 'projects' && $parts[2] === 'sections') {
    Response::json(Util::normList(Db::q('SELECT * FROM "ProjectSection" WHERE "projectId"=? ORDER BY "sortOrder" ASC', [$parts[1]])));
  }
  if ($method === 'POST' && $parts === ['sections']) {
    $pid = $body['projectId'] ?? null; $name = $body['name'] ?? null;
    if (!$pid) Response::bad('projectId is required');
    if (!$name) Response::bad('name is required');
    $c = Db::one('SELECT COUNT(*) as n FROM "ProjectSection" WHERE "projectId"=?', [$pid]);
    $id = Util::cuid();
    Db::insert('INSERT INTO "ProjectSection" (id, "projectId", name, description, color, "leadId", "sortOrder") VALUES (?,?,?,?,?,?,?)',
      [$id, $pid, $name, $body['description'] ?? null, $body['color'] ?? null, $body['leadId'] ?? null, (int)($c['n'] ?? 0)]);
    Util::audit($uid, 'sections.create', 'ProjectSection', $id);
    Response::json(Util::normRow(Db::one('SELECT * FROM "ProjectSection" WHERE id=?', [$id])));
  }
  if ($method === 'PATCH' && count($parts) === 2 && $parts[0] === 'sections') {
    $allow = ['name','description','color','leadId','archived'];
    $sets = []; $p = [];
    foreach ($allow as $k) if (array_key_exists($k, $body)) { $sets[] = "\"$k\"=?"; $p[] = is_bool($body[$k]) ? (int)$body[$k] : $body[$k]; }
    if ($sets) { $p[] = $parts[1]; Db::exec('UPDATE "ProjectSection" SET ' . implode(',', $sets) . ' WHERE id=?', $p); }
    Util::audit($uid, 'sections.edit', 'ProjectSection', $parts[1], substr(json_encode($body), 0, 2000));
    Response::json(Util::normRow(Db::one('SELECT * FROM "ProjectSection" WHERE id=?', [$parts[1]])));
  }
  if ($method === 'GET' && count($parts) === 3 && $parts[0] === 'sections' && $parts[2] === 'members') {
    Response::json(Db::q('SELECT * FROM "SectionMember" WHERE "sectionId"=?', [$parts[1]]));
  }
  if ($method === 'POST' && count($parts) === 3 && $parts[0] === 'sections' && $parts[2] === 'members') {
    $mid = $body['userId'] ?? null;
    if (!$mid) Response::bad('userId is required');
    if (!Db::one('SELECT * FROM "SectionMember" WHERE "sectionId"=? AND "userId"=?', [$parts[1], $mid]))
      Db::insert('INSERT INTO "SectionMember" ("sectionId","userId") VALUES (?,?)', [$parts[1], $mid]);
    Util::audit($uid, 'sections.manage_members', 'ProjectSection', $parts[1], $mid);
    Response::json(['ok' => true]);
  }
  return false;
}
