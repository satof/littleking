<?php

if (!function_exists('e')) {
  function e($s): string {
    if ($s) {
      return htmlspecialchars($s, ENT_QUOTES, 'UTF-8', false);
    } else {
      return '';
    }
  }
}

function getMembers($db) {
  $sql = "SELECT id, name, is_admin, sort_order FROM members order by sort_order";
  $stmt = $db->query($sql);
  $members = [];
  while($row = $stmt->fetch()) {
      $members[$row['id']] = ['name' => $row['name'], 'is_admin' => $row['is_admin'], 'sort_order' => $row['sort_order']];
  }
  return $members;
}

function makeToken() {
  return uniqid('', true);
}

function saveToken($db, $token, $member_id) {
  $sql = "INSERT INTO tokens VALUES(:token, :member_id, now(), now())";
  $stmt = $db->prepare($sql);
  $stmt->bindParam(':token', $token);
  $stmt->bindParam(':member_id', $member_id);
  $stmt->execute();
  setcookie('token', $token, time()+60*60*24*30);
  return true;
}

function checkAndUpdateToken($db) {
  if (!isset($_COOKIE['token'])) {
    return 0;
  }

  $member_id = 0;
  $token = $_COOKIE['token'];

  $sql = "SELECT member_id FROM tokens where token = :token";
  $stmt = $db->prepare($sql);
  $stmt->bindParam(':token', $token);
  $stmt->execute();
  if ($row = $stmt->fetch()) {
    $member_id = $row['member_id'];

    $sql = "UPDATE tokens SET updated_at = now() WHERE token = :token";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':token', $token);
    $stmt->execute();

    setcookie('token', $token, time()+60*60*24*30);
  }
  return $member_id;
}

function deleteExpiredToken($db) {
  $sql = "DELETE FROM tokens WHERE updated_at < :date";
  $stmt = $db->prepare($sql);
  $stmt->bindParam(':date', (new DateTime)->modify('-1 month')->format('Y-m-d'));
  $stmt->execute();
  return true;
}

function removeToken($db) {
  if (!isset($_COOKIE['token'])) {
    return false;
  }
  $token = $_COOKIE['token'];
  $sql = "DELETE FROM tokens WHERE token = :token";
  $stmt = $db->prepare($sql);
  $stmt->bindParam(':token', $token);
  $stmt->execute();
  setcookie('token', '', time()-60);
  return true;
}

function auth($db, $id, $pass) {
  $hash = password_hash($pass, PASSWORD_DEFAULT);
  $sql = "SELECT password FROM members where id = :id";
  $stmt = $db->prepare($sql);
  $stmt->bindParam(':id', $id);
  $stmt->execute();
  if ($row = $stmt->fetch()) {
    if (password_verify($pass, $row['password'])) {
      return true;
    }
  }
  return false;
}

function getSelfMember($db) {
  if ($member_id = checkAndUpdateToken($db)) {
    $sql = "SELECT id, name, is_admin FROM members where id = :id";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':id', $member_id);
    $stmt->execute();
    if ($row = $stmt->fetch()) {
      return $row;
    }
  }
  return null;
}

function getSelfSchedules($db) {
  if ($member_id = checkAndUpdateToken($db)) {
    $sql = "SELECT schedule_date, schedule_type_id, comment FROM answers where member_id = :member_id order by schedule_date";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':member_id', $member_id);
    $stmt->execute();
    $schedules = [];
    while ($row = $stmt->fetch()) {
      $schedules[$row['schedule_date']] = ['schedule_type_id' => $row['schedule_type_id'], 'comment' => $row['comment']];
    }
  }
  return $schedules;
}

function updateScheduleTerm($db) {
  $today = new DateTime(date('Y-m-d'));

  $sql = "SELECT schedule_date FROM schedule_dates order by schedule_date";
  $stmt = $db->query($sql);
  $storedDates = [];
  $storedEndDate = null;
  while ($row = $stmt->fetch()) {
    $storedEndDate = new DateTime($row['schedule_date']);
    $storedDates[] = $storedEndDate;
  }

  if (!empty($storedDates)) {
    $pastScheduleDates = [];
    foreach ($storedDates as $storedDate) {
      if ($storedDate <= $today) {
        $pastScheduleDates[] = $storedDate;
      }
    }
    // 過去のschedule_dateが存在しない、または当日までschedule_dateが続いている場合は期間更新しない
    // （週に1回は誰かがスケジュールを見て、1週ずつ更新される前提）
    if (empty($pastScheduleDates) ||
        count($pastScheduleDates) == ($pastScheduleDates[0]->diff($today))->days + 1) {
      return true;
    }
  }

  // 7週分の土日月までを追加対象とする（月曜は祝日が多いため）
  $addEndDate = clone $today;
  for ($i = 1; $i <= 7; $i++) {
    $addEndDate = $addEndDate->modify('next monday');
  }

  // 祝日の取得
  $holidays = [];
  if ($holidayJson = file_get_contents('https://holidays-jp.github.io/api/v1/date.json')) {
  //if ($holidayJson = file_get_contents(__DIR__ . '/../date.json')) {
    $holidayJson = mb_convert_encoding($holidayJson, 'UTF8', 'ASCII,JIS,UTF-8,EUC-JP,SJIS-WIN');
    $hoidays = json_decode($holidayJson, true);
  } else {
    return false;
  }
  $addStartDate = $storedEndDate ? $storedEndDate : $today;
  $addStartDate->modify('+1 days');
  $addDates = [];
  foreach ($hoidays as $date => $desc) {
    $holiday = new DateTime($date);
    if ($holiday >= $addStartDate && $holiday <= $addEndDate) {
      $addDates[$holiday->format('Y-m-d')] = true;
    }
  }

  // 追加する土日の取得
  $date = clone $addStartDate;
  while ($date->modify('next sunday') <= $addEndDate) {
    $addDates[$date->modify('-1 days')->format('Y-m-d')] = true;
    $addDates[$date->modify('+1 days')->format('Y-m-d')] = true;
  }
  ksort($addDates);
  
  // DBに追加
  foreach($addDates as $date => $f) {
    $sql = "INSERT INTO schedule_dates values (:date, null, now(), now())";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':date', $date);
    $stmt->execute();
  }

  // 過去日を削除
  $sql = "DELETE FROM schedule_dates WHERE schedule_date < :date";
  $stmt = $db->prepare($sql);
  $stmt->bindParam(':date', $today->format('Y-m-d'));
  $stmt->execute();
  $sql = "DELETE FROM answers WHERE schedule_date < :date";
  $stmt = $db->prepare($sql);
  $stmt->bindParam(':date', $today->format('Y-m-d'));
  $stmt->execute();

  return true;
}

function getScheduleTypes($db) {
  $sql = "SELECT id, symbol, description FROM schedule_types order by sort_order";
  $stmt = $db->query($sql);
  $scheduleTypes = [];
  while($row = $stmt->fetch()) {
      $scheduleTypes[$row['id']] = ['symbol' => $row['symbol'], 'description' => $row['description']];
  }
  return $scheduleTypes;
}

function getScheduleDates($db) {
  $sql = "SELECT schedule_date, comment FROM schedule_dates order by schedule_date";
  $stmt = $db->query($sql);
  $scheduleDates = [];
  while($row = $stmt->fetch()) {
      $scheduleDates[$row['schedule_date']] = ['comment' => $row['comment']];
  }
  return $scheduleDates;
}

function getAnswers($db) {
  $sql = "SELECT member_id, schedule_date, schedule_type_id, comment FROM answers order by member_id, schedule_date";
  $stmt = $db->query($sql);
  $answers = [];
  while($row = $stmt->fetch()) {
    $answers[$row['member_id']][$row['schedule_date']] = ['schedule_type_id' => $row['schedule_type_id'], 'comment' => $row['comment']];
  }
  return $answers;
}

function youbi($w) {
  $youbi = [0 => '日', 1 => '月', 2 => '火', 3 => '水', 4 => '木', 5 => '金', 6 => '土'];
  return $youbi[$w];
}

function dfmt($date) {
  $d = new DateTime($date);
  return $d->format('n/j').'('.youbi($d->format('w')).')';
}

function updatePassword($db, $member_id, $pass) {
  $hash = password_hash($pass, PASSWORD_DEFAULT);
  $sql = "UPDATE members SET password = :hash, updated_at = now() WHERE id = :member_id";
  $stmt = $db->prepare($sql);
  $stmt->bindParam(':hash', $hash);
  $stmt->bindParam(':member_id', $member_id);
  return $stmt->execute();
}

function updateAnswers($db, $member_id, $dates, $schedule_type_ids, $comments) {
  $sql = "SELECT schedule_date FROM answers where member_id = :member_id";
  $stmt = $db->prepare($sql);
  $stmt->bindParam(':member_id', $member_id);
  $stmt->execute();
  $existsDates = [];
  while($row = $stmt->fetch()) {
    $existsDates[$row['schedule_date']] = true;
  }

  foreach($dates as $i => $date) {
    if ($schedule_type_ids[$i] == '0' && $comments[$i] == '') {
      if (array_key_exists($date, $existsDates)) {
        $sql = "DELETE FROM answers WHERE member_id = :member_id and schedule_date = :date";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':member_id', $member_id);
        $stmt->bindParam(':date', $date);
        $stmt->execute();
      }
    } else {
      $sql = "INSERT INTO answers values(:member_id, :date, :schedule_type_id, :comment, now(), now())";
      if (array_key_exists($date, $existsDates)) {
        $sql = "UPDATE answers SET schedule_type_id = :schedule_type_id, comment = :comment, updated_at = now() WHERE member_id = :member_id and schedule_date = :date";
      }
      $stmt = $db->prepare($sql);
      $stmt->bindParam(':member_id', $member_id);
      $stmt->bindParam(':date', $date);
      $stmt->bindParam(':schedule_type_id', $schedule_type_ids[$i]);
      $stmt->bindParam(':comment', $comments[$i]);
      $stmt->execute();
    }
  }
  return true;
}

function scheduleSummary($members, $dates, $answers) {
  $sammary = [];
  $numOfMember = count($members);

  foreach($answers as $answerDates) {
    foreach($answerDates as $answerDate => $answer) {
      if (isset($sammary[$answerDate][$answer['schedule_type_id']])) {
        $sammary[$answerDate][$answer['schedule_type_id']]++;
      } else {
        $sammary[$answerDate][$answer['schedule_type_id']] = 1;
      }
    }
  }

  foreach ($dates as $date => $c) {
    $totalCount = 0;
    if (isset($sammary[$date])) {
      foreach ($sammary[$date] as $schedule_type_id => $count) {
        if ($schedule_type_id != 0) {
          $totalCount += $count;
        }
      }
    }
    $sammary[$date][0] = $numOfMember - $totalCount;
  }

  return $sammary;
}


function isExistsAdmin($is_admin_arr, $remove_arr) {
  foreach ($is_admin_arr as $i => $is_admin) {
    if ($is_admin == '1' && $remove_arr[$i] == '0') {
      return true;
    }
  }
  return false;
}


function updateMembers($db, $member_id_arr, $name_arr, $is_admin_arr, $remove_arr) {
  $members = getMembers($db);
  $db->beginTransaction();
  $sort_order = 1;

  foreach ($member_id_arr as $i => $member_id) {
    if ($i == 0) continue; // row of origin of copy

    if (isset($members[$member_id])) {
      if ($remove_arr[$i] == '1') {
        $sql = 'DELETE FROM members WHERE id = :member_id';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':member_id', intval($member_id));
        if (!($stmt->execute())) {
          $db->rollback();
          return false;
        }
        $sql = 'DELETE FROM tokens WHERE member_id = :member_id';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':member_id', intval($member_id));
        if (!($stmt->execute())) {
          $db->rollback();
          return false;
        }
      } else if ($members[$member_id]['name'] != $name_arr[$i] ||
        $members[$member_id]['is_admin'] != $is_admin_arr[$i] ||
        $members[$member_id]['sort_order'] != $sort_order) {

        $sql = 'UPDATE members SET name=:name, is_admin=:is_admin, sort_order=:sort_order, updated_at=now() where id=:member_id';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':name', $name_arr[$i]);
        $stmt->bindParam(':is_admin', intval($is_admin_arr[$i]));
        $stmt->bindParam(':sort_order', intval($sort_order));
        $stmt->bindParam(':member_id', intval($member_id));
        if (!($stmt->execute())) {
          $db->rollback();
          return false;
        }
      }
    } else {
      foreach ($members as $member) {
        if ($name_arr[$i] == $member['name']) {
          continue;
        }
      }
      if ($remove_arr[$i] == '0') {
        $sql = 'INSERT INTO members(name, sort_order, is_admin, created_at, updated_at) VALUES(:name, :sort_order, :is_admin, now(), now())';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':name', $name_arr[$i]);
        $stmt->bindParam(':is_admin', intval($is_admin_arr[$i]));
        $stmt->bindParam(':sort_order', intval($sort_order));
        if (!($stmt->execute())) {
          $db->rollback();
          return false;
        }
      }
    }

    $sort_order++;
  }

  $db->commit();
  return true;
}

function updateDates($db, $dates, $comments) {

  foreach($dates as $i => $date) {
    $sql = "UPDATE schedule_dates SET comment = :comment, updated_at = now() WHERE schedule_date = :date";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':date', $date);
    $stmt->bindParam(':comment', $comments[$i]);
    $stmt->execute();
  }

  return true;
}

function updateScheduleTypes($db, $schedule_type_id_arr, $symbol_arr, $desc_arr, $remove_arr) {
  $scheduleTypes = getScheduleTypes($db);
  $db->beginTransaction();
  $sort_order = 1;

  foreach ($schedule_type_id_arr as $i => $schedule_type_id) {
    if ($i == 0) continue; // row of origin of copy

    if (isset($scheduleTypes[$schedule_type_id])) {
      if ($remove_arr[$i] == '1') {
        $sql = 'DELETE FROM schedule_types WHERE id = :schedule_type_id';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':schedule_type_id', intval($schedule_type_id));
        if (!($stmt->execute())) {
          $db->rollback();
          return false;
        }
      } else if ($scheduleTypes[$schedule_type_id]['symbol'] != $symbol_arr[$i] ||
        $scheduleTypes[$schedule_type_id]['description'] != $desc_arr[$i] ||
        $scheduleTypes[$schedule_type_id]['sort_order'] != $sort_order) {

        $sql = 'UPDATE schedule_types SET symbol=:symbol, description=:desc, sort_order=:sort_order, updated_at=now() where id=:schedule_type_id';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':symbol', $symbol_arr[$i]);
        $stmt->bindParam(':desc', $desc_arr[$i]);
        $stmt->bindParam(':sort_order', intval($sort_order));
        $stmt->bindParam(':schedule_type_id', intval($schedule_type_id));
        if (!($stmt->execute())) {
          $db->rollback();
          return false;
        }
      }
    } else {
      foreach ($scheduleTypes as $scheduleType) {
        if ($symbol_arr[$i] == $scheduleType['symbol']) {
          continue;
        }
      }
      if ($remove_arr[$i] == '0') {
        $sql = 'INSERT INTO schedule_types(symbol, description, sort_order, created_at, updated_at) VALUES(:symbol, :desc, :sort_order, now(), now())';
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':symbol', $symbol_arr[$i]);
        $stmt->bindParam(':desc', $desc_arr[$i]);
        $stmt->bindParam(':sort_order', intval($sort_order));
        if (!($stmt->execute())) {
          $db->rollback();
          return false;
        }
      }
    }

    $sort_order++;
  }

  $db->commit();
  return true;
}