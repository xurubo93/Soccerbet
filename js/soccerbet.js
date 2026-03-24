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
   * KO-Runden: Aufsteiger-Dropdown nur bei Unentschieden anzeigen.
   */
  Drupal.behaviors.soccerbetKoWinner = {
    attach(context) {
      once('soccerbet-ko-winner', '.soccerbet-winner-select[data-game-id]', context).forEach((select) => {
        const gameId  = select.dataset.gameId;
        const fieldset = select.closest('.soccerbet-game');
        if (!fieldset) return;
        const tipp1   = fieldset.querySelector(`[name="tipp1_${gameId}"]`);
        const tipp2   = fieldset.querySelector(`[name="tipp2_${gameId}"]`);
        const wrapper = select.closest('.soccerbet-winner-wrap');
        if (!tipp1 || !tipp2 || !wrapper) return;

        function update() {
          const v1 = tipp1.value.trim();
          const v2 = tipp2.value.trim();
          const isDraw = v1 !== '' && v2 !== '' && parseInt(v1, 10) === parseInt(v2, 10);
          wrapper.style.display = isDraw ? '' : 'none';
        }

        tipp1.addEventListener('input', update);
        tipp2.addEventListener('input', update);
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

  /**
   * Live-Menüpunkt: pulsierender Dot wenn ein Spiel live ist.
   * Läuft auf allen Soccerbet-Seiten außer der Live-Seite selbst.
   * Prüft beim Seitenload und danach jede Minute erneut.
   */
  Drupal.behaviors.soccerbetLiveMenuDot = {
    attach(context) {
      once('soccerbet-live-menu', 'body', context).forEach(function () {
        if (document.querySelector('#soccerbet-live-root')) return;

        const url = drupalSettings?.soccerbet?.liveJsonUrl;
        if (!url) return;

        function checkLive() {
          fetch(url, { headers: { Accept: 'application/json' } })
            .then(r => r.ok ? r.json() : null)
            .then(data => {
              setLiveMenuDot(!!(data && data.is_live));
            })
            .catch(() => {});
        }

        checkLive();
        setInterval(checkLive, 60000);
      });
    },
  };

  /**
   * Rangliste Tabs: Rangliste ↔ Bonus umschalten.
   */
  Drupal.behaviors.soccerbetStandingsTabs = {
    attach(context) {
      once('soccerbet-standings-tabs', '.soccerbet-standings__tabs', context).forEach(function (nav) {
        nav.addEventListener('click', function (e) {
          const tab = e.target.closest('.soccerbet-standings__tab');
          if (!tab) return;
          e.preventDefault();

          const tabId = tab.dataset.tab;
          nav.querySelectorAll('.soccerbet-standings__tab').forEach(t => {
            t.classList.toggle('soccerbet-standings__tab--active', t.dataset.tab === tabId);
          });

          const standings = nav.closest('.soccerbet-standings') || document;
          standings.querySelectorAll('.soccerbet-standings__tab-content').forEach(panel => {
            panel.hidden = panel.id !== 'tab-' + tabId;
          });
        });
      });
    },
  };

  /**
   * Ranglisten-Verlauf: AJAX-Navigation (kein Page-Reload, kein Scroll-Reset).
   */
  Drupal.behaviors.soccerbetStepsAjax = {
    attach(context) {
      once('soccerbet-steps-ajax', '.soccerbet-steps', context).forEach(function (nav) {
        nav.addEventListener('click', function (e) {
          const btn = e.target.closest('.soccerbet-steps__btn');
          if (!btn) return;
          e.preventDefault();

          const url      = btn.href;
          const standings = document.querySelector('.soccerbet-standings');
          if (!standings) return;

          nav.style.opacity       = '0.5';
          nav.style.pointerEvents = 'none';

          fetch(url)
            .then(r => r.text())
            .then(html => {
              const doc = new DOMParser().parseFromString(html, 'text/html');
              const fresh = doc.querySelector('.soccerbet-standings');
              if (fresh) {
                standings.replaceWith(fresh);
                history.pushState(null, '', url);
                Drupal.attachBehaviors(fresh, drupalSettings);
              }
            })
            .catch(() => { window.location.href = url; });
        });
      });
    },
  };

  function setLiveMenuDot(isLive) {
    const menuLink = document.querySelector('a.menu-item--live');
    if (!menuLink) return;
    menuLink.classList.toggle('soccerbet-live-active', isLive);
    let dot = menuLink.querySelector('.soccerbet-menu-dot');
    if (isLive && !dot) {
      dot = document.createElement('span');
      dot.className = 'soccerbet-menu-dot';
      menuLink.appendChild(dot);
    } else if (!isLive && dot) {
      dot.remove();
    }
  }

})(jQuery, Drupal, once);
