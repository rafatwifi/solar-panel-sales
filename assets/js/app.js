(function () {
  var cat = document.querySelector('#category');
  function syncCat() {
    if (!cat) return;
    var value = cat.value;
    document.querySelectorAll('[data-show-for]').forEach(function (el) {
      var allowed = el.getAttribute('data-show-for').split(',');
      var show = allowed.indexOf(value) !== -1;
      el.hidden = !show;
      var fields = el.querySelectorAll('input, select, textarea');
      for (var i = 0; i < fields.length; i++) {
        fields[i].disabled = !show;
      }
    });
  }
  if (cat) {
    cat.addEventListener('change', syncCat);
    syncCat();
  }

  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (event) {
      if (!window.confirm(el.getAttribute('data-confirm'))) {
        event.preventDefault();
      }
    });
  });

  var file = document.querySelector('#image');
  if (file) {
    file.addEventListener('change', function () {
      var img = document.querySelector('#imagePreview');
      if (!img || !file.files || !file.files[0]) return;
      img.src = URL.createObjectURL(file.files[0]);
      img.hidden = false;
    });
  }

  var links = document.querySelectorAll('.nav a');
  links.forEach(function (link) {
    link.addEventListener('click', function () {
      var box = document.querySelector('#navToggle');
      if (box) box.checked = false;
    });
  });
})();

function printQuote(mode) {
  document.body.classList.toggle('print-customer', mode === 'customer');
  window.print();
}
window.addEventListener('afterprint', function () {
  document.body.classList.remove('print-customer');
});
