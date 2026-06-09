<?php
// ============================================================
//  templates/footer.php
//
//  Erwartet folgende Variablen aus der einbindenden Datei:
//    $js_modules — true = App-JS laden (Standard: false)
//    $js_vars    — Array mit PHP→JS Variablen (optional)
//                  z.B. ['AKTUELLER_BENUTZER' => $ich, 'IST_ADMIN' => true]
// ============================================================
$js_modules = $js_modules ?? false;
$js_vars    = $js_vars    ?? [];
?>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Highlight.js — Syntax Highlighting -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>

<?php if (!empty($js_vars)): ?>
<script>
<?php foreach ($js_vars as $name => $value): ?>
  const <?= $name ?> = <?= json_encode($value) ?>;
<?php endforeach; ?>
</script>
<?php endif; ?>

<?php if ($js_modules): ?>
<!-- Projekt-Timeline JS-Module -->
<?php
// Cache-Busting: Versionsnummer an alle JS-Dateien anhängen
$_v = '';
$_vf = __DIR__ . '/../version.json';
if (file_exists($_vf)) {
    $_vd = json_decode(file_get_contents($_vf), true);
    $_v  = '?v=' . ($_vd['version'] ?? '1.0.0');
}
?>
<script src="<?= ($base_path ?? '') ?>assets/js/config.js<?= $_v ?>"></script>
<script src="<?= ($base_path ?? '') ?>assets/js/api.js<?= $_v ?>"></script>
<script src="<?= ($base_path ?? '') ?>assets/js/auth.js<?= $_v ?>"></script>
<script src="<?= ($base_path ?? '') ?>assets/js/sidebar.js<?= $_v ?>"></script>
<script src="<?= ($base_path ?? '') ?>assets/js/matrix.js<?= $_v ?>"></script>
<script src="<?= ($base_path ?? '') ?>assets/js/rubriken.js<?= $_v ?>"></script>
<script src="<?= ($base_path ?? '') ?>assets/js/timeline.js<?= $_v ?>"></script>
<script src="<?= ($base_path ?? '') ?>assets/js/board.js<?= $_v ?>"></script>
<script src="<?= ($base_path ?? '') ?>assets/js/anhaenge.js<?= $_v ?>"></script>
<script src="<?= ($base_path ?? '') ?>assets/js/diskussion.js<?= $_v ?>"></script>
<script src="<?= ($base_path ?? '') ?>assets/js/detail.js<?= $_v ?>"></script>
<script src="<?= ($base_path ?? '') ?>assets/js/modals.js<?= $_v ?>"></script>
<script src="<?= ($base_path ?? '') ?>assets/js/crud.js<?= $_v ?>"></script>
<script src="<?= ($base_path ?? '') ?>assets/js/app.js<?= $_v ?>"></script>
<?php endif; ?>

</body>
</html>
