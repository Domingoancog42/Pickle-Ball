<div class="drawer-backdrop" data-action="sidebar" hidden></div>
<aside class="sidebar" id="sidebar" aria-label="Admin navigation">
  <a class="brand" href="index.php" data-page="dashboard"><img src="../assets/images/logo.png" alt="Pikol Castle" width="42" height="42"><span><b id="club-brand-name"><?= e($business['venue_name'] ?? 'Pikol Castle') ?></b><small>CLUB MANAGEMENT</small></span></a>
  
  <nav aria-label="Main navigation"><p class="nav-caption">WORKSPACE</p>
    <?php foreach ($navigation as $key=>$item): ?>
      <?php if(($item['sidebar'] ?? true)===false)continue;$active=($navigation[$page]['parent'] ?? $page)===$key; ?>
      <a href="<?= e($item['file']) ?>" data-page="<?= e($key) ?>" title="<?= e($item['title']) ?>" class="nav-link <?= $active?'active':'' ?>" <?= $active?'aria-current="page"':'' ?>>
        <i data-lucide="<?= e($item['icon']) ?>"></i><span><?= e($item['title']) ?></span><?php if(in_array($key,['reservations','payments'],true)): ?><b id="<?= $key==='reservations'?'nav-pending':'nav-pending-payments' ?>" class="nav-count" aria-live="polite" hidden></b><?php endif ?>
      </a>
    <?php endforeach ?>
  </nav>
</aside>
