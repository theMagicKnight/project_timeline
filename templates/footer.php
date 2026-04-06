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

<?php if (!empty($js_vars)): ?>
<script>
<?php foreach ($js_vars as $name => $value): ?>
  const <?= $name ?> = <?= json_encode($value) ?>;
<?php endforeach; ?>
</script>
<?php endif; ?>

<?php if ($js_modules): ?>
<!-- Projekt-Timeline JS-Module -->
<script src="<?= $base_path ?? '' ?>assets/js/config.js"></script>
<script src="<?= $base_path ?? '' ?>assets/js/api.js"></script>
<script src="<?= $base_path ?? '' ?>assets/js/auth.js"></script>
<script src="<?= $base_path ?? '' ?>assets/js/sidebar.js"></script>
<script src="<?= $base_path ?? '' ?>assets/js/matrix.js"></script>
<script src="<?= $base_path ?? '' ?>assets/js/rubriken.js"></script>
<script src="<?= $base_path ?? '' ?>assets/js/timeline.js"></script>
<script src="<?= $base_path ?? '' ?>assets/js/anhaenge.js"></script>
<script src="<?= $base_path ?? '' ?>assets/js/detail.js"></script>
<script src="<?= $base_path ?? '' ?>assets/js/modals.js"></script>
<script src="<?= $base_path ?? '' ?>assets/js/crud.js"></script>
<script src="<?= $base_path ?? '' ?>assets/js/app.js"></script>
<?php endif; ?>

</body>
</html>