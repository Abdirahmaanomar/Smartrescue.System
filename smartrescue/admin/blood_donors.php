<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php"); exit();
}
$tables = mysqli_query($conn, "SHOW TABLES LIKE 'blood_donors'");
if (mysqli_num_rows($tables) == 0) {
    mysqli_query($conn, "CREATE TABLE blood_donors (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, blood_type VARCHAR(10) NOT NULL, phone VARCHAR(50) NOT NULL, lat DECIMAL(10,8) NULL, lng DECIMAL(11,8) NULL, is_available TINYINT(1) DEFAULT 1, notes TEXT DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $defaults = [['Kumar Maxamed','B+','689109810'],['Dr. Abdirahman Jama','O+','615112233'],['Amino Hassan','A+','615223344'],['Farhiya Ali','B+','615334455'],['Mohamud Omar','AB+','615445566']];
    foreach ($defaults as $d) { $n=mysqli_real_escape_string($conn,$d[0]);$b=mysqli_real_escape_string($conn,$d[1]);$p=mysqli_real_escape_string($conn,$d[2]); mysqli_query($conn,"INSERT INTO blood_donors(name,blood_type,phone,is_available)VALUES('$n','$b','$p',1)"); }
}
$cols = mysqli_query($conn, "SHOW COLUMNS FROM blood_donors LIKE 'notes'");
if (mysqli_num_rows($cols) == 0) { mysqli_query($conn,"ALTER TABLE blood_donors ADD COLUMN notes TEXT DEFAULT NULL AFTER is_available"); }
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $a=$_POST['action'];
    if($a==='add'){$n=mysqli_real_escape_string($conn,trim($_POST['name']??''));$b=mysqli_real_escape_string($conn,trim($_POST['blood_type']??''));$p=mysqli_real_escape_string($conn,trim($_POST['phone']??''));$nt=mysqli_real_escape_string($conn,trim($_POST['notes']??''));$av=(int)($_POST['is_available']??1);if(!$n||!$b||!$p){echo json_encode(['success'=>false,'msg'=>'Required fields missing']);exit;}$r=mysqli_query($conn,"INSERT INTO blood_donors(name,blood_type,phone,notes,is_available)VALUES('$n','$b','$p','$nt',$av)");echo json_encode(['success'=>(bool)$r,'id'=>mysqli_insert_id($conn)]);exit;}
    if($a==='edit'){$id=(int)($_POST['id']??0);$n=mysqli_real_escape_string($conn,trim($_POST['name']??''));$b=mysqli_real_escape_string($conn,trim($_POST['blood_type']??''));$p=mysqli_real_escape_string($conn,trim($_POST['phone']??''));$nt=mysqli_real_escape_string($conn,trim($_POST['notes']??''));$av=(int)($_POST['is_available']??1);if(!$id||!$n||!$b||!$p){echo json_encode(['success'=>false,'msg'=>'Required fields missing']);exit;}$r=mysqli_query($conn,"UPDATE blood_donors SET name='$n',blood_type='$b',phone='$p',notes='$nt',is_available=$av WHERE id=$id");echo json_encode(['success'=>(bool)$r]);exit;}
    if($a==='delete'){$id=(int)($_POST['id']??0);$r=mysqli_query($conn,"DELETE FROM blood_donors WHERE id=$id");echo json_encode(['success'=>(bool)$r]);exit;}
    if($a==='toggle'){$id=(int)($_POST['id']??0);mysqli_query($conn,"UPDATE blood_donors SET is_available=1-is_available WHERE id=$id");$row=mysqli_fetch_assoc(mysqli_query($conn,"SELECT is_available FROM blood_donors WHERE id=$id"));echo json_encode(['success'=>true,'new_val'=>(int)($row['is_available']??0)]);exit;}
    echo json_encode(['success'=>false,'msg'=>'Unknown action']);exit;
}
$res=mysqli_query($conn,"SELECT * FROM blood_donors ORDER BY created_at DESC");
$donors=[];
while($row=mysqli_fetch_assoc($res))$donors[]=$row;
$total=count($donors);$avail_ct=count(array_filter($donors,fn($d)=>$d['is_available']==1));$busy_ct=$total-$avail_ct;
$bt_counts=[];foreach($donors as $d){$bt=$d['blood_type'];$bt_counts[$bt]=($bt_counts[$bt]??0)+1;}arsort($bt_counts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Blood Donors and Volunteers | SmartRescue Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#f0f4f9;--card:#fff;--text:#0f172a;--muted:#64748b;--border:#e2e8f0;--accent:#e11d48;--accent-grad:linear-gradient(135deg,#e11d48 0%,#9f1239 100%);--accent-glow:rgba(225,29,72,.15);--green:#10b981;--green-bg:rgba(16,185,129,.08);--green-border:rgba(16,185,129,.2);--sidebar-w:268px;--topbar-h:72px;--r-md:12px;--r-lg:16px;--r-xl:20px;--shadow-sm:0 1px 3px rgba(0,0,0,.04);--shadow-md:0 8px 24px -8px rgba(0,0,0,.08),0 1px 3px rgba(0,0,0,.03);--shadow-lg:0 20px 40px -12px rgba(0,0,0,.12),0 1px 5px rgba(0,0,0,.04);}
body{font-family:'Outfit',sans-serif;background:var(--bg);color:var(--text);overflow-x:hidden;}
body::before{content:'';position:fixed;top:-10%;right:-10%;width:60vw;height:60vw;background:radial-gradient(circle,rgba(225,29,72,.05) 0%,transparent 70%);z-index:-1;pointer-events:none;}
::-webkit-scrollbar{width:6px}::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:10px}
.main-wrapper{margin-left:var(--sidebar-w);min-height:100vh;display:flex;flex-direction:column;}
.topbar{position:sticky;top:0;z-index:200;background:rgba(255,255,255,.88);backdrop-filter:blur(20px) saturate(120%);border-bottom:1px solid var(--border);height:var(--topbar-h);display:flex;align-items:center;padding:0 36px;gap:16px;box-shadow:var(--shadow-sm);}
.topbar-title{font-size:1.15rem;font-weight:800;display:flex;align-items:center;gap:12px;letter-spacing:-.3px;}
.topbar-icon{width:44px;height:44px;background:var(--accent-grad);border-radius:var(--r-md);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1rem;box-shadow:0 6px 18px rgba(225,29,72,.3);flex-shrink:0;animation:breathe 3s infinite ease-in-out;}
@keyframes breathe{0%,100%{box-shadow:0 6px 18px rgba(225,29,72,.3)}50%{box-shadow:0 10px 28px rgba(225,29,72,.5)}}
.topbar-spacer{flex:1}
.topbar-clock{display:flex;align-items:center;gap:8px;font-size:.84rem;font-weight:700;background:rgba(255,255,255,.7);border:1.5px solid var(--border);border-radius:50px;padding:7px 16px;}
.clock-dot{width:6px;height:6px;border-radius:50%;background:var(--green);box-shadow:0 0 8px var(--green);animation:pdot 2s infinite ease-in-out;}
@keyframes pdot{0%,100%{opacity:1;transform:scale(.9)}50%{opacity:.4;transform:scale(1.2)}}
.content-area{padding:32px 36px 56px;display:flex;flex-direction:column;gap:24px;max-width:1600px;width:100%;margin:0 auto;}
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;}
.stat-card{background:var(--card);border-radius:var(--r-lg);border:1.5px solid var(--border);padding:20px 22px;display:flex;align-items:center;gap:16px;box-shadow:var(--shadow-md);transition:all .3s ease;position:relative;overflow:hidden;}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;border-radius:var(--r-lg) var(--r-lg) 0 0;opacity:0;transition:opacity .3s;}
.stat-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-lg);}
.stat-card:hover::before{opacity:1;}
.sc-r::before{background:linear-gradient(90deg,#e11d48,#f43f5e)}.sc-g::before{background:linear-gradient(90deg,#10b981,#34d399)}.sc-a::before{background:linear-gradient(90deg,#f59e0b,#fbbf24)}.sc-p::before{background:linear-gradient(90deg,#8b5cf6,#a78bfa)}
.stat-icon{width:50px;height:50px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;}
.stat-num{font-size:2rem;font-weight:900;line-height:1;letter-spacing:-1px;}
.stat-lbl{font-size:.73rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;margin-top:3px;}
.bt-pills{display:flex;flex-wrap:wrap;gap:8px;}
.bt-pill{display:inline-flex;align-items:center;gap:8px;padding:6px 14px 6px 8px;border-radius:50px;font-size:.8rem;font-weight:800;border:1.5px solid;transition:transform .2s;}
.bt-pill:hover{transform:scale(1.05);}
.bt-drop{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.68rem;font-weight:900;color:#fff;}
.toolbar{display:flex;align-items:center;gap:14px;flex-wrap:wrap;background:var(--card);padding:18px 22px;border-radius:var(--r-xl);border:1.5px solid var(--border);box-shadow:var(--shadow-md);transition:all .3s;}
.toolbar:hover{box-shadow:var(--shadow-lg);border-color:rgba(225,29,72,.15);}
.search-wrap{position:relative;flex:1;min-width:240px;}
.search-wrap i{position:absolute;left:16px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:.85rem;pointer-events:none;}
.search-input{width:100%;padding:11px 16px 11px 42px;border:1.5px solid var(--border);border-radius:50px;font-family:'Outfit',sans-serif;font-size:.87rem;font-weight:500;color:var(--text);background:var(--bg);outline:none;transition:all .25s;}
.search-input:focus{border-color:var(--accent);background:#fff;box-shadow:0 0 0 4px var(--accent-glow);}
.search-input::placeholder{color:var(--muted);}
.fsel{padding:11px 16px;border:1.5px solid var(--border);border-radius:50px;font-family:'Outfit',sans-serif;font-size:.87rem;font-weight:600;color:var(--text);background:var(--bg);outline:none;cursor:pointer;}
.fsel:focus{border-color:var(--accent);box-shadow:0 0 0 4px var(--accent-glow);}
.res-lbl{font-size:.82rem;font-weight:700;color:var(--accent);white-space:nowrap;background:rgba(225,29,72,.06);border-radius:50px;padding:9px 16px;border:1.5px solid rgba(225,29,72,.12);display:inline-flex;align-items:center;gap:6px;}
.add-btn{background:var(--accent-grad);color:#fff;padding:11px 22px;border-radius:50px;font-weight:700;font-size:.87rem;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:8px;white-space:nowrap;transition:all .3s cubic-bezier(.4,0,.2,1);box-shadow:0 4px 16px rgba(225,29,72,.3);}
.add-btn:hover{transform:translateY(-2px);box-shadow:0 8px 26px rgba(225,29,72,.4);}
.donors-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:18px;}
.donor-card{background:var(--card);border-radius:var(--r-lg);border:1.5px solid var(--border);box-shadow:var(--shadow-md);overflow:hidden;transition:all .3s cubic-bezier(.4,0,.2,1);display:flex;flex-direction:column;}
.donor-card:hover{transform:translateY(-5px);box-shadow:var(--shadow-lg);border-color:rgba(225,29,72,.2);}
.donor-card.unavail{opacity:.62;}
.card-bar{height:4px;background:var(--accent-grad);}
.donor-card.unavail .card-bar{background:linear-gradient(90deg,#94a3b8,#cbd5e1);}
.card-body{padding:18px 20px 14px;flex:1;}
.badge-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;}
.blood-badge{width:52px;height:52px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1rem;font-weight:900;color:#fff;box-shadow:0 4px 14px rgba(0,0,0,.15);letter-spacing:-.5px;flex-shrink:0;transition:transform .3s;}
.donor-card:hover .blood-badge{transform:scale(1.1) rotate(-5deg);}
.avail-badge{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:50px;font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.6px;}
.avail-badge.yes{background:var(--green-bg);color:#047857;border:1.5px solid var(--green-border);}
.avail-badge.no{background:rgba(148,163,184,.08);color:#64748b;border:1.5px solid rgba(148,163,184,.18);}
.avail-dot{width:5px;height:5px;border-radius:50%;background:currentColor;animation:pdot 2s infinite;}
.avail-badge.no .avail-dot{animation:none;}
.donor-name{font-size:1rem;font-weight:800;color:var(--text);margin-bottom:4px;letter-spacing:-.2px;transition:color .2s;}
.donor-card:hover .donor-name{color:var(--accent);}
.donor-phone{display:flex;align-items:center;gap:7px;font-size:.84rem;font-weight:600;color:var(--muted);}
.donor-notes{font-size:.76rem;color:var(--muted);font-style:italic;margin-top:6px;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.card-footer{padding:12px 20px;border-top:1px solid var(--border);display:flex;align-items:center;gap:8px;background:rgba(248,250,252,.6);}
.date-chip{font-size:.7rem;color:var(--muted);font-weight:600;display:flex;align-items:center;gap:5px;flex:1;}
.act-btn{width:34px;height:34px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;border:1.5px solid var(--border);cursor:pointer;transition:all .25s cubic-bezier(.4,0,.2,1);font-size:.8rem;background:var(--bg);color:var(--muted);text-decoration:none;}
.act-btn:hover{transform:translateY(-2px) scale(1.08);}
.ac{color:#2563eb;border-color:rgba(37,99,235,.15);background:rgba(37,99,235,.05)}.ac:hover{background:#2563eb!important;color:#fff!important;border-color:#2563eb!important;box-shadow:0 4px 12px rgba(37,99,235,.3)}
.ae{color:#7c3aed;border-color:rgba(124,58,237,.15);background:rgba(124,58,237,.05)}.ae:hover{background:#7c3aed!important;color:#fff!important;border-color:#7c3aed!important;box-shadow:0 4px 12px rgba(124,58,237,.3)}
.at{color:#f59e0b;border-color:rgba(245,158,11,.15);background:rgba(245,158,11,.05)}.at:hover{background:#f59e0b!important;color:#fff!important;border-color:#f59e0b!important;box-shadow:0 4px 12px rgba(245,158,11,.3)}
.ad{color:#dc2626;border-color:rgba(220,38,38,.15);background:rgba(220,38,38,.05)}.ad:hover{background:#dc2626!important;color:#fff!important;border-color:#dc2626!important;box-shadow:0 4px 12px rgba(220,38,38,.3)}
.empty-state{text-align:center;padding:80px 24px;background:var(--card);border-radius:var(--r-xl);border:1.5px solid var(--border);box-shadow:var(--shadow-md);}
.empty-icon{width:80px;height:80px;border-radius:50%;background:rgba(225,29,72,.06);border:2px solid rgba(225,29,72,.12);display:flex;align-items:center;justify-content:center;font-size:2rem;color:var(--accent);margin:0 auto 20px;animation:breathe 3s infinite;}
.empty-state h3{font-size:1.2rem;font-weight:800;margin-bottom:8px}
.empty-state p{color:var(--muted);font-size:.88rem}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);backdrop-filter:blur(8px);z-index:9000;align-items:center;justify-content:center;}
.modal-overlay.open{display:flex;}
.modal-box{background:#fff;border-radius:24px;box-shadow:0 32px 80px rgba(0,0,0,.2);padding:36px;width:100%;max-width:500px;max-height:90vh;overflow-y:auto;animation:min .3s cubic-bezier(.34,1.56,.64,1);}
@keyframes min{from{opacity:0;transform:scale(.9) translateY(20px)}to{opacity:1;transform:scale(1) translateY(0)}}
.modal-hdr{display:flex;align-items:center;gap:14px;margin-bottom:26px;}
.modal-hdr-icon{width:44px;height:44px;border-radius:12px;background:var(--accent-grad);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1rem;box-shadow:0 4px 14px rgba(225,29,72,.25);}
.modal-title{font-size:1.15rem;font-weight:800;letter-spacing:-.2px;}
.modal-sub{font-size:.75rem;color:var(--muted);margin-top:2px;}
.flbl{font-size:.78rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.7px;margin-bottom:8px;display:block;}
.finput,.fssel,.ftextarea{width:100%;padding:11px 14px;border:1.5px solid var(--border);border-radius:12px;font-family:'Outfit',sans-serif;font-size:.9rem;font-weight:600;color:var(--text);background:var(--bg);outline:none;transition:all .2s;}
.finput:focus,.fssel:focus,.ftextarea:focus{border-color:var(--accent);background:#fff;box-shadow:0 0 0 4px var(--accent-glow);}
.finput::placeholder,.ftextarea::placeholder{color:#94a3b8;}
.ftextarea{resize:vertical;}
.tog-row{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;background:var(--bg);border-radius:12px;border:1.5px solid var(--border);}
.tog-lbl{font-size:.88rem;font-weight:700;}
.tog-sub{font-size:.72rem;color:var(--muted);margin-top:2px;}
.ios-toggle{position:relative;display:inline-block;width:46px;height:26px;}
.ios-toggle input{opacity:0;width:0;height:0;}
.ios-slider{position:absolute;cursor:pointer;inset:0;background:#cbd5e1;border-radius:13px;transition:background .3s;}
.ios-slider::before{content:'';position:absolute;width:20px;height:20px;left:3px;bottom:3px;background:#fff;border-radius:50%;box-shadow:0 2px 6px rgba(0,0,0,.18);transition:transform .3s cubic-bezier(.34,1.56,.64,1);}
.ios-toggle input:checked + .ios-slider{background:var(--accent);}
.ios-toggle input:checked + .ios-slider::before{transform:translateX(20px);}
.modal-ftr{display:flex;gap:10px;justify-content:flex-end;margin-top:26px;}
.btn-cancel{padding:11px 22px;border-radius:50px;font-weight:700;font-size:.88rem;cursor:pointer;background:var(--bg);color:var(--muted);border:1.5px solid var(--border);transition:all .2s;}
.btn-cancel:hover{background:#e2e8f0;}
.btn-save{padding:11px 28px;border-radius:50px;font-weight:700;font-size:.88rem;cursor:pointer;background:var(--accent-grad);color:#fff;border:none;transition:all .3s;box-shadow:0 4px 14px rgba(225,29,72,.25);}
.btn-save:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(225,29,72,.35);}
.del-icon{width:70px;height:70px;border-radius:50%;background:rgba(239,68,68,.08);border:2px solid rgba(239,68,68,.15);display:flex;align-items:center;justify-content:center;font-size:1.6rem;color:#ef4444;margin:0 auto 18px;}
.del-title{font-size:1.2rem;font-weight:800;text-align:center;margin-bottom:8px;}
.del-desc{color:var(--muted);text-align:center;font-size:.88rem;line-height:1.5;}
.btn-del{background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;padding:11px 28px;border-radius:50px;font-weight:700;font-size:.88rem;cursor:pointer;border:none;box-shadow:0 4px 14px rgba(239,68,68,.25);transition:all .3s;}
.btn-del:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(239,68,68,.35);}
.toast-wrap{position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:10px;}
.toast{display:flex;align-items:center;gap:12px;background:#0f172a;color:#fff;padding:14px 18px;border-radius:14px;font-size:.88rem;font-weight:600;box-shadow:0 8px 32px rgba(0,0,0,.25);animation:tin .4s cubic-bezier(.34,1.56,.64,1);max-width:320px;}
@keyframes tin{from{opacity:0;transform:translateX(60px) scale(.9)}to{opacity:1;transform:translateX(0) scale(1)}}
.t-icon{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.85rem;flex-shrink:0;}
.toast.ok .t-icon{background:rgba(16,185,129,.2);color:#10b981}
.toast.err .t-icon{background:rgba(239,68,68,.2);color:#ef4444}
#no-results{display:none}
@media(max-width:992px){.main-wrapper{margin-left:0}.topbar{padding:0 20px}.content-area{padding:20px 16px 40px}}
@media(max-width:640px){.stats-row{grid-template-columns:1fr 1fr}.donors-grid{grid-template-columns:1fr}.topbar-clock{display:none}}
</style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-wrapper">
<header class="topbar">
  <div class="topbar-title">
    <div class="topbar-icon"><i class="fa fa-droplet"></i></div>
    Blood Donors &amp; Volunteers
  </div>
  <div class="topbar-spacer"></div>
  <div class="topbar-clock"><span class="clock-dot"></span><span id="clockTime">--:--</span></div>
</header>
<div class="content-area">

<!-- STATS -->
<div class="stats-row">
  <div class="stat-card sc-r">
    <div class="stat-icon" style="background:rgba(225,29,72,.1);color:#e11d48"><i class="fa fa-users"></i></div>
    <div><div class="stat-num" style="color:#e11d48" id="stat-total"><?php echo $total; ?></div><div class="stat-lbl">Total Donors</div></div>
  </div>
  <div class="stat-card sc-g">
    <div class="stat-icon" style="background:rgba(16,185,129,.1);color:#10b981"><i class="fa fa-heart-pulse"></i></div>
    <div><div class="stat-num" style="color:#10b981" id="stat-avail"><?php echo $avail_ct; ?></div><div class="stat-lbl">Available Now</div></div>
  </div>
  <div class="stat-card sc-a">
    <div class="stat-icon" style="background:rgba(245,158,11,.1);color:#f59e0b"><i class="fa fa-pause-circle"></i></div>
    <div><div class="stat-num" style="color:#f59e0b" id="stat-busy"><?php echo $busy_ct; ?></div><div class="stat-lbl">Unavailable</div></div>
  </div>
  <div class="stat-card sc-p">
    <div class="stat-icon" style="background:rgba(139,92,246,.1);color:#8b5cf6"><i class="fa fa-vials"></i></div>
    <div><div class="stat-num" style="color:#8b5cf6"><?php echo count($bt_counts); ?></div><div class="stat-lbl">Blood Types</div></div>
  </div>
</div>

<!-- BLOOD TYPE PILLS -->
<?php
$btc=['A+'=>['#ef4444','rgba(239,68,68,.1)','rgba(239,68,68,.25)'],'A-'=>['#f87171','rgba(248,113,113,.1)','rgba(248,113,113,.25)'],'B+'=>['#f59e0b','rgba(245,158,11,.1)','rgba(245,158,11,.25)'],'B-'=>['#fbbf24','rgba(251,191,36,.1)','rgba(251,191,36,.25)'],'AB+'=>['#8b5cf6','rgba(139,92,246,.1)','rgba(139,92,246,.25)'],'AB-'=>['#a78bfa','rgba(167,139,250,.1)','rgba(167,139,250,.25)'],'O+'=>['#10b981','rgba(16,185,129,.1)','rgba(16,185,129,.25)'],'O-'=>['#34d399','rgba(52,211,153,.1)','rgba(52,211,153,.25)']];
if(!empty($bt_counts)):?>
<div style="background:var(--card);border-radius:var(--r-lg);border:1.5px solid var(--border);padding:18px 22px;box-shadow:var(--shadow-md);">
  <div style="font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:14px;"><i class="fa fa-chart-bar"></i>&nbsp; Blood Type Distribution</div>
  <div class="bt-pills">
    <?php foreach($bt_counts as $bt=>$cnt):$c=$btc[$bt]??['#94a3b8','rgba(148,163,184,.1)','rgba(148,163,184,.25)'];?>
    <div class="bt-pill" style="background:<?php echo $c[1];?>;color:<?php echo $c[0];?>;border-color:<?php echo $c[2];?>;">
      <div class="bt-drop" style="background:<?php echo $c[0];?>"><?php echo htmlspecialchars($bt);?></div>
      <?php echo $cnt; ?> donor<?php echo $cnt!=1?'s':''; ?>
    </div>
    <?php endforeach;?>
  </div>
</div>
<?php endif;?>

<!-- TOOLBAR -->
<div class="toolbar">
  <div class="search-wrap">
    <i class="fa fa-magnifying-glass"></i>
    <input type="text" class="search-input" id="searchQ" placeholder="Search donors by name, phone or blood type..." oninput="filterDonors()" autocomplete="off">
  </div>
  <select class="fsel" id="filterBt" onchange="filterDonors()">
    <option value="">All Blood Types</option>
    <option value="A+">A+</option><option value="A-">A-</option><option value="B+">B+</option><option value="B-">B-</option>
    <option value="AB+">AB+</option><option value="AB-">AB-</option><option value="O+">O+</option><option value="O-">O-</option>
  </select>
  <select class="fsel" id="filterAv" onchange="filterDonors()">
    <option value="">All Status</option>
    <option value="1">Available</option>
    <option value="0">Unavailable</option>
  </select>
  <span class="res-lbl"><i class="fa fa-droplet"></i> <span id="resN"><?php echo $total;?></span> Donors</span>
  <button class="add-btn" onclick="openAdd()" id="btn-add-donor"><i class="fa fa-plus"></i> Add Donor</button>
</div>

<!-- DONOR GRID -->
<?php
$btColors=['A+'=>'#ef4444','A-'=>'#f87171','B+'=>'#f59e0b','B-'=>'#fbbf24','AB+'=>'#8b5cf6','AB-'=>'#a78bfa','O+'=>'#10b981','O-'=>'#34d399'];
if(empty($donors)):?>
<div class="empty-state">
  <div class="empty-icon"><i class="fa fa-droplet"></i></div>
  <h3>No Blood Donors Yet</h3>
  <p>Click "Add Donor" to register your first volunteer blood donor.</p>
</div>
<?php else:?>
<div class="donors-grid" id="donorsGrid">
<?php foreach($donors as $d):
  $av=(int)$d['is_available'];
  $clr=$btColors[$d['blood_type']]??'#94a3b8';
  $s=strtolower($d['name'].' '.$d['phone'].' '.$d['blood_type']);
?>
<div class="donor-card <?php echo $av?'':'unavail';?>" data-id="<?php echo $d['id'];?>" data-search="<?php echo htmlspecialchars($s);?>" data-bt="<?php echo htmlspecialchars($d['blood_type']);?>" data-av="<?php echo $av;?>">
  <div class="card-bar"></div>
  <div class="card-body">
    <div class="badge-row">
      <div class="blood-badge" style="background:linear-gradient(135deg,<?php echo $clr;?>,<?php echo $clr;?>cc)"><?php echo htmlspecialchars($d['blood_type']);?></div>
      <div class="avail-badge <?php echo $av?'yes':'no';?>"><span class="avail-dot"></span><?php echo $av?'Available':'Unavailable';?></div>
    </div>
    <div class="donor-name"><?php echo htmlspecialchars($d['name']);?></div>
    <div class="donor-phone"><i class="fa fa-phone" style="font-size:.72rem;opacity:.5"></i> <?php echo htmlspecialchars($d['phone']);?></div>
    <?php if(!empty($d['notes'])):?><div class="donor-notes"><?php echo htmlspecialchars($d['notes']);?></div><?php endif;?>
  </div>
  <div class="card-footer">
    <div class="date-chip"><i class="fa fa-calendar-days" style="opacity:.4"></i> <?php echo date('M d, Y',strtotime($d['created_at']));?></div>
    <a href="tel:<?php echo htmlspecialchars($d['phone']);?>" class="act-btn ac" title="Call"><i class="fa fa-phone"></i></a>
    <button class="act-btn ae" title="Edit" onclick='openEdit(<?php echo json_encode($d);?>)'><i class="fa fa-pen"></i></button>
    <button class="act-btn at" title="Toggle availability" onclick="toggleDonor(<?php echo $d['id'];?>,this)"><i class="fa <?php echo $av?'fa-toggle-on':'fa-toggle-off';?>"></i></button>
    <button class="act-btn ad" title="Delete" onclick="openDel(<?php echo $d['id'];?>,'<?php echo htmlspecialchars(addslashes($d['name']));?>')"><i class="fa fa-trash"></i></button>
  </div>
</div>
<?php endforeach;?>
</div>
<div id="no-results" class="empty-state">
  <div class="empty-icon"><i class="fa fa-magnifying-glass"></i></div>
  <h3>No Donors Found</h3>
  <p>Try adjusting your search or filter criteria.</p>
</div>
<?php endif;?>

</div><!-- /content-area -->
</div><!-- /main-wrapper -->

<!-- ADD/EDIT MODAL -->
<div class="modal-overlay" id="formModal" onclick="if(event.target===this)closeForm()">
<div class="modal-box">
  <div class="modal-hdr">
    <div class="modal-hdr-icon"><i class="fa fa-droplet"></i></div>
    <div><div class="modal-title" id="mTitle">Add Blood Donor</div><div class="modal-sub" id="mSub">Register a new volunteer donor</div></div>
  </div>
  <form id="donorForm" onsubmit="submitForm(event)">
    <input type="hidden" id="fId" name="id" value="">
    <input type="hidden" id="fAction" name="action" value="add">
    <div class="mb-3"><label class="flbl">Full Name *</label><input type="text" name="name" id="fName" class="finput" placeholder="e.g. Abdirahman Ali" required autocomplete="off"></div>
    <div class="mb-3">
      <label class="flbl">Blood Type *</label>
      <select name="blood_type" id="fBlood" class="fssel" required>
        <option value="">Select Blood Type</option>
        <option value="A+">A+</option><option value="A-">A-</option><option value="B+">B+</option><option value="B-">B-</option>
        <option value="AB+">AB+</option><option value="AB-">AB-</option><option value="O+">O+</option><option value="O-">O-</option>
      </select>
    </div>
    <div class="mb-3"><label class="flbl">Phone Number *</label><input type="text" name="phone" id="fPhone" class="finput" placeholder="e.g. 615XXXXXX" required autocomplete="off"></div>
    <div class="mb-3"><label class="flbl">Notes (Optional)</label><textarea name="notes" id="fNotes" class="ftextarea" rows="2" placeholder="Any additional info..." data-gramm="false" data-gramm_editor="false" data-enable-grammarly="false" spellcheck="false"></textarea></div>
    <div class="mb-3">
      <div class="tog-row">
        <div><div class="tog-lbl">Volunteer as a Blood Donor</div><div class="tog-sub">Name, blood group, and contact will be visible to the community in emergencies</div></div>
        <label class="ios-toggle"><input type="checkbox" name="is_available" id="fAvail" value="1" checked><span class="ios-slider"></span></label>
      </div>
    </div>
    <div class="modal-ftr">
      <button type="button" class="btn-cancel" onclick="closeForm()">Cancel</button>
      <button type="submit" class="btn-save" id="saveBtn"><i class="fa fa-check" style="margin-right:6px"></i>Save Donor</button>
    </div>
  </form>
</div>
</div>

<!-- DELETE MODAL -->
<div class="modal-overlay" id="delModal" onclick="if(event.target===this)closeDel()">
<div class="modal-box" style="max-width:420px;text-align:center;">
  <div class="del-icon"><i class="fa fa-trash-can"></i></div>
  <div class="del-title">Delete Donor</div>
  <p class="del-desc" id="delDesc">Are you sure you want to permanently delete this donor?</p>
  <div class="modal-ftr" style="justify-content:center;margin-top:22px;">
    <button class="btn-cancel" onclick="closeDel()">Cancel</button>
    <button class="btn-del" id="delOkBtn" onclick="confirmDel()"><i class="fa fa-trash" style="margin-right:6px"></i>Yes, Delete</button>
  </div>
</div>
</div>

<div class="toast-wrap" id="toastWrap"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function tick(){const el=document.getElementById('clockTime');if(el)el.textContent=new Date().toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false});setTimeout(tick,1000);})();
function toast(msg,type='ok'){const w=document.getElementById('toastWrap'),t=document.createElement('div');t.className=`toast ${type}`;t.innerHTML=`<div class="t-icon"><i class="fa ${type==='ok'?'fa-check':'fa-xmark'}"></i></div><span>${msg}</span>`;w.appendChild(t);setTimeout(()=>{t.style.cssText='opacity:0;transform:translateX(60px);transition:.4s';setTimeout(()=>t.remove(),400)},3200);}
function filterDonors(){const q=document.getElementById('searchQ').value.toLowerCase(),bt=document.getElementById('filterBt').value,av=document.getElementById('filterAv').value;let n=0;document.querySelectorAll('.donor-card').forEach(c=>{const ok=(!q||c.dataset.search.includes(q))&&(!bt||c.dataset.bt===bt)&&(av===''||c.dataset.av===av);c.style.display=ok?'':'none';if(ok)n++;});document.getElementById('resN').textContent=n;const nr=document.getElementById('no-results');if(nr)nr.style.display=(n===0&&document.querySelectorAll('.donor-card').length>0)?'block':'none';}
function openAdd(){document.getElementById('mTitle').textContent='Add Blood Donor';document.getElementById('mSub').textContent='Register a new volunteer donor';document.getElementById('donorForm').reset();document.getElementById('fAction').value='add';document.getElementById('fId').value='';document.getElementById('fAvail').checked=true;document.getElementById('saveBtn').innerHTML='<i class="fa fa-plus" style="margin-right:6px"></i>Add Donor';document.getElementById('formModal').classList.add('open');}
function openEdit(d){document.getElementById('mTitle').textContent='Edit Donor';document.getElementById('mSub').textContent='Update donor information';document.getElementById('fAction').value='edit';document.getElementById('fId').value=d.id;document.getElementById('fName').value=d.name;document.getElementById('fBlood').value=d.blood_type;document.getElementById('fPhone').value=d.phone;document.getElementById('fNotes').value=d.notes||'';document.getElementById('fAvail').checked=(d.is_available==1);document.getElementById('saveBtn').innerHTML='<i class="fa fa-check" style="margin-right:6px"></i>Save Changes';document.getElementById('formModal').classList.add('open');}
function closeForm(){document.getElementById('formModal').classList.remove('open');}
let _delId=null;
function openDel(id,name){_delId=id;document.getElementById('delDesc').innerHTML=`Permanently remove <strong>${name}</strong> from the donor list?<br><br><small style="color:#ef4444">This action cannot be undone.</small>`;document.getElementById('delModal').classList.add('open');}
function closeDel(){document.getElementById('delModal').classList.remove('open');_delId=null;}
async function post(data){const r=await fetch('blood_donors.php',{method:'POST',body:new URLSearchParams(data)});return r.json();}
async function submitForm(e){e.preventDefault();const btn=document.getElementById('saveBtn');btn.disabled=true;btn.innerHTML='<i class="fa fa-spinner fa-spin" style="margin-right:6px"></i>Saving...';const fd=new FormData(document.getElementById('donorForm')),data={};fd.forEach((v,k)=>data[k]=v);if(!data.is_available)data.is_available=0;try{const r=await post(data);if(r.success){toast(data.action==='add'?'Donor added successfully!':'Donor updated successfully!');closeForm();setTimeout(()=>location.reload(),900);}else{toast(r.msg||'Something went wrong','err');btn.disabled=false;btn.innerHTML='<i class="fa fa-check" style="margin-right:6px"></i>Save';}}catch{toast('Network error. Please try again.','err');btn.disabled=false;btn.innerHTML='<i class="fa fa-check" style="margin-right:6px"></i>Save';}}
async function toggleDonor(id,btn){btn.disabled=true;try{const r=await post({action:'toggle',id});if(r.success){const card=btn.closest('.donor-card'),nv=r.new_val;card.dataset.av=nv;card.classList.toggle('unavail',!nv);const ab=card.querySelector('.avail-badge');ab.className=`avail-badge ${nv?'yes':'no'}`;ab.innerHTML=`<span class="avail-dot"></span>${nv?'Available':'Unavailable'}`;btn.querySelector('i').className=`fa ${nv?'fa-toggle-on':'fa-toggle-off'}`;const total=document.querySelectorAll('.donor-card').length,avail=[...document.querySelectorAll('.donor-card')].filter(c=>c.dataset.av==='1').length;document.getElementById('stat-total').textContent=total;document.getElementById('stat-avail').textContent=avail;document.getElementById('stat-busy').textContent=total-avail;toast(nv?'Donor marked as available':'Donor marked as unavailable');}else toast('Could not update status','err');}catch{toast('Network error','err');}btn.disabled=false;}
async function confirmDel(){if(!_delId)return;const btn=document.getElementById('delOkBtn');btn.disabled=true;btn.innerHTML='<i class="fa fa-spinner fa-spin" style="margin-right:6px"></i>Deleting...';try{const r=await post({action:'delete',id:_delId});if(r.success){toast('Donor deleted successfully.');closeDel();setTimeout(()=>location.reload(),900);}else{toast('Delete failed.','err');btn.disabled=false;btn.innerHTML='<i class="fa fa-trash" style="margin-right:6px"></i>Yes, Delete';}}catch{toast('Network error','err');btn.disabled=false;btn.innerHTML='<i class="fa fa-trash" style="margin-right:6px"></i>Yes, Delete';}}
document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeForm();closeDel();}});
</script>
</body>
</html>
