// Variables globales
let asistencias = [];

// Inicialización cuando el DOM está listo
document.addEventListener('DOMContentLoaded', function() {
    inicializarReloj();
    cargarAsistencias();
    cargarEstadisticas();
    
    // Establecer fechas por defecto (hoy y hace 7 días)
    const hoy = new Date();
    const hace7Dias = new Date(hoy.getTime() - 7 * 24 * 60 * 60 * 1000);
    
    document.getElementById('fechaFin').value = hoy.toISOString().split('T')[0];
    document.getElementById('fechaInicio').value = hace7Dias.toISOString().split('T')[0];
});

// Reloj en tiempo real
function inicializarReloj() {
    function actualizarReloj() {
        const ahora = new Date();
        const horas = String(ahora.getHours()).padStart(2, '0');
        const minutos = String(ahora.getMinutes()).padStart(2, '0');
        const segundos = String(ahora.getSeconds()).padStart(2, '0');
        
        document.getElementById('reloj').textContent = `${horas}:${minutos}:${segundos}`;
    }
    
    actualizarReloj();
    setInterval(actualizarReloj, 1000);
}

// Mostrar alertas
function mostrarAlerta(mensaje, tipo = 'info') {
    const alertContainer = document.getElementById('alertContainer');
    const alertId = 'alert-' + Date.now();
    
    const alertHtml = `
        <div class="alert alert-${tipo} alert-dismissible fade show" id="${alertId}" role="alert">
            ${mensaje}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    `;
    
    alertContainer.insertAdjacentHTML('beforeend', alertHtml);
    
    // Auto-eliminar después de 5 segundos
    setTimeout(() => {
        const alert = document.getElementById(alertId);
        if (alert) {
            alert.remove();
        }
    }, 5000);
}

// Mostrar/ocultar loading
function toggleLoading(show) {
    document.getElementById('loading').style.display = show ? 'block' : 'none';
}

// Cargar asistencias
async function cargarAsistencias() {
    toggleLoading(true);
    
    try {
        const params = new URLSearchParams();
        
        const fechaInicio = document.getElementById('fechaInicio').value;
        const fechaFin = document.getElementById('fechaFin').value;
        const empleadoId = document.getElementById('empleadoIdFilter').value;
        
        if (fechaInicio) params.append('fecha_inicio', fechaInicio);
        if (fechaFin) params.append('fecha_fin', fechaFin);
        if (empleadoId) params.append('empleado_id', empleadoId);
        
        const response = await fetch('/api/asistencia?' + params.toString());
        const result = await response.json();
        
        if (result.success) {
            asistencias = result.data;
            renderTablaAsistencias();
        } else {
            mostrarAlerta('Error al cargar las asistencias: ' + result.message, 'danger');
        }
    } catch (error) {
        mostrarAlerta('Error de conexión: ' + error.message, 'danger');
    } finally {
        toggleLoading(false);
    }
}

// Renderizar tabla de asistencias
function renderTablaAsistencias() {
    const tbody = document.getElementById('tablaAsistencias');
    
    if (asistencias.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">No se encontraron registros</td></tr>';
        return;
    }
    
    tbody.innerHTML = asistencias.map(asistencia => `
        <tr>
            <td>${asistencia.id}</td>
            <td>${asistencia.empleado_id}</td>
            <td>${formatDate(asistencia.fecha)}</td>
            <td>${asistencia.hora_entrada || '-'}</td>
            <td>${asistencia.hora_salida || '-'}</td>
            <td><span class="badge badge-${getEstadoClass(asistencia.estado)}">${asistencia.estado}</span></td>
            <td>
                <button class="btn btn-sm btn-primary" onclick="editarAsistencia(${asistencia.id})">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-danger" onclick="eliminarAsistencia(${asistencia.id})">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

// Obtener clase CSS para estado
function getEstadoClass(estado) {
    const clases = {
        'presente': 'presente',
        'ausente': 'ausente',
        'tarde': 'tarde',
        'permiso': 'permiso'
    };
    return clases[estado] || 'secondary';
}

// Formatear fecha
function formatDate(fechaString) {
    if (!fechaString) return '-';
    const fecha = new Date(fechaString);
    return fecha.toLocaleDateString('es-ES');
}

// Marcar asistencia
async function marcarAsistencia() {
    const empleadoId = document.getElementById('empleadoId').value;
    
    if (!empleadoId) {
        mostrarAlerta('Por favor ingrese su ID de empleado', 'warning');
        return;
    }
    
    try {
        const response = await fetch('/api/asistencia/marcar', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                empleado_id: parseInt(empleadoId)
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            const tipo = result.data.tipo;
            const hora = result.data.hora;
            const estado = result.data.estado;
            
            mostrarAlerta(
                `✅ ${tipo.charAt(0).toUpperCase() + tipo.slice(1)} registrada a las ${hora}` + 
                (estado ? ` (${estado})` : ''), 
                'success'
            );
            
            // Limpiar campo y recargar datos
            document.getElementById('empleadoId').value = '';
            cargarAsistencias();
            cargarEstadisticas();
        } else {
            mostrarAlerta('Error: ' + result.message, 'danger');
        }
    } catch (error) {
        mostrarAlerta('Error de conexión: ' + error.message, 'danger');
    }
}

// Cargar estadísticas
async function cargarEstadisticas() {
    try {
        const fechaInicio = document.getElementById('fechaInicio').value;
        const fechaFin = document.getElementById('fechaFin').value;
        
        if (!fechaInicio || !fechaFin) {
            return;
        }
        
        const params = new URLSearchParams({
            fecha_inicio: fechaInicio,
            fecha_fin: fechaFin
        });
        
        const response = await fetch('/api/asistencia/resumen?' + params.toString());
        const result = await response.json();
        
        if (result.success) {
            const stats = result.data;
            document.getElementById('totalPresentes').textContent = stats.presentes || 0;
            document.getElementById('totalAusentes').textContent = stats.ausentes || 0;
            document.getElementById('totalTardanzas').textContent = stats.tardanzas || 0;
            document.getElementById('totalPermisos').textContent = stats.permisos || 0;
        }
    } catch (error) {
        console.error('Error al cargar estadísticas:', error);
    }
}

// Editar asistencia
async function editarAsistencia(id) {
    const asistencia = asistencias.find(a => a.id === id);
    
    if (!asistencia) {
        mostrarAlerta('Registro no encontrado', 'danger');
        return;
    }
    
    // Llenar formulario
    document.getElementById('editId').value = asistencia.id;
    document.getElementById('editEmpleadoId').value = asistencia.empleado_id;
    document.getElementById('editFecha').value = asistencia.fecha;
    document.getElementById('editHoraEntrada').value = asistencia.hora_entrada || '';
    document.getElementById('editHoraSalida').value = asistencia.hora_salida || '';
    document.getElementById('editEstado').value = asistencia.estado;
    
    // Mostrar modal
    $('#editarModal').modal('show');
}

// Guardar cambios
async function guardarCambios() {
    const id = document.getElementById('editId').value;
    
    const data = {
        empleado_id: parseInt(document.getElementById('editEmpleadoId').value),
        fecha: document.getElementById('editFecha').value,
        hora_entrada: document.getElementById('editHoraEntrada').value || null,
        hora_salida: document.getElementById('editHoraSalida').value || null,
        estado: document.getElementById('editEstado').value
    };
    
    try {
        const response = await fetch(`/api/asistencia/${id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            mostrarAlerta('Registro actualizado exitosamente', 'success');
            $('#editarModal').modal('hide');
            cargarAsistencias();
            cargarEstadisticas();
        } else {
            mostrarAlerta('Error al actualizar: ' + result.message, 'danger');
        }
    } catch (error) {
        mostrarAlerta('Error de conexión: ' + error.message, 'danger');
    }
}

// Eliminar asistencia
async function eliminarAsistencia(id) {
    if (!confirm('¿Está seguro de eliminar este registro?')) {
        return;
    }
    
    try {
        const response = await fetch(`/api/asistencia/${id}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            mostrarAlerta('Registro eliminado exitosamente', 'success');
            cargarAsistencias();
            cargarEstadisticas();
        } else {
            mostrarAlerta('Error al eliminar: ' + result.message, 'danger');
        }
    } catch (error) {
        mostrarAlerta('Error de conexión: ' + error.message, 'danger');
    }
}

// Limpiar filtros
function limpiarFiltros() {
    document.getElementById('fechaInicio').value = '';
    document.getElementById('fechaFin').value = '';
    document.getElementById('empleadoIdFilter').value = '';
    
    // Restablecer fechas por defecto
    const hoy = new Date();
    const hace7Dias = new Date(hoy.getTime() - 7 * 24 * 60 * 60 * 1000);
    
    document.getElementById('fechaFin').value = hoy.toISOString().split('T')[0];
    document.getElementById('fechaInicio').value = hace7Dias.toISOString().split('T')[0];
    
    cargarAsistencias();
    cargarEstadisticas();
}

// Exportar datos
function exportarDatos() {
    if (asistencias.length === 0) {
        mostrarAlerta('No hay datos para exportar', 'warning');
        return;
    }
    
    // Crear CSV
    let csv = 'ID,Empleado ID,Fecha,Hora Entrada,Hora Salida,Estado\n';
    
    asistencias.forEach(asistencia => {
        csv += `${asistencia.id},${asistencia.empleado_id},${asistencia.fecha},"${asistencia.hora_entrada || ''}","${asistencia.hora_salida || ''}",${asistencia.estado}\n`;
    });
    
    // Descargar archivo
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', `asistencia_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    mostrarAlerta('Datos exportados exitosamente', 'success');
}

// Manejar Enter en campo de empleado
document.getElementById('empleadoId').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        marcarAsistencia();
    }
});

// Cargar estadísticas cuando cambian las fechas
document.getElementById('fechaInicio').addEventListener('change', cargarEstadisticas);
document.getElementById('fechaFin').addEventListener('change', cargarEstadisticas);
