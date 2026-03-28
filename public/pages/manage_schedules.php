<?php
// public/pages/manage_schedules.php (VERSÃO CORRETA E FINAL)

// Define qual é a ação padrão (ver a lista) e obtém a ação do URL (ex: &action=create)
$action = $_GET['action'] ?? 'list';
?>

<h1><i class="fa-solid fa-calendar-days"></i> Agendamentos Automáticos</h1>

<div class="tabs-nav">
    <a href="index.php?page=manage_schedules&action=list" class="tab-link <?= $action === 'list' ? 'active' : '' ?>">
        <i class="fa-solid fa-list-ul"></i> Ver Agendamentos
    </a>
    <a href="index.php?page=manage_schedules&action=create" class="tab-link <?= $action === 'create' ? 'active' : '' ?>">
        <i class="fa-solid fa-plus"></i> Criar Novo
    </a>
    <a href="index.php?page=manage_schedules&action=closing" class="tab-link <?= $action === 'closing' ? 'active' : '' ?>">
        <i class="fa-solid fa-door-closed"></i> Agendar Fecho
    </a>
</div>
<script>
(function () {
  const KEY = 'spotmaster_open_groups';

  const read = () => {
    try { return JSON.parse(localStorage.getItem(KEY)) || []; }
    catch { return []; }
  };
  const write = (v) => localStorage.setItem(KEY, JSON.stringify(v));
  const add = (id) => { const v = read(); if (!v.includes(id)) { v.push(id); write(v); } };
  const del = (id) => write(read().filter(x => x !== id));

  document.addEventListener('DOMContentLoaded', function () {
    // 1) Reabre o que estava aberto
    const toOpen = new Set(read());
    document.querySelectorAll('.accordion-panel[id]').forEach(panel => {
      const id = panel.id;
      if (toOpen.has(id)) {
        // abre visualmente (ajusta conforme o teu CSS/JS do accordion)
        panel.style.display = 'block';
        const item = panel.closest('.accordion-item');
        if (item) item.classList.add('open');
      }
    });

    // 2) Ouve cliques nos cabeçalhos para guardar/retirar do storage
    document.body.addEventListener('click', function (e) {
      const header = e.target.closest('.accordion-header');
      if (!header) return;

      const item  = header.closest('.accordion-item');
      const panel = item && item.querySelector('.accordion-panel[id]');
      if (!panel) return;

      const id = panel.id;

      // Se o teu accordion tem JS próprio que alterna classes, espera um tick e lê o estado:
      setTimeout(() => {
        const isOpen = panel.offsetParent !== null || panel.style.display === 'block' || item.classList.contains('open');
        if (isOpen) add(id); else del(id);
      }, 0);
    });
  });
})();
</script>


<?php
// Carrega o ficheiro parcial correto com base na ação do URL
if ($action === 'create') {
    // Se a ação for 'criar', carrega o formulário
    include __DIR__ . '/partials/schedule_form.php';
} elseif ($action === 'closing') { // <-- NOVO 'ELSEIF'
    include __DIR__ . '/partials/park_closing_form.php';
} else {
    // Para qualquer outra ação (incluindo 'listar'), carrega a lista de agendamentos
    include __DIR__ . '/partials/schedule_list.php';
}
?>