
    document.addEventListener('DOMContentLoaded', () => {
      // Catalog products store
      const catalogProducts = window.BongoProducts ? window.BongoProducts.getAll() : [];
      
      // State arrays
      let requestedCatalogProducts = [];
      let requestedUnlistedProducts = [];

      // Check URL params for pre-selected item
      const urlParams = new URLSearchParams(window.location.search);
      const preselectId = urlParams.get('product') || urlParams.get('id');

      if (preselectId && catalogProducts.length > 0) {
        const found = catalogProducts.find(p => p.id === parseInt(preselectId));
        if (found) {
          requestedCatalogProducts.push({
            uid: 'prod_' + Date.now(),
            productId: found.id,
            variants: [
              { uid: 'var_1', color: '', size: '', qty: '' }
            ]
          });
        }
      }

      const catalogContainer = document.getElementById('bulk-products-container');
      const unlistedContainer = document.getElementById('unlisted-items-container');
      const btnAddCatalog = document.getElementById('btn-add-catalog-product');
      const btnAddCustom = document.getElementById('btn-add-custom-product');
      const formEl = document.getElementById('bulk-order-form');

      function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
      }

      // Helper: Format Select2 option template safely
      function formatProductOption(option) {
        if (!option.id) return option.text;
        const img = option.element ? option.element.getAttribute('data-image') : '';
        const meta = option.element ? option.element.getAttribute('data-meta') : '';
        
        if (window.jQuery) {
          return window.jQuery(
            `<div style="display: flex; align-items: center; gap: 10px; padding: 4px 0;">
              <img src="${img || window.PLACEHOLDER_IMG}" style="width: 36px; height: 36px; object-fit: cover; border-radius: var(--radius-sm); border: 1px solid var(--color-grey-200);" onerror="this.onerror=null;this.src=window.PLACEHOLDER_IMG;">
              <div style="flex: 1; overflow: hidden;">
                <div style="font-weight: 600; font-size: 0.9rem; color: var(--color-dark); text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">${option.text}</div>
                ${meta ? `<div style="font-size: 0.75rem; color: var(--color-grey-600); text-overflow: ellipsis; overflow: hidden; white-space: nowrap; margin-top: 1px;">${meta}</div>` : ''}
              </div>
            </div>`
          );
        }
        return option.text;
      }

      // Render Catalog Products
      function renderCatalogProducts() {
        if (!catalogContainer) return;
        catalogContainer.innerHTML = '';

        if (requestedCatalogProducts.length === 0) {
          catalogContainer.innerHTML = `
            <div class="empty-product-state" style="text-align: center; padding: 2rem 1rem; background: var(--color-surface-alt); border-radius: var(--radius-md); border: 1px dashed var(--color-grey-300);">
              <div style="font-size: 2rem; margin-bottom: 0.5rem; color: var(--color-grey-400);">📦</div>
              <h4 style="font-family: var(--font-heading); font-size: 1.05rem; font-weight: var(--fw-bold); color: var(--color-dark); margin-bottom: 0.25rem;">
                No catalog products added yet
              </h4>
              <p style="color: var(--color-grey-600); font-size: var(--fs-small); margin: 0;">
                Click <strong>"+ Add Product"</strong> below to select items from our online catalog.
              </p>
            </div>
          `;
          updateTotalUnitsBadge();
          return;
        }

        requestedCatalogProducts.forEach((pState, pIndex) => {
          const cardEl = document.createElement('div');
          cardEl.className = 'bulk-product-card';

          const curProd = pState.productId ? catalogProducts.find(cp => cp.id === pState.productId) : null;

          // Image box: SVG placeholder if null, or product image
          let imageHTML = '';
          if (curProd) {
            imageHTML = `<img src="${curProd.image}" alt="${curProd.name}">`;
          } else {
            imageHTML = `
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 32px; height: 32px; color: var(--color-grey-400);">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
              </svg>
            `;
          }

          // Options for Select2 with Colours and Sizes metadata
          const optionsHTML = catalogProducts.map(cp => {
            const colorList = (cp.colors && cp.colors.length > 0)
              ? cp.colors.map(c => (window.COLOR_NAMES || {})[c] || c).join(', ')
              : 'Standard';
            const sizeList = (cp.sizes && cp.sizes.length > 0) ? cp.sizes.join(', ') : 'Standard';
            const metaText = `<b>Colours:</b> ${colorList} · <b>Sizes:</b> ${sizeList}`;

            return `<option value="${cp.id}" data-image="${cp.image}" data-meta="${escapeHtml(metaText)}" ${curProd && curProd.id === cp.id ? 'selected' : ''}>${cp.name}</option>`;
          }).join('');

          // Variant rows
          const variantsHTML = pState.variants.map((vState, vIndex) => {
            let colorOpts = `<option value="" ${!vState.color ? 'selected' : ''}>Select Color</option>`;
            let sizeOpts = `<option value="" ${!vState.size ? 'selected' : ''}>Select Size</option>`;

            if (curProd) {
              if (curProd.colors && curProd.colors.length > 0) {
                colorOpts += curProd.colors.map(c => {
                  const label = (window.COLOR_NAMES || {})[c] || c;
                  return `<option value="${label}" ${vState.color === label ? 'selected' : ''}>${label}</option>`;
                }).join('');
              } else {
                colorOpts += `<option value="Standard" ${vState.color === 'Standard' ? 'selected' : ''}>Standard Color</option>`;
              }

              if (curProd.sizes && curProd.sizes.length > 0) {
                sizeOpts += curProd.sizes.map(s => `<option value="${s}" ${vState.size === s ? 'selected' : ''}>Size: ${s}</option>`).join('');
              } else {
                sizeOpts += `<option value="Standard" ${vState.size === 'Standard' ? 'selected' : ''}>Standard Size</option>`;
              }
            } else {
              colorOpts += `<option value="Standard" ${vState.color === 'Standard' ? 'selected' : ''}>Standard Color</option>`;
              sizeOpts += `<option value="Standard" ${vState.size === 'Standard' ? 'selected' : ''}>Standard Size</option>`;
            }

            return `
              <div class="bulk-variant-row" data-vindex="${vIndex}">
                <div>
                  <label style="display:block; font-size:var(--fs-xs); color:var(--color-grey-600); margin-bottom:2px;">Color / Finish</label>
                  <select class="variant-color-select" style="width:100%; padding:0.45rem; border:1px solid var(--color-grey-300); border-radius:var(--radius-sm); font-size:var(--fs-small); background:var(--color-surface-card);">
                    ${colorOpts}
                  </select>
                </div>
                <div>
                  <label style="display:block; font-size:var(--fs-xs); color:var(--color-grey-600); margin-bottom:2px;">Size / Dimension</label>
                  <select class="variant-size-select" style="width:100%; padding:0.45rem; border:1px solid var(--color-grey-300); border-radius:var(--radius-sm); font-size:var(--fs-small); background:var(--color-surface-card);">
                    ${sizeOpts}
                  </select>
                </div>
                <div class="bulk-variant-qty-col">
                  <label style="display:block; font-size:var(--fs-xs); color:var(--color-grey-600); margin-bottom:2px;">Quantity</label>
                  <input type="number" min="1" value="${vState.qty !== undefined && vState.qty !== null ? vState.qty : ''}" placeholder="Qty" class="variant-qty-input" style="width:100%; padding:0.45rem; border:1px solid var(--color-grey-300); border-radius:var(--radius-sm); font-size:var(--fs-small); font-weight:var(--fw-semibold); background:var(--color-surface-card);">
                </div>
                <div style="text-align:center;">
                  <label style="display:block; font-size:var(--fs-xs); visibility:hidden;">Del</label>
                  <button type="button" class="btn-remove-item btn-del-variant" title="Remove variant">&times;</button>
                </div>
              </div>
            `;
          }).join('');

          cardEl.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding-bottom: 1rem; margin-bottom: 1rem; border-bottom: 1px solid var(--color-grey-200);">
              <div style="display: flex; align-items: center; gap: 1rem; flex: 1;">
                <div class="product-image-box">
                  ${imageHTML}
                </div>
                <div style="flex: 1;">
                  <label style="display: block; font-size: var(--fs-xs); color: var(--color-grey-500); font-weight: var(--fw-semibold); margin-bottom: 4px;">ITEM #${pIndex + 1}</label>
                  <select class="product-select2" style="width: 100%;">
                    <option value="">Search or select a catalog product...</option>
                    ${optionsHTML}
                  </select>
                </div>
              </div>
              <button type="button" class="btn-remove-item btn-del-card" title="Remove Product" style="font-size: 1.1rem; color: var(--color-grey-500); padding: 0.35rem 0.65rem;">
                &times;
              </button>
            </div>

            <div>
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                <span style="font-size:var(--fs-small); font-weight:var(--fw-semibold); color:var(--color-dark);">Variants & Quantities:</span>
              </div>
              <div class="variants-container">
                ${variantsHTML}
              </div>
              <button type="button" class="btn btn-outline btn-sm btn-add-variant-row" style="margin-top:0.5rem; font-size:var(--fs-xs);">
                + Add Another Color / Size Variant
              </button>
            </div>
          `;

          catalogContainer.appendChild(cardEl);

          // Native change listener on select
          const rawSelect = cardEl.querySelector('.product-select2');
          if (rawSelect) {
            rawSelect.addEventListener('change', (e) => {
              const val = e.target.value;
              if (val) {
                const selectedId = parseInt(val);
                const foundCp = catalogProducts.find(cp => cp.id === selectedId);
                if (foundCp && pState.productId !== foundCp.id) {
                  pState.productId = foundCp.id;
                  pState.name = foundCp.name;
                  pState.variants = [{ uid: 'var_' + Date.now(), color: '', size: '', qty: '' }];
                  renderCatalogProducts();
                }
              } else if (pState.productId !== null) {
                pState.productId = null;
                pState.name = '';
                renderCatalogProducts();
              }
            });
          }

          // Initialize Select2 safely with jQuery if available
          if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
            const $select = window.jQuery(cardEl).find('.product-select2');
            $select.select2({
              placeholder: 'Search or select a catalog product...',
              allowClear: true,
              templateResult: formatProductOption,
              templateSelection: function(option) {
                if (!option.id) return option.text;
                return option.text;
              }
            });

            $select.on('change', function() {
              const val = window.jQuery(this).val();
              if (val) {
                const selectedId = parseInt(val);
                const foundCp = catalogProducts.find(cp => cp.id === selectedId);
                if (foundCp && pState.productId !== foundCp.id) {
                  pState.productId = foundCp.id;
                  pState.name = foundCp.name;
                  pState.variants = [{ uid: 'var_' + Date.now(), color: '', size: '', qty: '' }];
                  renderCatalogProducts();
                }
              } else if (pState.productId !== null) {
                pState.productId = null;
                pState.name = '';
                renderCatalogProducts();
              }
            });
          }

          // Remove Item Card Listener
          cardEl.querySelector('.btn-del-card').addEventListener('click', () => {
            requestedCatalogProducts.splice(pIndex, 1);
            renderCatalogProducts();
          });

          // Add Variant Row Listener
          cardEl.querySelector('.btn-add-variant-row').addEventListener('click', () => {
            pState.variants.push({ uid: 'var_' + Date.now(), color: '', size: '', qty: '' });
            renderCatalogProducts();
          });

          // Variant Rows Input Listeners
          const variantRows = cardEl.querySelectorAll('.bulk-variant-row');
          variantRows.forEach((vRow, vIdx) => {
            const colorSel = vRow.querySelector('.variant-color-select');
            const sizeSel = vRow.querySelector('.variant-size-select');
            const qtyInp = vRow.querySelector('.variant-qty-input');
            const delBtn = vRow.querySelector('.btn-del-variant');

            colorSel.addEventListener('change', (e) => { pState.variants[vIdx].color = e.target.value; });
            sizeSel.addEventListener('change', (e) => { pState.variants[vIdx].size = e.target.value; });
            qtyInp.addEventListener('input', (e) => {
              pState.variants[vIdx].qty = e.target.value ? parseInt(e.target.value) : '';
              updateTotalUnitsBadge();
            });
            delBtn.addEventListener('click', () => {
              if (pState.variants.length > 1) {
                pState.variants.splice(vIdx, 1);
              } else {
                alert('Each product item must have at least one variant quantity row.');
              }
              renderCatalogProducts();
            });
          });

        });

        updateTotalUnitsBadge();
      }

      // Render Separate Unlisted / Custom Products
      function renderUnlistedProducts() {
        if (!unlistedContainer) return;
        unlistedContainer.innerHTML = '';

        if (requestedUnlistedProducts.length === 0) return;

        requestedUnlistedProducts.forEach((uState, uIndex) => {
          const cardEl = document.createElement('div');
          cardEl.style.cssText = 'background: var(--color-surface-alt); border: 1px solid var(--color-grey-300); border-radius: var(--radius-md); padding: 1.25rem; margin-top: 1rem;';

          cardEl.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1rem;">
              <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: var(--fs-xs); font-weight: var(--fw-bold); color: var(--color-brand); text-transform: uppercase;">UNLISTED REQUEST #${uIndex + 1}</span>
              </div>
              <button type="button" class="btn-remove-item btn-del-unlisted" title="Remove Unlisted Request" style="font-size: 0.9rem; font-weight: var(--fw-semibold); display: flex; align-items: center; gap: 4px;">
                <span>Remove</span> &times;
              </button>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem; margin-bottom: 1rem;" class="form-row-2col">
              <div>
                <label style="display: block; font-size: var(--fs-xs); font-weight: var(--fw-semibold); color: var(--color-dark); margin-bottom: 4px;">
                  What item are you looking for? <span style="color: var(--color-danger)">*</span>
                </label>
                <input type="text" class="unlisted-name-input" value="${uState.name || ''}" placeholder="e.g. Custom Engraved Brass Coasters, Artisanal Silk Scarf" style="width: 100%; padding: 0.6rem 0.8rem; border: 1px solid var(--color-grey-300); border-radius: var(--radius-sm); font-size: var(--fs-small); background: var(--color-surface-card);" required>
              </div>
              <div>
                <label style="display: block; font-size: var(--fs-xs); font-weight: var(--fw-semibold); color: var(--color-dark); margin-bottom: 4px;">
                  Total Quantity <span style="color: var(--color-danger)">*</span>
                </label>
                <input type="number" min="1" value="${uState.qty !== undefined && uState.qty !== null ? uState.qty : ''}" placeholder="e.g. 50" class="unlisted-qty-input" style="width: 100%; padding: 0.6rem 0.8rem; border: 1px solid var(--color-grey-300); border-radius: var(--radius-sm); font-size: var(--fs-small); font-weight: var(--fw-semibold); background: var(--color-surface-card);" required>
              </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;" class="form-row-2col">
              <div>
                <label style="display: block; font-size: var(--fs-xs); font-weight: var(--fw-semibold); color: var(--color-dark); margin-bottom: 4px;">
                  Specifications / Material / Color & Size <span style="color: var(--color-grey-500); font-weight: var(--fw-normal)">(Optional)</span>
                </label>
                <input type="text" class="unlisted-specs-input" value="${uState.customSpecs || ''}" placeholder="e.g. Solid brass with lacquered finish, set of 4" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--color-grey-300); border-radius: var(--radius-sm); font-size: var(--fs-small); background: var(--color-surface-card);">
              </div>
              <div>
                <label style="display: block; font-size: var(--fs-xs); font-weight: var(--fw-semibold); color: var(--color-dark); margin-bottom: 4px;">
                  Reference Link or Image URL <span style="color: var(--color-grey-500); font-weight: var(--fw-normal)">(Optional)</span>
                </label>
                <input type="url" class="unlisted-link-input" value="${uState.productLink || ''}" placeholder="https://instagram.com/p/... or website link" style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--color-grey-300); border-radius: var(--radius-sm); font-size: var(--fs-small); background: var(--color-surface-card);">
              </div>
            </div>
          `;

          unlistedContainer.appendChild(cardEl);

          // Event Listeners for Unlisted Card
          cardEl.querySelector('.unlisted-name-input').addEventListener('input', (e) => { uState.name = e.target.value; });
          cardEl.querySelector('.unlisted-qty-input').addEventListener('input', (e) => {
            uState.qty = e.target.value ? parseInt(e.target.value) : '';
            updateTotalUnitsBadge();
          });
          cardEl.querySelector('.unlisted-specs-input').addEventListener('input', (e) => { uState.customSpecs = e.target.value; });
          cardEl.querySelector('.unlisted-link-input').addEventListener('input', (e) => { uState.productLink = e.target.value; });

          cardEl.querySelector('.btn-del-unlisted').addEventListener('click', () => {
            requestedUnlistedProducts.splice(uIndex, 1);
            renderUnlistedProducts();
            updateTotalUnitsBadge();
          });
        });

        updateTotalUnitsBadge();
      }

      function updateTotalUnitsBadge() {
        let total = 0;
        requestedCatalogProducts.forEach(p => {
          if (p.variants) {
            p.variants.forEach(v => { total += (parseInt(v.qty) || 0); });
          }
        });
        requestedUnlistedProducts.forEach(u => {
          total += (parseInt(u.qty) || 0);
        });

        const badge = document.getElementById('bulk-total-units-badge');
        if (badge) badge.textContent = `${total} Total Units`;
      }

      // Add Catalog Product Button Listener (ONE single button at bottom right)
      btnAddCatalog?.addEventListener('click', () => {
        requestedCatalogProducts.push({
          uid: 'prod_' + Date.now(),
          productId: null, // Initially empty!
          name: '',
          variants: [
            { uid: 'var_' + Date.now(), color: '', size: '', qty: '' } // All variant fields initially empty!
          ]
        });
        renderCatalogProducts();
      });

      // Add Unlisted Product Button Listener
      btnAddCustom?.addEventListener('click', () => {
        requestedUnlistedProducts.push({
          uid: 'unlisted_' + Date.now(),
          name: '',
          qty: '', // Initially empty!
          customSpecs: '',
          productLink: ''
        });
        renderUnlistedProducts();
      });

      // Form Submission Handler
      formEl?.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        let isValid = true;
        const nameInput = document.getElementById('bulk-name');
        const contactInput = document.getElementById('bulk-contact');
        const postcodeInput = document.getElementById('bulk-postcode');

        if (!nameInput.value.trim()) {
          document.getElementById('error-bulk-name').style.display = 'block';
          isValid = false;
        } else {
          document.getElementById('error-bulk-name').style.display = 'none';
        }

        if (!contactInput.value.trim()) {
          document.getElementById('error-bulk-contact').style.display = 'block';
          isValid = false;
        } else {
          document.getElementById('error-bulk-contact').style.display = 'none';
        }

        if (!postcodeInput.value.trim()) {
          document.getElementById('error-bulk-postcode').style.display = 'block';
          isValid = false;
        } else {
          document.getElementById('error-bulk-postcode').style.display = 'none';
        }

        if (requestedCatalogProducts.length === 0 && requestedUnlistedProducts.length === 0) {
          alert('Please add at least one catalog product or unlisted item to your request.');
          return;
        }

        // Validate unlisted product names
        for (let u of requestedUnlistedProducts) {
          if (!u.name.trim()) {
            alert('Please enter a name for your unlisted product request.');
            return;
          }
        }

        if (!isValid) return;

        // Combine items into payload
        const allItems = [];

        requestedCatalogProducts.forEach(p => {
          if (p.productId) {
            const foundCp = catalogProducts.find(c => c.id === p.productId);
            allItems.push({
              id: p.productId,
              name: foundCp ? foundCp.name : 'Catalog Product',
              category: foundCp ? foundCp.category : 'General',
              image: foundCp ? foundCp.image : '',
              isCustom: false,
              variants: p.variants
            });
          }
        });

        requestedUnlistedProducts.forEach(u => {
          allItems.push({
            id: null,
            name: u.name,
            category: 'unlisted',
            image: '',
            isCustom: true,
            customSpecs: u.customSpecs,
            productLink: u.productLink,
            variants: [
              { color: 'Custom / Standard', size: 'Standard', qty: u.qty }
            ]
          });
        });

        const payload = {
          customerName: nameInput.value.trim(),
          contactInfo: contactInput.value.trim(),
          postcode: postcodeInput.value.trim(),
          targetDate: document.getElementById('bulk-target-date').value || null,
          notes: document.getElementById('bulk-notes').value.trim(),
          items: allItems.map(it => ({
            id: it.id, name: it.name, isCustom: it.isCustom, customSpecs: it.customSpecs || '', productLink: it.productLink || '',
            variants: it.variants.map(v => ({ color: v.color || null, size: v.size || null, qty: parseInt(v.qty, 10) || 0 })).filter(v => v.qty > 0),
          })).filter(it => it.variants.length > 0),
        };
        if (payload.items.length === 0) {
          alert('Please enter a quantity for at least one item.');
          return;
        }

        const res = await window.bongoApi('POST', '/bulk-order', payload);
        if (!res.ok) {
          showToast(window.bongoApiMessage(res));
          return;
        }
        const created = {
          customerName: payload.customerName, requestNumber: res.data.requestNumber, contactInfo: res.data.contactInfo,
          totalUnits: res.data.totalUnits, postcode: payload.postcode,
          items: payload.items.map(it => ({ name: it.name, isCustom: it.isCustom, variants: it.variants })),
        };

        // Show Success View
        document.getElementById('bulk-form-wrapper').style.display = 'none';
        const successWrapper = document.getElementById('bulk-success-wrapper');
        successWrapper.style.display = 'block';

        if (created) {
          document.getElementById('submitted-bulk-name').textContent = created.customerName;
          document.getElementById('submitted-bulk-ref').textContent = created.requestNumber;
          document.getElementById('submitted-bulk-contact').textContent = created.contactInfo;
          document.getElementById('summary-bulk-products-count').textContent = `${created.items.length} Item(s)`;
          document.getElementById('summary-bulk-total-units').textContent = `${created.totalUnits} Units`;
          document.getElementById('summary-bulk-postcode').textContent = created.postcode;

          const summaryItemsEl = document.getElementById('summary-bulk-items-list');
          summaryItemsEl.innerHTML = created.items.map(item => `
            <div style="margin-bottom: 0.5rem; font-size: var(--fs-xs);">
              <strong style="color: var(--color-dark);">${escapeHtml(item.name)}</strong> ${item.isCustom ? '<span style="color:var(--color-brand)">(Unlisted Item)</span>' : ''}
              <div style="color: var(--color-grey-600); padding-left: 0.5rem;">
                ${item.variants.map(v => `${escapeHtml(v.color || 'Standard')} / ${escapeHtml(v.size || 'Standard')} &times; ${v.qty} units`).join(' | ')}
              </div>
            </div>
          `).join('');
        }

        window.scrollTo({ top: 200, behavior: 'smooth' });
      });

      // Submit another request listener
      document.getElementById('btn-submit-another-bulk')?.addEventListener('click', () => {
        formEl.reset();
        requestedCatalogProducts = [];
        requestedUnlistedProducts = [];
        renderCatalogProducts();
        renderUnlistedProducts();
        document.getElementById('bulk-success-wrapper').style.display = 'none';
        document.getElementById('bulk-form-wrapper').style.display = 'block';
      });

      // Initial render
      renderCatalogProducts();
      renderUnlistedProducts();
    });
  