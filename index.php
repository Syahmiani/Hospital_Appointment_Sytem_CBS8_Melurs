<?php
session_start();
// IMPORTANT: Adjust this path if your config/db.php is in a different location
require 'config/db.php'; 

// --- MESSAGE STATUS DISPLAY LOGIC ---
$msg_success = '';
$msg_errors = [];

if (isset($_GET['msg_status'])) {
    if ($_GET['msg_status'] === 'success') {
        $msg_success = 'Thank you! Your message has been sent successfully. We will get back to you shortly.';
    } elseif ($_GET['msg_status'] === 'error' && isset($_SESSION['msg_errors'])) {
        $msg_errors = $_SESSION['msg_errors'];
    }
    unset($_SESSION['msg_errors']);
}

// --- REDIRECT LOGIC for Logged-in Users to appropriate dashboard ---
$user_type = $_SESSION['user']['user_type'] ?? null;
$logged_in = !empty($_SESSION['user']);
$dashboard_link = 'dashboard.php'; 

if ($logged_in) {
    if ($user_type === 'admin') {
        $dashboard_link = 'admin/admin.php';
    } elseif ($user_type === 'doctor') {
        $dashboard_link = 'doctor/doctor.php';
    } elseif ($user_type === 'patient') {
        $dashboard_link = 'patient/patient.php';
    }
}

// --- FETCH ALL DOCTORS FOR DISPLAY ---
$doctors = [];
$qr = "SELECT d.doctor_id, u.f_name, u.l_name, d.specialization 
       FROM doctor d 
       JOIN `user` u ON d.user_id = u.user_id
       ORDER BY u.f_name ASC"; 

$res = mysqli_query($conn, $qr);

if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $doctors[] = $r;
    }
}
// --- END FETCH DOCTORS ---
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
 <title>Hospital System - Home</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <header class="header">
    <div class="brand">Hospital System</div>
    <nav class="nav">
      <a href="#home">Home</a>
      <a href="#about">About</a>
      <a href="#doctors">Doctors</a>
      <a href="#resources">Resources</a>
      <a href="#contact">Contact</a>
      <a href="appointment.php">Appointment</a>
      <?php if (!empty($_SESSION['user'])): ?>
        <a href="<?= htmlspecialchars($dashboard_link) ?>"></a>
        <a href="logout.php" class="btn btn-secondary">Logout</a>
      <?php else: ?>
        <a href="login.php">Login</a>
        <a href="register.php" class="btn btn-secondary">Register</a>
      <?php endif; ?>
      <button id="theme-toggle" class="btn btn-secondary theme-toggle-btn">🌙</button>
    </nav>
  </header>

  <div class="container">
    <?php if ($msg_success): ?>
        <div class="alert-success"><?=htmlspecialchars($msg_success)?></div>
    <?php endif; ?>

    <?php if (!empty($msg_errors)): ?>
        <div class="alert-error">
            <p>Error sending message:</p>
            <ul>
                <?php foreach($msg_errors as $e): ?>
                    <li><?=htmlspecialchars($e)?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
        <section id="home" class="hero">
      <div class="left">
        <h1 class="hero-heading">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-heart-pulse">
                <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>
                <path d="M12 21.35V25"/>
                <path d="M12 17.5l-2.5-2.5 2.5-3 2.5 3-2.5 2.5"/>
            </svg>
            Your Health, Our Priority
        </h1>
        <p class="lead">Easily book appointments, track status, and get quality care from trusted doctors.</p>
        
                <div class="btn-group" style="display: flex; gap: 15px;"> 
     <a href="appointment.php" class="btn btn-primary">Book Appointment</a> 
          <a href="#about" class="btn btn-tertiary">Learn More</a>             
        </div>
        
      </div>
      <div class="right">
        <img src="assets/image/OIP.webp" onerror="this.src='https://placehold.co/500x350/A6D0E7/007BFF?text=Hospital+Image';" alt="Modern Hospital" class="hero-image">
      </div>
    </section>

    <section id="about" class="section">
      <h2>About Us</h2>
      <p class="section-lead">Committed to providing exceptional medical care with compassion and professionalism. Our team is dedicated to your well-being.</p>
      <div class="about-content">
        <img src="assets/image/lobby.jpg" onerror="this.src='https://placehold.co/250x180/D8F0F8/007BFF?text=Hospital+Staff';" alt="Hospital Staff" class="staff-image">
        <div class="about-text">
          <p><b>Our Mission:</b> Deliver healthcare that respects every patient.</p>
          <p><b>Services:</b> Outpatient, consultations, diagnostics, emergency care.</p>
        </div>
      </div>

      <hr class="hr-margin" style="max-width: 500px;">
      <h3 class="text-primary" style="font-size: 1.4em; margin-bottom: 15px;">Need to know more about a medical term?</h3>
      <form action="https://en.wikipedia.org/w/index.php" method="get" target="_blank" class="wikipedia-search">
        <input type="search" name="search" placeholder="e.g., Cardiology, Neurology, Fracture" required>
        <button class="btn btn-primary" type="submit">Search Wiki</button> 
      </form>
          </section>

    <section id="doctors" class="section">
        <h2>Meet Our Doctors</h2>
        <p class="section-lead">Experience and expertise you can trust. Browse our top specialists and book an appointment today.</p>
        
        <div class="doctor-grid">
            <?php if (empty($doctors)): ?>
                <p style="text-align: center; width: 100%;">No doctors currently registered in the system. Please contact the administrator.</p>
            <?php else: ?>
                <?php foreach($doctors as $doc): ?>
                    <div class="doctor-card">
                        <img src="https://placehold.co/100x100/A6D0E7/007BFF?text=Dr" alt="Doctor <?=htmlspecialchars($doc['f_name'])?>">
                        
                        <h4>Dr. <?=htmlspecialchars($doc['f_name'] . ' ' . $doc['l_name'])?></h4>
                        
                        <p><?=htmlspecialchars($doc['specialization'] ?: 'General Practitioner')?></p>
                        
                        <a href="appointment.php?doctor_id=<?=htmlspecialchars($doc['doctor_id'])?>" class="btn btn-primary btn-sm">Book Now</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
    <section id="resources" class="section">
        <h2>Patient Resources</h2>
        <p class="section-lead">Find everything you need for your visit, from forms to FAQs.</p>
        <div class="resources-list">
            <div class="resource-item">
                <h4 style="color:#007bff;">Pre-Visit Checklists</h4>
                <p>Get ready for your appointment.</p>
            </div>
            <div class="resource-item">
                <h4 style="color:#007bff;">Insurance & Billing</h4>
                <p>View accepted insurance plans.</p>
            </div>
            <div class="resource-item">
                <h4 style="color:#007bff;">FAQs</h4>
                <p>Answers to common questions.</p>
            </div>

            <div class="first-aid-tutorial">
            <hr class="hr-margin" style="max-width: 700px;">
            <h3 class="text-primary"><span role="img" aria-label="First Aid Cross">🚨</span> Quick First Aid Tutorial: Using Your First Aid Kit</h3>
            
            <p>
                Know what to use and when to use it! Familiarity with your first aid kit is crucial in an emergency. Watch this quick guide:
            </p>
            
            <div class="video-responsive">
                <iframe 
                    width="560" 
                    height="315" 
                    src="https://www.youtube.com/embed/gn6xt1ca8A0" 
                    frameborder="0" 
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                    allowfullscreen>
                </iframe>
            </div>
            
            <div class="key-contents">
                <h4>Key Kit Contents</h4>
                <ul class="d-inline-block text-left" style="list-style: disc; padding-left: 20px;">
                    <li class="mb-2">
                        <strong>Wound Dressings:</strong> For large cuts, to apply pressure and stop bleeding.
                    </li>
                    <li class="mb-2">
                        <strong>Roller Bandages:</strong> Used to hold dressings in place or to secure ice packs.
                    </li>
                    <li class="mb-2">
                        <strong>Triangular Bandages:</strong> Can be used as a large dressing or to cover a burn.
                    </li>
                    <li class="mb-2">
                        <strong>Disposable Gloves:</strong> Always wear them to reduce the risk of infection when dealing with a wound or bodily fluid.
                    </li>
                    <li class="mb-2">
                        <strong>Scissors/Tape:</strong> To cut clothing to access a wound or secure bandages.
                    </li>
                    <li class="mb-2">
                        <strong>Cling Film:</strong> Useful for dressing burns and scalds.
                    </li>
                </ul>
            </div>
        </div>
    </section>
    <div class="cta-banner">
        <h3>Ready to Schedule Your Visit?</h3>
        <p>Use our easy online portal to manage your health today.</p>
        <a href="appointment.php" class="btn btn-secondary" style="background-color: #f7f7f7; color: #007bff; margin-left: 10px;">Book Fast</a>
    </div>
        <section id="contact" class="section">
      <h2>Contact Us</h2>
      <div class="contact-info">
        <p>Address: 123 Health St, Wellness City</p>
        <p>Email: contact@hospital.example</p>
        <p>Phone: +60 12-345 6789</p>
      </div>
      <div class="contact-form-group">
        <form action="message_send.php" method="post" class="contact-form">
          <div class="form-group"><label for="name">Your Name</label><input type="text" id="name" name="name" required></div>
          <div class="form-group"><label for="email">Your Email</label><input type="email" id="email" name="email" required></div>
          <div class="form-group"><label for="message">Message</label><textarea id="message" name="message" required></textarea></div>
          <button class="btn btn-primary" type="submit">Send Message</button> 
        </form>
        <div class="contact-hours">
            <p>We are available 24/7 for emergencies. For non-urgent inquiries,</p>
            <p>please use the form or call during business hours (9am - 5pm, Mon-Fri).</p>
        </div>
      </div>
    </section>

  </div>
  <footer class="footer">
    &copy; <?= date('Y') ?> Hospital System. All rights reserved. | <a href="#home">Home</a> | <a href="#about">About</a> | <a href="#contact">Contact</a>
  </footer>
  <script src="theme.js"></script>
</body>
</html>
