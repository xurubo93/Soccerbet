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

        btn.addEventListener('click', function () {
          btn.disabled = true;
          btn.textContent = Drupal.t('Wird geladen…');
          fetch(refreshUrl, { headers: { Accept: 'application/json' } })
            .then(r => r.json())
            .then(data => {
              renderGames(root, data.games);
              renderRanking(root, data.games, data.ranking);
              updateLiveDot(root, data.is_live);
              if (updatedEl) {
                updatedEl.textContent = Drupal.t('Zuletzt aktualisiert: @t', { '@t': data.updated });
              }
            })
            .catch(() => {
              if (updatedEl) updatedEl.textContent = Drupal.t('Aktualisierung fehlgeschlagen.');
            })
            .finally(() => {
              btn.disabled = false;
              btn.textContent = '↻ ' + Drupal.t('Aktualisieren');
            });
        });
      });

      once('soccerbet-live-menu', 'body', context).forEach(checkLiveMenu);
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
    let html = '<table class="soccerbet-live__table"><thead><tr>'
      + '<th class="col-rank">' + Drupal.t('Rang') + '</th>'
      + '<th class="col-name">' + Drupal.t('Name') + '</th>';
    (games || []).forEach(g => {
      html += '<th class="col-tipp">'
        + g.team1_name.substring(0,3).toUpperCase() + '<br>'
        + g.team2_name.substring(0,3).toUpperCase() + '</th>';
    });
    html += '<th class="col-total">' + Drupal.t('Punkte') + '</th></tr></thead><tbody>';

    ranking.forEach(row => {
      // Rang + Diff
      let rankHtml = row.rank;
      const diff = row.rank_diff || 0;
      if (diff > 0) {
        rankHtml += ' <span class="soccerbet-live__diff soccerbet-live__diff--up">▲' + diff + '</span>';
      } else if (diff < 0) {
        rankHtml += ' <span class="soccerbet-live__diff soccerbet-live__diff--down">▼' + Math.abs(diff) + '</span>';
      }

      html += '<tr class="soccerbet-live__row">'
        + '<td class="col-rank">' + rankHtml + '</td>'
        + '<td class="col-name">' + (row.detail_url ? '<a href="' + esc(row.detail_url) + '">' + esc(row.name) + '</a>' : esc(row.name)) + (row.stars > 0 ? ' <span class="soccerbet-stars">' + '★'.repeat(row.stars) + '</span>' : '') + '</td>';

      (games || []).forEach(g => {
        const t = (row.live_tipps && row.live_tipps[g.game_id]) || { tipp: '—', status: 'none', points: 0 };
        const ptsHtml = ['exact','tendency','wrong'].includes(t.status)
          ? '<span class="soccerbet-live__tipp-pts">(' + t.points + ')</span>'
          : '';
        html += '<td class="col-tipp soccerbet-live__tipp-cell soccerbet-live__tipp-cell--' + t.status + '">'
          + '<span class="soccerbet-live__tipp-score">' + esc(t.tipp) + '</span>'
          + ptsHtml + '</td>';
      });

      html += '<td class="col-total"><strong>' + row.total + '</strong></td></tr>';
    });

    html += '</tbody></table>';
    html += '<div class="soccerbet-live__legend">'
      + '<span class="soccerbet-live__tipp-cell soccerbet-live__tipp-cell--exact">■</span> ' + Drupal.t('Richtiges Ergebnis') + ' '
      + '<span class="soccerbet-live__tipp-cell soccerbet-live__tipp-cell--tendency">■</span> ' + Drupal.t('Richtige Tendenz') + ' '
      + '<span class="soccerbet-live__tipp-cell soccerbet-live__tipp-cell--wrong">■</span> ' + Drupal.t('Falsch')
      + '</div>';
    wrap.innerHTML = html;
  }

  function updateLiveDot(root, isLive) {
    const dot = root.querySelector('.soccerbet-live__dot');
    if (dot) dot.style.display = isLive ? '' : 'none';
    const menuLink = document.querySelector('.menu-item--live a');
    if (menuLink) menuLink.classList.toggle('soccerbet-live-active', !!isLive);
  }

  function checkLiveMenu() {
    const menuLink = document.querySelector('.menu-item--live a');
    if (!menuLink || document.querySelector('#soccerbet-live-root')) return;
    const href = menuLink.getAttribute('href') || '';
    if (!href) return;
    const jsonUrl = href.replace(/\/$/, '') + '/json';
    fetch(jsonUrl, { headers: { Accept: 'application/json' } })
      .then(r => r.ok ? r.json() : null)
      .then(data => {
        if (data && data.is_live) {
          menuLink.classList.add('soccerbet-live-active');
          if (!menuLink.querySelector('.soccerbet-menu-live-dot')) {
            const dot = document.createElement('span');
            dot.className = 'soccerbet-menu-live-dot';
            menuLink.appendChild(dot);
          }
        }
      })
      .catch(() => {});
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
