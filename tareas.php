<?php
require_once __DIR__ . '/api/lib/auth.php';
$usuarioActual = timbra_requerir_modulo('tareas');

$proyectoId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$modoDetalle = $proyectoId !== null && $proyectoId > 0;

$puedeCrearProyecto = in_array($usuarioActual['rol'], ['admin', 'lider_proyecto'], true);
$puedeCrearTareaAqui = false;
$proyectoActual = null;

if ($modoDetalle) {
    $pdo = timbra_db();
    $stmt = $pdo->prepare('
        SELECT p.*, u.nombre AS creado_por_nombre
        FROM proyectos p JOIN usuarios u ON u.id = p.creado_por
        WHERE p.id = ?
    ');
    $stmt->execute([$proyectoId]);
    $proyectoActual = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$proyectoActual || !timbra_puede_ver_proyecto($pdo, $usuarioActual, $proyectoActual)) {
        header('Location: tareas.php');
        exit;
    }

    $puedeCrearTareaAqui = $usuarioActual['rol'] === 'admin'
        || ($usuarioActual['rol'] === 'lider_proyecto' && (int) $proyectoActual['creado_por'] === (int) $usuarioActual['id']);
}

$pageTitle    = $modoDetalle ? $proyectoActual['nombre'] : 'Proyectos';
$activeNav    = 'tareas';
$headerTitle  = $modoDetalle ? $proyectoActual['nombre'] : 'Proyectos';
$headerSub    = $modoDetalle
    ? 'Creado por ' . $proyectoActual['creado_por_nombre']
    : 'Cada tarea vive dentro de una etapa de un proyecto — entra a uno para organizarlo';
$extraScripts = ['assets/js/tareas.js?v=2'];
include __DIR__ . '/includes/layout-top.php';
?>

<script>
  const TIMBRA_ROL_ACTUAL = <?= json_encode($usuarioActual['rol']) ?>;
  const TIMBRA_USUARIO_ID = <?= (int) $usuarioActual['id'] ?>;
  const TIMBRA_MODO = <?= json_encode($modoDetalle ? 'detalle' : 'lista') ?>;
  const TIMBRA_PROYECTO_ID = <?= $modoDetalle ? (int) $proyectoId : 'null' ?>;
  const TIMBRA_PUEDE_CREAR_TAREA_AQUI = <?= $puedeCrearTareaAqui ? 'true' : 'false' ?>;
  const TIMBRA_COSTO_BASE = <?= ($modoDetalle && $proyectoActual['tipo'] === 'externo') ? (float) $proyectoActual['costo'] : 'null' ?>;
</script>

<?php if (!$modoDetalle): ?>
  <!-- ===================== LISTA DE PROYECTOS ===================== -->
  <div class="d-flex justify-content-end mb-3">
    <?php if ($puedeCrearProyecto): ?>
      <button class="btn btn-timbra btn-sm" id="btn-nuevo-proyecto" data-bs-toggle="modal" data-bs-target="#modalProyecto">
        <i class="bi bi-folder-plus me-1"></i> Nuevo proyecto
      </button>
    <?php endif; ?>
  </div>

  <div class="card-timbra">
    <div class="card-body-timbra">
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>Proyecto</th>
              <th>Tipo</th>
              <th>Fechas tentativas</th>
              <th>Avance</th>
              <th>Creado por</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="tabla-proyectos-body">
            <tr><td colspan="6" class="hint">Cargando proyectos…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <?php if ($puedeCrearProyecto): ?>
  <!-- Modal: nuevo proyecto -->
  <div class="modal fade" id="modalProyecto" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title font-display" id="modalProyectoTitulo">Nuevo proyecto</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="form-proyecto">
          <div class="modal-body">
            <input type="hidden" name="accion" id="p_accion" value="crear">
            <input type="hidden" name="id" id="p_id" value="">
            <div class="mb-3">
              <label class="form-label" for="p_nombre">Nombre</label>
              <input type="text" class="form-control" id="p_nombre" name="nombre" required>
              <div class="field-error"></div>
            </div>

            <div class="mb-3">
              <label class="form-label d-block">Tipo de proyecto</label>
              <div class="btn-group w-100" role="group">
                <input type="radio" class="btn-check" name="tipo" id="p_tipo_interno" value="interno" checked>
                <label class="btn btn-outline-timbra" for="p_tipo_interno">Interno</label>
                <input type="radio" class="btn-check" name="tipo" id="p_tipo_externo" value="externo">
                <label class="btn btn-outline-timbra" for="p_tipo_externo">Externo (cliente)</label>
              </div>
              <div class="field-error"></div>
            </div>

            <div class="mb-3" id="p_costo_wrap" style="display:none">
              <label class="form-label" for="p_costo">Costo del proyecto</label>
              <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" step="0.01" min="0.01" class="form-control font-mono" id="p_costo" name="costo" placeholder="25000.00">
              </div>
              <div class="hint">Obligatorio para proyectos externos; un proyecto interno no lleva costo.</div>
              <div class="field-error"></div>
            </div>

            <div class="mb-3">
              <label class="form-label" for="p_negocio">Negocio</label>
              <select class="form-select" id="p_negocio" name="negocio_id"></select>
            </div>

            <div class="mb-3" id="p_cliente_wrap" style="display:none">
              <label class="form-label" for="p_cliente">Cliente</label>
              <select class="form-select" id="p_cliente" name="cliente_id">
                <option value="">Cargando clientes…</option>
              </select>
              <div class="hint">Obligatorio para proyectos externos. ¿No está en la lista? Regístralo primero en <a href="clientes.php" target="_blank">Clientes</a>.</div>
              <div class="field-error"></div>
            </div>

            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label" for="p_fecha_inicio">Fecha tentativa de inicio</label>
                <input type="date" class="form-control" id="p_fecha_inicio" name="fecha_inicio">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label" for="p_fecha_fin">Fecha tentativa de fin</label>
                <input type="date" class="form-control" id="p_fecha_fin" name="fecha_fin_tentativa">
                <div class="field-error"></div>
              </div>
            </div>
            <div class="mb-1">
              <label class="form-label" for="p_descripcion">Descripción (opcional)</label>
              <textarea class="form-control" id="p_descripcion" name="descripcion" rows="2"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-timbra" id="modalProyectoBoton">Crear proyecto</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

<?php else: ?>
  <!-- ===================== DETALLE DE PROYECTO (etapas + tableros) ===================== -->
  <div class="mb-3">
    <a href="tareas.php" class="hint text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Proyectos</a>
  </div>

  <div class="card-timbra mb-3">
    <div class="card-body-timbra py-3">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
          <div class="hint mb-0"><?= htmlspecialchars($proyectoActual['descripcion'] ?: 'Sin descripción.') ?></div>
          <span class="badge-prioridad <?= $proyectoActual['tipo'] === 'externo' ? 'alta' : 'baja' ?> mt-2 d-inline-block">
            <?= $proyectoActual['tipo'] === 'externo' ? 'Externo' : 'Interno' ?>
          </span>
          <?php if ($proyectoActual['tipo'] === 'externo'): ?>
            <span class="hint mb-0"><i class="bi bi-person-vcard me-1"></i><?= htmlspecialchars($proyectoActual['cliente_nombre'] ?? '—') ?></span>
          <?php endif; ?>
        </div>
        <div class="text-end">
          <div class="hint mb-0">
            <i class="bi bi-calendar-range me-1"></i>
            <?= htmlspecialchars($proyectoActual['fecha_inicio'] ?: '¿?') ?> →
            <?= htmlspecialchars($proyectoActual['fecha_fin_tentativa'] ?: '¿?') ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php if ($proyectoActual['tipo'] === 'externo'): ?>
  <div class="card-timbra mb-3">
    <div class="card-header-timbra">
      <h2><i class="bi bi-cash-coin me-1"></i> Costos</h2>
      <div class="d-flex align-items-center gap-2">
        <a class="btn btn-sm btn-outline-secondary" href="ticket-proyecto.php?id=<?= (int) $proyectoId ?>" target="_blank">
          <i class="bi bi-printer me-1"></i> Imprimir ticket
        </a>
        <?php if ($puedeCrearTareaAqui): ?>
          <button class="btn btn-sm btn-outline-timbra" data-bs-toggle="modal" data-bs-target="#modalCostoExtra">
            <i class="bi bi-plus-lg me-1"></i> Costo extra
          </button>
        <?php endif; ?>
      </div>
    </div>
    <div class="card-body-timbra">
      <div class="d-flex gap-4 flex-wrap mb-3">
        <div>
          <div class="hint mb-0">Costo base</div>
          <div class="mono" style="font-size:1.1rem">$<?= number_format((float) $proyectoActual['costo'], 2) ?></div>
        </div>
        <div>
          <div class="hint mb-0">Extras</div>
          <div class="mono" style="font-size:1.1rem" id="costos-extra-suma">$0.00</div>
        </div>
        <div>
          <div class="hint mb-0">Total</div>
          <div class="mono" style="font-size:1.1rem; color:var(--brand-dark); font-weight:600" id="costos-total">$<?= number_format((float) $proyectoActual['costo'], 2) ?></div>
        </div>
      </div>
      <div id="lista-costos-extra">
        <div class="hint">Cargando…</div>
      </div>
    </div>
  </div>

  <div class="card-timbra mb-3">
    <div class="card-header-timbra">
      <h2><i class="bi bi-wallet2 me-1"></i> Abonos</h2>
      <?php if ($puedeCrearTareaAqui): ?>
        <button class="btn btn-sm btn-timbra" data-bs-toggle="modal" data-bs-target="#modalAbono">
          <i class="bi bi-plus-lg me-1"></i> Registrar abono
        </button>
      <?php endif; ?>
    </div>
    <div class="card-body-timbra">
      <div class="d-flex gap-4 flex-wrap mb-3">
        <div>
          <div class="hint mb-0">Total del proyecto</div>
          <div class="mono" style="font-size:1.1rem" id="abonos-total-proyecto">$0.00</div>
        </div>
        <div>
          <div class="hint mb-0">Abonado</div>
          <div class="mono" style="font-size:1.1rem; color:var(--brand-dark); font-weight:600" id="abonos-total-abonado">$0.00</div>
        </div>
        <div>
          <div class="hint mb-0">Saldo pendiente</div>
          <div class="mono" style="font-size:1.1rem" id="abonos-saldo">$0.00</div>
        </div>
      </div>
      <div class="progress progress-timbra mb-3">
        <div class="progress-bar" id="abonos-barra" style="width:0%"></div>
      </div>
      <div id="lista-abonos">
        <div class="hint">Cargando…</div>
      </div>
    </div>
  </div>

  <?php if ($puedeCrearTareaAqui): ?>
  <!-- Modal: nuevo abono -->
  <div class="modal fade" id="modalAbono" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title font-display">Registrar abono</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="form-abono">
          <div class="modal-body">
            <input type="hidden" name="proyecto_id" value="<?= (int) $proyectoId ?>">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label" for="ab_monto">Monto</label>
                <div class="input-group">
                  <span class="input-group-text">$</span>
                  <input type="number" step="0.01" min="0.01" class="form-control font-mono" id="ab_monto" name="monto" required placeholder="5000.00">
                </div>
                <div class="field-error"></div>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label" for="ab_fecha">Fecha del pago</label>
                <input type="date" class="form-control" id="ab_fecha" name="fecha" required>
                <div class="field-error"></div>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="ab_metodo">Método de pago (opcional)</label>
              <select class="form-select" id="ab_metodo" name="metodo_pago">
                <option value="">Sin especificar</option>
                <option value="Efectivo">Efectivo</option>
                <option value="Transferencia">Transferencia</option>
                <option value="Tarjeta">Tarjeta</option>
                <option value="Cheque">Cheque</option>
                <option value="Otro">Otro</option>
              </select>
            </div>
            <div class="mb-1">
              <label class="form-label" for="ab_notas">Notas (opcional)</label>
              <textarea class="form-control" id="ab_notas" name="notas" rows="2"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-timbra">Registrar</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($puedeCrearTareaAqui): ?>
  <!-- Modal: nuevo costo extra -->
  <div class="modal fade" id="modalCostoExtra" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title font-display">Costo extra</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="form-costo-extra">
          <div class="modal-body">
            <input type="hidden" name="proyecto_id" value="<?= (int) $proyectoId ?>">
            <div class="mb-3">
              <label class="form-label" for="ce_descripcion">Descripción</label>
              <input type="text" class="form-control" id="ce_descripcion" name="descripcion" required placeholder="Ej. Toma extra solicitada por el cliente">
              <div class="field-error"></div>
            </div>
            <div class="mb-1">
              <label class="form-label" for="ce_monto">Monto</label>
              <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" step="0.01" min="0.01" class="form-control font-mono" id="ce_monto" name="monto" required placeholder="1500.00">
              </div>
              <div class="field-error"></div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-timbra">Agregar</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <?php endif; ?>

  <?php if ($puedeCrearTareaAqui): ?>
    <div class="d-flex justify-content-end gap-2 mb-3">
      <a href="exportar-proyecto.php?id=<?= (int) $proyectoId ?>" class="btn btn-outline-timbra btn-sm">
        <i class="bi bi-file-earmark-excel me-1"></i> Exportar proyecto
      </a>
      <button class="btn btn-outline-timbra btn-sm" id="btn-nueva-etapa" data-bs-toggle="modal" data-bs-target="#modalEtapa">
        <i class="bi bi-signpost-split me-1"></i> Nueva etapa
      </button>
    </div>
  <?php endif; ?>

  <div id="etapas-container">
    <div class="hint">Cargando etapas…</div>
  </div>

  <template id="tpl-etapa">
    <div class="card-timbra mb-3 etapa-card">
      <div class="card-header-timbra">
        <h2><i class="bi bi-signpost-split me-1"></i><span class="etapa-nombre"></span></h2>
        <div class="d-flex align-items-center gap-2">
          <span class="hint mb-0 etapa-avance"></span>
          <?php if ($puedeCrearTareaAqui): ?>
            <button class="btn btn-sm btn-timbra btn-nueva-tarea-etapa"><i class="bi bi-plus-lg"></i> Tarea</button>
            <button class="btn btn-sm btn-outline-secondary btn-editar-etapa" title="Editar etapa"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-sm btn-outline-secondary btn-eliminar-etapa" title="Eliminar etapa"><i class="bi bi-trash"></i></button>
          <?php endif; ?>
        </div>
      </div>
      <div class="card-body-timbra">
        <div class="kanban">
          <div class="kanban-col" data-estado="pendiente">
            <div class="kanban-col-head"><span><i class="bi bi-circle text-muted me-1"></i>Pendiente</span> <span class="badge-count count-pendiente">0</span></div>
            <div class="kanban-col-body col-pendiente"></div>
          </div>
          <div class="kanban-col" data-estado="en_progreso">
            <div class="kanban-col-head"><span><i class="bi bi-arrow-repeat text-muted me-1"></i>En progreso</span> <span class="badge-count count-en_progreso">0</span></div>
            <div class="kanban-col-body col-en_progreso"></div>
          </div>
          <div class="kanban-col" data-estado="completada">
            <div class="kanban-col-head"><span><i class="bi bi-check-circle text-muted me-1"></i>Completada</span> <span class="badge-count count-completada">0</span></div>
            <div class="kanban-col-body col-completada"></div>
          </div>
        </div>
      </div>
    </div>
  </template>

  <?php if ($puedeCrearTareaAqui): ?>
  <!-- Modal: nueva etapa -->
  <div class="modal fade" id="modalEtapa" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title font-display" id="modalEtapaTitulo">Nueva etapa</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="form-etapa">
          <div class="modal-body">
            <input type="hidden" name="accion" id="e_accion" value="crear">
            <input type="hidden" name="id" id="e_id" value="">
            <input type="hidden" name="proyecto_id" value="<?= (int) $proyectoId ?>">
            <div class="mb-1">
              <label class="form-label" for="e_nombre">Nombre de la etapa</label>
              <input type="text" class="form-control" id="e_nombre" name="nombre" required placeholder="Ej. Grabar los videos en las locaciones">
              <div class="field-error"></div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-timbra" id="modalEtapaBoton">Crear etapa</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal: nueva tarea (siempre dentro de una etapa de ESTE proyecto) -->
  <div class="modal fade" id="modalTarea" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title font-display" id="modalTareaTitulo">Nueva tarea</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="form-tarea">
          <div class="modal-body">
            <input type="hidden" name="accion" id="t_accion" value="crear">
            <input type="hidden" name="id" id="t_id" value="">
            <div class="mb-3">
              <label class="form-label" for="t_etapa">Etapa</label>
              <select class="form-select" id="t_etapa" name="etapa_id" required></select>
              <div class="field-error"></div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="t_titulo">Título</label>
              <input type="text" class="form-control" id="t_titulo" name="titulo" required>
              <div class="field-error"></div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="t_descripcion">Descripción</label>
              <textarea class="form-control" id="t_descripcion" name="descripcion" rows="2"></textarea>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label" for="t_asignado">Asignar a <span class="text-danger">*</span></label>
                <select class="form-select" id="t_asignado" name="asignado_a" required>
                  <option value="" disabled selected>Selecciona a alguien…</option>
                </select>
                <div class="hint">Obligatorio: sin responsable no se puede crear la tarea.</div>
                <div class="field-error"></div>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label" for="t_prioridad">Prioridad</label>
                <select class="form-select" id="t_prioridad" name="prioridad">
                  <option value="baja">Baja</option>
                  <option value="media" selected>Media</option>
                  <option value="alta">Alta</option>
                </select>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 mb-1">
                <label class="form-label" for="t_fecha_inicio">Fecha de inicio</label>
                <input type="date" class="form-control" id="t_fecha_inicio" name="fecha_inicio" required>
                <div class="field-error"></div>
              </div>
              <div class="col-md-6 mb-1">
                <label class="form-label" for="t_fecha_fin">Fecha final</label>
                <input type="date" class="form-control" id="t_fecha_fin" name="fecha_fin" required>
                <div class="field-error"></div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-timbra" id="modalTareaBoton">Crear tarea</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

<?php endif; ?>

<?php include __DIR__ . '/includes/layout-bottom.php'; ?>
