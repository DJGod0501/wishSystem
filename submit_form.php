<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/csrf.php';

if (isset($conn) && !isset($pdo)) $pdo = $conn;

/* ================= SUBMIT HANDLER (INLINE) ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit') {

    // CSRF compatible
    $csrf_ok = false;
    if (function_exists('csrf_check')) {
        $csrf_ok = (bool)csrf_check();
    } else {
        $posted = $_POST['csrf_token'] ?? '';
        $sess   = $_SESSION['csrf_token'] ?? ($_SESSION['csrf'] ?? '');
        if ($posted !== '' && $sess !== '' && hash_equals((string)$sess, (string)$posted)) {
            $csrf_ok = true;
        }
    }
    if (!$csrf_ok) {
        http_response_code(400);
        exit('Bad CSRF token');
    }

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'online_posting') {
        http_response_code(403);
        exit('Forbidden');
    }

    $postv = function(string $k): string {
        return trim((string)($_POST[$k] ?? ''));
    };

    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        http_response_code(401);
        exit('Unauthorized');
    }

    $name = $postv('name');
    $age = $postv('age');
    $gender = $postv('gender');
    $phone = $postv('phone');
    $position = $postv('position');
    $transportation = $postv('transportation');
    $education = $postv('education');
    $area = $postv('area');
    $area_other = $postv('area_other');

    // keep simple (no "approx" requirement)
    $expected_salary = $postv('expected_salary');

    $work_experience = $postv('work_experience');
    $start_date = $postv('start_date');

    $cw = $postv('chinese_writing');
    $cs = $postv('chinese_speaking');
    $ew = $postv('english_writing');
    $es = $postv('english_speaking');
    $mw = $postv('malay_writing');
    $ms = $postv('malay_speaking');

    if ($name === '' || $phone === '' || $position === '') {
        header('Location: submit_form.php?err=missing#form');
        exit;
    }

    if (strcasecmp($area, 'Others') === 0 && $area_other !== '') {
        $area = $area_other;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO interview_forms
            (user_id, name, age, gender, phone, position,
             transportation, education, area, expected_salary,
             work_experience, start_date,
             interview_stage, interview_date,
             created_at, stage_updated_at)
            VALUES
            (:uid, :name, :age, :gender, :phone, :position,
             :transportation, :education, :area, :salary,
             :work, :start_date,
             'new', NULL,
             NOW(), NOW())
        ");

        $stmt->execute([
            ':uid' => $user_id,
            ':name' => $name,
            ':age' => ($age === '' ? null : $age),
            ':gender' => ($gender === '' ? null : $gender),
            ':phone' => $phone,
            ':position' => $position,
            ':transportation' => ($transportation === '' ? null : $transportation),
            ':education' => ($education === '' ? null : $education),
            ':area' => ($area === '' ? null : $area),
            ':salary' => ($expected_salary === '' ? null : $expected_salary),
            ':work' => ($work_experience === '' ? null : $work_experience),
            ':start_date' => ($start_date === '' ? null : $start_date),
        ]);

        $form_id = (int)$pdo->lastInsertId();

        $stmt2 = $pdo->prepare("
            INSERT INTO language_skills
            (form_id, chinese_writing, chinese_speaking, english_writing, english_speaking, malay_writing, malay_speaking)
            VALUES
            (:fid, :cw, :cs, :ew, :es, :mw, :ms)
        ");

        $stmt2->execute([
            ':fid' => $form_id,
            ':cw' => ($cw === '' ? null : $cw),
            ':cs' => ($cs === '' ? null : $cs),
            ':ew' => ($ew === '' ? null : $ew),
            ':es' => ($es === '' ? null : $es),
            ':mw' => ($mw === '' ? null : $mw),
            ':ms' => ($ms === '' ? null : $ms),
        ]);

        // after submit, clear prefill so next submit starts clean
        unset($_SESSION['prefill_submission']);

        header('Location: my_form.php?submitted=1');
        exit;

    } catch (Throwable $e) {
        http_response_code(500);
        echo "Submit failed: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        exit;
    }
}

/* ================= helpers / parser ================= */
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function extract_field(string $label, string $text): string {
    if (preg_match('/^\s*' . preg_quote($label, '/') . '\s*:\s*(.*?)\s*$/im', $text, $m)) return trim($m[1]);
    return '';
}
function extract_any(array $labels, string $text): string {
    foreach ($labels as $lb) { $v = extract_field($lb, $text); if ($v !== '') return $v; }
    return '';
}
function normalize_gender(string $v): string {
    $v = strtolower(trim($v));
    if ($v === 'm' || $v === 'male' || $v === 'man') return 'Male';
    if ($v === 'f' || $v === 'female' || $v === 'woman') return 'Female';
    return '';
}
function normalize_yesno(string $v): string {
    $v = strtolower(trim($v));
    if ($v === 'yes' || $v === 'y') return 'Yes';
    if ($v === 'no' || $v === 'n') return 'No';
    return '';
}
function normalize_lang(string $v): string {
    $v = trim($v);
    if ($v === '') return '';
    if (preg_match('/\b([1-3])\b/', $v, $m)) return $m[1];
    return '';
}
function normalize_date(string $v): string {
    $v = trim($v);
    if ($v === '') return '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v;
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $v, $m)) {
        $d=(int)$m[1]; $mo=(int)$m[2]; $y=(int)$m[3];
        if ($mo > 12) { $tmp=$d; $d=$mo; $mo=$tmp; }
        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }
    return '';
}
function normalize_state(string $v): string {
    $v = trim($v);
    if ($v === '') return '';
    $lc = strtolower($v);

    $map = [
        'subang' => 'Selangor',
        'petaling jaya' => 'Selangor',
        'pj' => 'Selangor',
        'shah alam' => 'Selangor',
        'kl' => 'Kuala Lumpur',
        'kuala lumpur' => 'Kuala Lumpur',
        'putrajaya' => 'Putrajaya',
        'labuan' => 'Labuan',
        'penang' => 'Pulau Pinang',
        'pulau pinang' => 'Pulau Pinang',
        'jb' => 'Johor',
        'johor bahru' => 'Johor',
    ];
    foreach ($map as $k => $st) if (strpos($lc, $k) !== false) return $st;

    $states = [
        'Johor','Kedah','Kelantan','Melaka','Negeri Sembilan',
        'Pahang','Perak','Perlis','Pulau Pinang','Sabah','Sarawak',
        'Selangor','Terengganu','Kuala Lumpur','Putrajaya','Labuan'
    ];
    foreach ($states as $s) if (strcasecmp($v, $s) === 0) return $s;

    return 'Others';
}

/* ================= prefill ================= */
$prefill = [
    'name'=>'','age'=>'','gender'=>'','phone'=>'',
    'position'=>'','transportation'=>'','education'=>'',
    'area'=>'','area_other'=>'','expected_salary'=>'',
    'work_experience'=>'','start_date'=>'',
    'chinese_writing'=>'','chinese_speaking'=>'',
    'english_writing'=>'','english_speaking'=>'',
    'malay_writing'=>'','malay_speaking'=>''
];

// Copy session prefill
if (isset($_SESSION['prefill_submission'])) {
    $payload = $_SESSION['prefill_submission'];
    $ts = $payload['ts'] ?? 0;

    if ($ts && (time() - $ts) <= 600) {
        $data = $payload['data'] ?? [];
        if (is_array($data)) {
            foreach ($prefill as $k => $_v) {
                if (isset($data[$k]) && $data[$k] !== '') $prefill[$k] = (string)$data[$k];
            }
        }
    } else {
        unset($_SESSION['prefill_submission']);
    }
}

// Paste parse overrides prefill
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '') === 'parse') {
    $t = $_POST['paste_text'] ?? '';

    $prefill['name'] = extract_any(['Name'], $t);
    $prefill['age'] = extract_any(['Age'], $t);
    $prefill['gender'] = normalize_gender(extract_any(['Gender'], $t));
    $prefill['phone'] = extract_any(['Phone Number','Phone'], $t);
    $prefill['position'] = extract_any(['Position of Interest','Position'], $t);
    $prefill['transportation'] = normalize_yesno(extract_any(['Own Transportation (Yes/No)','Own Transportation'], $t));
    $prefill['education'] = extract_any(['Highest Education Level','Highest Education'], $t);

    $rawArea = extract_any(['Residential Area'], $t);
    $prefill['area'] = normalize_state($rawArea);
    $prefill['area_other'] = ($prefill['area'] === 'Others') ? $rawArea : '';

    $prefill['expected_salary'] = extract_any(['Expected Salary'], $t);
    $prefill['work_experience'] = extract_any(['Work Experience'], $t);
    $prefill['start_date'] = normalize_date(extract_any(['Available Start Date'], $t));

    $prefill['chinese_writing'] = normalize_lang(extract_any(['Chinese Writing'], $t));
    $prefill['chinese_speaking'] = normalize_lang(extract_any(['Chinese Speaking'], $t));
    $prefill['english_writing'] = normalize_lang(extract_any(['English Writing'], $t));
    $prefill['english_speaking'] = normalize_lang(extract_any(['English Speaking'], $t));
    $prefill['malay_writing'] = normalize_lang(extract_any(['Malay Writing'], $t));
    $prefill['malay_speaking'] = normalize_lang(extract_any(['Malay Speaking'], $t));
}

include 'header.php';
?>

<h4>Submit Interview Form</h4>

<?php if (isset($_GET['err']) && $_GET['err'] === 'missing'): ?>
  <div class="alert alert-danger">Please fill at least Name, Phone, and Position.</div>
<?php endif; ?>

<div class="d-flex flex-wrap gap-2 mb-3">
  <form method="POST" action="copy_last_submission.php" class="m-0">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <button type="submit" class="btn btn-outline-primary">Copy Last Submission</button>
  </form>

  <form method="POST" action="clear_prefill.php" class="m-0">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <button type="submit" class="btn btn-outline-secondary">Clear Prefill</button>
  </form>

  <?php if (isset($_GET['copy']) && $_GET['copy'] === 'ok'): ?>
    <div class="alert alert-success py-2 px-3 m-0">Last submission loaded.</div>
  <?php elseif (isset($_GET['copy']) && $_GET['copy'] === 'none'): ?>
    <div class="alert alert-warning py-2 px-3 m-0">No previous submission found.</div>
  <?php elseif (isset($_GET['prefill']) && $_GET['prefill'] === 'cleared'): ?>
    <div class="alert alert-info py-2 px-3 m-0">Prefill cleared.</div>
  <?php endif; ?>
</div>

<!-- Paste parser -->
<div class="card mb-3">
  <div class="card-header">Copy & Paste (Optional)</div>
  <div class="card-body">
    <form method="POST" action="<?= h($_SERVER['PHP_SELF']) ?>">
      <input type="hidden" name="action" value="parse">
      <textarea name="paste_text" class="form-control mb-2" rows="10" placeholder="Paste interview text here..."></textarea>
      <button class="btn btn-secondary">Parse & Fill</button>
    </form>
  </div>
</div>

<!-- Form anchor: Copy should land here -->
<div id="form"></div>

<form method="POST" action="<?= h($_SERVER['PHP_SELF']) ?>" class="card">
<div class="card-body">
  <input type="hidden" name="action" value="submit">
  <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

  <label>Name</label>
  <input class="form-control" name="name" value="<?= h($prefill['name']) ?>">

  <label>Age</label>
  <input class="form-control" name="age" value="<?= h($prefill['age']) ?>">

  <label>Gender</label>
  <select class="form-select" name="gender">
    <option value="">-- Select --</option>
    <option value="Male" <?= $prefill['gender']==='Male'?'selected':'' ?>>Male</option>
    <option value="Female" <?= $prefill['gender']==='Female'?'selected':'' ?>>Female</option>
  </select>

  <label>Phone</label>
  <input class="form-control" name="phone" value="<?= h($prefill['phone']) ?>">

  <label>Position</label>
  <input class="form-control" name="position" value="<?= h($prefill['position']) ?>">

  <label>Own Transportation</label>
  <select class="form-select" name="transportation">
    <option value="">-- Select --</option>
    <option value="Yes" <?= $prefill['transportation']==='Yes'?'selected':'' ?>>Yes</option>
    <option value="No" <?= $prefill['transportation']==='No'?'selected':'' ?>>No</option>
  </select>

  <label>Education</label>
  <input class="form-control" name="education" value="<?= h($prefill['education']) ?>">

  <label>Residential Area</label>
  <select class="form-select" name="area">
    <?php
      $states = ['Johor','Kedah','Kelantan','Melaka','Negeri Sembilan','Pahang','Perak','Perlis','Pulau Pinang','Sabah','Sarawak','Selangor','Terengganu','Kuala Lumpur','Putrajaya','Labuan','Others'];
      foreach ($states as $s) {
          $sel = ($prefill['area']===$s)?'selected':'';
          echo "<option value='".h($s)."' {$sel}>".h($s)."</option>";
      }
    ?>
  </select>

  <?php if ($prefill['area'] === 'Others'): ?>
    <label>Residential Area (Others)</label>
    <input class="form-control" name="area_other" value="<?= h($prefill['area_other']) ?>">
  <?php endif; ?>

  <label>Expected Salary</label>
  <input class="form-control" name="expected_salary" value="<?= h($prefill['expected_salary']) ?>">

  <label>Work Experience</label>
  <textarea class="form-control" name="work_experience" rows="4"><?= h($prefill['work_experience']) ?></textarea>

  <label>Available Start Date</label>
  <input class="form-control" name="start_date" value="<?= h($prefill['start_date']) ?>" placeholder="YYYY-MM-DD">

  <hr class="my-3">

  <?php
  function lang_sel($name, $val) {
      $val = (string)$val;
      echo "<label class='mt-2'>" . h(ucfirst(str_replace('_',' ', $name))) . "</label>";
      echo "<select class='form-select' name='".h($name)."'>";
      echo "<option value=''>-- Select --</option>";
      for ($i=1; $i<=3; $i++) {
          $sel = ($val === (string)$i) ? 'selected' : '';
          echo "<option value='{$i}' {$sel}>{$i}</option>";
      }
      echo "</select>";
  }
  lang_sel('chinese_writing',$prefill['chinese_writing']);
  lang_sel('chinese_speaking',$prefill['chinese_speaking']);
  lang_sel('english_writing',$prefill['english_writing']);
  lang_sel('english_speaking',$prefill['english_speaking']);
  lang_sel('malay_writing',$prefill['malay_writing']);
  lang_sel('malay_speaking',$prefill['malay_speaking']);
  ?>

  <button class="btn btn-primary mt-3">Submit</button>
</div>
</form>

<script>
// ✅ Auto-scroll to form after Copy / Clear / validation error
(function () {
  const params = new URLSearchParams(window.location.search);
  if (params.get('copy') === 'ok' || params.get('prefill') === 'cleared' || params.get('err') === 'missing') {
    const el = document.getElementById('form');
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
})();
</script>

<?php include 'footer.php'; ?>
