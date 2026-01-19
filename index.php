<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

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

include 'includes/header.php';
?>

<div class="page-header">
  <h2>Products</h2>
  <?php if(isset($_SESSION['username'])): ?>
    <p style="color: #64748b; margin-top: 0.5rem;">Welcome back, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>!</p>
  <?php endif; ?>
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
      html += `\n<div class="col-md-4">\n  <div class="card h-100">\n    ${image?`<img src="${image}" class="card-img-top" alt="${name}" style="height: 200px; object-fit: contain; padding: 1rem; background: #f8fafc;">` : ''}\n    <div class="card-body d-flex flex-column">\n      ${category?`<span class="badge bg-secondary mb-2" style="width: fit-content;">${category}</span>` : ''}\n      <h5 class="card-title mb-2">${name}</h5>\n      <p class="card-text" style="color: #64748b; flex-grow: 1; font-size: 0.9rem;">${desc.length>100?desc.substr(0,100)+'...':desc}</p>\n      <p class="card-text mb-3"><strong style="font-size: 1.25rem;">$${price}</strong></p>\n      ${ (window.SCP && window.SCP.user_id) ? `
      <form method="post" action="/SCP/products/cart.php" class="d-flex gap-2">\n        <input type="hidden" name="product_id" value="${id}">\n        <input type="number" name="quantity" min="1" value="1" class="form-control" style="max-width: 80px;">\n        <button type="submit" name="add_to_cart" value="1" class="btn btn-primary flex-grow-1">Add to Cart</button>\n      </form>` : `\n      <button class="btn btn-secondary w-100" onclick="alert('Please login to add items to cart')">Login to Purchase</button>` }
    </div>\n  </div>\n</div>`;
    }
    html += '\n</div>';
    container.innerHTML = html;
  }

  // Attach change listeners to selects
  form.querySelectorAll('select[name="category"], select[name="sort"]').forEach(function(el){
    el.addEventListener('change', function(){ fetchAndRender(); });
  });

  // Intercept form submit to do AJAX
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
  ?>
    <div class="col-md-4">
      <div class="card h-100">
        <?php if ($image): ?>
        <img src="<?= $image ?>" class="card-img-top" alt="<?= $name ?>" style="height: 200px; object-fit: contain; padding: 1rem; background: #f8fafc;">
        <?php endif; ?>
        <div class="card-body d-flex flex-column">
          <?php if ($category): ?>
          <span class="badge bg-secondary mb-2" style="width: fit-content;"><?= $category ?></span>
          <?php endif; ?>
          <h5 class="card-title mb-2"><?= $name ?></h5>
          <p class="card-text" style="color: #64748b; flex-grow: 1; font-size: 0.9rem;"><?= strlen($desc) > 100 ? substr($desc, 0, 100) . '...' : $desc ?></p>
          <p class="card-text mb-3"><strong style="font-size: 1.25rem;">$<?= $price ?></strong></p>
          <?php if (isset($_SESSION['user_id'])): ?>
          <form method="post" action="/SCP/products/cart.php" class="d-flex gap-2">
            <input type="hidden" name="product_id" value="<?= $id ?>">
            <input type="number" name="quantity" min="1" value="1" class="form-control" style="max-width: 80px;">
            <button type="submit" name="add_to_cart" value="1" class="btn btn-primary flex-grow-1">Add to Cart</button>
          </form>
          <?php else: ?>
          <button class="btn btn-secondary w-100" onclick="alert('Please login to add items to cart')">Login to Purchase</button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>