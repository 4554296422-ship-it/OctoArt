(function () {
  function formatPhone(value) {
    var digits = String(value || '').replace(/\D/g, '').slice(0, 11);
    if (!digits) return '';
    if (digits.length <= 2) return '(' + digits;
    return '(' + digits.slice(0, 2) + ') ' + digits.slice(2);
  }
  function setupPhone(input) {
    if (!input || input.dataset.phoneMaskReady === '1') return;
    input.dataset.phoneMaskReady = '1';
    input.type = 'tel';
    input.inputMode = 'numeric';
    input.autocomplete = 'tel';
    input.maxLength = 15;
    input.placeholder = '(48) 984490605';
    input.value = formatPhone(input.value);
    input.addEventListener('input', function () {
      var before = input.value;
      var pos = input.selectionStart || before.length;
      var digitsBefore = before.slice(0, pos).replace(/\D/g, '').length;
      input.value = formatPhone(before);
      var newPos = 0, seen = 0;
      while (newPos < input.value.length && seen < digitsBefore) {
        if (/\d/.test(input.value.charAt(newPos))) seen++;
        newPos++;
      }
      if (digitsBefore > 0 && newPos < input.value.length && input.value.charAt(newPos) === ' ') newPos++;
      try { input.setSelectionRange(newPos, newPos); } catch (e) {}
    });
  }
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('input[name="telefone"]').forEach(setupPhone);
  });
})();
