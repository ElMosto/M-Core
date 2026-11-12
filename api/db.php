<?php
/**
 * Conexión PDO (SQLite) + migración perezosa del esquema.
 * SQLite se eligió por ser "ligero, sin dependencias pesadas" (no requiere
 * un servidor de base de datos aparte). Para producción con varios
 * servidores de aplicación, sustituir el DSN por MySQL/Postgres.
 */

require_once __DIR__ . '/config.php';

function timbra_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dbDir = dirname(TIMBRA_DB_PATH);
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0770, true);
    }

    $pdo = new PDO('sqlite:' . TIMBRA_DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON');

    timbra_migrar($pdo);

    return $pdo;
}

function timbra_migrar(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tenants (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            rfc            TEXT NOT NULL UNIQUE,
            razon_social   TEXT NOT NULL,
            regimen_fiscal TEXT NOT NULL,
            cer_path       TEXT NOT NULL,
            key_path       TEXT NOT NULL,
            created_at     TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS timbres (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            origen        TEXT NOT NULL DEFAULT 'individual', -- individual | lote
            lote_id       TEXT,
            uuid          TEXT,
            folio         TEXT,
            rfc_receptor  TEXT,
            total         REAL,
            exitoso       INTEGER NOT NULL,
            codigo_error  TEXT,
            motivo_error  TEXT,
            created_at    TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_timbres_created ON timbres(created_at)");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS lotes (
            id          TEXT PRIMARY KEY,
            total       INTEGER NOT NULL,
            procesados  INTEGER NOT NULL DEFAULT 0,
            exitosos    INTEGER NOT NULL DEFAULT 0,
            rechazados  INTEGER NOT NULL DEFAULT 0,
            terminado   INTEGER NOT NULL DEFAULT 0,
            created_at  TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS lote_filas (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            lote_id    TEXT NOT NULL,
            fila       INTEGER NOT NULL,
            rfc_receptor TEXT,
            razon_receptor TEXT,
            uso_cfdi   TEXT,
            total      TEXT,
            metodo_pago TEXT,
            estado     TEXT NOT NULL DEFAULT 'pendiente', -- pendiente | exitoso | rechazado
            codigo_error TEXT,
            motivo_error TEXT
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_lote_filas_lote ON lote_filas(lote_id, estado)");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pac_status_log (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            estado      TEXT NOT NULL,
            latencia_ms INTEGER,
            created_at  TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS alertas_ack (
            id         TEXT PRIMARY KEY,
            ack_hasta  TEXT NOT NULL
        )
    ");

    /* ---------------- Login / roles ---------------- */
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS usuarios (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre        TEXT NOT NULL,
            email         TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            rol           TEXT NOT NULL, -- clave de un rol en la tabla `roles` (ver más abajo)
            activo        INTEGER NOT NULL DEFAULT 1,
            created_at    TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");
    // Foto de perfil (ruta pública bajo assets/avatars/). No es CSD ni dato
    // fiscal, así que se sirve directo por HTTP sin pasar por storage/.
    timbra_asegurar_columna($pdo, 'usuarios', 'foto_path', 'TEXT');

    /* ---------------- Roles (dinámicos) ---------------- */
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS roles (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            clave      TEXT NOT NULL UNIQUE,
            nombre     TEXT NOT NULL,
            es_sistema INTEGER NOT NULL DEFAULT 0, -- protege a los 4 roles originales de borrarse o cambiar de clave
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS rol_modulos (
            rol_id INTEGER NOT NULL REFERENCES roles(id),
            modulo TEXT NOT NULL,
            PRIMARY KEY (rol_id, modulo)
        )
    ");
    timbra_seed_roles($pdo);

    /* ---------------- Clientes ---------------- */
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS clientes (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre         TEXT NOT NULL,
            contacto_nombre TEXT,
            email          TEXT,
            telefono       TEXT,
            notas          TEXT,
            creado_por     INTEGER NOT NULL REFERENCES usuarios(id),
            activo         INTEGER NOT NULL DEFAULT 1,
            created_at     TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");

    /* ---------------- Módulo de tareas ---------------- */
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS proyectos (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre       TEXT NOT NULL,
            descripcion  TEXT,
            creado_por   INTEGER NOT NULL REFERENCES usuarios(id),
            archivado    INTEGER NOT NULL DEFAULT 0,
            created_at   TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");
    // Bases creadas antes de tener fechas de proyecto: se agregan las
    // columnas si aún no existen, sin perder los proyectos ya guardados.
    timbra_asegurar_columna($pdo, 'proyectos', 'fecha_inicio', 'TEXT');
    timbra_asegurar_columna($pdo, 'proyectos', 'fecha_fin_tentativa', 'TEXT');
    // tipo: interno (sin costo) | externo (con costo, para un cliente)
    timbra_asegurar_columna($pdo, 'proyectos', 'tipo', "TEXT NOT NULL DEFAULT 'interno'");
    timbra_asegurar_columna($pdo, 'proyectos', 'costo', 'REAL');
    // Obligatorio cuando tipo = externo; se valida en la app, no aquí.
    timbra_asegurar_columna($pdo, 'proyectos', 'cliente_id', 'INTEGER REFERENCES clientes(id)');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS costos_extra (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            proyecto_id  INTEGER NOT NULL REFERENCES proyectos(id),
            descripcion  TEXT NOT NULL,
            monto        REAL NOT NULL,
            creado_por   INTEGER NOT NULL REFERENCES usuarios(id),
            created_at   TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_costos_extra_proyecto ON costos_extra(proyecto_id)");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS etapas (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            proyecto_id  INTEGER NOT NULL REFERENCES proyectos(id),
            nombre       TEXT NOT NULL,
            orden        INTEGER NOT NULL DEFAULT 0,
            created_at   TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_etapas_proyecto ON etapas(proyecto_id, orden)");
    // Borrado suave: una etapa "eliminada" se oculta en todas las consultas
    // pero no se pierde su historial.
    timbra_asegurar_columna($pdo, 'etapas', 'eliminado', 'INTEGER NOT NULL DEFAULT 0');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tareas (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            proyecto_id  INTEGER NOT NULL REFERENCES proyectos(id),
            titulo       TEXT NOT NULL,
            descripcion  TEXT,
            asignado_a   INTEGER REFERENCES usuarios(id),
            creado_por   INTEGER NOT NULL REFERENCES usuarios(id),
            prioridad    TEXT NOT NULL DEFAULT 'media', -- baja | media | alta
            estado       TEXT NOT NULL DEFAULT 'pendiente', -- pendiente | en_progreso | completada
            fecha_limite TEXT, -- heredado; ver fecha_inicio/fecha_fin más abajo (se migra sola)
            created_at   TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at   TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tareas_asignado ON tareas(asignado_a, estado)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tareas_proyecto ON tareas(proyecto_id)");
    // Toda tarea vive dentro de una etapa del proyecto. Se agrega como
    // columna migrable porque `tareas` ya existía en instalaciones previas.
    timbra_asegurar_columna($pdo, 'tareas', 'etapa_id', 'INTEGER REFERENCES etapas(id)');
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tareas_etapa ON tareas(etapa_id)");
    // Flujo de aprobación: la persona asignada PROPONE un cambio de estado;
    // el líder dueño del proyecto o un admin lo aprueba o lo rechaza. Mientras
    // no se apruebe, `estado` (el oficial) no cambia.
    timbra_asegurar_columna($pdo, 'tareas', 'estado_propuesto', 'TEXT');
    timbra_asegurar_columna($pdo, 'tareas', 'propuesto_por', 'INTEGER REFERENCES usuarios(id)');
    timbra_asegurar_columna($pdo, 'tareas', 'propuesto_en', 'TEXT');
    // Borrado suave: igual que en etapas, se oculta pero no se pierde.
    timbra_asegurar_columna($pdo, 'tareas', 'eliminado', 'INTEGER NOT NULL DEFAULT 0');
    // Antes solo había "fecha límite" (fecha_limite); ahora la tarea tiene
    // fecha de inicio y fecha de fin. Se agregan las columnas y, para
    // instalaciones que ya tenían fecha_limite capturada, se migra ese
    // valor a fecha_fin la primera vez (no se pierde lo ya capturado).
    timbra_asegurar_columna($pdo, 'tareas', 'fecha_inicio', 'TEXT');
    timbra_asegurar_columna($pdo, 'tareas', 'fecha_fin', 'TEXT');
    $pdo->exec("UPDATE tareas SET fecha_fin = fecha_limite WHERE fecha_fin IS NULL AND fecha_limite IS NOT NULL");

    /* ---------------- Inventario interno ---------------- */
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS inventario_items (
            id                  INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre              TEXT NOT NULL,
            descripcion         TEXT,
            cantidad_total      INTEGER NOT NULL DEFAULT 0,
            cantidad_disponible INTEGER NOT NULL DEFAULT 0,
            activo              INTEGER NOT NULL DEFAULT 1,
            creado_por          INTEGER NOT NULL REFERENCES usuarios(id),
            created_at          TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS inventario_solicitudes (
            id               INTEGER PRIMARY KEY AUTOINCREMENT,
            item_id          INTEGER NOT NULL REFERENCES inventario_items(id),
            solicitado_por   INTEGER NOT NULL REFERENCES usuarios(id),
            cantidad         INTEGER NOT NULL DEFAULT 1,
            estado           TEXT NOT NULL DEFAULT 'pendiente', -- pendiente | aprobado | rechazado
            fecha_solicitud  TEXT NOT NULL DEFAULT (datetime('now')),
            aprobado_por     INTEGER REFERENCES usuarios(id),
            fecha_aprobacion TEXT,
            devuelto         INTEGER NOT NULL DEFAULT 0,
            fecha_devolucion TEXT,
            notas            TEXT
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_inv_solicitudes_item ON inventario_solicitudes(item_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_inv_solicitudes_usuario ON inventario_solicitudes(solicitado_por)");

    /* ---------------- Productos/servicios y ventas ---------------- */
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS productos_servicios (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre      TEXT NOT NULL,
            descripcion TEXT,
            tipo        TEXT NOT NULL DEFAULT 'producto', -- producto | servicio
            precio      REAL NOT NULL DEFAULT 0,
            activo      INTEGER NOT NULL DEFAULT 1,
            creado_por  INTEGER NOT NULL REFERENCES usuarios(id),
            created_at  TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ventas (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            cliente_id   INTEGER REFERENCES clientes(id),
            vendido_por  INTEGER NOT NULL REFERENCES usuarios(id),
            total        REAL NOT NULL DEFAULT 0,
            notas        TEXT,
            created_at   TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS venta_items (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            venta_id        INTEGER NOT NULL REFERENCES ventas(id),
            producto_id     INTEGER REFERENCES productos_servicios(id),
            nombre_item     TEXT NOT NULL,
            tipo_item       TEXT NOT NULL,
            precio_unitario REAL NOT NULL,
            cantidad        INTEGER NOT NULL DEFAULT 1,
            subtotal        REAL NOT NULL
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_venta_items_venta ON venta_items(venta_id)");
    // Para Finanzas: distinguir ingresos ya cobrados de los pendientes de cobro.
    timbra_asegurar_columna($pdo, 'ventas', 'cobrado', 'INTEGER NOT NULL DEFAULT 0');
    timbra_asegurar_columna($pdo, 'proyectos', 'cobrado', 'INTEGER NOT NULL DEFAULT 0');

    /* ---------------- Abonos de proyecto ---------------- */
    // Control fino de lo que se ha pagado de un proyecto externo: en vez
    // de un simple sí/no ("cobrado"), se registran los pagos parciales
    // conforme van llegando. La columna `cobrado` de arriba se deja tal
    // cual (no se borra nada), pero Finanzas ahora calcula lo cobrado de
    // un proyecto sumando sus abonos, que es más preciso que un interruptor.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS proyecto_abonos (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            proyecto_id     INTEGER NOT NULL REFERENCES proyectos(id),
            monto           REAL NOT NULL,
            fecha           TEXT NOT NULL,
            metodo_pago     TEXT,
            notas           TEXT,
            registrado_por  INTEGER NOT NULL REFERENCES usuarios(id),
            created_at      TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_abonos_proyecto ON proyecto_abonos(proyecto_id)");

    /* ---------------- Agenda (citas) ---------------- */
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS citas (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            titulo       TEXT NOT NULL,
            descripcion  TEXT,
            cliente_id   INTEGER REFERENCES clientes(id),
            fecha        TEXT NOT NULL, -- YYYY-MM-DD
            hora_inicio  TEXT NOT NULL, -- HH:MM
            hora_fin     TEXT,
            creado_por   INTEGER NOT NULL REFERENCES usuarios(id),
            cancelada    INTEGER NOT NULL DEFAULT 0,
            created_at   TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_citas_fecha ON citas(fecha)");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cita_participantes (
            cita_id    INTEGER NOT NULL REFERENCES citas(id),
            usuario_id INTEGER NOT NULL REFERENCES usuarios(id),
            estado     TEXT NOT NULL DEFAULT 'invitado', -- invitado | aceptado | rechazado
            PRIMARY KEY (cita_id, usuario_id)
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_cita_part_usuario ON cita_participantes(usuario_id, estado)");

    /* ---------------- Leads ---------------- */
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS leads_config (
            id              INTEGER PRIMARY KEY CHECK (id = 1),
            meta_activo     INTEGER NOT NULL DEFAULT 0,
            linkedin_activo INTEGER NOT NULL DEFAULT 0
        )
    ");
    $pdo->exec("INSERT OR IGNORE INTO leads_config (id, meta_activo, linkedin_activo) VALUES (1, 0, 0)");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS leads (
            id               INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre           TEXT NOT NULL,
            empresa          TEXT,
            email            TEXT,
            telefono         TEXT,
            fuente           TEXT NOT NULL, -- meta | linkedin
            mensaje          TEXT,
            estado           TEXT NOT NULL DEFAULT 'nuevo', -- nuevo | asignado | contactado | convertido | descartado
            asignado_a       INTEGER REFERENCES usuarios(id),
            asignado_por     INTEGER REFERENCES usuarios(id),
            fecha_asignacion TEXT,
            created_at       TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_leads_asignado ON leads(asignado_a)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_leads_fuente ON leads(fuente)");

    // Concede el módulo "leads" a lider_proyecto/colaborador aunque la base
    // ya existiera de antes (los roles ya estaban sembrados y no se vuelven
    // a sembrar solos) — idempotente: no duplica si ya se había concedido.
    $pdo->exec("
        INSERT INTO rol_modulos (rol_id, modulo)
        SELECT r.id, 'leads' FROM roles r
        WHERE r.clave IN ('lider_proyecto', 'colaborador')
          AND NOT EXISTS (SELECT 1 FROM rol_modulos rm WHERE rm.rol_id = r.id AND rm.modulo = 'leads')
    ");

    /* ---------------- Tokens de sesión (para la app, sin cookies) ---------------- */
    // La web sigue usando cookies de sesión de PHP. Un cliente que no
    // puede depender de cookies entre orígenes (la app empaquetada con
    // Capacitor) se identifica con un token en el header
    // "Authorization: Bearer <token>" en cada petición — ver
    // timbra_usuario_por_token() en api/lib/auth.php.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sesiones_token (
            token_hash TEXT PRIMARY KEY,
            usuario_id INTEGER NOT NULL REFERENCES usuarios(id),
            creado_en  TEXT NOT NULL DEFAULT (datetime('now')),
            expira_en  TEXT NOT NULL
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sesiones_token_usuario ON sesiones_token(usuario_id)");

    /* ---------------- Apariencia (logo + colores) ---------------- */
    // Fila única (id=1), igual que leads_config. "paleta" solo guarda el
    // nombre de la paleta preestablecida elegida (o "personalizada") para
    // poder resaltarla en la pantalla — los colores reales que se
    // aplican son siempre color_ink/color_brand/color_brand_dark, sin
    // importar si vinieron de una paleta o los escribió el admin a mano.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS apariencia_config (
            id               INTEGER PRIMARY KEY CHECK (id = 1),
            logo_path        TEXT,
            paleta           TEXT NOT NULL DEFAULT 'verde_sello',
            color_ink        TEXT NOT NULL DEFAULT '#12211F',
            color_brand      TEXT NOT NULL DEFAULT '#0F6E5C',
            color_brand_dark TEXT NOT NULL DEFAULT '#0B4F42',
            updated_at       TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");
    $pdo->exec("INSERT OR IGNORE INTO apariencia_config (id) VALUES (1)");

    timbra_seed_admin($pdo);

    // --- Negocios (multi-negocio) ---
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS negocios (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre     TEXT NOT NULL,
            logo_path  TEXT,
            activo     INTEGER NOT NULL DEFAULT 1,
            creado_por INTEGER NOT NULL REFERENCES usuarios(id),
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");
    // Negocio por defecto para instalaciones nuevas / migración.
    $existeNeg = (int) $pdo->query('SELECT COUNT(*) FROM negocios')->fetchColumn();
    if ($existeNeg === 0) {
        $pdo->prepare('INSERT INTO negocios (nombre, creado_por) VALUES (?, ?)')
            ->execute(['Negocio principal', 1]);
    }

    timbra_asegurar_columna($pdo, 'ventas', 'negocio_id', 'INTEGER REFERENCES negocios(id)');
    timbra_asegurar_columna($pdo, 'proyectos', 'negocio_id', 'INTEGER REFERENCES negocios(id)');
    // Asigna el negocio por defecto a registros existentes sin negocio.
    $pdo->exec('UPDATE ventas SET negocio_id = (SELECT id FROM negocios ORDER BY id LIMIT 1) WHERE negocio_id IS NULL');
    $pdo->exec('UPDATE proyectos SET negocio_id = (SELECT id FROM negocios ORDER BY id LIMIT 1) WHERE negocio_id IS NULL');

    // Migración de seguridad: los tokens de app se guardan hasheados
    // (antes se guardaban en claro). Se invalidan los existentes una sola
    // vez para forzar re-login de la app. El marcador evita repetirlo.
    $marcador = TIMBRA_ROOT . '/storage/.tokens_hasheados';
    if (!is_file($marcador)) {
        $pdo->exec('DELETE FROM sesiones_token');
        @file_put_contents($marcador, date('c'));
    }
}

/**
 * Agrega una columna a una tabla ya existente si todavía no la tiene.
 * SQLite no soporta "ADD COLUMN IF NOT EXISTS", así que se revisa con
 * PRAGMA table_info antes de alterar. Permite evolucionar el esquema sin
 * perder los datos de instalaciones que ya estaban corriendo.
 */
function timbra_asegurar_columna(PDO $pdo, string $tabla, string $columna, string $tipoSql): void
{
    $columnas = $pdo->query("PRAGMA table_info($tabla)")->fetchAll(PDO::FETCH_ASSOC);
    $nombres = array_column($columnas, 'name');
    if (!in_array($columna, $nombres, true)) {
        $pdo->exec("ALTER TABLE $tabla ADD COLUMN $columna $tipoSql");
    }
}

/**
 * Siembra los 4 roles originales (antes fijos en TIMBRA_MODULOS) la primera
 * vez que se crea la tabla `roles`, con exactamente los mismos permisos que
 * tenían codificados — para que una instalación ya corriendo no note ningún
 * cambio de comportamiento al actualizar. A partir de aquí, todo se
 * administra desde la pantalla de Roles.
 */
function timbra_seed_roles(PDO $pdo): void
{
    $existe = (int) $pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn();
    if ($existe > 0) {
        return;
    }

    $rolesOriginales = [
        // clave => [nombre, es_sistema, módulos]
        'admin'          => ['Administrador', 1, []], // el bypass de admin es fijo en código, no necesita filas aquí
        'contador'       => ['Contador', 1, ['dashboard']],
        'lider_proyecto' => ['Líder de proyecto', 1, ['tareas', 'clientes']],
        'colaborador'    => ['Colaborador', 1, ['tareas', 'inventario', 'ventas']],
    ];

    $insertRol = $pdo->prepare('INSERT INTO roles (clave, nombre, es_sistema) VALUES (?, ?, 1)');
    $insertModulo = $pdo->prepare('INSERT INTO rol_modulos (rol_id, modulo) VALUES (?, ?)');

    foreach ($rolesOriginales as $clave => [$nombre, , $modulos]) {
        $insertRol->execute([$clave, $nombre]);
        $rolId = (int) $pdo->lastInsertId();
        foreach ($modulos as $modulo) {
            $insertModulo->execute([$rolId, $modulo]);
        }
    }

    // contador y lider_proyecto también tenían acceso a inventario/ventas en
    // la configuración original — se agregan aparte para que la tabla de
    // arriba quede legible por rol.
    $stmt = $pdo->query("SELECT id, clave FROM roles WHERE clave IN ('contador', 'lider_proyecto')");
    foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $rolId => $clave) {
        foreach (['inventario', 'ventas'] as $modulo) {
            $insertModulo->execute([$rolId, $modulo]);
        }
    }
}

function timbra_seed_admin(PDO $pdo): void
{
    $existe = (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    if ($existe > 0) {
        return;
    }

    $pdo->prepare('
        INSERT INTO usuarios (nombre, email, password_hash, rol)
        VALUES (:nombre, :email, :hash, :rol)
    ')->execute([
        'nombre' => 'Administrador',
        'email'  => 'admin@m-core.local',
        'hash'   => password_hash('CambiaEstaClave123', PASSWORD_DEFAULT),
        'rol'    => 'admin',
    ]);
}
