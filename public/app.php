<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>CRUD Personas</title>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="style.css?v=1">
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script>
</head>
<body>

<div class="card">
    <div class="card-header">
        <div>
            <h1>Registro de Personas</h1>
            <p class="subtitulo">Esta página permite ver los registros de personas y modificarlos</p>
        </div>
        <button class="secundario" onclick="abrirModal('modalCrear')">+ Nueva persona</button>
    </div>
    <div class="caja-interna">
        <div class="fila-filtro">
            <div class="select-envoltorio">
                <select id="filtroBusqueda" onchange="cambiarFiltro()">
                    <option value="todos">Listar todos</option>
                    <option value="nombre">Por nombre / apellido</option>
                    <option value="documento">Por documento</option>
                </select>
            </div>
            <div class="grupo-busqueda">
                <input type="text" id="terminoNombre" placeholder="Nombre..." style="display:none;">
                <input type="text" id="terminoApellido" placeholder="Apellido..." style="display:none;">
                <input type="text" id="terminoDocumento" placeholder="Nro. de documento..." style="display:none;" inputmode="numeric" pattern="[0-9]*">
                <button class="primario" onclick="prepararBusqueda()">Buscar</button>
            </div>
        </div>
        <div id="errorFiltro" class="error-filtro" style="display:none;"></div>
    </div>
</div>

<div class="card" id="cardResultados" style="text-align:left; display:none;">
    <h3 style="margin-top:0;">Personas registradas</h3>
    <table id="tablaPersonas">
        <thead>
            <tr><th>Nombres</th><th>Apellidos</th><th>Documento</th><th>Edad</th><th>Acciones</th></tr>
        </thead>
        <tbody></tbody>
    </table>
    <div class="pagination">
        <span id="infoPaginacion"></span>
        <div class="botones">
            <button class="secundario" id="btnAnterior" onclick="cambiarPagina(-1)">← Anterior</button>
            <button class="secundario" id="btnSiguiente" onclick="cambiarPagina(1)">Siguiente →</button>
        </div>
    </div>
</div>


<div class="modal-overlay" id="modalCaptcha">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Verificación</h3>
            <!-- <button class="cerrar-modal" onclick="cerrarModal('modalCaptcha')">✕</button>-->
        </div>
        <p style="font-size:14px; color:#64748b;">Confirmá que sos humano para continuar.</p>
        <div id="turnstileContainer"></div>
    </div>
</div>

<div class="modal-overlay" id="modalCargando">
    <div class="modal-box" style="text-align:center;">
        <div class="spinner"></div>
        <p style="margin-top:16px; color:#64748b; font-size:14px;">Cargando datos...</p>
    </div>
</div>

<!-- Modal: Nueva persona -->
<div class="modal-overlay" id="modalCrear">
    <div class="modal-box ancho">
        <div class="modal-header">
            <h3>Nueva persona</h3>
            <button class="cerrar-modal" onclick="cerrarModal('modalCrear')">✕</button>
        </div>
        <form id="formCrear">
            <label>Nombres <input type="text" name="nombres" required pattern="[A-Za-zÀ-ÿ\s]+"
                oninvalid="this.setCustomValidity('Los nombres sólo pueden contener letras')"
                oninput="this.setCustomValidity('')"></label>
            <label>Apellidos <input type="text" name="apellidos" required pattern="[A-Za-zÀ-ÿ\s]+"
                oninvalid="this.setCustomValidity('Los apellidos sólo pueden contener letras')"
                oninput="this.setCustomValidity('')"></label>
            <label>Nro. Documento <input type="text" name="nro_documento" required inputmode="numeric" pattern="[0-9]*"
                oninvalid="this.setCustomValidity('El número de documento debe ser numérico')"
                oninput="this.setCustomValidity('')"></label>
            <label>Fecha de nacimiento
                <input type="date" name="fecha_nacimiento" max="<?= date('Y-m-d') ?>" required
                    oninvalid="this.setCustomValidity('La fecha de nacimiento no puede ser futura')"
                    oninput="this.setCustomValidity('')">
            </label>
            <label>Foto cédula (frente)</label>
            <div class="file-drop">
                <span class="file-label" id="labelFrente">Click para seleccionar imagen</span>
                <input type="file" name="foto_frente" accept="image/*" required onchange="previsualizar(this, 'previewFrente', 'labelFrente')">
                <img class="file-preview" id="previewFrente">
            </div>

            <label>Foto cédula (dorso)</label>
            <div class="file-drop">
                <span class="file-label" id="labelDorso">Click para seleccionar imagen</span>
                <input type="file" name="foto_dorso" accept="image/*" required onchange="previsualizar(this, 'previewDorso', 'labelDorso')">
                <img class="file-preview" id="previewDorso">
            </div>

            <button type="submit" class="primario" style="margin-top:18px; width:100%;">Guardar</button>
        </form>
    </div>
</div>

<!-- Modal: Editar persona -->
<div class="modal-overlay" id="modalEditar">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Editar persona</h3>
            <button class="cerrar-modal" onclick="cerrarModal('modalEditar')">✕</button>
        </div>
        <form id="formEditar">
            <input type="hidden" id="editarId">
            <label>Nombres <input type="text" id="editarNombres" required pattern="[A-Za-zÀ-ÿ\s]+"
                oninvalid="this.setCustomValidity('Los nombres sólo pueden contener letras')"
                oninput="this.setCustomValidity('')"></label>
            <label>Apellidos <input type="text" id="editarApellidos" required pattern="[A-Za-zÀ-ÿ\s]+"
                oninvalid="this.setCustomValidity('Los apellidos sólo pueden contener letras')"
                oninput="this.setCustomValidity('')"></label>
            <label>Fecha de nacimiento
                <input type="date" id="editarFecha" max="<?= date('Y-m-d') ?>" required
                    oninvalid="this.setCustomValidity('La fecha de nacimiento no puede ser futura')"
                    oninput="this.setCustomValidity('')">
            </label>

            <label>Foto cédula (frente) — dejar vacío para no cambiar</label>
            <div class="file-drop">
                <span class="file-label" id="labelEditarFrente">Click para reemplazar imagen</span>
                <input type="file" id="editarFrente" accept="image/*" onchange="previsualizar(this, 'previewEditarFrente', 'labelEditarFrente')">
                <img class="file-preview" id="previewEditarFrente">
            </div>

            <label>Foto cédula (dorso) — dejar vacío para no cambiar</label>
            <div class="file-drop">
                <span class="file-label" id="labelEditarDorso">Click para reemplazar imagen</span>
                <input type="file" id="editarDorso" accept="image/*" onchange="previsualizar(this, 'previewEditarDorso', 'labelEditarDorso')">
                <img class="file-preview" id="previewEditarDorso">
            </div>

            <button type="submit" class="primario" style="margin-top:18px; width:100%;">Guardar cambios</button>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalVer">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Documento de identidad</h3>
            <button class="cerrar-modal" onclick="cerrarModal('modalVer')">✕</button>
        </div>
        <p style="font-size:13px; color:#64748b; margin-top:0;">Frente</p>
        <img id="verFrente" style="width:100%; display:block; border-radius:8px; margin-bottom:16px;">
        <p style="font-size:13px; color:#64748b;">Dorso</p>
        <img id="verDorso" style="width:100%; border-radius:8px;">
    </div>
</div>

<script>
const API = window.location.origin;
let paginaActual = 1;
let totalPersonas = 0;
const PORPAGINA = 20;
let captchaToken = null;
let turnstileWidgetId = null;

function abrirModal(id) { document.getElementById(id).classList.add('abierto'); }
function cerrarModal(id) { document.getElementById(id).classList.remove('abierto'); }

document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', (e) => {
        if (overlay.id === 'modalCaptcha' || overlay.id === 'modalCargando') return; // no se cierran haciendo clic afuera ;)
        if (e.target === overlay) overlay.classList.remove('abierto');
    });
});

function previsualizar(input, previewId, labelId) {
    const preview = document.getElementById(previewId);
    const label = document.getElementById(labelId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = (e) => {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
        label.textContent = input.files[0].name;
    }
}

function cambiarFiltro() {
    const filtro = document.getElementById('filtroBusqueda').value;
    const esNombre = filtro === 'nombre';
    document.getElementById('terminoNombre').style.display = esNombre ? 'inline-block' : 'none';
    document.getElementById('terminoApellido').style.display = esNombre ? 'inline-block' : 'none';
    document.getElementById('terminoDocumento').style.display = filtro === 'documento' ? 'inline-block' : 'none';

    document.querySelectorAll('#terminoNombre, #terminoApellido, #terminoDocumento').forEach(input => {
        marcarCampoInvalido(input, false);
    });
    mostrarErrorFiltro(null);
}

function obtenerErrorFiltro() {
    const filtro = document.getElementById('filtroBusqueda').value;

    if (filtro === 'nombre') {
        const nombre = document.getElementById('terminoNombre').value.trim();
        const apellido = document.getElementById('terminoApellido').value.trim();
        if (!nombre && !apellido) return 'Completá al menos nombre o apellido';
        const soloLetras = /^[\p{L}\s]*$/u;
        if (!soloLetras.test(nombre) || !soloLetras.test(apellido)) return 'Nombre y apellido sólo pueden contener letras';
    } else if (filtro === 'documento') {
        const documento = document.getElementById('terminoDocumento').value.trim();
        if (documento.length < 5) return 'El documento debe tener al menos 5 caracteres';
        if (!/^\d+$/.test(documento)) return 'El documento sólo puede contener números';
    }

    return null;
}

function marcarCampoInvalido(input, invalido) {
    input.classList.toggle('campo-invalido', invalido);
}

function mostrarErrorFiltro(mensaje) {
    const contenedor = document.getElementById('errorFiltro');
    contenedor.textContent = mensaje || '';
    contenedor.style.display = mensaje ? 'block' : 'none';
}

function validarCamposFiltroActual(mostrarError) {
    const filtro = document.getElementById('filtroBusqueda').value;
    const error = obtenerErrorFiltro();

    if (filtro === 'nombre') {
        marcarCampoInvalido(document.getElementById('terminoNombre'), !!error);
        marcarCampoInvalido(document.getElementById('terminoApellido'), !!error);
    } else if (filtro === 'documento') {
        marcarCampoInvalido(document.getElementById('terminoDocumento'), !!error);
    }

    if (mostrarError) {
        mostrarErrorFiltro(error);
    }

    return error;
}

document.getElementById('terminoNombre').addEventListener('blur', () => validarCamposFiltroActual(true));
document.getElementById('terminoApellido').addEventListener('blur', () => validarCamposFiltroActual(true));
document.getElementById('terminoDocumento').addEventListener('blur', () => validarCamposFiltroActual(true));

document.querySelectorAll('#terminoNombre, #terminoApellido, #terminoDocumento').forEach(input => {
    input.addEventListener('input', () => {
        marcarCampoInvalido(input, false);
        mostrarErrorFiltro(null);
    });
});

document.querySelectorAll('#terminoDocumento, input[name="nro_documento"]').forEach(input => {
    input.addEventListener('input', () => {
        input.value = input.value.replace(/\D/g, '');
    });
});

document.querySelectorAll('#terminoNombre, #terminoApellido, input[name="nombres"], input[name="apellidos"], #editarNombres, #editarApellidos').forEach(input => {
    input.addEventListener('input', () => {
        input.value = input.value.replace(/[^\p{L}\s]/gu, '');
    });
});

function prepararBusqueda() {
    if (validarCamposFiltroActual(true)) return;

    captchaToken = null;
    abrirModal('modalCaptcha');

    if (turnstileWidgetId !== null) {
        turnstile.remove(turnstileWidgetId);
    }

    turnstileWidgetId = turnstile.render('#turnstileContainer', {
        sitekey: '0x4AAAAAAEWBvb_HlVrAwpgW',
        callback: onCaptchaResuelto
    });
}

function onCaptchaResuelto(token) {
    captchaToken = token;
    ejecutarBusqueda(1);
}

async function ejecutarBusqueda(pagina) {
    const filtro = document.getElementById('filtroBusqueda').value;

    if (validarCamposFiltroActual(true)) {
        cerrarModal('modalCaptcha');
        return;
    }

    let params = new URLSearchParams({ filtro, pagina, captcha_token: captchaToken });

    if (filtro === 'nombre') {
        params.append('nombre', document.getElementById('terminoNombre').value.trim());
        params.append('apellido', document.getElementById('terminoApellido').value.trim());
    } else if (filtro === 'documento') {
        params.append('documento', document.getElementById('terminoDocumento').value.trim());
    }

    cerrarModal('modalCaptcha');
    abrirModal('modalCargando');

    const res = await fetch(`${API}/buscar?${params.toString()}`);
    const data = await res.json();

    cerrarModal('modalCargando');

    if (!res.ok) {
        Swal.fire({ icon: 'error', title: 'Error en la búsqueda', text: data.error });
        return;
    }

    paginaActual = data.pagina;
    totalPersonas = data.total;
    renderTabla(data.data);
}

async function refrescarTabla(pagina) {
    const filtro = document.getElementById('filtroBusqueda').value;
    let params = new URLSearchParams({ filtro, pagina });

    if (filtro === 'nombre') {
        params.append('nombre', document.getElementById('terminoNombre').value.trim());
        params.append('apellido', document.getElementById('terminoApellido').value.trim());
    } else if (filtro === 'documento') {
        params.append('documento', document.getElementById('terminoDocumento').value.trim());
    }

    const res = await fetch(`${API}/personas-refrescar?${params.toString()}`);
    const data = await res.json();

    if (!res.ok) {
        Swal.fire({ icon: 'warning', title: 'La verificación expiró', text: 'Volvé a buscar para actualizar el listado.' });
        return;
    }

    paginaActual = data.pagina;
    totalPersonas = data.total;
    renderTabla(data.data);
}

function renderTabla(personas) {
    document.getElementById('cardResultados').style.display = 'block';

    const tbody = document.querySelector('#tablaPersonas tbody');
    tbody.innerHTML = '';

    if (personas.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; color:#64748b;">No existen registros para esos datos</td></tr>';
    }

    personas.forEach(p => {
        const fila = document.createElement('tr');
        fila.innerHTML = `
            <td>${p.nombres}</td>
            <td>${p.apellidos}</td>
            <td>${p.nro_documento}</td>
            <td>${p.edad}</td>
            <td>
                <button class="secundario" onclick="verDocumento('${p.foto_frente}', '${p.foto_dorso}')" style="padding:6px 12px; font-size:13px;">Cédula</button>
                <button class="edit" onclick='abrirEditar(${JSON.stringify(p)})'>Editar</button>
                <button class="danger" onclick="eliminarPersona(${p.id})">Eliminar</button>
            </td>
        `;
        tbody.appendChild(fila);
    });

    const totalPaginas = Math.max(1, Math.ceil(totalPersonas / PORPAGINA));
    document.getElementById('infoPaginacion').innerText =
        `Página ${paginaActual} de ${totalPaginas} — ${totalPersonas} personas`;
    document.getElementById('btnAnterior').disabled = paginaActual <= 1;
    document.getElementById('btnSiguiente').disabled = paginaActual >= totalPaginas;
}

function cambiarPagina(delta) {
    refrescarTabla(paginaActual + delta);
}

function verDocumento(frente, dorso) {
    document.getElementById('verFrente').src = `${API}/cedulas/${frente}`;
    document.getElementById('verDorso').src = `${API}/cedulas/${dorso}`;
    abrirModal('modalVer');
}

async function eliminarPersona(id) {
    const confirmacion = await Swal.fire({
        title: '¿Eliminar esta persona?',
        text: 'Esta acción no se puede deshacer y también se eliminarán sus imágenes de cédula.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc2626'
    });
    if (!confirmacion.isConfirmed) return;

    const res = await fetch(`${API}/personas/${id}`, { method: 'DELETE' });
    refrescarTabla(paginaActual);

    if (res.ok) {
        Swal.fire({ icon: 'success', title: 'Persona eliminada', timer: 1200, showConfirmButton: false });
    } else {
        Swal.fire({ icon: 'error', title: 'No se pudo eliminar', text: 'Intentá de nuevo en unos segundos.' });
    }
}

function abrirEditar(persona) {
    document.getElementById('editarId').value = persona.id;
    document.getElementById('editarNombres').value = persona.nombres;
    document.getElementById('editarApellidos').value = persona.apellidos;
    document.getElementById('editarFecha').value = persona.fecha_nacimiento;
    document.getElementById('editarFrente').value = '';
    document.getElementById('editarDorso').value = '';
    document.getElementById('previewEditarFrente').style.display = 'none';
    document.getElementById('previewEditarDorso').style.display = 'none';
    document.getElementById('labelEditarFrente').textContent = 'Click para reemplazar imagen';
    document.getElementById('labelEditarDorso').textContent = 'Click para reemplazar imagen';
    abrirModal('modalEditar');
}

document.getElementById('formEditar').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('editarId').value;

    const formData = new FormData();
    formData.append('_method', 'PUT');
    formData.append('nombres', document.getElementById('editarNombres').value);
    formData.append('apellidos', document.getElementById('editarApellidos').value);
    formData.append('fecha_nacimiento', document.getElementById('editarFecha').value);

    const frenteInput = document.getElementById('editarFrente');
    const dorsoInput = document.getElementById('editarDorso');
    if (frenteInput.files[0]) formData.append('foto_frente', frenteInput.files[0]);
    if (dorsoInput.files[0]) formData.append('foto_dorso', dorsoInput.files[0]);

    const res = await fetch(`${API}/personas/${id}`, { method: 'POST', body: formData });
    const resultado = await res.json();

    if (res.ok) {
        refrescarTabla(paginaActual);
        cerrarModal('modalEditar');
        Swal.fire({ icon: 'success', title: 'Persona actualizada', text: 'Los cambios se guardaron correctamente.', timer: 1500, showConfirmButton: false });
    } else {
        Swal.fire({ icon: 'error', title: 'No se pudo actualizar', text: resultado.error || 'Verificá los datos e intentá de nuevo.' });
    }
});

document.getElementById('formCrear').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);

    const res = await fetch(`${API}/personas`, { method: 'POST', body: formData });
    const resultado = await res.json();

    if (res.ok) {
        e.target.reset();
        document.querySelectorAll('.file-preview').forEach(img => img.style.display = 'none');
        document.getElementById('labelFrente').textContent = 'Click para seleccionar imagen';
        document.getElementById('labelDorso').textContent = 'Click para seleccionar imagen';
        cerrarModal('modalCrear');
        Swal.fire({ icon: 'success', title: 'Persona creada correctamente', text: `Se registró a ${formData.get('nombres')} ${formData.get('apellidos')}.`, timer: 1800, showConfirmButton: false });
    } else {
        Swal.fire({ icon: 'error', title: 'No se pudo crear la persona', text: resultado.error });
    }
});
</script>

</body>
</html>