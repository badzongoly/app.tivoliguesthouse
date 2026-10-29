<?php
header("Location: auth/login.php");
exit;
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Tivoli Guesthouse — Elegant, Minimal Luxury</title>
  <meta name="description" content="A refined, minimalist luxury guesthouse experience with elegant rooms, curated amenities, and effortless booking."/>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NXNQYJ7xX2dT2LrLQ3w12ug1L6Nq35p3pbbK8Zz1w=" crossorigin=""/>
  <style>
    :root{
      /* Soft, luxurious palette */
      --bg:#f7f6f2;           /* page background (warm cream) */
      --surface:#ffffff;      /* cards/nav surfaces */
      --muted:#6b7280;        /* text muted */
      --text:#0f172a;         /* text strong */
      --line:#e5e7eb;         /* borders */
      --accent:#c7a45d;       /* gold accent */
      --accent-2:#0ea5e9;     /* cool accent for focus */
      --shadow:0 10px 30px rgba(15,23,42,.08);
    }
    html { scroll-behavior:smooth; }
    body { margin:0; background:var(--bg); color:var(--text); font-family:'Montserrat', system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; }
    h1,h2,h3,h4 { font-family:'Cormorant Garamond', serif; letter-spacing:.2px; }
    a { color:inherit; text-decoration:none; }

    /* NAV */
    .nav { position:fixed; top:0; left:0; right:0; z-index:50; display:flex; align-items:center; justify-content:space-between; padding:18px 24px; backdrop-filter: blur(10px); background:rgba(255,255,255,.55); border-bottom:1px solid rgba(0,0,0,.05); }
    .nav .logo { font-family:'Cormorant Garamond',serif; font-weight:600; font-size:22px; letter-spacing:.6px; }
    .nav .links { display:flex; gap:22px; }
    .nav .links a { opacity:.85; }
    .nav .links a:hover { opacity:1; }
    .nav .btn { padding:10px 16px; border-radius:999px; border:1px solid var(--accent); color:#1f2937; background:linear-gradient(180deg, #e9d8a6, var(--accent)); font-weight:600; }

    /* HERO */
    .hero { position:relative; min-height:100vh; display:grid; place-items:center; overflow:hidden; }
    .hero::before { content:""; position:absolute; inset:0; background:url('https://images.unsplash.com/photo-1505691938895-1758d7feb511?q=80&w=2400&auto=format&fit=crop') center/cover no-repeat; filter:brightness(.72) saturate(1.05); }
    .hero .overlay { position:relative; text-align:center; color:#fff; padding:0 16px; }
    .hero .brandmark { display:inline-flex; align-items:center; gap:10px; padding:10px 14px; border:1px solid rgba(255,255,255,.35); border-radius:999px; background:rgba(255,255,255,.08); margin-bottom:14px; }
    .hero h1 { font-size:clamp(36px, 6vw, 72px); line-height:1.05; margin:0 0 10px; }
    .hero p { font-size:clamp(14px, 2.2vw, 18px); opacity:.9; margin:0 0 22px; }
    .scroll-indicator { position:absolute; bottom:24px; left:50%; transform:translateX(-50%); width:24px; height:40px; border-radius:20px; border:2px solid rgba(255,255,255,.8); }
    .scroll-indicator::after { content:""; position:absolute; top:6px; left:50%; transform:translateX(-50%); width:6px; height:6px; background:#fff; border-radius:50%; animation:bounce 1.4s infinite; }
    @keyframes bounce { 0%,100%{ transform:translate(-50%,0);} 50%{ transform:translate(-50%,8px);} }

    /* SECTION WRAPPER */
    section { padding:80px 20px; }
    .container { max-width:1160px; margin:0 auto; }
    .section-head { display:flex; justify-content:space-between; align-items:end; gap:20px; margin-bottom:24px; }
    .section-head h2 { font-size:36px; margin:0; }
    .muted { color:var(--muted); }

    /* ROOMS */
    .tabs { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; }
    .tab { padding:8px 12px; border-radius:999px; border:1px solid var(--line); background:var(--surface); cursor:pointer; font-weight:600; font-size:14px; }
    .tab.active { border-color:var(--accent); box-shadow:0 0 0 3px rgba(199,164,93,.18); }
    .room-scroller { display:flex; gap:16px; overflow-x:auto; scroll-snap-type:x mandatory; padding-bottom:8px; }
    .room-card { min-width:320px; max-width:360px; background:var(--surface); border:1px solid var(--line); border-radius:16px; overflow:hidden; scroll-snap-align:start; box-shadow:var(--shadow); transition:transform .2s ease, box-shadow .2s ease; }
    .room-card:hover { transform:translateY(-4px); box-shadow:0 16px 40px rgba(15,23,42,.12); }
    .room-card img { width:100%; height:220px; object-fit:cover; display:block; }
    .room-card .body { padding:14px 14px 16px; }
    .pill { display:inline-block; padding:6px 10px; border-radius:999px; background:#f8f7f3; border:1px solid var(--line); font-size:12px; margin-right:8px; }
    .price-row { display:flex; justify-content:space-between; align-items:center; margin-top:10px; }
    .price { font-weight:700; font-size:18px; }
    .cta { padding:10px 14px; border-radius:10px; background:var(--text); color:#fff; font-weight:600; border:0; cursor:pointer; }
    .cta:hover { filter:brightness(.95); }

    /* AMENITIES */
    .amenities { background:linear-gradient(180deg, #f1efe9, #f7f6f2); border-top:1px solid var(--line); border-bottom:1px solid var(--line); }
    .amen-grid { display:grid; grid-template-columns:repeat(12, 1fr); gap:16px; }
    .amen-card { grid-column: span 4; background:var(--surface); border:1px solid var(--line); border-radius:16px; padding:18px; box-shadow:var(--shadow); display:flex; align-items:flex-start; gap:14px; transition:transform .2s ease, box-shadow .2s; }
    .amen-card:hover { transform:translateY(-3px); box-shadow:0 16px 38px rgba(15,23,42,.11); }
    .amen-icon { width:42px; height:42px; border-radius:10px; background:#faf7f0; display:grid; place-items:center; border:1px solid var(--line); }
    .amen-title { font-weight:700; margin:0 0 6px; }
    .amen-desc { color:var(--muted); font-size:14px; }
    @media (max-width: 920px){ .amen-card{ grid-column:span 6; } }
    @media (max-width: 640px){ .amen-card{ grid-column:span 12; } }

    /* LOCATION */
    #map { width:100%; height:460px; border-radius:16px; border:1px solid var(--line); box-shadow:var(--shadow); }
    .poi-list { display:grid; grid-template-columns:repeat(3, 1fr); gap:16px; margin-top:16px; }
    .poi-item { background:var(--surface); border:1px solid var(--line); border-radius:14px; padding:14px; display:flex; align-items:center; gap:10px; }
    @media (max-width: 900px){ .poi-list{ grid-template-columns:1fr; } }

    /* BOOKING BAR */
    .booking-bar { position:fixed; bottom:16px; left:50%; transform:translateX(-50%); width:min(1160px, 92%); background:rgba(255,255,255,.85); backdrop-filter: blur(8px); border:1px solid var(--line); border-radius:16px; padding:12px; box-shadow:0 20px 40px rgba(15,23,42,.12); z-index:60; }
    .booking-grid { display:grid; grid-template-columns: repeat(12, 1fr); gap:10px; align-items:end; }
    .booking-grid .field { grid-column: span 3; display:flex; flex-direction:column; gap:6px; }
    .booking-grid .field label { font-size:12px; color:var(--muted); }
    .booking-grid input, .booking-grid select { height:42px; border-radius:10px; border:1px solid var(--line); padding:0 12px; background:#fff; }
    .counter { display:flex; align-items:center; gap:8px; }
    .counter button { width:34px; height:34px; border-radius:10px; border:1px solid var(--line); background:#fff; font-weight:700; }
    .total { grid-column: span 2; display:flex; align-items:center; justify-content:center; font-weight:700; }
    .book-btn { grid-column: span 2; height:44px; border:none; border-radius:12px; background:linear-gradient(180deg, #e9d8a6, var(--accent)); color:#1f2937; font-weight:800; cursor:pointer; }
    @media (max-width: 900px){
      .booking-grid { grid-template-columns:1fr 1fr; }
      .booking-grid .field { grid-column:span 1; }
      .total, .book-btn { grid-column:span 2; }
    }

    /* FOOTER */
    footer { background:var(--surface); border-top:1px solid var(--line); padding:32px 20px; margin-top:80px; }
    .foot-grid { display:grid; grid-template-columns:2fr 1fr 1fr 2fr; gap:20px; max-width:1160px; margin:0 auto; }
    .foot-title { font-weight:700; margin-bottom:10px; }
    .foot-link { display:block; color:var(--muted); margin:6px 0; }
    .foot-contact { display:flex; align-items:center; gap:10px; margin:6px 0; color:var(--muted); }
    .newsletter { display:flex; gap:8px; }
    .newsletter input { flex:1; height:42px; border-radius:10px; border:1px solid var(--line); padding:0 12px; }
    .newsletter button { padding:0 16px; border-radius:10px; border:1px solid var(--accent); background:linear-gradient(180deg, #e9d8a6, var(--accent)); font-weight:700; }
    .copy { text-align:center; color:var(--muted); font-size:12px; margin-top:16px; }

    /* REVEAL ANIMATIONS */
    .reveal { opacity:0; transform:translateY(16px); transition:opacity .6s ease, transform .6s ease; }
    .reveal.in { opacity:1; transform:none; }
  </style>
</head>
<body>
  <!-- NAV -->
  <nav class="nav">
    <div class="logo">Tivoli Guesthouse</div>
    <div class="links">
      <a href="#rooms">Rooms</a>
      <a href="#amenities">Amenities</a>
      <a href="#location">Location</a>
      <a href="auth/login.php" class="btn">Login</a>
    </div>
  </nav>

  <!-- HERO -->
  <header class="hero" id="top">
    <div class="overlay">
      <div class="brandmark">Luxury • Minimal • Comfort</div>
      <h1>Where Quiet Luxury Meets Effortless Comfort</h1>
      <p>Elegant rooms, curated amenities, and attentive service — designed for discerning travellers.</p>
      <a href="#rooms" class="btn" style="display:inline-block; margin-top:10px; background:linear-gradient(180deg,#1f2937,#0f172a); border:1px solid rgba(255,255,255,.3); color:#fff;">Explore Rooms</a>
    </div>
    <div class="scroll-indicator" aria-hidden="true"></div>
  </header>

  <!-- ROOMS -->
  <section id="rooms" class="reveal">
    <div class="container">
      <div class="section-head">
        <div>
          <h2>Rooms & Suites</h2>
          <div class="muted">Minimal design. Maximum comfort. Choose your escape.</div>
        </div>
        <div class="tabs" role="tablist">
          <button class="tab active" data-filter="all" aria-selected="true">All</button>
          <button class="tab" data-filter="deluxe">Deluxe</button>
          <button class="tab" data-filter="double">Double</button>
          <button class="tab" data-filter="suite">Suite</button>
        </div>
      </div>

      <div class="room-scroller" id="roomScroller">
        <!-- Room card template items -->
        <article class="room-card" data-cat="deluxe">
          <img src="https://images.unsplash.com/photo-1560449204-e02f11c3d0e2?q=80&w=1600&auto=format&fit=crop" alt="Deluxe Room" loading="lazy">
          <div class="body">
            <div>
              <span class="pill">King Bed</span>
              <span class="pill">Rain Shower</span>
              <span class="pill">City View</span>
            </div>
            <h3 style="margin:10px 0 4px;">Deluxe King</h3>
            <div class="muted">An intimate, light-filled space with curated textures and bespoke bedding.</div>
            <div class="price-row">
              <div class="price">₵150 / night</div>
              <button class="cta" data-room="Deluxe King" data-price="150">Book Now</button>
            </div>
          </div>
        </article>

        <article class="room-card" data-cat="double">
          <img src="https://images.unsplash.com/photo-1551776235-dde6d4829808?q=80&w=1600&auto=format&fit=crop" alt="Double Room" loading="lazy">
          <div class="body">
            <div>
              <span class="pill">Double</span>
              <span class="pill">Desk</span>
              <span class="pill">Garden View</span>
            </div>
            <h3 style="margin:10px 0 4px;">Double</h3>
            <div class="muted">Clean lines, gentle light, and everything you need for a restful stay.</div>
            <div class="price-row">
              <div class="price">₵120 / night</div>
              <button class="cta" data-room="Double" data-price="120">Book Now</button>
            </div>
          </div>
        </article>

        <article class="room-card" data-cat="double">
          <img src="https://images.unsplash.com/photo-1588854337221-4t80?ixid=&q=80&w=1600&auto=format&fit=crop" alt="Double No AC" loading="lazy" onerror="this.src='https://images.unsplash.com/photo-1588854337221-4b9e0d26c3ed?q=80&w=1600&auto=format&fit=crop';">
          <div class="body">
            <div>
              <span class="pill">Double</span>
              <span class="pill">Fan</span>
              <span class="pill">Courtyard</span>
            </div>
            <h3 style="margin:10px 0 4px;">Double (No AC)</h3>
            <div class="muted">Thoughtful simplicity with excellent value.</div>
            <div class="price-row">
              <div class="price">₵100 / night</div>
              <button class="cta" data-room="Double (No AC)" data-price="100">Book Now</button>
            </div>
          </div>
        </article>
      </div>
    </div>
  </section>

  <!-- AMENITIES -->
  <section id="amenities" class="amenities reveal">
    <div class="container">
      <div class="section-head"><h2>Amenities</h2><div class="muted">Everything you need, beautifully considered.</div></div>
      <div class="amen-grid">
        <div class="amen-card">
          <div class="amen-icon">🍳</div>
          <div>
            <div class="amen-title">Gourmet Breakfast</div>
            <div class="amen-desc">Locally-sourced ingredients, freshly baked breads, barista coffee.</div>
          </div>
        </div>
        <div class="amen-card">
          <div class="amen-icon">💻</div>
          <div>
            <div class="amen-title">Fast Wi‑Fi</div>
            <div class="amen-desc">Reliable connectivity across the property (up to 100 Mbps).</div>
          </div>
        </div>
        <div class="amen-card">
          <div class="amen-icon">🧺</div>
          <div>
            <div class="amen-title">Laundry</div>
            <div class="amen-desc">Express laundry service on request — travel light, feel fresh.</div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- LOCATION -->
  <section id="location" class="reveal">
    <div class="container">
      <div class="section-head"><h2>Location & Surroundings</h2><div class="muted">Close to everything, wrapped in calm.</div></div>
      <div class="poi-list">
        <div class="poi-item">📍 <strong>Newman Int’l School</strong> — 1 min</div>
        <div class="poi-item">🌿 <strong>Abuakwa Town Centre</strong> — 5 min</div>
        <div class="poi-item">✈️ <strong>Kumasi Airport (KMS)</strong> — ~35 min</div>
      </div>
    </div>
  </section>

  <!-- BOOKING BAR -->
  <!--<div class="booking-bar" id="book">
    <div class="booking-grid">
      <div class="field">
        <label>Check-in</label>
        <input type="date" id="checkin" />
      </div>
      <div class="field">
        <label>Check-out</label>
        <input type="date" id="checkout" />
      </div>
      <div class="field">
        <label>Room</label>
        <select id="roomType">
          <option value="Deluxe King" data-price="150">Deluxe King — ₵150</option>
          <option value="Double" data-price="120">Double — ₵120</option>
          <option value="Double (No AC)" data-price="100">Double (No AC) — ₵100</option>
        </select>
      </div>
      <div class="field">
        <label>Guests</label>
        <div class="counter">
          <button type="button" id="minus">−</button>
          <div id="guestCount" aria-live="polite">2</div>
          <button type="button" id="plus">+</button>
        </div>
      </div>
      <div class="total">
        <span>Total: <span id="totalPrice">₵0</span></span>
      </div>
      <button class="book-btn" id="checkBtn">Check Availability</button>
    </div>
  </div>

   FOOTER -->
  <footer>
    <div class="foot-grid">
      <div>
        <h3 class="foot-title">Tivoli Guesthouse</h3>
        <div class="muted">Near Newman International School, Abuakwa — Kumasi, Ghana.</div>
      </div>
      <div>
        <div class="foot-title">Quick Links</div>
        <a class="foot-link" href="#rooms">Rooms</a>
        <a class="foot-link" href="#amenities">Amenities</a>
        <a class="foot-link" href="#location">Location</a>
        <a class="foot-link" href="#book">Book</a>
      </div>
      <div>
        <div class="foot-title">Connect</div>
        <div class="foot-contact">📞 +233 54 721 7363</div>
        <div class="foot-contact">✉️ stay@tivoligh.com</div>
        <div class="foot-contact">📍 Abuakwa, Kumasi</div>
        <div class="foot-contact">🔗 @tivoligh</div>
      </div>
      <div>
        <div class="foot-title">Newsletter</div>
        <form class="newsletter" onsubmit="event.preventDefault(); alert('Thanks for subscribing!');">
          <input type="email" placeholder="you@example.com" required>
          <button type="submit">Subscribe</button>
        </form>
      </div>
    </div>
    <div class="copy">© <span id="year"></span> Tivoli Guesthouse. All rights reserved.</div>
  </footer>

  <script>
    // Year
    document.getElementById('year').textContent = new Date().getFullYear();

    // Reveal on scroll
    const io = new IntersectionObserver((entries)=>{
      entries.forEach(e=>{ if(e.isIntersecting) e.target.classList.add('in'); });
    },{ threshold:.2 });
    document.querySelectorAll('.reveal').forEach(el=>io.observe(el));

    // Tabs filter
    const tabs = document.querySelectorAll('.tab');
    const cards = document.querySelectorAll('.room-card');
    tabs.forEach(t=>t.addEventListener('click', ()=>{
      tabs.forEach(x=>x.classList.remove('active'));
      t.classList.add('active');
      const cat = t.dataset.filter;
      cards.forEach(c=>{
        c.style.display = (cat==='all' || c.dataset.cat===cat) ? 'block' : 'none';
      });
    }));

    // Book Now buttons feed the booking bar
    document.querySelectorAll('.room-card .cta').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        document.getElementById('roomType').value = btn.dataset.room;
        updateTotal();
        document.getElementById('book').scrollIntoView({behavior:'smooth'});
      });
    });

    // Booking logic
    const checkin = document.getElementById('checkin');
    const checkout = document.getElementById('checkout');
    const roomType = document.getElementById('roomType');
    const totalPriceEl = document.getElementById('totalPrice');
    const plus = document.getElementById('plus');
    const minus = document.getElementById('minus');
    const guestCount = document.getElementById('guestCount');
    const checkBtn = document.getElementById('checkBtn');

    // defaults
    const today = new Date();
    const tomorrow = new Date(Date.now()+86400000);
    checkin.valueAsDate = today;
    checkout.valueAsDate = tomorrow;

    let guests = 2;
    function updateTotal(){
      const price = Number(roomType.selectedOptions[0].dataset.price||0);
      const nights = Math.max(1, Math.ceil((new Date(checkout.value) - new Date(checkin.value))/(1000*60*60*24)) || 1);
      const total = price * nights; // you can add taxes/fees here
      totalPriceEl.textContent = '₵'+ total.toLocaleString();
    }
    [checkin, checkout, roomType].forEach(el=>el.addEventListener('change', updateTotal));
    plus.addEventListener('click', ()=>{ guests=Math.min(6, guests+1); guestCount.textContent=guests; });
    minus.addEventListener('click', ()=>{ guests=Math.max(1, guests-1); guestCount.textContent=guests; });
    updateTotal();

    checkBtn.addEventListener('click', ()=>{
      checkBtn.disabled = true; const old = checkBtn.textContent; checkBtn.textContent='Checking…';
      setTimeout(()=>{
        const available = Math.random()>0.12; // mock availability
        if (available) {
          const price = totalPriceEl.textContent;
          checkBtn.textContent = 'Book Now';
          checkBtn.disabled = false;
          checkBtn.onclick = ()=>{
            alert('Great choice! We\'ll hold this room for you. Total '+price+' for '+roomType.value+'.');
          };
        } else {
          alert('Selected room is not available for your dates. Please try another room or date.');
          checkBtn.textContent = old; checkBtn.disabled = false;
        }
      }, 900);
    });

    // Map (Leaflet, no API key)
    const map = L.map('map', { scrollWheelZoom:false }).setView([6.690, -1.620], 12); // Approx. Kumasi area
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap'
    }).addTo(map);
    const poi = [
      { name:'Tivoli Guesthouse', coords:[6.690, -1.620], desc:'Welcome to elegant calm.' },
      { name:'Newman Intl School', coords:[6.696, -1.619], desc:'2 mins away.' },
      { name:'Abuakwa Centre', coords:[6.705, -1.626], desc:'Shops & eateries.' }
    ];
    poi.forEach(p=> L.marker(p.coords).addTo(map).bindPopup(`<b>${p.name}</b><br>${p.desc}`));
  </script>
</body>
</html>
