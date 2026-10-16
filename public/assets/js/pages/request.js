
    document.addEventListener('DOMContentLoaded', () => {
      const form = document.getElementById('sourcing-request-form');
      const uploadZone = document.getElementById('file-upload-zone');
      const fileInput = document.getElementById('request-file-input');
      const previewList = document.getElementById('file-preview-list');
      const formWrapper = document.getElementById('request-form-wrapper');
      const successWrapper = document.getElementById('request-success-wrapper');
      const btnSubmitAnother = document.getElementById('btn-submit-another');

      let selectedFiles = [];

      // File Drag and Drop Handlers
      if (uploadZone && fileInput) {
        uploadZone.addEventListener('click', () => fileInput.click());

        uploadZone.addEventListener('dragover', (e) => {
          e.preventDefault();
          uploadZone.classList.add('dragover');
        });

        uploadZone.addEventListener('dragleave', () => {
          uploadZone.classList.remove('dragover');
        });

        uploadZone.addEventListener('drop', (e) => {
          e.preventDefault();
          uploadZone.classList.remove('dragover');
          if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            handleFilesAdded(Array.from(e.dataTransfer.files));
          }
        });

        fileInput.addEventListener('change', (e) => {
          if (e.target.files && e.target.files.length > 0) {
            handleFilesAdded(Array.from(e.target.files));
          }
        });
      }

      function handleFilesAdded(files) {
        files.forEach(file => {
          if (!file.type.startsWith('image/')) return;
          // Avoid duplicates by name
          if (selectedFiles.some(f => f.name === file.name && f.size === file.size)) return;
          selectedFiles.push(file);
        });
        renderFilePreviews();
      }

      function renderFilePreviews() {
        if (!previewList) return;
        previewList.innerHTML = '';
        selectedFiles.forEach((file, index) => {
          const itemEl = document.createElement('div');
          itemEl.className = 'file-preview-item';
          
          const imgEl = document.createElement('img');
          imgEl.alt = file.name;
          
          const reader = new FileReader();
          reader.onload = (e) => { imgEl.src = e.target.result; };
          reader.readAsDataURL(file);

          const nameEl = document.createElement('span');
          nameEl.className = 'file-preview-name';
          nameEl.textContent = file.name;

          const removeBtn = document.createElement('button');
          removeBtn.type = 'button';
          removeBtn.className = 'file-preview-remove';
          removeBtn.innerHTML = '&times;';
          removeBtn.title = 'Remove image';
          removeBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            selectedFiles.splice(index, 1);
            renderFilePreviews();
          });

          itemEl.appendChild(imgEl);
          itemEl.appendChild(nameEl);
          itemEl.appendChild(removeBtn);
          previewList.appendChild(itemEl);
        });
      }

      // Form Validation & Submission
      if (form) {
        form.addEventListener('submit', async (e) => {
          e.preventDefault();

          const itemNameInput = document.getElementById('request-item-name');
          const quantityInput = document.getElementById('request-quantity');
          const nameInput = document.getElementById('request-name');
          const contactInput = document.getElementById('request-contact');
          const postcodeInput = document.getElementById('request-postcode');

          let isValid = true;

          // Clear previous errors
          document.querySelectorAll('.field-error-msg').forEach(msg => msg.style.display = 'none');

          if (!itemNameInput.value.trim()) {
            document.getElementById('error-item-name').style.display = 'block';
            isValid = false;
          }

          if (!quantityInput.value || parseInt(quantityInput.value, 10) < 1) {
            document.getElementById('error-quantity').style.display = 'block';
            isValid = false;
          }

          if (!nameInput.value.trim()) {
            document.getElementById('error-name').style.display = 'block';
            isValid = false;
          }

          if (!contactInput.value.trim()) {
            document.getElementById('error-contact').style.display = 'block';
            isValid = false;
          }

          const postcodeVal = postcodeInput.value.trim();
          if (!postcodeVal || !/^\d{4}$/.test(postcodeVal)) {
            document.getElementById('error-postcode').style.display = 'block';
            isValid = false;
          }

          if (!isValid) return;

          const fd = new FormData();
          fd.append('customerName', nameInput.value.trim());
          fd.append('contactInfo', contactInput.value.trim());
          fd.append('postcode', postcodeVal);
          fd.append('itemName', itemNameInput.value.trim());
          fd.append('productLink', (document.getElementById('request-product-link') || {}).value || '');
          fd.append('colorSize', (document.getElementById('request-variant') || {}).value || '');
          fd.append('qty', parseInt(quantityInput.value, 10));
          fd.append('details', (document.getElementById('request-details') || {}).value || '');
          selectedFiles.forEach(f => fd.append('images[]', f));

          const submitBtn = form.querySelector('button[type="submit"]');
          if (submitBtn) submitBtn.disabled = true;
          let res;
          try {
            const r = await fetch('/request', {
              method: 'POST', body: fd, credentials: 'same-origin',
              headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            });
            res = { ok: r.ok, data: await r.json().catch(() => ({})) };
          } catch (err) {
            res = { ok: false, data: { message: 'Network error. Please try again.' } };
          }
          if (submitBtn) submitBtn.disabled = false;
          if (!res.ok) {
            const first = res.data.errors ? Object.values(res.data.errors)[0][0] : null;
            if (typeof showToast === 'function') showToast(first || res.data.message || 'Could not submit your request.');
            return;
          }

          // Show Success State
          document.getElementById('submitted-user-name').textContent = nameInput.value.trim();
          document.getElementById('submitted-item-name').textContent = itemNameInput.value.trim().slice(0, 45) + (itemNameInput.value.trim().length > 45 ? '...' : '');
          document.getElementById('submitted-contact').textContent = contactInput.value.trim();
          
          document.getElementById('summary-item-name').textContent = itemNameInput.value.trim().slice(0, 35) + (itemNameInput.value.trim().length > 35 ? '...' : '');
          document.getElementById('summary-quantity').textContent = quantityInput.value;
          document.getElementById('summary-postcode').textContent = postcodeVal;

          formWrapper.style.display = 'none';
          successWrapper.style.display = 'block';

          if (typeof showToast === 'function') {
            showToast('Sourcing request submitted!');
          }

          window.scrollTo({ top: formWrapper.offsetTop - 100, behavior: 'smooth' });
        });
      }

      // Pre-fill user data if logged in
      const currUser = typeof window.getStoredCurrentUser === 'function' ? window.getStoredCurrentUser() : null;
      if (currUser) {
        const n = document.getElementById('request-name');
        const c = document.getElementById('request-contact');
        const pc = document.getElementById('request-postcode');
        if (n && !n.value) n.value = currUser.name || '';
        if (c && !c.value) c.value = currUser.email || currUser.phone || '';
        if (pc && !pc.value) pc.value = currUser.postcode || '';
      }

      if (btnSubmitAnother) {
        btnSubmitAnother.addEventListener('click', () => {
          form.reset();
          selectedFiles = [];
          renderFilePreviews();
          successWrapper.style.display = 'none';
          formWrapper.style.display = 'block';
        });
      }
    });
  