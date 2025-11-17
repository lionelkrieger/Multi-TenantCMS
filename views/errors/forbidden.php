<?php
ob_start();
?>
<section>
    <h2>Access denied</h2>
    <p>You don't have permission to view this page.</p>
</section>
<?php
$content = ob_get_clean();
$title = 'Forbidden';
require view_path('layouts/base.php');
