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
              renderGames(root, data.games);
              renderRanking(root, data.games, data.ranking);
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

  function renderGames(root, games) {
    const wrap = root.querySelector('#soccerbet-live-games');
    if (!wrap) return;
    if (!games || games.length === 0) {
      wrap.innerHTML = '<p class="soccerbet-live__no-games">' + Drupal.t('Derzeit laufen keine Spiele.') + '</p>';
      return;
    }
    let html = '<div class="soccerbet-live__scoreboard">';
    games.forEach(g => {
      const score = (g.score1 !== null && g.score2 !== null)
        ? '<strong>' + g.score1 + ' : ' + g.score2 + '</strong>'
        : '<span class="soccerbet-live__match-score--pending">— : —</span>';
      html += '<div class="soccerbet-live__match" data-game-id="' + g.game_id + '">'
        + '<div class="soccerbet-live__match-team soccerbet-live__match-team--home">'
        + flagHtml(g.team1_flag, g.team1_name) + '<span>' + esc(g.team1_name) + '</span></div>'
        + '<div class="soccerbet-live__match-score">' + score + '</div>'
        + '<div class="soccerbet-live__match-team soccerbet-live__match-team--away">'
        + '<span>' + esc(g.team2_name) + '</span>' + flagHtml(g.team2_flag, g.team2_name) + '</div>'
        + '</div>';
    });
    html += '</div>';
    wrap.innerHTML = html;
  }

  function renderRanking(root, games, ranking) {
    const wrap = root.querySelector('#soccerbet-live-ranking');
    if (!wrap || !ranking) return;
    if (ranking.length === 0) {
      wrap.innerHTML = '<p class="soccerbet-live__empty">' + Drupal.t('Keine Teilnehmer.') + '</p>';
      return;
    }

    // Tabellenkopf
    let html = '<div class="soccerbet-live__table-wrap"><table class="soccerbet-live__table"><thead><tr>'
      + '<th class="col-rank">' + Drupal.t('Rang') + '</th>'
      + '<th class="col-name">' + Drupal.t('Name') + '</th>';
    (games || []).forEach(g => {
      html += '<th class="col-tipp" title="' + esc(g.team1_name) + ' vs ' + esc(g.team2_name) + '">'
        + g.team1_name.substring(0,3).toUpperCase() + ' : '
        + g.team2_name.substring(0,3).toUpperCase() + '</th>';
    });
    html += '<th class="col-total">' + Drupal.t('Punkte') + '</th></tr></thead><tbody>';

    ranking.forEach(row => {
      // Rang + Diff
      let rankHtml = row.rank;
      const diff = row.rank_diff || 0;
      if (diff > 0) {
        rankHtml += ' <span class="rank-diff rank-diff--up">(+' + diff + ')</span>';
      } else if (diff < 0) {
        rankHtml += ' <span class="rank-diff rank-diff--down">(' + diff + ')</span>';
      }

      html += '<tr class="soccerbet-live__row">'
        + '<td class="col-rank">' + rankHtml + '</td>'
        + '<td class="col-name">' + (row.detail_url ? '<a href="' + esc(row.detail_url) + '">' + esc(row.name) + '</a>' : esc(row.name)) + (row.stars > 0 ? ' <span class="soccerbet-stars">' + '★'.repeat(row.stars) + '</span>' : '') + '</td>';

      (games || []).forEach(g => {
        const t = (row.live_tipps && row.live_tipps[g.game_id]) || { tipp: '—', status: 'none', points: 0 };
        const ptsHtml = ['exact','tendency','wrong'].includes(t.status)
          ? '<span class="soccerbet-live__tipp-pts">(' + t.points + ')</span>'
          : '';
        html += '<td class="col-tipp">'
          + '<span class="soccerbet-live__tipp soccerbet-live__tipp--' + t.status + '">'
          + '<span class="soccerbet-live__tipp-score">' + esc(t.tipp) + '</span>'
          + ptsHtml + '</span></td>';
      });

      html += '<td class="col-total"><strong>' + row.total + '</strong></td></tr>';
    });

    html += '</tbody></table></div>';
    html += '<div class="soccerbet-live__legend">'
      + '<span class="soccerbet-live__tipp soccerbet-live__tipp--exact">2:1<br><small>(3)</small></span> ' + Drupal.t('Richtiges Ergebnis') + ' '
      + '<span class="soccerbet-live__tipp soccerbet-live__tipp--tendency">1:0<br><small>(1)</small></span> ' + Drupal.t('Richtige Tendenz') + ' '
      + '<span class="soccerbet-live__tipp soccerbet-live__tipp--wrong">0:2<br><small>(0)</small></span> ' + Drupal.t('Falsch')
      + '</div>';
    wrap.innerHTML = html;
  }

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

  function esc(str) {
    return String(str)
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function flagHtml(code, alt) {
    if (!code) return '';
    const u = code.toUpperCase(), l = code.toLowerCase();
    const b = '/modules/custom/soccerbet/images/flags';
    return '<img src="' + b + '/svg/' + l + '.svg"'
      + ' onerror="this.onerror=null;this.src=\'' + b + '/PNG/2x/' + u + '@2x.png\''
      + ';this.onerror=function(){this.src=\'' + b + '/PNG/1x/' + u + '.png\'}"'
      + ' alt="' + esc(alt||u) + '" width="36" height="24" class="soccerbet-flag" loading="lazy">';
  }

}(Drupal, once));
