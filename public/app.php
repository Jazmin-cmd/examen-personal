<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>CRUD Personas</title>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="style.css?v=1">
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</head>
<body>

<div class="card">
    <h1>Registro de Personas</h1>
    <p class="subtitulo">Esta página permite ver los registros de personas y modificarlos</p>
    <div class="caja-interna">
        <button class="verde" onclick="toggleListado()">Listar personas</button>
        <button class="secundario" onclick="abrirModal('modalCrear')">+ Nueva persona</button>
        <button class="primario" onclick="abrirModal('modalBuscar')">Buscar</button>
    </div>
</div>

<div class="card" id="cardListado" style="text-align:left; display:none;">
    <h3 style="margin-top:0;">Personas Registradas</h3>
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

<!-- Modal: Nueva persona -->
<div class="modal-overlay" id="modalCrear">
    <div class="modal-box ancho">
        <div class="modal-header">
            <h3>Nueva persona</h3>
            <button class="cerrar-modal" onclick="cerrarModal('modalCrear')">✕</button>
        </div>
        <form id="formCrear">
            <label>Nombres <input type="text" name="nombres" required></label>
            <label>Apellidos <input type="text" name="apellidos" required></label>
            <label>Nro. Documento <input type="text" name="nro_documento" required></label>
            <label>Fecha de nacimiento <input type="date" name="fecha_nacimiento" required></label>

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
            <label>Nombres <input type="text" id="editarNombres" required></label>
            <label>Apellidos <input type="text" id="editarApellidos" required></label>
            <label>Fecha de nacimiento <input type="date" id="editarFecha" required></label>

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

<!-- Modal: Buscar -->
<div class="modal-overlay" id="modalBuscar">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Buscar personas</h3>
            <button class="cerrar-modal" onclick="cerrarModal('modalBuscar')">✕</button>
        </div>
        <form id="formBuscar">
            <label>Nombre, apellido o documento
                <input type="text" id="terminoBusqueda" required>
            </label>
            <div class="cf-turnstile" data-sitekey="0x4AAAAAAEWBvb_HlVrAwpgW" data-callback="onCaptchaResuelto"></div>
            <button type="submit" id="btnBuscar" class="primario" disabled style="margin-top:18px; width:100%;">Buscar</button>
        </form>
        <div id="resultadosBusqueda" style="margin-top:16px;"></div>
    </div>
</div>

<script>
const API = window.location.origin;
let paginaActual = 1;
let totalPersonas = 0;
const PORPAGINA = 20;

function abrirModal(id) { document.getElementById(id).classList.add('abierto'); }
function cerrarModal(id) { document.getElementById(id).classList.remove('abierto'); }

document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', (e) => {
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

async function cargarPersonas(pagina = 1) {
    const res = await fetch(`${API}/personas?pagina=${pagina}`);
    const data = await res.json();

    paginaActual = data.pagina;
    totalPersonas = data.total;

    const tbody = document.querySelector('#tablaPersonas tbody');
    tbody.innerHTML = '';

    data.data.forEach(p => {
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
    cargarPersonas(paginaActual + delta);
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
    cargarPersonas(paginaActual);

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
        cargarPersonas(paginaActual);
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
        cargarPersonas(1);
        cerrarModal('modalCrear');
        Swal.fire({ icon: 'success', title: 'Persona creada correctamente', text: `Se registró a ${formData.get('nombres')} ${formData.get('apellidos')}.`, timer: 1800, showConfirmButton: false });
    } else {
        Swal.fire({ icon: 'error', title: 'No se pudo crear la persona', text: resultado.error });
    }

});

let captchaToken = null;
function onCaptchaResuelto(token) {
    captchaToken = token;
    document.getElementById('btnBuscar').disabled = false;
}

document.getElementById('formBuscar').addEventListener('submit', async (e) => {
    e.preventDefault();
    const termino = document.getElementById('terminoBusqueda').value;
    const div = document.getElementById('resultadosBusqueda');

    div.innerHTML = '<p style="text-align:center; color:#64748b;">Buscando...</p>';

    const res = await fetch(`${API}/buscar?q=${encodeURIComponent(termino)}&captcha_token=${captchaToken}`);
    const data = await res.json();

    if (!res.ok) {
        div.innerHTML = '';
        Swal.fire({ icon: 'error', title: 'Error en la búsqueda', text: data.error });
        return;
    }

    div.innerHTML = `<p><strong>${data.total} resultado(s)</strong></p>` + data.data.map(p =>
        `<p>${p.nombres} ${p.apellidos} — ${p.nro_documento} (${p.edad} años)</p>`
    ).join('');
});

function verDocumento(frente, dorso) {
    document.getElementById('verFrente').src = `${API}/cedulas/${frente}`;
    document.getElementById('verDorso').src = `${API}/cedulas/${dorso}`;
    abrirModal('modalVer');
}

let listadoVisible = false;
function toggleListado() {
    listadoVisible = !listadoVisible;
    document.getElementById('cardListado').style.display = listadoVisible ? 'block' : 'none';
    if (listadoVisible) cargarPersonas(1);
}

//cargarPersonas();
</script>

</body>
</html>