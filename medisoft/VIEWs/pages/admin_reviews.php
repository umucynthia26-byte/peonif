<?php
$adminTitle = 'Reviews';
$adminSubtitle = 'Manage product feedback and remove abusive reviews.';
include __DIR__ . '/../partials/admin_workspace_start.php';
?>
<div class="rounded-xl border bg-card p-4">
  <h2 class="font-heading text-xl">Reviews</h2>
  <ul id="admin-reviews" class="mt-2 divide-y"></ul>
</div>
<?php include __DIR__ . '/../partials/admin_workspace_end.php'; ?>
