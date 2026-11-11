<?php
/* Janela de denúncia (usada pelo feed e pela página do projeto). Só para membros logados. */
if (!empty($u ?? $user ?? null) && !is_admin($u ?? $user)):
?>
<div class="report-overlay" id="reportModal" hidden>
  <div class="report-box" role="dialog" aria-modal="true" aria-labelledby="reportTitle">
    <div class="report-head">
      <h3 id="reportTitle"> Denunciar publicação</h3>
      <button type="button" class="report-close" data-report-close aria-label="Fechar">✕</button>
    </div>
    <p class="muted" style="margin:0 0 12px;font-size:.9rem">Por que esta publicação deve ser analisada? Sua denúncia é enviada para a administração.</p>
    <form id="reportForm">
      <input type="hidden" name="acao" value="denunciar">
      <input type="hidden" name="id_projeto" id="reportPid" value="">
      <div class="report-options">
        <?php foreach (motivos_denuncia() as $k => $label): ?>
          <label class="report-option"><input type="radio" name="motivo" value="<?= h($k) ?>" required> <span><?= h($label) ?></span></label>
        <?php endforeach; ?>
      </div>
      <div class="field" style="margin-top:12px">
        <label>Detalhes <span class="muted">(opcional)</span></label>
        <textarea name="detalhes" maxlength="1000" rows="3" placeholder="Conte o que aconteceu..."></textarea>
      </div>
      <div class="report-error" id="reportError" hidden></div>
      <div style="display:flex;gap:8px;justify-content:flex-end">
        <button type="button" class="btn btn-ghost" data-report-close>Cancelar</button>
        <button type="submit" class="btn btn-primary" id="reportSend">Enviar denúncia</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
