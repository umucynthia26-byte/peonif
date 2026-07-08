<?php
$adminTitle = 'Support';
$adminSubtitle = 'Customer messages and inquiries from contact forms.';
include __DIR__ . '/../partials/admin_workspace_start.php';
?>
<div class="rounded-xl border bg-card p-4">
  <h2 class="font-heading text-xl">Inbox</h2>
  <p class="mt-1 text-sm text-muted-foreground">Messages from customers. Click email to reply quickly.</p>
  <ul id="admin-messages" class="mt-2 divide-y"></ul>
</div>
<?php include __DIR__ . '/../partials/admin_workspace_end.php'; ?>
