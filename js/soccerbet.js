/**
 * @file
 * Soccer Bet – Frontend-JavaScript.
 * Tipp-Formular: Live-Validierung und Auto-Fokus.
 */

(function ($, Drupal, once) {
  'use strict';

  /**
   * Tipp-Formular: Beim Eingeben in Tipp1 → Fokus springt automatisch zu Tipp2.
   */
  Drupal.behaviors.soccerbetTippFocus = {
    attach(context) {
      once('soccerbet-tipp-focus', '.soccerbet-tipp-input', context).forEach((input) => {
        input.addEventListener('input', function () {
          if (this.value.length >= 2 || (this.value.length === 1 && parseInt(this.value) > 9)) {
            // Nächstes Input im selben Spiel-Fieldset fokussieren
            const fieldset = this.closest('.soccerbet-game');
            if (!fieldset) return;
            const inputs = fieldset.querySelectorAll('.soccerbet-tipp-input:not([disabled])');
            const idx = Array.from(inputs).indexOf(this);
            if (idx === 0 && inputs[1]) {
              inputs[1].focus();
              inputs[1].select();
            }
          }
        });
      });
    },
  };

  /**
   * Zeige verbleibende Zeit bis zur Tipp-Sperre an.
   */
  Drupal.behaviors.soccerbetCountdown = {
    attach(context) {
      once('soccerbet-countdown', '[data-kickoff]', context).forEach((el) => {
        const kickoff    = parseInt(el.dataset.kickoff, 10) * 1000;
        const lockBefore = (parseInt(el.dataset.lockMinutes, 10) || 0) * 60 * 1000;
        const deadline   = kickoff - lockBefore;

        function update() {
          const remaining = deadline - Date.now();
          if (remaining <= 0) {
            el.textContent = Drupal.t('Tipp geschlossen');
            el.classList.add('countdown--closed');
            return;
          }
          const hours   = Math.floor(remaining / 3600000);
          const minutes = Math.floor((remaining % 3600000) / 60000);
          const seconds = Math.floor((remaining % 60000) / 1000);
          el.textContent = hours > 0
            ? Drupal.t('@h h @m min', { '@h': hours, '@m': minutes })
            : Drupal.t('@m min @s s', { '@m': minutes, '@s': seconds });
          setTimeout(update, 1000);
        }
        update();
      });
    },
  };

  /**
   * Unsaved-changes-Warnung beim Verlassen der Seite.
   */
  Drupal.behaviors.soccerbetUnsavedWarning = {
    attach(context) {
      const form = once('soccerbet-unsaved', '#soccerbet-place-bets-form', context)[0];
      if (!form) return;

      let dirty = false;
      form.addEventListener('change', () => { dirty = true; });
      form.addEventListener('submit', () => { dirty = false; });

      window.addEventListener('beforeunload', (e) => {
        if (dirty) {
          e.preventDefault();
          e.returnValue = '';
        }
      });
    },
  };

})(jQuery, Drupal, once);
