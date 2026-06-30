
// Helpdesk Enterprise Admin UI v2 helper
// Adds the same A0-style search bar to list cards that do not already have one.
document.addEventListener('DOMContentLoaded', function(){
  const cards = document.querySelectorAll('.master-card, .assign-card, .kbc-card');
  cards.forEach(function(card){
    const table = card.querySelector('table');
    const header = card.querySelector('.master-card-header, .assign-card-header, .kbc-card-header');
    if(!table || !header) return;
    let search = card.querySelector('input[type="text"][id*="Search"], input[type="text"][id*="search"], .admin-v2-search');
    if(!search){
      const wrap = document.createElement('div');
      wrap.className = 'input-group admin-v2-search-wrap';
      wrap.innerHTML = '<span class="input-group-text"><i class="bi bi-search"></i></span><input type="text" class="form-control admin-v2-search" placeholder="Search...">';
      header.appendChild(wrap);
      search = wrap.querySelector('input');
    }
    if(search.dataset.adminV2Bound === '1') return;
    search.dataset.adminV2Bound = '1';
    search.addEventListener('input', function(){
      const keyword = this.value.toLowerCase().trim();
      table.querySelectorAll('tbody tr').forEach(function(row){
        row.style.display = row.innerText.toLowerCase().includes(keyword) ? '' : 'none';
      });
    });
  });
});
