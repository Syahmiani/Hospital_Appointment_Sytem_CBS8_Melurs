<footer>
  <div class="footer-content">
    <p>© 2025 Hospital Appointment System | All Rights Reserved</p>
    <div class="social-icons">
      <a href="https://www.instagram.com/yourhospital" target="_blank" title="Instagram">
        <i class="fab fa-instagram"></i>
      </a>
      <a href="https://www.twitter.com/yourhospital" target="_blank" title="X (Twitter)">
        <i class="fab fa-x-twitter"></i>
      </a>
      <a href="https://www.tiktok.com/@yourhospital" target="_blank" title="TikTok">
        <i class="fab fa-tiktok"></i>
      </a>
    </div>
  </div>
</footer>

html
Copy code
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
🎨 2. Basic CSS for Styling
css
Copy code
footer {
  background-color: #0c2340;
  color: white;
  text-align: center;
  padding: 20px 0;
}

.social-icons a {
  color: white;
  margin: 0 10px;
  font-size: 24px;
  transition: color 0.3s ease;
}

.social-icons a:hover {
  color: #1da1f2; /* changes color on hover */
}