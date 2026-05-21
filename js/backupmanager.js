/* BackupManager Plugin JS
   OWASP A03 — client-side input helpers / UX enhancements
   No localStorage / no eval / strict mode */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        /* Toggle encryption algorithm row */
        var encField = document.querySelector('[name="encryption_enabled"]');
        var algoWrap = document.querySelector('[name="encryption_algorithm"]');
        if (encField && algoWrap) {
            var algoRow = algoWrap.closest('tr');
            function toggleAlgo() {
                if (algoRow) algoRow.style.display = encField.value === '1' ? '' : 'none';
            }
            encField.addEventListener('change', toggleAlgo);
            toggleAlgo();
        }

        /* Cron expression basic hint */
        var cronField = document.querySelector('[name="schedule_cron"]');
        if (cronField) {
            cronField.setAttribute('placeholder', '0 2 * * *');
            cronField.setAttribute('title', 'Cron format: min hour day month weekday — ex: 0 2 * * * (daily at 02:00)');
        }

        /* Confirm before delete / purge */
        document.querySelectorAll('[name="delete"],[name="purge"]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                var msg = btn.name === 'purge'
                    ? 'Permanently delete this record and ALL related data? This cannot be undone.'
                    : 'Move this record to trash?';
                if (!window.confirm(msg)) e.preventDefault();
            });
        });

        /* Auto-format size fields */
        document.querySelectorAll('[name="size_mb"],[name="capacity_gb"],[name="used_gb"]').forEach(function(f){
            f.addEventListener('blur', function() {
                var v = parseFloat(f.value);
                if (!isNaN(v) && v < 0) f.value = 0;
            });
        });
    });
})();

/**
 * Atualiza o dropdown de ativo de origem quando o itemtype muda
 * Usa o endpoint nativo do GLPI para recarregar o select via AJAX
 */
function pluginBackupmanagerUpdateSourceItem(itemtype) {
    var container = document.querySelector('[name="source_items_id"]');
    if (!container) return;

    // Limpa o select atual e mostra loading
    var parent = container.closest('td');
    if (!parent) return;
    parent.innerHTML = '<span class="text-muted">Loading...</span>';

    // Usa o helper AJAX nativo do GLPI
    var url = CFG_GLPI.root_doc + '/ajax/dropdownAllItems.php';
    var params = new URLSearchParams({
        itemtype:  itemtype,
        name:      'source_items_id',
        value:     0,
        entity_restrict: -1,
    });

    fetch(url + '?' + params.toString(), { credentials: 'same-origin' })
        .then(function(r) { return r.text(); })
        .then(function(html) { parent.innerHTML = html; })
        .catch(function() { parent.innerHTML = '<input type="number" name="source_items_id" value="0">'; });
}
