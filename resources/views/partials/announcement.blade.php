@php
  $announcement = \App\Support\Delivery::text('announcement');
  $announcementParts = $announcement !== null ? explode(' · ', $announcement, 2) : [];
@endphp
<div class="announcement-bar" id="announcement-bar" role="region" aria-label="Store announcement">
  <div class="container announcement-bar-inner">
    @if (count($announcementParts) === 2)
      <span class="announcement-text"><span class="announcement-line">{{ $announcementParts[0] }}</span><span class="announcement-sep"> · </span><span class="announcement-line">{{ $announcementParts[1] }}</span></span>
    @else
      <span class="announcement-text">{{ $announcement }}</span>
    @endif
  </div>
</div>
