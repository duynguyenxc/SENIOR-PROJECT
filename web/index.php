<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/layout.php';

$cust = current_customer();
render_header('Home | Veg Buffet');
?>

<section class="hero hero-home">
  <h1 class="hero-home-title">Welcome to <span class="text-accent">Veg Buffet</span></h1>
  <p class="hero-home-copy" style="display: block; width: 100%; max-width: 600px; margin: 0 auto 30px; text-align: center !important;">
    Experience the finest vegetarian cuisine crafted fresh every day. Join us for a unique and ever-changing weekly menu.
  </p>
  <div class="btnrow btnrow-centered btnrow-wrap">
    <a href="/menu.php" class="btn btn-primary btn-roomy">View Weekly Menu</a>
    <a href="/takeout.php" class="btn btn-roomy">Order Takeout</a>
  </div>
</section>

<div class="grid grid-halves">
  <section class="card card-hover card-accent-primary">
    <h2 class="icon-heading">
      <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
      Hours & Location
    </h2>
    <p class="muted text-relaxed">
      <strong>123 Vegetarian Avenue</strong><br/>
      Oxford, MS 38655<br/><br/>
      <strong>Monday - Sunday:</strong> 11:00 AM - 9:00 PM<br/>
      <strong>Thursdays:</strong> Discount Day!
    </p>
  </section>

  <aside class="card card-hover card-accent-accent">
    <h2 class="icon-heading">
      <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
      Buffet Pricing
    </h2>
    <div class="price-line">
      Regular Price: <strong class="price-amount">$17</strong> / person
    </div>
    <div class="alert alert-ok alert-spaced-top">
      <strong class="text-accent">Thursday Special:</strong> Enjoy our buffet for only <strong>$15</strong> per person!
    </div>
  </aside>
</div>

<?php render_footer(); ?>

