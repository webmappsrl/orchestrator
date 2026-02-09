/**
 * Force Italian number format (141.500,00) for all metrics on the Renewals resource,
 * regardless of the UI language. Matches the "valore contratto" column format in the index.
 */
(function () {
  function isRenewalsPage() {
    var path = (window.location.pathname || '');
    return path.indexOf('renewals') !== -1;
  }

  /**
   * Format number in Italian style: dot for thousands, comma for decimals (e.g. 141.500,00)
   */
  function formatItalianNumber(value, format) {
    var n = parseFloat(value);
    if (isNaN(n)) {
      return typeof value === 'string' ? value : String(value);
    }
    var decimals = 2;
    if (format && (typeof format === 'string' && format.indexOf('0.00') !== -1)) {
      decimals = 2;
    }
    var fixed = n.toFixed(decimals);
    var parts = fixed.split('.');
    var intPart = parts[0].replace(/^-?/, '') || '0';
    var neg = n < 0 ? '-' : '';
    var withDots = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return neg + withDots + ',' + parts[1];
  }

  function patchNova() {
    if (typeof Nova === 'undefined' || typeof Nova.formatNumber !== 'function') {
      return;
    }

    var originalFormatNumber = Nova.formatNumber.bind(Nova);

    Nova.formatNumber = function (number, format) {
      if (isRenewalsPage()) {
        return formatItalianNumber(number, format);
      }
      return originalFormatNumber(number, format);
    };
  }

  var retries = 0;
  function run() {
    if (typeof Nova !== 'undefined' && typeof Nova.formatNumber === 'function') {
      patchNova();
      return;
    }
    if (retries < 20) {
      retries += 1;
      setTimeout(run, 50);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
})();
