(function () {
    function initTableSort(table) {
        const headers = table.querySelectorAll('thead th');

        headers.forEach(function (th, colIndex) {
            th.setAttribute('tabindex', '0');
            th.setAttribute('aria-sort', 'none');
            th.style.cursor = 'pointer';

            function doSort() {
                const asc = th.getAttribute('aria-sort')
                    !== 'ascending';
                headers.forEach(function (h) {h.setAttribute('aria-sort', 'none'); });
                th.setAttribute('aria-sort', asc ? 'ascending' : 'descending');

                const tbody = table.querySelector('tbody');
                const rows = Array.from(tbody.querySelectorAll('tr'));

                rows.sort(function (a, b) {
                    const aCell = a.cells[colIndex];
                    const bCell = b.cells[colIndex];
                    const aRaw = aCell ? aCell.dataset.sortValue : null;
                    const bRaw = bCell ? bCell.dataset.sortValue : null;

                    if (aRaw !== null && aRaw !== undefined && bRaw !== null &&
                        bRaw !== undefined) {
                        const aNum = parseFloat(aRaw);
                        const bNum = parseFloat(bRaw);
                        if (!isNaN(aNum) && !isNaN(bNum)) {
                            return asc ? aNum - bNum : bNum - aNum;
                        }
                        return asc ? aRaw.localeCompare(bRaw) :
                            bRaw.localeCompare(aRaw);
                    }

                    const aText = aCell ? aCell.textContent.trim() : '';
                    const bText = bCell ? bCell.textContent.trim() : '';
                    return asc ? aText.localeCompare(bText) :
                        bText.localeCompare(aText);
                });


                rows.forEach(function (row) {
                    tbody.appendChild(row); });
            }

            th.addEventListener('click', doSort);
            th.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    doSort();
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function
        () {
        document.querySelectorAll('.faubox-filetable').forEach(initTableSort);
    });
}());