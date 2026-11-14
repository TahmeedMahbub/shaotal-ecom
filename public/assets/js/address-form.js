/*
 * Shared Australian address fields (State → Suburb → Street → Postcode → Address type).
 * Markup: resources/views/components/address-fields.blade.php. Data: GET /api/locations/{states,suburbs,postcodes}.
 * Used by checkout and Account → My Locations; never hardcode suburbs or postcodes in a page script.
 *
 *   const af = AddressForm.mount(document.getElementById('addr-fields'));
 *   af.get()                    → { state, suburb, postcode, street, line2, addressType, customLabel }
 *   await af.set({ ... })       → fills the fields (loads the suburb list for the state first)
 *   af.reset()                  → back to step 1
 *   af.check()                  → null, or [message, [fieldKeys]] for the first problem
 *
 * Without location data on the server the component renders plain fields (data-mode="legacy") and the same API works.
 */
(function () {
  const API = '/api/locations';
  const states = { promise: null };

  async function getJson(url) {
    const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    const body = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(body.message || 'Could not load locations.');
    return body;
  }
  const loadStates = () => (states.promise = states.promise || getJson(`${API}/states`).then(b => b.data).catch(e => { states.promise = null; throw e; }));
  const norm = s => String(s || '').trim().toLowerCase();

  /* ── Searchable dropdown (Select2-style, keyboard + touch friendly) ── */
  function Combo(el, opts) {
    const input = el.querySelector('input[type="text"]');
    const list = el.querySelector('.addr-cb-list');
    const self = { items: [], page: 1, hasMore: false, loading: false, active: -1, selected: null, token: 0, timer: 0, down: null };
    const clearBtn = el.querySelector('.addr-cb-clear');
    const sync = () => el.classList.toggle('has-value', input.value !== '');

    const open = () => { list.hidden = false; input.setAttribute('aria-expanded', 'true'); };
    const close = () => { list.hidden = true; input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); self.active = -1; };
    const status = text => { const li = document.createElement('li'); li.className = 'addr-cb-status'; li.setAttribute('role', 'presentation'); li.textContent = text; return li; };

    function render(message) {
      list.textContent = '';
      self.items.forEach((it, i) => {
        const li = document.createElement('li');
        li.className = 'addr-cb-option' + (i === self.active ? ' is-active' : '') + (self.selected && self.selected.id === it.id ? ' is-selected' : '');
        li.id = `${list.id}-o${i}`;
        li.setAttribute('role', 'option');
        li.setAttribute('aria-selected', self.selected && self.selected.id === it.id ? 'true' : 'false');
        li.dataset.i = i;
        const main = document.createElement('span');
        main.textContent = it.label;
        li.appendChild(main);
        if (it.hint) { const h = document.createElement('small'); h.textContent = it.hint; li.appendChild(h); }
        list.appendChild(li);
      });
      if (self.loading) list.appendChild(status('Loading…'));
      else if (message) list.appendChild(status(message));
      else if (!self.items.length) list.appendChild(status(opts.empty(input.value.trim())));
    }

    async function fetchPage(reset) {
      if (opts.disabled()) return;
      const token = ++self.token;
      if (reset) { self.page = 1; self.items = []; self.active = -1; }
      self.loading = true; open(); render();
      el.classList.add('is-loading');
      try {
        const r = await opts.source(input.value.trim(), self.page);
        if (token !== self.token) return;
        self.items = reset ? r.items : self.items.concat(r.items);
        self.hasMore = r.hasMore;
        self.loading = false;
        el.classList.remove('is-loading');
        if (reset && self.items.length) self.active = 0;
        render();
      } catch (e) {
        if (token !== self.token) return;
        self.loading = false; el.classList.remove('is-loading');
        render(e.message || 'Could not load. Please try again.');
      }
    }

    function choose(i) {
      const it = self.items[i];
      if (!it) return;
      self.selected = it;
      input.value = it.value;
      sync();
      close();
      opts.onSelect(it);
    }

    function move(d) {
      if (list.hidden) { fetchPage(true); return; }
      if (!self.items.length) return;
      self.active = (self.active + d + self.items.length) % self.items.length;
      render();
      const a = list.querySelector('.is-active');
      if (a) { input.setAttribute('aria-activedescendant', a.id); a.scrollIntoView({ block: 'nearest' }); }
    }

    input.addEventListener('input', () => {
      if (self.selected && norm(input.value) !== norm(self.selected.value)) { self.selected = null; opts.onClear(); }
      sync();
      clearTimeout(self.timer);
      self.timer = setTimeout(() => fetchPage(true), opts.debounce || 0);
    });
    input.addEventListener('focus', () => { if (!opts.disabled()) fetchPage(true); });
    input.addEventListener('click', () => { if (list.hidden && !opts.disabled()) fetchPage(true); });
    input.addEventListener('keydown', e => {
      if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
      else if (e.key === 'Enter') { if (!list.hidden && self.active >= 0) { e.preventDefault(); choose(self.active); } }
      else if (e.key === 'Escape') { if (!list.hidden) { e.preventDefault(); close(); } }
      else if (e.key === 'Tab') { if (!list.hidden && self.active >= 0 && input.value.trim()) choose(self.active); else close(); }
    });
    input.addEventListener('blur', () => {
      if (self.down) return; // a finger/mouse is on the list (maybe scrolling it): keep it open
      setTimeout(() => {
        close();
        // Typed text that was never picked from the list does not count: restore the chosen value or clear it.
        if (self.selected) input.value = self.selected.value; else if (input.value) { input.value = ''; opts.onClear(); }
        sync();
      }, 120);
    });
    // Pick on pointer-UP and only for a tap: a finger that moves (scrolling the list) or is cancelled by the browser's
    // scroll handling selects nothing. No touch handlers and no preventDefault on touch, so native scrolling is untouched.
    list.addEventListener('pointerdown', e => {
      const li = e.target.closest('.addr-cb-option');
      self.down = li ? { li, x: e.clientX, y: e.clientY } : null;
    });
    list.addEventListener('pointerup', e => {
      const d = self.down;
      self.down = null;
      if (!d) return;
      const li = e.target.closest('.addr-cb-option');
      if (li && li === d.li && Math.abs(e.clientX - d.x) < 10 && Math.abs(e.clientY - d.y) < 10) choose(Number(li.dataset.i));
    });
    list.addEventListener('pointercancel', () => { self.down = null; });
    list.addEventListener('mousedown', e => e.preventDefault()); // mouse only: keep focus in the input
    if (clearBtn) {
      clearBtn.addEventListener('mousedown', e => e.preventDefault());
      clearBtn.addEventListener('click', () => {
        self.selected = null; input.value = ''; self.items = [];
        opts.onClear();
        sync();
        if (!input.disabled) input.focus();
      });
    }
    list.addEventListener('scroll', () => {
      if (self.hasMore && !self.loading && list.scrollTop + list.clientHeight >= list.scrollHeight - 24) { self.page++; fetchPage(false); }
    });

    return {
      input,
      setValue(item) { self.selected = item; input.value = item ? item.value : ''; sync(); },
      clear() { self.selected = null; input.value = ''; self.items = []; close(); sync(); },
      selected: () => self.selected,
    };
  }

  /* ── The shared component ── */
  function mount(root) {
    if (!root) return null;
    if (root.__addressForm) return root.__addressForm;
    const legacy = root.dataset.mode === 'legacy';
    const p = root.dataset.prefix;
    const $ = id => document.getElementById(id);
    const f = {
      state: $(`${p}-state`), suburb: $(`${p}-suburb`), postcode: $(`${p}-postcode`),
      street: $(root.dataset.street), line2: root.dataset.line2 ? $(root.dataset.line2) : null,
      custom: $(`${p}-custom-label`), customGroup: $(`${p}-custom-label-group`),
      streetGroup: $(`${p}-street-group`),
    };
    const types = () => root.querySelectorAll(`input[name="${p}-type"]`);
    const stateInput = root.querySelector('[data-cb="state"] input[type="text"]');
    const suburbInput = root.querySelector('[data-cb="suburb"] input[type="text"]');
    let stateObj = null; // { id, code, name }
    let pcCache = [];
    let chooseState = () => {};

    const toggleCustom = () => {
      const c = root.querySelector(`input[name="${p}-type"]:checked`);
      if (f.customGroup) f.customGroup.hidden = !c || c.value !== 'Other';
    };
    types().forEach(r => r.addEventListener('change', toggleCustom));

    // Postcode rules (from the dataset): 1 postcode → used automatically and shown beside the suburb name, no field;
    // several → a required, manually entered field (beside Address Type); none/no suburb → hidden and empty.
    const pcGroup = $(`${p}-postcode-group`);
    const pcList = $(`${p}-postcode-list`);
    const suburbLabel = (name, list) => (list && list.length === 1 ? `${name} (${list[0]})` : name);
    function setPostcodes(list, keep) {
      pcCache = list;
      const pc = f.postcode;
      pc.value = '';
      if (pcList) pcList.textContent = '';
      if (list.length > 1) {
        list.forEach(v => { const o = document.createElement('option'); o.value = v; if (pcList) pcList.appendChild(o); });
        if (keep && list.includes(keep)) pc.value = keep;
      } else if (list.length === 1) pc.value = list[0];
      if (pcGroup) pcGroup.hidden = list.length < 2;
    }

    function showSteps(suburbChosen) {
      if (f.streetGroup) f.streetGroup.hidden = !suburbChosen;
    }

    const api = {
      el: root,
      legacy,
      get() {
        const c = root.querySelector(`input[name="${p}-type"]:checked`);
        return {
          state: f.state.value, suburb: f.suburb.value.trim(), postcode: (f.postcode.value || '').trim(),
          street: f.street ? f.street.value.trim() : '', line2: f.line2 ? f.line2.value.trim() : '',
          addressType: c ? c.value : 'Home', customLabel: f.custom ? f.custom.value.trim() : '',
        };
      },
      check() {
        const d = api.get();
        if (!d.state) return ['Please choose your state or territory.', ['state']];
        if (!d.suburb) return ['Please choose your suburb from the list.', ['suburb']];
        if (!/^\d{4}$/.test(d.postcode)) return [pcCache.length > 1 ? 'Please enter your postcode for this suburb.' : 'Please enter a valid 4-digit Australian postcode.', ['postcode']];
        if (!legacy && pcCache.length && !pcCache.includes(d.postcode)) return [`This postcode does not belong to ${d.suburb}. Please check it (${pcCache.join(', ')}).`, ['postcode']];
        if (!d.street) return ['Please enter the street address.', ['street']];
        return null;
      },
      reset() {
        if (legacy) { f.state.value = ''; f.suburb.value = ''; f.postcode.value = ''; }
        else {
          stateObj = null; f.state.value = ''; f.suburb.value = '';
          stateInput.value = ''; suburbInput.value = ''; suburbInput.disabled = true;
          if (api._state) api._state.clear();
          if (api._suburb) api._suburb.clear();
          setPostcodes([]); showSteps(false);
        }
        if (f.street) f.street.value = '';
        if (f.line2) f.line2.value = '';
        if (f.custom) f.custom.value = '';
        types().forEach(r => { r.checked = r.value === 'Home'; });
        toggleCustom();
      },
      async set(v) {
        v = v || {};
        if (legacy) {
          f.state.value = v.state || ''; f.suburb.value = v.suburb || ''; f.postcode.value = v.postcode || '';
        } else {
          api.reset();
          if (v.state) {
            const list = await loadStates().catch(() => []);
            const s = list.find(x => x.code === v.state);
            if (s) {
              chooseState({ id: s.id, value: `${s.name} (${s.code})`, code: s.code, name: s.name });
              api._state.setValue({ id: s.id, value: `${s.name} (${s.code})` });
              if (v.suburb) {
                const r = await getJson(`${API}/suburbs?state_id=${s.id}&search=${encodeURIComponent(v.suburb)}&per_page=30`).catch(() => null);
                const m = r && r.data.find(x => norm(x.name) === norm(v.suburb));
                if (m) {
                  api._suburb.setValue({ id: m.id, value: suburbLabel(m.name, m.postcodes), name: m.name });
                  f.suburb.value = m.name;
                  setPostcodes(m.postcodes, v.postcode);
                  showSteps(true);
                } else if (v.suburb) {
                  // Older saved address whose suburb is not in the list: show it, ask the customer to re-pick it.
                  suburbInput.value = v.suburb;
                  showSteps(false);
                }
              }
            }
          }
        }
        if (f.street) f.street.value = v.street || v.line1 || '';
        if (f.line2) f.line2.value = v.line2 || '';
        if (f.custom) f.custom.value = v.customLabel || '';
        const t = v.addressType || 'Home';
        types().forEach(r => { r.checked = r.value === t; });
        toggleCustom();
      },
    };

    if (!legacy) {
      chooseState = it => {
        stateObj = { id: it.id, code: it.code, name: it.name };
        f.state.value = it.code;
        // A new state invalidates the suburb and postcode.
        f.suburb.value = ''; api._suburb.clear(); setPostcodes([]); showSteps(false);
        suburbInput.disabled = false;
        suburbInput.placeholder = `Search suburbs in ${it.code}`;
      };
      api._state = Combo(root.querySelector('[data-cb="state"]'), {
        empty: () => 'No matching state or territory.',
        disabled: () => false,
        source: async q => {
          const all = (await loadStates()).filter(s => s.deliverable);
          const n = norm(q);
          const items = all.filter(s => !n || norm(s.name).startsWith(n) || norm(s.code) === n || norm(s.name).includes(n))
            .map(s => ({ id: s.id, label: s.name, hint: s.code, value: `${s.name} (${s.code})`, code: s.code, name: s.name }));
          return { items, hasMore: false };
        },
        onSelect: chooseState,
        onClear: () => {
          stateObj = null; f.state.value = ''; f.suburb.value = '';
          api._suburb.clear(); suburbInput.value = ''; suburbInput.disabled = true; suburbInput.placeholder = 'Choose a state first';
          setPostcodes([]); showSteps(false);
        },
      });
      api._suburb = Combo(root.querySelector('[data-cb="suburb"]'), {
        debounce: 220,
        empty: q => (q ? `No suburb matching “${q}” in ${stateObj ? stateObj.code : 'this state'}.` : 'No suburbs found.'),
        disabled: () => !stateObj,
        source: async (q, page) => {
          const r = await getJson(`${API}/suburbs?state_id=${stateObj.id}&search=${encodeURIComponent(q)}&page=${page}`);
          return {
            hasMore: !!(r.meta && r.meta.has_more),
            items: r.data.map(s => ({ id: s.id, label: s.name, hint: `${s.state} ${s.postcodes.join(', ')}`, value: suburbLabel(s.name, s.postcodes), name: s.name, postcodes: s.postcodes })),
          };
        },
        onSelect: it => {
          f.suburb.value = it.name;
          setPostcodes(it.postcodes || []);
          showSteps(true);
          if (f.street) setTimeout(() => f.street.focus({ preventScroll: true }), 0);
        },
        onClear: () => { f.suburb.value = ''; setPostcodes([]); showSteps(false); },
      });
      loadStates().catch(() => {});
      suburbInput.disabled = true;
      setPostcodes([]);
      showSteps(!!f.suburb.value);
    } else {
      if (pcGroup) pcGroup.hidden = false;
    }
    f.postcode.addEventListener('input', () => { f.postcode.value = f.postcode.value.replace(/\D/g, '').slice(0, 4); });
    toggleCustom();
    root.__addressForm = api;
    return api;
  }

  window.AddressForm = { mount };
})();
