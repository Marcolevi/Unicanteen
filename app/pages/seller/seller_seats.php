<?php
require_once __DIR__ . '/../../components/common_footer.php';
session_start();
include 'db_connection.php';

// -----------------------------
// ENSURE SELLER IS LOGGED IN
// -----------------------------
if (!isset($_SESSION['seller_id'])) {
    header("Location: seller_login.php");
    exit;
}

$seller_id = intval($_SESSION['seller_id']);
$seller_name = $_SESSION['seller_name'] ?? "Seller";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Seller Dashboard | UniCanteen</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;900&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<style>
:root{--sidebar-bg:#1e293b;--accent:#2563eb;}
body{margin:0;font-family:"Poppins",sans-serif;background:#f1f5f9;;color:#0f172a;display:flex;min-height:100vh;overflow-x:hidden;}
.sidebar{width:230px;background:var(--sidebar-bg);color:#fff;height:100vh;position:fixed;display:flex;flex-direction:column;z-index:10;}
.sidebar h2{
  margin:0;
  text-align:center;
  padding:1rem 0;
  border-bottom:1px solid rgba(255,255,255,0.06);
  font-family:Montserrat, Poppins, sans-serif}
.sidebar a{color:#cbd5e1;text-decoration:none;padding:0.85rem 1.4rem;display:block;margin:6px 8px;border-radius:8px;transition:0.15s;}
.sidebar a:hover,.sidebar a.active{background:rgba(0, 85, 255, 0.12);color:#fff}
.sidebar #logoutLink{margin-top:auto;margin-bottom:14px;background:rgba(220,38,38,.16);color:#fecaca;border:1px solid rgba(252,165,165,.25);font-weight:600}
.sidebar #logoutLink:hover{background:#dc2626;color:#fff;border-color:#dc2626}
.main{margin-left:230px;padding:1.5rem;flex:1;width:calc(100% - 230px);box-sizing:border-box;display:flex;flex-direction:column;min-height:100vh;}
.main > footer { margin-top:37%; }
.topbar{background:white;border-radius:12px;padding:0.8rem 1rem;margin-bottom:1rem;display:flex;justify-content:space-between;align-items:center;box-shadow:0 2px 6px rgba(0,0,0,0.06);}
.username{font-weight:600;color:#1e293b;}
.container-box{background:white;padding:25px;border-radius:12px;box-shadow:0 2px 6px rgba(0,0,0,0.05);}
.container-box h3{margin:0;font-size:22px;font-weight:600;color:#1e293b;}
.add-seat{margin-top:20px;display:flex;gap:10px;}
.add-seat input{flex:1;padding:10px;border-radius:8px;border:1px solid #cbd5e1;}
.add-seat button{padding:10px 18px;background:var(--accent);border:none;border-radius:8px;color:white;cursor:pointer;transition:0.2s;}
.add-seat button:hover{background:#1d4ed8;}
.seat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:15px;margin-top:25px;padding-bottom:10px;}
#backBtn{background:#1e293bff;color:#fff;border-radius:8px;border:0;padding:8px 12px;cursor:pointer;}
#backBtn:hover{background:#566270;}
.seat{padding:16px 10px;border-radius:12px;text-align:center;font-weight:600;font-size:15px;cursor:pointer;color:white;transition:0.15s;box-shadow:0 2px 4px rgba(0,0,0,0.08);}
.available{background:#22c55e;}
.reserved{background:#ef4444;}
.seat:hover{transform:translateY(-3px);box-shadow:0 4px 8px rgba(0,0,0,0.15);}
.manage-seats-list{margin-top:20px;display:none;}
.manage-seats-list table{width:100%;border-collapse:collapse;}
.manage-seats-list th,.manage-seats-list td{border:1px solid #cbd5e1;padding:10px;text-align:left;}
.manage-seats-list th{background:#f1f5f9;}
.action-btn{padding:5px 10px;border:none;border-radius:6px;cursor:pointer;color:white;font-size:13px;}
.edit-btn{background:#3b82f6;}
.delete-btn{background:#ef4444;}
.logo-box {width: 100%;padding: 20px 0 5px;text-align: center;border-bottom: 1px solid rgba(255,255,255,0.06);}
.logo-box img {width: 125px;height: auto;border-radius: 12px;object-fit: contain;}
.uc-modal-overlay {position: fixed;inset: 0;background: rgba(0,0,0,0.45);display: none;justify-content: center;align-items: center;z-index: 99999;backdrop-filter: blur(3px);}
.uc-modal-box {background: #ffffff;width: 320px;padding: 25px 25px 20px;border-radius: 14px;text-align: center;box-shadow: 0 8px 30px rgba(0,0,0,0.15);animation: fadeUp 0.25s ease-out;}
.uc-modal-box h3 {margin: 0 0 8px;font-size: 20px;font-weight: 700;color: #1e293b;}
.uc-modal-box p {margin: 0 0 20px;font-size: 15px;color: #475569;}
.uc-modal-buttons {display: flex;justify-content: space-between;gap: 10px;margin-top: 20px;}
.uc-modal-box input {
  width: 100%;
  padding: 10px 12px;
  font-size: 15px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  outline: none;
  transition: 0.2s;
  box-sizing: border-box;
}

.uc-modal-box input:focus {
  border-color: #2563eb; /* blue accent */
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2);
}

.uc-btn {flex: 1;padding: 10px 0;border-radius: 8px;border: none;cursor: pointer;font-size: 14px;font-weight: 600;transition: 0.2s;}
.uc-btn.yes {background: #2563eb;color: white;}
.uc-btn.yes:hover {background: #1d4ed8;}
.uc-btn.no {background: #e2e8f0;color: #1e293b;}
.uc-btn.no:hover {background: #cbd5e1;}
@keyframes fadeUp{
  from {opacity: 0;transform: translateY(15px);}
  to {opacity: 1;transform: translateY(0);}
}

/* ========== FOOTER STYLES ========== */
footer {
  background: #0d131d;
  color: #cbd5e1;
  margin-top: auto;
  margin-left: -1.5rem;
  margin-right: -1.5rem;
  margin-bottom: 0;
  padding: 40px 24px 20px;
  border-top: 2px solid rgba(37, 99, 235, 0.2);
  font-size: 14px;
  width: calc(100% + 3rem);
  box-sizing: border-box;
}

.footer-content {
  max-width: 900px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: repeat(3, minmax(220px, 240px));
  gap: 25%;
  margin-bottom: 30px;
  justify-content: center;
}

.footer-section h4 {
  color: #fff;
  font-weight: 700;
  margin: 0 0 15px;
  font-size: 15px;
  font-family: Montserrat, Poppins, sans-serif;
}

.footer-section ul {
  list-style: none;
  padding: 0;
  margin: 0;
}

.footer-section li {
  margin-bottom: 10px;
}

.footer-section a {
  color: #cbd5e1;
  text-decoration: none;
  transition: color 0.2s;
}

.footer-section a:hover {
  color: #2563eb;
}

.footer-bottom {
  border-top: 1px solid rgba(255, 255, 255, 0.06);
  padding-top: 20px;
  text-align: center;
  color: #94a3b8;
  font-size: 13px;
}

@media (max-width: 768px) {
  footer { margin-left:-16px;margin-right:-16px;width:calc(100% + 32px);padding:30px 15px 15px; }
  .footer-content { grid-template-columns: 1fr; gap: 20px; }
  .sidebar{position:static;width:100%;height:auto;min-height:0}
  .main{margin-left:0;width:100%;padding:16px}
  .sidebar #logoutLink{margin-top:6px}
}

</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
  <!-- Logo Container Added -->
  <div class="logo-box">
    <img src="images/logo2.png" alt="School Logo">
  </div>
<h2>UniCanteen</h2>
  <a href="seller_dashboard.php" >Food Orders</a>
  <a href="seller_seats.php"class="active">Manage Tables</a>
<a href="seller_menu.php">Menu & Inventory</a>
  <a href="seller_analytics.php">Sales Analytics</a>
  <a href="seller_profile.php">Profile</a>
  <a href="#" id="logoutLink">Logout</a>
</div>


<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <h2>Table Management</h2>
  </div>

  <div class="container-box">
    <h3>Table Control Panel</h3>
    <p style="margin-top:5px;margin-bottom:20px;color:#64748b;">
      Add a new table below or manage existing tables.
    </p>

    <div class="add-seat">
      <input type="text" id="seatNumber" placeholder="Enter table name or number">
      <button onclick="addSeat()">Add Table</button>
      <button onclick="showManageSeats()">Manage Tables</button>
    </div>

    <div class="seat-grid" id="seatGrid"></div>

    <div class="manage-seats-list" id="manageSeatsList">
      <button id="backBtn" onclick="hideManageSeats()">← Back</button>
      <h3>Manage Tables</h3>
      <table>
        <thead>
          <tr>
            <th>Table Number</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="manageSeatsBody"></tbody>
      </table>
    </div>
  </div>

<!-- ========== FOOTER ========== -->
<footer>
  <div class="footer-content">
    <div class="footer-section">
      <h4>About UniCanteen</h4>
      <ul>
        <li><a href="about_us.php">About Us</a></li>
        <li><a href="news.php">News</a></li>
        <li><a href="#careers">Careers</a></li>
        <li><a href="#news">News</a></li>
      </ul>
    </div>
    
    <div class="footer-section">
      <h4>Support</h4>
      <ul>
        <li><a href="#faq">FAQ</a></li>
        <li><a href="#help">Help Center</a></li>
        <li><a href="#privacy">Privacy Policy</a></li>
        <li><a href="#terms">Terms of Service</a></li>
      </ul>
    </div>
    <div class="footer-section">
      <h4>Contact Info</h4>
      <ul>
        <li>Email: support@unicanteen.edu</li>
        <li>Phone: +63 (2) 1234-5678</li>
        <li>Hours: Mon-Fri, 7AM-6PM</li>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">
    <p>&copy; 2026 UniCanteen System. All rights reserved.</p>
  </div>
</footer>
</div>

<div id="logoutModal" class="uc-modal-overlay">
  <div class="uc-modal-box">
      <h3>Confirm Logout</h3>
      <p>Are you sure you want to logout?</p>

      <div class="uc-modal-buttons">
        <button id="confirmLogout" class="uc-btn yes">Logout</button>
        <button id="cancelLogout" class="uc-btn no">Cancel</button>
      </div>
  </div>
</div>

<script>
// -------------------
// LOGOUT MODAL
// -------------------
const logoutLink = document.getElementById('logoutLink');
const logoutModal = document.getElementById('logoutModal');
const confirmBtn = document.getElementById('confirmLogout');
const cancelBtn = document.getElementById('cancelLogout');

logoutLink.addEventListener('click', e => {
  e.preventDefault();
  logoutModal.style.display = 'flex';
});

confirmBtn.addEventListener('click', () => window.location.href = 'logout.php');
cancelBtn.addEventListener('click', () => logoutModal.style.display = 'none');
logoutModal.addEventListener('click', e => { if(e.target === logoutModal) logoutModal.style.display = 'none'; });

// -------------------
// CUSTOM MODAL
// -------------------
function showModal(title, content, buttons){
  const overlay = document.createElement('div');
  overlay.className = 'uc-modal-overlay';
  overlay.style.display = 'flex';

  const box = document.createElement('div');
  box.className = 'uc-modal-box';
  box.innerHTML = `<h3>${title}</h3><div class="uc-modal-content"></div><div class="uc-modal-buttons"></div>`;
  overlay.appendChild(box);

  // Insert content (can be string or HTMLElement)
  const contentContainer = box.querySelector('.uc-modal-content');
  if (typeof content === 'string') {
    contentContainer.innerHTML = content;
  } else {
    contentContainer.appendChild(content);
  }

  // Add buttons
  const buttonsContainer = box.querySelector('.uc-modal-buttons');
  buttons.forEach(btn => {
    const b = document.createElement('button');
    b.className = 'uc-btn ' + (btn.class || '');
    b.innerText = btn.text;
    b.onclick = () => { if(btn.onClick) btn.onClick(); overlay.remove(); };
    buttonsContainer.appendChild(b);
  });

  document.body.appendChild(overlay);
}


// -------------------
// SEAT FUNCTIONS
// -------------------
function addSeat() {
  let seat = document.getElementById("seatNumber").value.trim();
  if (!seat) return showModal('Error','Please enter a seat number/name',[{text:'OK',class:'no'}]);

  fetch("seller_add_seat.php", {
    method: "POST",
    headers: {"Content-Type": "application/x-www-form-urlencoded"},
    body: "seat_number=" + encodeURIComponent(seat)
  }).then(r => r.json()).then(d => {
    if (d.status === "success") {
      document.getElementById("seatNumber").value = "";
      appendSeatToGrid(d.seat);
      appendSeatToManage(d.seat);
    } else {
      showModal('Error', d.msg || 'Failed to add seat',[{text:'OK',class:'no'}]);
    }
  });
}

function appendSeatToGrid(seat) {
  const seatGrid = document.getElementById("seatGrid");
  const div = document.createElement("div");
  div.className = "seat " + (seat.is_reserved ? "reserved" : "available");
  div.textContent = seat.seat_number;
  div.dataset.id = seat.id;

  div.addEventListener("click", () => {
    const newStatus = div.classList.contains('available') ? 'reserved' : 'available';
    const msg = newStatus === 'reserved' ? 'Mark this seat as reserved?' : 'Mark this seat as available?';
    
    showModal('Confirm Seat Update', msg, [
      { text: 'Cancel', class: 'no' },
      { text: 'Yes', class: 'yes', onClick: () => {
        fetch("seller_update_seat.php", {
          method: "POST",
          headers: {"Content-Type":"application/x-www-form-urlencoded"},
          body: `seat_id=${seat.id}&toggle=1`
        }).then(r => r.json()).then(d => {
          if(d.status === "success") refreshSeats();
          else showModal('Error', d.msg || 'Failed',[{text:'OK',class:'no'}]);
        });
      }}
    ]);
  });

  seatGrid.appendChild(div);
}

function appendSeatToManage(seat) {
  const manageBody = document.getElementById("manageSeatsBody");
  const row = document.createElement("tr");
  row.innerHTML = `
    <td>${seat.seat_number}</td>
    <td>${seat.is_reserved ? 'Reserved' : 'Available'}</td>
    <td>
      <button class="action-btn edit-btn" onclick="editSeat(${seat.id}, '${seat.seat_number}')">Edit</button>

      <button class="action-btn delete-btn" onclick="deleteSeat(${seat.id})">Delete</button>
    </td>
  `;
  manageBody.appendChild(row);
}

function editSeat(id, currentName = '') {
  const input = document.createElement('input');
  input.type = 'text';
  input.value = currentName; // pre-fill current seat name
  input.placeholder = 'Enter new seat name';
  input.style.width = '100%';
  input.style.padding = '8px';
  input.style.marginTop = '10px';
  input.style.border = '1px solid #cbd5e1';
  input.style.borderRadius = '6px';
  input.style.fontSize = '14px';

  showModal('Edit Table', input, [
    { text: 'Cancel', class: 'no' },
    { text: 'Save', class: 'yes', onClick: () => {
        const newName = input.value.trim();
        if (!newName) return showModal('Error', 'Seat name cannot be empty', [{ text: 'OK', class: 'no' }]);
        
        fetch("seller_update_seat.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: "seat_id=" + id + "&seat_number=" + encodeURIComponent(newName)
        })
        .then(r => r.json())
        .then(d => {
          if(d.status === "success") refreshSeats();
          else showModal('Error', d.msg || 'Failed', [{ text: 'OK', class: 'no' }]);
        });
      }
    }
  ]);

  input.focus();
}

function deleteSeat(id){
  showModal('Delete Table','Are you sure you want to delete this table?',[ 
    {text:'Cancel',class:'no'},
    {text:'Delete',class:'yes',onClick:()=>{
      fetch("seller_delete_seat.php",{
        method:"POST",
        headers:{"Content-Type":"application/x-www-form-urlencoded"},
        body:"seat_id="+id
      }).then(r=>r.json()).then(d=>{
        if(d.status==="success") refreshSeats();
        else showModal('Error',d.msg||'Failed',[{text:'OK',class:'no'}]);
      });
    }}
  ]);
}

function showManageSeats(){
  document.getElementById("seatGrid").style.display="none";
  document.getElementById("manageSeatsList").style.display="block";
}

function hideManageSeats(){
  document.getElementById("seatGrid").style.display="grid";
  document.getElementById("manageSeatsList").style.display="none";
}

// -------------------
// REFRESH SEATS
// -------------------
function refreshSeats(){
  fetch("seller_fetch_seats.php")
  .then(r=>r.json())
  .then(data=>{
    if(data.status!=="success") return;
    const seatGrid = document.getElementById("seatGrid");
    const manageBody = document.getElementById("manageSeatsBody");
    seatGrid.innerHTML = "";
    manageBody.innerHTML = "";

    data.seats.forEach(seat=>{
      appendSeatToGrid(seat);
      appendSeatToManage(seat);
    });
  });
}

document.addEventListener("DOMContentLoaded", refreshSeats);
setInterval(refreshSeats,2000);
</script>

</body>
</html>
