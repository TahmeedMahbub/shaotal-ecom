<div class="modal-overlay" id="modal-overlay">
    <div class="modal" id="quick-view-modal">
      <div class="modal-image" style="position:relative">
        <button class="modal-close" id="modal-close" aria-label="Close modal">&times;</button>
        <img src="" alt="" id="modal-product-img">
      </div>
      <div class="modal-content">
        <div>
          <div class="modal-category-tag" id="modal-category-tag">Fashion</div>
          <h3 class="modal-title"></h3>
          <p class="modal-subtitle"></p>
        </div>
        <div class="modal-price"></div>
        
        <div class="modal-options-section" id="modal-colors-wrap">
          <p class="modal-option-label">Colour / Finish</p>
          <div class="modal-colors"></div>
        </div>
        
        <div class="modal-options-section" id="modal-sizes-wrap">
          <p class="modal-option-label">Size / Dimension</p>
          <div class="modal-sizes"></div>
        </div>

        <button class="btn btn-primary btn-lg" id="modal-add-to-cart" style="width:100%; margin-top: 1rem">Add to Cart</button>
      </div>
    </div>
  </div>
<div class="modal-overlay" id="variant-modal-overlay">
    <div class="modal variant-modal" id="variant-selection-modal">
      <div class="modal-image" style="position:relative">
        <button class="modal-close" id="variant-modal-close" aria-label="Close modal">&times;</button>
        <img src="" alt="" id="variant-modal-img">
      </div>
      <div class="modal-content">
        <div>
          <div class="modal-category-tag" id="variant-modal-category">Fashion</div>
          <h3 class="modal-title" id="variant-modal-title">Product Name</h3>
          <p class="modal-subtitle" id="variant-modal-subtitle">Artisan Crafted · Curated for Australia</p>
        </div>
        <div class="modal-price" id="variant-modal-price">
          <span class="current">A$0.00</span>
        </div>
        
        <div class="modal-options-section" id="variant-modal-colors-wrap">
          <p class="modal-option-label">Color: <span id="variant-modal-selected-color" style="font-weight:var(--fw-semibold); color:var(--color-dark)"></span></p>
          <div class="modal-colors" id="variant-modal-colors"></div>
        </div>
        
        <div class="modal-options-section" id="variant-modal-sizes-wrap">
          <p class="modal-option-label">Size: <span id="variant-modal-selected-size" style="font-weight:var(--fw-semibold); color:var(--color-dark)"></span></p>
          <div class="modal-sizes" id="variant-modal-sizes"></div>
        </div>

        <div class="modal-options-section" id="variant-modal-qty-wrap">
          <p class="modal-option-label">Quantity</p>
          <div class="cart-quantity-selector" style="height: 38px;">
            <button class="cart-qty-btn" id="variant-qty-minus" style="width:36px; height:38px;" type="button">&minus;</button>
            <span class="cart-qty-value" id="variant-qty-value" style="min-width:36px; font-size:1rem;">1</span>
            <button class="cart-qty-btn" id="variant-qty-plus" style="width:36px; height:38px;" type="button">+</button>
          </div>
        </div>

        <div id="variant-modal-error" style="color:var(--color-danger); font-size:var(--fs-xs); display:none; margin-top: -0.5rem;">Please select required options</div>

        <button class="btn btn-primary btn-lg" id="variant-modal-confirm-btn" style="width:100%; margin-top: 0.5rem" type="button">Add to Cart</button>
      </div>
    </div>
  </div>
<div class="cart-drawer-overlay" id="cart-drawer-overlay"></div>
<div class="cart-drawer" id="cart-drawer">
    <div class="cart-drawer-header">
      <div class="cart-drawer-title-group">
        <h3>Your Cart</h3>
        <span class="cart-drawer-count-badge" id="cart-drawer-count">0 Items</span>
      </div>
      <button id="cart-drawer-close" aria-label="Close cart">&times;</button>
    </div>
    <div class="cart-drawer-body" id="cart-drawer-body">
      <div class="cart-drawer-empty">
        <p>Your cart is empty</p>
      </div>
    </div>
    <div class="cart-drawer-footer" id="cart-drawer-footer"></div>
  </div>
<div class="wishlist-drawer-overlay" id="wishlist-drawer-overlay"></div>
<div class="wishlist-drawer" id="wishlist-drawer">
    <div class="wishlist-drawer-header">
      <div class="wishlist-drawer-title-group">
        <h3>Wishlist</h3>
        <span class="wishlist-drawer-count-badge" id="wishlist-drawer-count">0 Items</span>
      </div>
      <button id="wishlist-drawer-close" aria-label="Close wishlist">&times;</button>
    </div>
    <div class="wishlist-drawer-body" id="wishlist-drawer-body">
      <div class="wishlist-drawer-empty">
        <p>Your wishlist is empty</p>
      </div>
    </div>
    <div class="wishlist-drawer-footer" id="wishlist-drawer-footer"></div>
  </div>
<div class="toast-container" id="toast-container"></div>
