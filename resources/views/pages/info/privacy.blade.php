@extends('pages.info.layout')
@section('info')
<p class="info-lead">What personal information the {{ config('storefront.brand') }} website collects and how it is used.</p>
<div class="account-card-panel info-prose">
  <h2>Information you give us</h2>
  <ul>
    <li><strong>Account:</strong> your name, email address, phone number and a password (stored only as a one-way hash), or your Google account name and email if you sign in with Google.</li>
    <li><strong>Orders:</strong> delivery addresses, the items you order, order notes and order history.</li>
    <li><strong>Requests and messages:</strong> what you send through the contact, product sourcing and bulk order forms, including any photos you attach (photos are re-saved without their embedded metadata, such as location).</li>
    <li><strong>Newsletter:</strong> your email address, if you subscribe.</li>
  </ul>
  <p>We do not collect or store card details. Orders are currently paid by cash on delivery.</p>

  <h2>Information stored in your browser</h2>
  <ul>
    <li>A session cookie that keeps you signed in, and a security cookie that protects forms against forgery.</li>
    <li>Your shopping bag, wishlist, recently viewed products and recent searches are kept in your browser’s local storage. When you are signed in, your bag and wishlist are also saved to your account.</li>
  </ul>

  <h2>How we use it</h2>
  <p>To run your account, process and deliver orders, reply to your messages and requests, protect the site against abuse (for example, limiting repeated sign-in attempts) and, if you subscribed, send you updates.</p>

  <h2>Services that receive data</h2>
  <ul>
    <li>Product images are loaded from image hosting services, and the site’s fonts from Google Fonts. Your browser contacts these services directly, which shares your IP address with them.</li>
    <li>If you choose Google sign-in, Google shares your name and email address with us.</li>
  </ul>

  <h2>Access and correction</h2>
  <p>You can view and update your profile and saved addresses in <a href="/account">My Account</a>. For other requests about your information, <a href="/contact">contact us</a>.</p>
</div>
@endsection
