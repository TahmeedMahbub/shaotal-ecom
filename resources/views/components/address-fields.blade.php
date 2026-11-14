{{--
  Shared Australian address fields: State → Suburb → Street → Postcode → Address type (+ custom label for "Other").
  Driven by public/assets/js/address-form.js (AddressForm.mount) from the central /api/locations service.
  Callers keep their own name/phone/default fields and read values through AddressForm.mount(el).get().
  Without location data in the database it renders plain fields (data-mode="legacy") so addresses can still be entered.

  @props prefix     id prefix: {prefix}-state, {prefix}-suburb, {prefix}-postcode, name="{prefix}-type", …
         streetId   id of the street input, line2Id optional id of the unit/apartment input
         variant    'form' (checkout classes) | 'auth' (account classes)
--}}
@props(['prefix', 'streetId', 'line2Id' => null, 'variant' => 'form', 'streetLabel' => 'Street Address'])
@php
    $dataset = \App\Models\Locations::available();
    $auth = $variant === 'auth';
    $g = $auth ? 'auth-form-group' : 'form-group';
    $lbl = $auth ? 'auth-label' : 'form-label';
    $inp = $auth ? 'auth-input' : 'form-input';
    $sel = $auth ? 'auth-input' : 'form-select';
    $p = $prefix;
    $legacyStates = \App\Models\Locations::DELIVERABLE;
@endphp
<div {{ $attributes->class(['addr-fields']) }} id="{{ $p }}-fields" data-prefix="{{ $p }}" data-street="{{ $streetId }}" data-line2="{{ $line2Id }}" data-mode="{{ $dataset ? 'central' : 'legacy' }}">
  {{-- Step 1: state --}}
  <div class="{{ $g }}">
    <label class="{{ $lbl }}" for="{{ $p }}-state-cb">State / Territory *</label>
    @if ($dataset)
      <div class="addr-cb" data-cb="state">
        <input type="text" id="{{ $p }}-state-cb" class="{{ $inp }}" role="combobox" aria-expanded="false" aria-autocomplete="list" aria-controls="{{ $p }}-state-list" autocomplete="off" placeholder="Search or select a state">
        <button type="button" class="addr-cb-clear" aria-label="Clear state" tabindex="-1">&times;</button>
        <ul class="addr-cb-list" id="{{ $p }}-state-list" role="listbox" hidden></ul>
      </div>
      <input type="hidden" id="{{ $p }}-state" value="">
    @else
      <select id="{{ $p }}-state" class="{{ $sel }}" autocomplete="address-level1">
        <option value="">Select state</option>
        @foreach ($legacyStates as $code)<option value="{{ $code }}">{{ $code }}</option>@endforeach
      </select>
    @endif
  </div>

  {{-- Step 2: suburb (disabled until a state is chosen) --}}
  <div class="{{ $g }}">
    <label class="{{ $lbl }}" for="{{ $dataset ? $p.'-suburb-cb' : $p.'-suburb' }}">Suburb / Locality *</label>
    @if ($dataset)
      <div class="addr-cb" data-cb="suburb">
        <input type="text" id="{{ $p }}-suburb-cb" class="{{ $inp }}" role="combobox" aria-expanded="false" aria-autocomplete="list" aria-controls="{{ $p }}-suburb-list" autocomplete="off" disabled placeholder="Choose a state first">
        <button type="button" class="addr-cb-clear" aria-label="Clear suburb" tabindex="-1">&times;</button>
        <span class="addr-cb-spinner" aria-hidden="true"></span>
        <ul class="addr-cb-list" id="{{ $p }}-suburb-list" role="listbox" hidden></ul>
      </div>
      <input type="hidden" id="{{ $p }}-suburb" value="">
    @else
      <input type="text" id="{{ $p }}-suburb" class="{{ $inp }}" maxlength="120" autocomplete="address-level2" placeholder="e.g. Adelaide">
    @endif
  </div>

  {{-- Step 3: street address (shown once a suburb is chosen) --}}
  <div class="{{ $g }} addr-full" id="{{ $p }}-street-group" @if ($dataset) hidden @endif>
    <label class="{{ $lbl }}" for="{{ $streetId }}">{{ $streetLabel }} *</label>
    <input type="text" id="{{ $streetId }}" class="{{ $inp }}" maxlength="160" autocomplete="address-line1" placeholder="Street number and name, e.g. 12 Smith St">
    @if ($line2Id)
      <input type="text" id="{{ $line2Id }}" class="{{ $inp }} addr-line2" maxlength="90" autocomplete="address-line2" placeholder="Unit, apartment, building (optional)" aria-label="Unit or apartment (optional)">
    @endif
  </div>

  {{-- Step 4: postcode (only when the suburb has several postcodes, or in legacy mode) beside the address type --}}
  <div class="addr-full addr-row">
    <div class="{{ $g }}" id="{{ $p }}-postcode-group" hidden>
      <label class="{{ $lbl }}" for="{{ $p }}-postcode">Postcode *</label>
      <input type="text" id="{{ $p }}-postcode" class="{{ $inp }}" inputmode="numeric" maxlength="4" pattern="[0-9]{4}" autocomplete="postal-code" placeholder="e.g. 3000" @if ($dataset) list="{{ $p }}-postcode-list" @endif>
      @if ($dataset)<datalist id="{{ $p }}-postcode-list"></datalist>@endif
    </div>
    <div class="{{ $g }}">
      <span class="{{ $lbl }}" id="{{ $p }}-type-label">Address Type</span>
      <div class="type-radio-group" role="radiogroup" aria-labelledby="{{ $p }}-type-label">
        <label class="type-radio-label"><input type="radio" name="{{ $p }}-type" value="Home" checked> Home</label>
        <label class="type-radio-label"><input type="radio" name="{{ $p }}-type" value="Office"> Work</label>
        <label class="type-radio-label"><input type="radio" name="{{ $p }}-type" value="Other"> Other</label>
      </div>
    </div>
  </div>
  <div class="{{ $g }} addr-full" id="{{ $p }}-custom-label-group" hidden>
    <label class="{{ $lbl }}" for="{{ $p }}-custom-label">Custom Label</label>
    <input type="text" id="{{ $p }}-custom-label" class="{{ $inp }}" maxlength="40" placeholder="e.g. Mum's place, Holiday house">
  </div>
</div>
