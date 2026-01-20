<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['staff_user', 'admin_sec'], true)) {
  header('Location: /SCP/staff/products_manage.php');
  exit;
}

$products = [];
$result = $conn->query("SELECT * FROM products ORDER BY display_order ASC, id ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$categoryFilter = isset($_GET['category']) ? trim($_GET['category']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : '';

$categories = [];
foreach ($products as $p) {
  if (!empty($p['category'])) $categories[] = $p['category'];
}
$categories = array_unique($categories);
sort($categories);

if ($q !== '' || ($categoryFilter !== '' && $categoryFilter !== 'all')) {
  $products = array_filter($products, function($p) use ($q, $categoryFilter) {
    $ok = true;
    if ($q !== '') {
      $ok = stripos($p['title'] ?? '', $q) !== false || stripos($p['description'] ?? '', $q) !== false;
    }
    if ($ok && $categoryFilter !== '' && $categoryFilter !== 'all') {
      $ok = ($p['category'] ?? '') === $categoryFilter;
    }
    return $ok;
  });
  $products = array_values($products);
}

if ($sort === 'price_asc') {
  usort($products, function($a, $b){ return ($a['price'] ?? 0) <=> ($b['price'] ?? 0); });
} elseif ($sort === 'price_desc') {
  usort($products, function($a, $b){ return ($b['price'] ?? 0) <=> ($a['price'] ?? 0); });
}

if (isset($_GET['ajax'])) {
  header('Content-Type: application/json');
  echo json_encode(array_values($products));
  exit;
}

include '../includes/header.php';
?>

<div class="position-fixed top-0 end-0 p-3" style="z-index: 11000;">
  <div id="cartToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header">
      <strong class="me-auto" id="toastTitle">Notification</strong>
      <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
    <div class="toast-body" id="toastBody"></div>
  </div>
</div>

<div class="page-header">
  <h2>Products</h2>
</div>

<style>
.filter-row .form-control,
.filter-row .form-select,
.filter-row .btn {
  height: calc(2.25rem + 2px);
  padding: .375rem .75rem;
  line-height: 1.5;
  border: 1px solid #ced4da;
}

.filter-row .input-group .form-control {
  border-right: none;
  border-top-right-radius: 0;
  border-bottom-right-radius: 0;
}

.filter-row .input-group .btn {
  border-top-left-radius: 0;
  border-bottom-left-radius: 0;
}

.filter-row input[type="search"]::-webkit-search-cancel-button {
  -webkit-appearance: none;
  appearance: none;
  height: 14px;
  width: 14px;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%231e293b'%3E%3Cpath d='M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z'/%3E%3C/svg%3E");
  background-size: 14px 14px;
  cursor: pointer;
}
</style>
<form class="row g-2 mb-4 filter-row" method="get" action="" data-filter-form="products-filter">
  <div class="col-md-6">
    <div class="input-group">
      <input type="search" name="q" class="form-control" placeholder="Search products..." value="<?= htmlspecialchars($q) ?>">
      <button class="btn btn-primary" type="submit">Search</button>
    </div>
  </div>
  <div class="col-md-3">
    <select name="category" class="form-select">
      <option value="all">All Categories</option>
      <?php foreach($categories as $cat): ?>
        <option value="<?= htmlspecialchars($cat) ?>" <?= $cat === $categoryFilter ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3 col-md-2">
    <select name="sort" class="form-select">
      <option value="">Sort</option>
      <option value="price_asc" <?= $sort==='price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
      <option value="price_desc" <?= $sort==='price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
    </select>
  </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function(){
  const form = document.querySelector('form[data-filter-form="products-filter"]');
  const container = document.getElementById('productsContainer');
  if (!form || !container) return;

  function buildQuery() {
    const formData = new FormData(form);
    const params = new URLSearchParams();
    for (const [key, value] of formData.entries()) {
      if (value) params.append(key, value);
    }
    return params.toString();
  }

  async function fetchAndRender() {
    container.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
    try {
      const url = window.location.pathname + '?' + buildQuery() + '&ajax=1';
      const res = await fetch(url, { cache: 'no-store' });
      if (!res.ok) throw new Error('Network response was not ok');
      const data = await res.json();
      renderProducts(data);
    } catch (e) {
      console.error('Fetch error', e);
      container.innerHTML = '<div class="alert alert-danger">Error loading products. Please try again.</div>';
    }
  }

  function escapeHtml(s){ return (s===null||s===undefined)?'':String(s).replace(/[&<>"']/g, function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[c];}); }

  function renderProducts(items){
    if (!items || items.length === 0) { 
      container.innerHTML = '<div class="alert alert-warning">No products found.</div>'; 
      return; 
    }
    let html = '<div class="row g-4">';
    for (const p of items) {
      const name = escapeHtml(p.title || 'N/A');
      const desc = escapeHtml(p.description || '');
      const price = (typeof p.price !== 'undefined') ? Number(p.price).toFixed(2) : '0.00';
      const id = parseInt(p.id) || 0;
      const image = escapeHtml(p.image || '');
      const category = escapeHtml(p.category || '');
      const rating = (p.rating && p.rating.rate) ? Number(p.rating.rate).toFixed(1) : '';
      const productJson = JSON.stringify(p).replace(/'/g, '&apos;').replace(/"/g, '&quot;');
      html += `\n<div class="col-md-4">\n  <div class="card h-100 product-card" onclick='openProductModal(${productJson})'>\n    ${image?`<img src="${image}" class="card-img-top" alt="${name}" style="height: 250px; object-fit: contain; padding: 1rem;">` : ''}\n    <div class="card-body d-flex flex-column">\n      ${category?`<span class="badge bg-secondary mb-2 align-self-start">${category}</span>` : ''}\n      <h5 class="card-title mb-2" style="min-height: 3rem; font-size: 1rem;">${name}</h5>\n      <p class="card-text" style="color: #64748b; flex-grow: 1; font-size: 0.9rem; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">${desc.length>100?desc.substr(0,100)+'...':desc}</p>\n      <div class="mt-auto">\n        <div class="d-flex justify-content-between align-items-center">\n          <strong class="text-primary" style="font-size: 1.25rem;">$${price}</strong>\n          ${rating?`<span class="text-muted" style="font-size: 0.85rem;"><span class="text-warning">★</span> ${rating}</span>`:''}
        </div>\n      </div>\n    </div>\n  </div>\n</div>`;
    }
    html += '\n</div>';
    container.innerHTML = html;
  }

  form.querySelectorAll('select[name="category"], select[name="sort"]').forEach(function(el){ el.addEventListener('change', function(){ fetchAndRender(); }); });
  form.addEventListener('submit', function(ev){ ev.preventDefault(); fetchAndRender(); });
});
</script>

<div id="productsContainer">
<?php if (empty($products)): ?>
  <div class="alert alert-warning">No products available at the moment.</div>
<?php else: ?>
  <div class="row g-4">
  <?php foreach ($products as $product):
      $name = htmlspecialchars($product['title'] ?? 'N/A');
      $desc = htmlspecialchars($product['description'] ?? '');
      $price = number_format((float)($product['price'] ?? 0), 2);
      $id = (int)($product['id'] ?? 0);
      $image = htmlspecialchars($product['image'] ?? '');
      $category = htmlspecialchars($product['category'] ?? '');
      $rating = isset($product['rating']['rate']) ? number_format($product['rating']['rate'], 1) : '';
      $productJson = htmlspecialchars(json_encode($product), ENT_QUOTES);
  ?>
    <div class="col-md-4">
      <div class="card h-100 product-card" onclick='openProductModal(<?= $productJson ?>)'>
        <?php if ($image): ?>
        <img src="<?= $image ?>" class="card-img-top" alt="<?= $name ?>" style="height: 250px; object-fit: contain; padding: 1rem;">
        <?php endif; ?>
        <div class="card-body d-flex flex-column">
          <?php if ($category): ?>
          <span class="badge bg-secondary mb-2 align-self-start"><?= $category ?></span>
          <?php endif; ?>
          <h5 class="card-title mb-2" style="min-height: 3rem; font-size: 1rem;"><?= $name ?></h5>
          <p class="card-text" style="color: #64748b; flex-grow: 1; font-size: 0.9rem; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;"><?= strlen($desc) > 100 ? substr($desc, 0, 100) . '...' : $desc ?></p>
          <div class="mt-auto">
            <div class="d-flex justify-content-between align-items-center">
              <strong class="text-primary" style="font-size: 1.25rem;">$<?= $price ?></strong>
              <?php if ($rating): ?>
              <span class="text-muted" style="font-size: 0.85rem;">
                <span class="text-warning">★</span> <?= $rating ?>
              </span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
</div>

<div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="border-radius: 15px; border: none; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
      <div class="modal-header border-0" style="padding: 1.5rem 2rem;">
        <h5 class="modal-title fw-bold" id="productModalLabel" style="color: #1e293b;"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" style="padding: 0 2rem 2rem 2rem;">
        <div class="row g-4">
          <div class="col-md-5">
            <div class="bg-light rounded p-3" style="height: 100%; display: flex; align-items: center; justify-content: center;">
              <img id="modalProductImage" src="" alt="" class="img-fluid rounded" style="max-height: 350px; object-fit: contain; width: 100%;">
            </div>
          </div>
          <div class="col-md-7">
            <div class="mb-3">
              <span id="modalProductCategory" class="badge bg-primary mb-3" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;"></span>
              <h4 id="modalProductTitle" class="mb-3 fw-bold" style="color: #1e293b; font-size: 1.5rem;"></h4>
              <p id="modalProductDescription" class="text-muted mb-4" style="font-size: 0.95rem; line-height: 1.6;"></p>
            </div>
            <div class="mb-4 pb-3 border-bottom">
              <div class="d-flex align-items-center mb-3">
                <span class="text-warning me-2" style="font-size: 1.2rem;">★</span>
                <span id="modalProductRating" class="me-2 fw-semibold" style="color: #1e293b;"></span>
                <span id="modalProductRatingCount" class="text-muted" style="font-size: 0.9rem;"></span>
              </div>
              <h3 id="modalProductPrice" class="text-primary mb-0 fw-bold" style="font-size: 2rem;"></h3>
            </div>
            <?php 
              $isAdminView = isset($_SESSION['role']) && in_array($_SESSION['role'], ['staff_user','administrator','admin_sec']);
              $isGuestUser = isset($_SESSION['role']) && $_SESSION['role'] === 'guest_user';
              $isRegularUser = isset($_SESSION['role']) && $_SESSION['role'] === 'regular_user';
              if (!$isAdminView): 
            ?>
              <?php if ($isGuestUser): ?>
                <div class="alert alert-warning" role="alert">
                  <i class="fas fa-exclamation-triangle me-2"></i>
                  Please verify your email to add items to cart.
                </div>
              <?php elseif ($isRegularUser): ?>
              <div class="mb-4">
                <label class="form-label fw-bold mb-3" style="color: #1e293b; font-size: 1rem;">Quantity</label>
                <div class="quantity-selector d-flex align-items-center gap-3 mb-4">
                  <button type="button" class="btn btn-outline-primary quantity-btn" id="decreaseQty" style="transition: all 0.2s ease;">
                    <i class="fas fa-minus"></i>
                  </button>
                  <input type="number" id="modalQuantity" class="form-control text-center border-2" value="1" min="1" max="99" style="width: 100px; font-size: 1.3rem; font-weight: 700; border-color: #3b82f6;">
                  <button type="button" class="btn btn-outline-primary quantity-btn" id="increaseQty" style="transition: all 0.2s ease;">
                    <i class="fas fa-plus"></i>
                  </button>
                </div>
              </div>
              <button type="button" class="btn btn-primary w-100 btn-lg shadow" id="addToCartBtn" data-product-id="" style="border-radius: 10px; padding: 0.8rem; font-size: 1.1rem; font-weight: 600; transition: all 0.3s ease;">
                <span id="addToCartBtnText">
                  <i class="fas fa-shopping-cart me-2"></i>Add to Cart
                </span>
                <span id="addToCartBtnLoading" class="d-none">
                  <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Adding...
                </span>
              </button>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.product-card {
  cursor: pointer;
  transition: all 0.3s ease;
  border: 1px solid #e2e8f0;
}
.product-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 10px 25px rgba(0,0,0,0.1);
  border-color: #3b82f6;
}
.quantity-selector .quantity-btn {
  width: 45px;
  height: 45px;
  padding: 0;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
  border-width: 2px;
  font-weight: 600;
}
.quantity-selector .quantity-btn:hover {
  background: #3b82f6;
  border-color: #3b82f6;
  color: white;
  transform: scale(1.1);
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}
.quantity-selector .quantity-btn:active {
  transform: scale(0.95);
}
#addToCartBtn:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4) !important;
}
#addToCartBtn:active {
  transform: translateY(0);
}
.quantity-selector input::-webkit-outer-spin-button,
.quantity-selector input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
.quantity-selector input[type=number] {
  -moz-appearance: textfield;
}
</style>

<script>
let currentProduct = null;

function showCartToast(message, type = 'success') {
  const toast = document.getElementById('cartToast');
  const toastTitle = document.getElementById('toastTitle');
  const toastBody = document.getElementById('toastBody');
  const toastHeader = toast ? toast.querySelector('.toast-header') : null;

  if (!toast || !toastHeader || !toastBody || !toastTitle || typeof bootstrap === 'undefined') {
    alert(message);
    return;
  }

  toastHeader.className = 'toast-header';
  
  if (type === 'success') {
    toastHeader.classList.add('bg-success', 'text-white');
    toastTitle.innerHTML = '<i class="fas fa-check-circle me-2"></i>Success!';
  } else if (type === 'danger') {
    toastHeader.classList.add('bg-danger', 'text-white');
    toastTitle.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>Error!';
  } else if (type === 'warning') {
    toastHeader.classList.add('bg-warning', 'text-dark');
    toastTitle.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Warning!';
  } else {
    toastTitle.textContent = 'Notice';
  }
  
  toastBody.textContent = message;
  const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
  bsToast.show();
}

function parseRating(rawRating) {
  if (!rawRating) return null;
  if (typeof rawRating === 'object') return rawRating;
  if (typeof rawRating === 'string') {
    try {
      const parsed = JSON.parse(rawRating);
      return typeof parsed === 'object' ? parsed : null;
    } catch (e) {
      return null;
    }
  }
  return null;
}

function openProductModal(product) {
  currentProduct = product;
  const safeTitle = product.title || 'Product';
  const safeDesc = product.description || 'No description available.';
  const safeImage = product.image || 'https://via.placeholder.com/400x300?text=No+Image';
  const ratingData = parseRating(product.rating);

  document.getElementById('productModalLabel').textContent = safeTitle;
  document.getElementById('modalProductImage').src = safeImage;
  document.getElementById('modalProductImage').alt = safeTitle;
  document.getElementById('modalProductCategory').textContent = product.category || 'Product';
  document.getElementById('modalProductTitle').textContent = safeTitle;
  document.getElementById('modalProductDescription').textContent = safeDesc;
  document.getElementById('modalProductPrice').textContent = '$' + (Number(product.price) || 0).toFixed(2);
  
  if (ratingData && typeof ratingData.rate !== 'undefined') {
    document.getElementById('modalProductRating').textContent = Number(ratingData.rate).toFixed(1) + '/5';
    document.getElementById('modalProductRatingCount').textContent = ratingData.count ? '(' + ratingData.count + ' reviews)' : '';
  } else {
    document.getElementById('modalProductRating').textContent = 'N/A';
    document.getElementById('modalProductRatingCount').textContent = '';
  }
  
  const qtyEl = document.getElementById('modalQuantity');
  if (qtyEl) qtyEl.value = 1;
  const addBtn = document.getElementById('addToCartBtn');
  if (addBtn) addBtn.setAttribute('data-product-id', product.id);
  
  const modal = new bootstrap.Modal(document.getElementById('productModal'));
  modal.show();
}

document.addEventListener('DOMContentLoaded', function() {
  const decBtn = document.getElementById('decreaseQty');
  const incBtn = document.getElementById('increaseQty');
  const qtyInputEl = document.getElementById('modalQuantity');
  if (decBtn && qtyInputEl) {
    decBtn.addEventListener('click', function() {
      let val = parseInt(qtyInputEl.value) || 1;
      if (val > 1) qtyInputEl.value = val - 1;
    });
  }
  if (incBtn && qtyInputEl) {
    incBtn.addEventListener('click', function() {
      let val = parseInt(qtyInputEl.value) || 1;
      if (val < 99) qtyInputEl.value = val + 1;
    });
  }
  if (qtyInputEl) {
    qtyInputEl.addEventListener('input', function() {
      let val = parseInt(this.value);
      if (isNaN(val) || val < 1) this.value = 1;
      if (val > 99) this.value = 99;
    });
  }
  
  const addBtn = document.getElementById('addToCartBtn');
  if (addBtn) addBtn.addEventListener('click', function() {
    <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'regular_user'): ?>
    const productId = this.getAttribute('data-product-id');
    const quantity = parseInt((document.getElementById('modalQuantity')||{value:1}).value) || 1;
    
    const btnText = document.getElementById('addToCartBtnText');
    const btnLoading = document.getElementById('addToCartBtnLoading');
    if (btnText && btnLoading) {
      btnText.classList.add('d-none');
      btnLoading.classList.remove('d-none');
      this.disabled = true;
    }
    
    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('quantity', quantity);
    formData.append('product_name', currentProduct.title || 'Unknown Product');
    formData.append('product_price', currentProduct.price || 0);
    formData.append('product_image', currentProduct.image || '');
    formData.append('add_to_cart', '1');
    
    fetch('/SCP/products/cart.php', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    })
    .then(response => {
      if (!response.ok) {
        throw new Error('Failed to add to cart');
      }
      bootstrap.Modal.getInstance(document.getElementById('productModal')).hide();
      showCartToast(`${quantity} × ${currentProduct.title} added to cart!`, 'success');
    })
    .catch(error => {
      console.error('Error:', error);
      showCartToast('Failed to add item to cart. Please try again.', 'danger');
    })
    .finally(() => {
      if (btnText && btnLoading) {
        btnText.classList.remove('d-none');
        btnLoading.classList.add('d-none');
        addBtn.disabled = false;
      }
    });
    <?php else: ?>
    bootstrap.Modal.getInstance(document.getElementById('productModal')).hide();
    setTimeout(function() {
      const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
      loginModal.show();
    }, 300);
    <?php endif; ?>
  });
});
</script>

<?php include '../includes/footer.php'; ?>