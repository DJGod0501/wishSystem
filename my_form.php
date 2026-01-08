<?php
require_once __DIR__."/auth_check.php";
if($_SESSION["role"]!=="online_posting") die("Access denied");

$title="My Forms";
require_once __DIR__."/header.php";

$filter=$_GET["filter"]??"";
$where="user_id=:uid";
$params=["uid"=>$_SESSION["user_id"]];

if($filter==="today"){
  $where.=" AND DATE(created_at)=CURDATE()";
}
if($filter==="week"){
  $where.=" AND created_at>=DATE_SUB(CURDATE(),INTERVAL 7 DAY)";
}

$stmt=$conn->prepare("SELECT * FROM interview_forms WHERE $where ORDER BY created_at DESC");
$stmt->execute($params);
$rows=$stmt->fetchAll();
?>

<h3 class="ws-page-title mb-3">My Interview Forms</h3>

<div class="mb-3">
  <a class="btn btn-outline-primary btn-sm" href="?filter=today">Today</a>
  <a class="btn btn-outline-primary btn-sm" href="?filter=week">This Week</a>
  <a class="btn btn-outline-secondary btn-sm" href="my_form.php">All</a>
</div>

<div class="card p-3">
<table class="table table-hover">
<thead>
<tr><th>ID</th><th>Candidate</th><th>Position</th><th>Date</th><th></th></tr>
</thead>
<tbody>
<?php foreach($rows as $i=>$r): ?>
<tr class="<?=($i===0?"table-success":"")?>">
<td><?=$r["form_id"]?></td>
<td><?=htmlspecialchars($r["name"])?></td>
<td><?=htmlspecialchars($r["position"])?></td>
<td><?=$r["created_at"]?></td>
<td><a class="btn btn-sm btn-primary" href="form_detail.php?id=<?=$r["form_id"]?>">View</a></td>
</tr>
<?php endforeach;?>
</tbody>
</table>
</div>

<?php require_once __DIR__."/footer.php"; ?>
