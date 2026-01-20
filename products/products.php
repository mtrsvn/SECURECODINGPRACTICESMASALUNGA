<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Fetch products from Fake Store API
$api_url = 'https://fakestoreapi.com/products';
$products = [];

$response = @file_get_contents($api_url);
if ($response !== false) {
    $products = json_decode($response, true);
} else {
    // Fallback: try using cURL if file_get_contents fails
    if (function_exists('curl_init')) {
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);
        if ($response !== false) {
            $products = json_decode($response, true);
        }
    }
    
}

// Handle search/filter inputs (GET)
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$categoryFilter = isset($_GET['category']) ? trim($_GET['category']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : '';

$categories = [];
foreach ($products as $p) {
  if (!empty($p['category'])) $categories[] = $p['category'];
}
$categories = array_unique($categories);
sort($categories);

// Apply filters to products array
if ($q !== '' || ($categoryFilter !== '' && $categoryFilter !== 'all')) {
  $products = array_filter($products, function($p) use ($q, $categoryFilter) {
    $ok = true;
    if ($q !== '') {
      $ok = stripos($p['title'] ?? '', $q) !== false;
    }
    if ($ok && $categoryFilter !== '' && $categoryFilter !== 'all') {
      $ok = ($p['category'] ?? '') === $categoryFilter;
    }
    return $ok;
  });
  $products = array_values($products);
}

// Apply sorting (independent of filters)
if ($sort === 'price_asc') {
  usort($products, function($a, $b){ return ($a['price'] ?? 0) <=> ($b['price'] ?? 0); });
} elseif ($sort === 'price_desc') {
  usort($products, function($a, $b){ return ($b['price'] ?? 0) <=> ($a['price'] ?? 0); });
}

// If requested by AJAX, return JSON of products
if (isset($_GET['ajax'])) {
  header('Content-Type: application/json');
  echo json_encode(array_values($products));
  exit;
}

include '../includes/header.php';
?>

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
    const qs = buildQuery();
    container.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
    try {
      const url = window.location.pathname + '?' + qs + '&ajax=1';
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
    if (!items || items.length === 0) { container.innerHTML = '<div class="alert alert-warning">No products found.</div>'; return; }
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
  <div class="alert alert-warning">Unable to load products from the API. Please try again later.</div>
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

<!-- Product Details Modal -->
<div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="productModalLabel"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-5">
            <img id="modalProductImage" src="" alt="" class="img-fluid rounded" style="max-height: 300px; object-fit: contain; width: 100%;">
          </div>
          <div class="col-md-7">
            <div class="mb-3">
              <span id="modalProductCategory" class="badge bg-secondary mb-2"></span>
              <h4 id="modalProductTitle" class="mb-3"></h4>
              <p id="modalProductDescription" class="text-muted mb-3" style="font-size: 0.95rem;"></p>
            </div>
            <div class="mb-4">
              <div class="d-flex align-items-center mb-2">
                <span class="text-warning me-2">★</span>
                <span id="modalProductRating" class="me-2"></span>
                <span id="modalProductRatingCount" class="text-muted" style="font-size: 0.9rem;"></span>
              </div>
              <h3 id="modalProductPrice" class="text-primary mb-4"></h3>
            </div>
            <div class="mb-4">
              <label class="form-label fw-bold">Quantity</label>
              <div class="quantity-selector d-flex align-items-center gap-3">
                <button type="button" class="btn btn-outline-secondary quantity-btn" id="decreaseQty">
                  <i class="fas fa-minus"></i>
                </button>
                <input type="number" id="modalQuantity" class="form-control text-center" value="1" min="1" max="99" style="width: 80px; font-size: 1.1rem; font-weight: 600;">
                <button type="button" class="btn btn-outline-secondary quantity-btn" id="increaseQty">
                  <i class="fas fa-plus"></i>
                </button>
              </div>
            </div>
            <button type="button" class="btn btn-primary w-100 btn-lg" id="addToCartBtn" data-product-id="">
              <i class="fas fa-shopping-cart me-2"></i>Add to Cart
            </button>
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
  width: 40px;
  height: 40px;
  padding: 0;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}
.quantity-selector .quantity-btn:hover {
  background: #3b82f6;
  border-color: #3b82f6;
  color: white;
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

function openProductModal(product) {
  currentProduct = product;
  document.getElementById('productModalLabel').textContent = product.title;
  document.getElementById('modalProductImage').src = product.image;
  document.getElementById('modalProductImage').alt = product.title;
  document.getElementById('modalProductCategory').textContent = product.category || 'Product';
  document.getElementById('modalProductTitle').textContent = product.title;
  document.getElementById('modalProductDescription').textContent = product.description || 'No description available.';
  document.getElementById('modalProductPrice').textContent = '$' + (product.price || 0).toFixed(2);
  
  if (product.rating) {
    document.getElementById('modalProductRating').textContent = product.rating.rate + '/5';
    document.getElementById('modalProductRatingCount').textContent = '(' + product.rating.count + ' reviews)';
  } else {
    document.getElementById('modalProductRating').textContent = 'N/A';
    document.getElementById('modalProductRatingCount').textContent = '';
  }
  
  document.getElementById('modalQuantity').value = 1;
  document.getElementById('addToCartBtn').setAttribute('data-product-id', product.id);
  
  const modal = new bootstrap.Modal(document.getElementById('productModal'));
  modal.show();
}

document.addEventListener('DOMContentLoaded', function() {
  // Quantity controls
  document.getElementById('decreaseQty').addEventListener('click', function() {
    const qtyInput = document.getElementById('modalQuantity');
    let val = parseInt(qtyInput.value) || 1;
    if (val > 1) {
      qtyInput.value = val - 1;
    }
  });
  
  document.getElementById('increaseQty').addEventListener('click', function() {
    const qtyInput = document.getElementById('modalQuantity');
    let val = parseInt(qtyInput.value) || 1;
    if (val < 99) {
      qtyInput.value = val + 1;
    }
  });
  
  // Validate quantity input
  document.getElementById('modalQuantity').addEventListener('input', function() {
    let val = parseInt(this.value);
    if (isNaN(val) || val < 1) this.value = 1;
    if (val > 99) this.value = 99;
  });
  
  // Add to cart functionality
  document.getElementById('addToCartBtn').addEventListener('click', function() {
    <?php if (isset($_SESSION['user_id'])): ?>
    const productId = this.getAttribute('data-product-id');
    const quantity = parseInt(document.getElementById('modalQuantity').value) || 1;
    
    // Create FormData for AJAX submission
    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('quantity', quantity);
    formData.append('product_name', currentProduct.title || 'Unknown Product');
    formData.append('product_price', currentProduct.price || 0);
    formData.append('product_image', currentProduct.image || '');
    formData.append('add_to_cart', '1');
    
    // Send AJAX request
    fetch('/SCP/products/cart.php', {
      method: 'POST',
      body: formData
    })
    .then(response => {
      // Close the modal
      bootstrap.Modal.getInstance(document.getElementById('productModal')).hide();
      // Show success message
      showToast('Item added to cart!', 'success');
    })
    .catch(error => {
      console.error('Error:', error);
      showToast('Failed to add item to cart', 'danger');
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