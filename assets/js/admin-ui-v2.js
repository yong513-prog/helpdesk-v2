// Helpdesk Admin UI v2 mobile table helper.
// It derives mobile card labels from the desktop table header so labels never mismatch between pages.
document.addEventListener('DOMContentLoaded', function () {
  function normalize(text) {
    return (text || '').replace(/\s+/g, ' ').trim();
  }

  document.querySelectorAll('table.master-table, table.assign-table, table.hd-mobile-card-table, .kbc-card table').forEach(function (table) {
    table.classList.add('hd-mobile-card-table');

    var headers = Array.from(table.querySelectorAll('thead th')).map(function (th) {
      return normalize(th.textContent);
    });

    table.querySelectorAll('tbody tr').forEach(function (row) {
      Array.from(row.children).forEach(function (cell, index) {
        if (cell.tagName && cell.tagName.toLowerCase() === 'td') {
          var label = headers[index] || '';
          cell.setAttribute('data-label', label);
        }
      });
    });
  });
});
