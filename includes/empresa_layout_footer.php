<?php
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}
$extra_scripts = $extra_scripts ?? '';
?>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function() {
    var t = document.getElementById('empresaMenuToggle');
    var b = document.getElementById('empresaSidebarBackdrop');
    function close() { document.body.classList.remove('empresa-sidebar-open'); }
    if (t) t.addEventListener('click', function() {
        document.body.classList.toggle('empresa-sidebar-open');
    });
    if (b) b.addEventListener('click', close);
    window.addEventListener('resize', function() {
        if (window.matchMedia('(min-width: 992px)').matches) close();
    });
})();
</script>
<?= $extra_scripts ?>
</body>
</html>
