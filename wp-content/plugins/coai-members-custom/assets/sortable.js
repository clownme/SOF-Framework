console.log("✅ sortable.js loaded");
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('table.sortable th').forEach(th => {
    th.addEventListener('click', () => {
      const table = th.closest('table');
      const tbody = table.querySelector('tbody');
      const index = Array.from(th.parentNode.children).indexOf(th);
      const rows = Array.from(tbody.querySelectorAll('tr'));

      const isAsc = !th.classList.contains('sorted-asc');
      table.querySelectorAll('th').forEach(th => th.classList.remove('sorted-asc', 'sorted-desc'));
      th.classList.add(isAsc ? 'sorted-asc' : 'sorted-desc');

      const sorted = rows.sort((a, b) => {
        const A = a.children[index].textContent.trim();
        const B = b.children[index].textContent.trim();

        const aNum = parseFloat(A);
        const bNum = parseFloat(B);
        const isNumeric = !isNaN(aNum) && !isNaN(bNum);

        if (isNumeric) {
          return isAsc ? aNum - bNum : bNum - aNum;
        } else {
          return isAsc ? A.localeCompare(B) : B.localeCompare(A);
        }
      });

      tbody.innerHTML = '';
      sorted.forEach(row => tbody.appendChild(row));
    });
  });
});