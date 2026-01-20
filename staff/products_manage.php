<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
include '../includes/header.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['staff_user','administrator','admin_sec'], true)) {
  include '../includes/footer.php';
  exit();
}
?>

<div class="page-header d-flex align-items-center justify-content-between">
  <h2>Products</h2>
  <div class="d-flex align-items-center gap-2">
    <a class="btn btn-outline-secondary" href="/SCP/index.php">View Products</a>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal" onclick="openAddModal()">Add Product</button>
  </div>
</div>

<div id="productsPanel" class="card">
  <div class="card-body">
    <div id="productsAlert"></div>
    <div class="table-responsive">
      <table class="table align-middle" id="productsTable">
        <thead>
          <tr>
            <th style="width: 50px;"></th>
            <th style="width: 80px;">ID</th>
            <th>Title</th>
            <th style="width: 120px;">Price</th>
            <th style="width: 200px;">Category</th>
            <th style="width: 160px;">Actions</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="productModalTitle">Add Product</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="productForm">
          <input type="hidden" id="product_id">
          <div class="mb-3">
            <label class="form-label">Title</label>
            <input type="text" class="form-control" id="title" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Price</label>
            <input type="number" step="0.01" min="0" class="form-control" id="price" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Category</label>
            <input type="text" class="form-control" id="category">
          </div>
          <div class="mb-3">
            <label class="form-label">Image URL</label>
            <input type="url" class="form-control" id="image">
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea class="form-control" id="description" rows="3"></textarea>
          </div>
          <button type="submit" class="btn btn-primary w-100" id="saveBtn">Save</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
const apiBase = '/SCP/staff/products_api.php';

function alertBox(message, type='danger'){
  const box = `<div class="alert alert-${type}">${message}</div>`;
  document.getElementById('productsAlert').innerHTML = box;
  setTimeout(()=>{ document.getElementById('productsAlert').innerHTML = ''; }, 4000);
}

async function fetchJSON(url, options={}){
  const res = await fetch(url, options);
  const data = await res.json().catch(()=>({success:false,message:'Invalid JSON'}));
  return data;
}

async function loadProducts(){
  const tbody = document.querySelector('#productsTable tbody');
  tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Loading…</td></tr>';
  const data = await fetchJSON(`${apiBase}?action=list`);
  if(!data.success){
    tbody.innerHTML = '<tr><td colspan="6" class="text-danger">Failed to load products.</td></tr>';
    return;
  }
  const products = Array.isArray(data.products) ? data.products : [];
  if(products.length === 0){
    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No products found.</td></tr>';
    return;
  }
  tbody.innerHTML = '';
  for(const p of products){
    const tr = document.createElement('tr');
    tr.setAttribute('draggable', 'true');
    tr.setAttribute('data-product-id', p.id ?? '');
    tr.classList.add('draggable-row');
    tr.innerHTML = `
      <td class="drag-handle" style="cursor: move;">
        <i class="fas fa-grip-vertical text-muted"></i>
      </td>
      <td>${p.id ?? ''}</td>
      <td>${escapeHtml(p.title ?? '')}</td>
      <td>$${Number(p.price ?? 0).toFixed(2)}</td>
      <td>${escapeHtml(p.category ?? '')}</td>
      <td>
        <div class="d-grid gap-2">
          <button class="btn btn-primary btn-sm w-100" onclick='openEditModal(${JSON.stringify(p.id ?? '')})'>Edit</button>
          <button class="btn btn-danger btn-sm w-100" onclick='confirmDelete(${JSON.stringify(p.id ?? '')})'>Delete</button>
        </div>
      </td>`;
    tbody.appendChild(tr);
  }
  initializeDragAndDrop();
}

function escapeHtml(s){
  return String(s).replace(/[&<>"]+/g, c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;"}[c]));
}

function openAddModal(){
  document.getElementById('productModalTitle').textContent = 'Add Product';
  document.getElementById('product_id').value = '';
  document.getElementById('title').value = '';
  document.getElementById('price').value = '';
  document.getElementById('category').value = '';
  document.getElementById('image').value = '';
  document.getElementById('description').value = '';
}

async function openEditModal(id){
  const data = await fetchJSON(`${apiBase}?action=get&id=${encodeURIComponent(id)}`);
  if(!data.success){ alertBox('Failed to load product for edit'); return; }
  const p = data.product || {};
  document.getElementById('productModalTitle').textContent = 'Edit Product';
  document.getElementById('product_id').value = p.id || '';
  document.getElementById('title').value = p.title || '';
  document.getElementById('price').value = p.price || '';
  document.getElementById('category').value = p.category || '';
  document.getElementById('image').value = p.image || '';
  document.getElementById('description').value = p.description || '';
  const modal = new bootstrap.Modal(document.getElementById('productModal'));
  modal.show();
}

async function confirmDelete(id){
  if(!confirm('Delete this product?')) return;
  console.log('Deleting product ID:', id);
  const form = new URLSearchParams();
  form.set('action','delete');
  form.set('product_id', id);
  const data = await fetchJSON(apiBase, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: form.toString() });
  console.log('Delete response:', data);
  if(data.success){ alertBox('Product deleted', 'success'); loadProducts(); }
  else { alertBox(data.message || 'Delete failed'); }
}

const productForm = document.getElementById('productForm');
productForm.addEventListener('submit', async (e)=>{
  e.preventDefault();
  const id = document.getElementById('product_id').value.trim();
  const title = document.getElementById('title').value.trim();
  const price = document.getElementById('price').value.trim();
  const category = document.getElementById('category').value.trim();
  const image = document.getElementById('image').value.trim();
  const description = document.getElementById('description').value.trim();
  if(!title || Number(price) <= 0){ alertBox('Please provide a valid title and price'); return; }

  const form = new URLSearchParams();
  form.set('title', title);
  form.set('price', price);
  form.set('category', category);
  form.set('image', image);
  form.set('description', description);

  let action = 'add';
  if(id){ action = 'update'; form.set('product_id', id); }
  form.set('action', action);

  console.log('Submitting product:', action, form.toString());
  const data = await fetchJSON(apiBase, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: form.toString() });
  console.log('Submit response:', data);
  if(data.success){
    alertBox('Product saved', 'success');
    const modalEl = document.getElementById('productModal');
    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    modal.hide();
    loadProducts();
  } else {
    alertBox(data.message || 'Save failed');
  }
});

let draggedElement = null;

function initializeDragAndDrop() {
  const rows = document.querySelectorAll('#productsTable tbody tr.draggable-row');
  
  rows.forEach(row => {
    row.addEventListener('dragstart', function(e) {
      draggedElement = this;
      this.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/html', this.innerHTML);
    });
    
    row.addEventListener('dragend', function(e) {
      this.classList.remove('dragging');
      document.querySelectorAll('#productsTable tbody tr').forEach(r => {
        r.classList.remove('drag-over');
      });
    });
    
    row.addEventListener('dragover', function(e) {
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
      
      if (this === draggedElement) return;
      
      document.querySelectorAll('#productsTable tbody tr').forEach(r => {
        r.classList.remove('drag-over');
      });
      this.classList.add('drag-over');
    });
    
    row.addEventListener('drop', async function(e) {
      e.preventDefault();
      e.stopPropagation();
      
      if (this === draggedElement) return;
      
      const tbody = document.querySelector('#productsTable tbody');
      const allRows = Array.from(tbody.querySelectorAll('tr.draggable-row'));
      const draggedIndex = allRows.indexOf(draggedElement);
      const targetIndex = allRows.indexOf(this);
      
      if (draggedIndex < targetIndex) {
        this.parentNode.insertBefore(draggedElement, this.nextSibling);
      } else {
        this.parentNode.insertBefore(draggedElement, this);
      }
      
      this.classList.remove('drag-over');
      
      await saveProductOrder();
    });
  });
}

async function saveProductOrder() {
  const tbody = document.querySelector('#productsTable tbody');
  const rows = tbody.querySelectorAll('tr.draggable-row');
  const order = Array.from(rows).map(row => row.getAttribute('data-product-id'));
  
  const formData = new URLSearchParams();
  formData.set('action', 'reorder');
  formData.set('order', JSON.stringify(order));
  
  const data = await fetchJSON(apiBase, {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: formData.toString()
  });
  
  if (data.success) {
    alertBox('Product order updated', 'success');
  } else {
    alertBox(data.message || 'Failed to save order', 'danger');
  }
}

loadProducts();
</script>

<style>
.draggable-row {
  cursor: move;
  transition: all 0.2s ease;
}

.draggable-row.dragging {
  opacity: 0.5;
  background-color: #f8f9fa;
}

.draggable-row.drag-over {
  border-top: 3px solid #3b82f6;
  background-color: #eff6ff;
}

.drag-handle:hover {
  background-color: #f8f9fa;
}
</style>

<?php include '../includes/footer.php'; ?>
