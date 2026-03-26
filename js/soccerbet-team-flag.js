(function (Drupal, once, drupalSettings) {
  'use strict';

  Drupal.behaviors.soccerbetTeamFlag = {
    attach(context) {
      once('team-flag-picker', '#edit-team-flag', context).forEach(function (input) {
        const basePath = drupalSettings.soccerbet.flagBasePath;
        const preview  = document.getElementById('soccerbet-flag-live-preview');

        function update() {
          const code = input.value.trim().toUpperCase();
          if (!preview) return;
          if (code.length === 0) {
            preview.style.display = 'none';
            return;
          }
          const img = preview.querySelector('img');
          img.src = basePath + code + '.svg';
          img.alt = code;
          preview.style.display = '';
        }

        input.addEventListener('input', update);
        // Normalize to uppercase on blur.
        input.addEventListener('blur', function () {
          input.value = input.value.trim().toUpperCase();
        });

        update();
      });
    }
  };

})(Drupal, once, drupalSettings);
