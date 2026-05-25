<div class="d-flex align-items-center gap-2">
    <span class="server-clock text-muted d-flex align-items-center gap-1"
          title="Server time" data-server-ts="<?= time() ?>">
        <i class="bi bi-clock d-none d-sm-inline"></i>
        <span class="server-clock-time"><?= date('H:i:s') ?></span>
        <span class="server-clock-tz d-none d-sm-inline"><?= date('T') ?></span>
    </span>
    <div class="vr opacity-25"></div>
    <?php include dirname(__FILE__) . '/color-modes.php'; ?>
    <?php include dirname(__FILE__) . '/lang-switcher.php'; ?>
</div>
<script>
(function () {
    var el = document.querySelector('[data-server-ts]');
    if (!el) return;
    var diff = parseInt(el.dataset.serverTs, 10) * 1000 - Date.now();
    var timeEl = el.querySelector('.server-clock-time');
    function pad(n) { return String(n).padStart(2, '0'); }
    function tick() {
        var d = new Date(Date.now() + diff);
        timeEl.textContent = pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
    }
    tick();
    setInterval(tick, 1000);
})();
</script>
