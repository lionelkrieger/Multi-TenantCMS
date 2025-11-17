<?php
ob_start();
?>
<section>
    <h2>Organization Not Found</h2>
    <p><?= sanitize($message ?? 'The requested organization could not be located.') ?></p>
</section>
<?php
$content = ob_get_clean();
$title = 'Organization Missing';
require view_path('layouts/base.php');
