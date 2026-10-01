(function () {
  var peso = function (n) {
    return '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };

  document.addEventListener('submit', function (e) {
    var msg = e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) {
      e.preventDefault();
    }
  });

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-step]');
    if (btn) {
      var input = btn.parentElement.querySelector('input');
      var step = parseInt(btn.getAttribute('data-step'), 10);
      var min = input.min === '' ? 0 : parseInt(input.min, 10);
      var max = input.max === '' ? Infinity : parseInt(input.max, 10);
      var next = (parseInt(input.value, 10) || 0) + step;
      input.value = Math.max(min, Math.min(max, next));
      input.dispatchEvent(new Event('input', { bubbles: true }));
    }
    var toggle = e.target.closest('[data-toggle-sidebar]');
    if (toggle) {
      document.querySelector('.sidebar').classList.toggle('open');
    }
    var printBtn = e.target.closest('[data-print]');
    if (printBtn) {
      window.print();
    }
  });

  document.querySelectorAll('input[data-filter]').forEach(function (box) {
    var rows = document.querySelectorAll(box.getAttribute('data-filter'));
    box.addEventListener('input', function () {
      var q = box.value.trim().toLowerCase();
      rows.forEach(function (row) {
        var hay = (row.getAttribute('data-name') || row.textContent).toLowerCase();
        row.style.display = hay.indexOf(q) === -1 ? 'none' : '';
      });
    });
  });

  var pos = document.getElementById('pos');
  if (pos) {
    var totalEl = document.getElementById('pos-total');
    var countEl = document.getElementById('pos-count');
    var submit = document.getElementById('pos-submit');
    var customer = document.getElementById('pos-customer');
    var walkin = document.getElementById('pos-walkin');
    var listaOpt = document.getElementById('pay-lista');
    var listaInput = listaOpt.querySelector('input');
    var listaNote = document.getElementById('pay-lista-note');
    var cashIn = document.getElementById('pos-cash');
    var changeEl = document.getElementById('pos-change');
    var total = 0;

    var recalc = function () {
      total = 0;
      var count = 0;
      pos.querySelectorAll('.pos-row').forEach(function (row) {
        var qtyInput = row.querySelector('input[type=number]');
        var qty = parseInt(qtyInput.value, 10) || 0;
        total += qty * parseFloat(row.getAttribute('data-price'));
        count += qty;
        row.classList.toggle('is-picked', qty > 0);
      });
      totalEl.textContent = peso(total);
      countEl.textContent = count + (count === 1 ? ' item' : ' items');
      submit.disabled = count === 0;
      updateChange();
      checkCredit();
    };

    var updateChange = function () {
      if (!cashIn) { return; }
      var given = parseFloat(cashIn.value);
      if (isNaN(given)) { changeEl.textContent = ''; return; }
      var change = given - total;
      changeEl.textContent = change >= 0 ? 'Change: ' + peso(change) : 'Short by ' + peso(-change);
    };

    var checkCredit = function () {
      var opt = customer.options[customer.selectedIndex];
      var hasList = opt && opt.getAttribute('data-credit') !== null && opt.getAttribute('data-credit') !== '';
      listaInput.disabled = !hasList;
      listaOpt.classList.toggle('is-disabled', !hasList);
      if (!hasList) {
        listaNote.textContent = customer.value ? 'This customer has no active suki list.' : 'Pick a registered customer to use the suki list.';
        if (listaInput.checked) { pos.querySelector('#pay-cash input').checked = true; }
      } else {
        var credit = parseFloat(opt.getAttribute('data-credit'));
        listaNote.textContent = peso(credit) + ' available' + (total > credit ? ' (not enough for this sale)' : '');
      }
      walkin.disabled = !!customer.value;
      if (customer.value) { walkin.value = ''; }
    };

    pos.addEventListener('input', recalc);
    customer.addEventListener('change', recalc);
    if (cashIn) { cashIn.addEventListener('input', updateChange); }
    recalc();
  }

  document.querySelectorAll('canvas[data-chart]').forEach(function (canvas) {
    if (!window.Chart) { return; }
    var labels = JSON.parse(canvas.getAttribute('data-labels'));
    var values = JSON.parse(canvas.getAttribute('data-values'));
    new Chart(canvas, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{ data: values, backgroundColor: '#2b4fc2', borderRadius: 4, maxBarThickness: 46 }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: function (c) { return peso(c.parsed.y); } } }
        },
        scales: {
          y: { beginAtZero: true, ticks: { callback: function (v) { return '₱' + v.toLocaleString('en-PH'); } }, grid: { color: '#e3e8f6' } },
          x: { grid: { display: false } }
        }
      }
    });
  });
})();
