// Suggest an event: a poster chosen on a phone is often bigger than the site takes (5 MB), so a large one is made
// smaller here before it is sent, to about 2,500 pixels on its long side as a JPEG, which is still easily read.
// Without this script, or where the browser cannot do it, the file is sent as it is and the site judges it.
(function () {
  'use strict';

  var LONGEST = 2500;
  var QUALITY = 0.85;
  // A JPEG already this small and no wider than LONGEST is sent as it is.
  var SMALL = 1.5 * 1024 * 1024;

  var input = document.getElementById('s-poster');
  var form = input && input.form;

  if (!input || !form || !window.DataTransfer || !window.createImageBitmap || !HTMLCanvasElement.prototype.toBlob) {
    return;
  }

  // While a poster is being made smaller, the form waits for it.
  var working = null;

  input.addEventListener('change', function () {
    var file = input.files && input.files[0];

    if (!file || !/^image\/(jpeg|png|webp)$/.test(file.type)) {
      return;
    }

    working = shrink(file).then(function (smaller) {
      if (smaller && smaller.size < file.size) {
        var chosen = new DataTransfer();
        chosen.items.add(smaller);
        input.files = chosen.files;
      }
    }).catch(function () {
      // The poster is sent as it was chosen.
    }).then(function () {
      working = null;
    });
  });

  form.addEventListener('submit', function (event) {
    if (working) {
      event.preventDefault();
      working.then(function () {
        form.requestSubmit ? form.requestSubmit() : form.submit();
      });
    }
  });

  function shrink(file) {
    return createImageBitmap(file).then(function (picture) {
      var scale = Math.min(1, LONGEST / Math.max(picture.width, picture.height));

      if (scale === 1 && file.type === 'image/jpeg' && file.size <= SMALL) {
        return null;
      }

      var canvas = document.createElement('canvas');
      canvas.width = Math.round(picture.width * scale);
      canvas.height = Math.round(picture.height * scale);
      canvas.getContext('2d').drawImage(picture, 0, 0, canvas.width, canvas.height);

      return new Promise(function (resolve) {
        canvas.toBlob(function (blob) {
          resolve(blob ? new File([blob], file.name.replace(/\.[a-z0-9]+$/i, '') + '.jpg', { type: 'image/jpeg' }) : null);
        }, 'image/jpeg', QUALITY);
      });
    });
  }
})();
