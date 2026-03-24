/**
 * @file
 * Soccerbet Live-Rangliste – Refresh und pulsierender Menüpunkt.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.soccerbetLive = {
    attach(context) {
      once('soccerbet-live', '#soccerbet-live-root', context).forEach(function (root) {
        const refreshUrl = root.dataset.refreshUrl;
        const btn        = root.querySelector('.soccerbet-live__refresh');
        const updatedEl  = root.querySelector('#soccerbet-live-updated');
        if (!refreshUrl || !btn) return;

        let autoTimer = null;

        function doRefresh(silent) {
          if (!silent) {
            btn.disabled = true;
            btn.textContent = Drupal.t('Wird geladen…');
          }
          fetch(refreshUrl, { headers: { Accept: 'application/json' } })
            .then(r => r.json())
            .then(data => {
              const gamesEl   = root.querySelector('#soccerbet-live-games');
              const rankingEl = root.querySelector('#soccerbet-live-ranking');
              if (gamesEl)   gamesEl.innerHTML   = data.games_html;
              if (rankingEl) rankingEl.innerHTML = data.ranking_html;
              updateLiveDot(root, data.is_live);
              if (updatedEl) {
                updatedEl.textContent = Drupal.t('Zuletzt aktualisiert: @t', { '@t': data.updated });
              }
              scheduleAutoRefresh(data.is_live);
            })
            .catch(() => {
              if (!silent && updatedEl) updatedEl.textContent = Drupal.t('Aktualisierung fehlgeschlagen.');
              scheduleAutoRefresh(false);
            })
            .finally(() => {
              if (!silent) {
                btn.disabled = false;
                btn.textContent = '↻ ' + Drupal.t('Aktualisieren');
              }
            });
        }

        function scheduleAutoRefresh(isLive) {
          clearTimeout(autoTimer);
          autoTimer = setTimeout(() => doRefresh(true), isLive ? 30000 : 300000);
        }

        btn.addEventListener('click', function () { doRefresh(false); });

        // Ersten Auto-Refresh starten
        scheduleAutoRefresh(root.querySelector('.soccerbet-live__dot') !== null);
      });

    }
  };

  /* ------------------------------------------------------------------ */

  function updateLiveDot(root, isLive) {
    const dot = root.querySelector('.soccerbet-live__dot');
    if (dot) dot.style.display = isLive ? '' : 'none';
    const menuLink = document.querySelector('a.menu-item--live');
    if (!menuLink) return;
    menuLink.classList.toggle('soccerbet-live-active', !!isLive);
    let menuDot = menuLink.querySelector('.soccerbet-menu-dot');
    if (isLive && !menuDot) {
      menuDot = document.createElement('span');
      menuDot.className = 'soccerbet-menu-dot';
      menuLink.appendChild(menuDot);
    } else if (!isLive && menuDot) {
      menuDot.remove();
    }
  }


}(Drupal, once));
