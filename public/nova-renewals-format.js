/**
 * Force Italian number format (19.500,00) for all metrics on the Renewals resource,
 * regardless of the UI language.
 */
(function () {
  if (typeof Nova === 'undefined') return;

  var originalFormatNumber = Nova.formatNumber.bind(Nova);

  Nova.formatNumber = function (number, format) {
    var path = (window.location.pathname || '');
    if (path.indexOf('renewals') !== -1) {
      var meta = document.querySelector('meta[name="locale"]');
      if (meta) {
        var saved = meta.getAttribute('content');
        meta.setAttribute('content', 'it');
        try {
          return originalFormatNumber(number, format);
        } finally {
          meta.setAttribute('content', saved);
        }
      }
    }
    return originalFormatNumber(number, format);
  };
})();
