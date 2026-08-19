<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>CRUD Personas</title>
<style>
    body { font-family: sans-serif; max-width: 900px; margin: 30px auto; padding: 0 15px; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
    form { border: 1px solid #ccc; padding: 15px; margin-bottom: 20px; }
    label { display: block; margin-top: 10px; }
    input { width: 100%; padding: 5px; box-sizing: border-box; }
    button { margin-top: 15px; padding: 8px 15px; }
</style>
</head>
<body>

<h1>Registro de Personas</h1>

<form id="formCrear">
    <h3>Nueva persona</h3>
    <label>Nombres <input type="text" name="nombres" required></label>
    <label>Apellidos <input type="text" name="apellidos" required></label>
    <label>Nro. Documento <input type="text" name="nro_documento" required></label>
    <label>Fecha de nacimiento <input type="date" name="fecha_nacimiento" required></label>
    <label>Foto cédula (frente) <input type="file" name="foto_frente" accept="image/*" required></label>
    <label>Foto cédula (dorso) <input type="file" name="foto_dorso" accept="image/*" required></label>
    <button type="submit">Guardar</button>
    <p id="mensajeCrear"></p>
</form>

<table id="tablaPersonas">
    <thead>
        <tr><th>Nombres</th><th>Apellidos</th><th>Documento</th><th>Edad</th><th>Acciones</th></tr>
    </thead>
    <tbody></tbody>
</table>

<div id="paginacion"></div>

<script>
const API = 'http://localhost:8000';

async function cargarPersonas(pagina = 1) {
    const res = await fetch(`${API}/personas?pagina=${pagina}`);
    const data = await res.json();

    const tbody = document.querySelector('#tablaPersonas tbody');
    tbody.innerHTML = '';

    data.data.forEach(p => {
        const fila = document.createElement('tr');
        fila.innerHTML = `
            <td>${p.nombres}</td>
            <td>${p.apellidos}</td>
            <td>${p.nro_documento}</td>
            <td>${p.edad}</td>
            <td><button onclick="eliminarPersona(${p.id})">Eliminar</button></td>
        `;
        tbody.appendChild(fila);
    });

    document.getElementById('paginacion').innerText =
        `Página ${data.pagina} — Total: ${data.total}`;
}

async function eliminarPersona(id) {
    if (!confirm('¿Eliminar esta persona?')) return;
    await fetch(`${API}/personas/${id}`, { method: 'DELETE' });
    cargarPersonas();
}

document.getElementById('formCrear').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);

    const res = await fetch(`${API}/personas`, {
        method: 'POST',
        body: formData
    });

    const resultado = await res.json();
    const mensaje = document.getElementById('mensajeCrear');

    if (res.ok) {
        mensaje.innerText = 'Persona creada correctamente';
        e.target.reset();
        cargarPersonas();
    } else {
        mensaje.innerText = 'Error: ' + resultado.error;
    }
});

cargarPersonas();
</script>

</body>
</html>