<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generar Factura - Sistema de Facturación Electrónica SRI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            background-color: #f5f5f5;
        }
        .main-container {
            margin-top: 30px;
            margin-bottom: 50px;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        }
        .header {
            background-color: #343a40;
            color: white;
            padding: 20px 0;
            margin-bottom: 30px;
        }
        .btn-add-item {
            margin-top: 10px;
        }
        .item-row {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="row">
                <div class="col-md-8">
                    <h1><i class="fas fa-file-invoice-dollar"></i> Generar Factura</h1>
                    <p class="lead">Sistema de Facturación Electrónica SRI</p>
                </div>
                <div class="col-md-4 text-end">
                    <p class="mt-2">Ambiente: <?php echo $config['ambiente'] == 1 ? 'PRUEBAS' : 'PRODUCCIÓN'; ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="container main-container">
        <div class="row mb-3">
            <div class="col-12">
                <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
            </div>
        </div>

        <form action="?accion=procesar-factura" method="post">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle"></i> Información General</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="secuencial" class="form-label">Secuencial</label>
                            <input type="text" class="form-control" id="secuencial" name="secuencial" required
                                   pattern="[0-9]{1,9}" title="Ingrese hasta 9 dígitos"
                                   placeholder="Ejemplo: 000000001">
                            <small class="text-muted">El secuencial será formateado a 9 dígitos</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="fecha_emision" class="form-label">Fecha de Emisión</label>
                            <input type="date" class="form-control" id="fecha_emision" name="fecha_emision" required
                                   value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="tipo_identificacion" class="form-label">Tipo de Identificación</label>
                            <select class="form-select" id="tipo_identificacion" name="tipo_identificacion" required>
                                <option value="">Seleccione...</option>
                                <option value="04">RUC</option>
                                <option value="05">Cédula</option>
                                <option value="06">Pasaporte</option>
                                <option value="07">Consumidor Final</option>
                                <option value="08">Identificación del Exterior</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="razon_social" class="form-label">Razón Social / Nombres y Apellidos</label>
                            <input type="text" class="form-control" id="razon_social" name="razon_social" required
                                   maxlength="300" placeholder="Razón Social del Cliente">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="identificacion" class="form-label">Identificación</label>
                            <input type="text" class="form-control" id="identificacion" name="identificacion" required
                                   maxlength="20" placeholder="RUC / Cédula / Pasaporte">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="direccion" class="form-label">Dirección</label>
                            <input type="text" class="form-control" id="direccion" name="direccion"
                                   maxlength="300" placeholder="Dirección del Cliente">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   maxlength="300" placeholder="Email del Cliente">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="text" class="form-control" id="telefono" name="telefono"
                                   maxlength="300" placeholder="Teléfono del Cliente">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-shopping-cart"></i> Detalles</h5>
                </div>
                <div class="card-body">
                    <div id="items-container">
                        <div class="item-row" data-index="0">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label for="item_codigo_0" class="form-label">Código</label>
                                    <input type="text" class="form-control" id="item_codigo_0" name="item_codigo[]" required
                                           maxlength="25" placeholder="Código">
                                </div>
                                <div class="col-md-9 mb-3">
                                    <label for="item_descripcion_0" class="form-label">Descripción</label>
                                    <input type="text" class="form-control" id="item_descripcion_0" name="item_descripcion[]" required
                                           maxlength="300" placeholder="Descripción">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label for="item_cantidad_0" class="form-label">Cantidad</label>
                                    <input type="number" class="form-control item-cantidad" id="item_cantidad_0" name="item_cantidad[]" required
                                           step="0.01" min="0.01" placeholder="0.00">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="item_precio_0" class="form-label">Precio Unitario</label>
                                    <input type="number" class="form-control item-precio" id="item_precio_0" name="item_precio[]" required
                                           step="0.01" min="0.01" placeholder="0.00">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="item_descuento_0" class="form-label">Descuento</label>
                                    <input type="number" class="form-control item-descuento" id="item_descuento_0" name="item_descuento[]"
                                           step="0.01" min="0" placeholder="0.00" value="0.00">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="item_subtotal_0" class="form-label">Subtotal</label>
                                    <input type="text" class="form-control item-subtotal" id="item_subtotal_0" readonly
                                           placeholder="0.00">
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-success btn-add-item">
                        <i class="fas fa-plus"></i> Agregar Ítem
                    </button>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-calculator"></i> Totales</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 offset-md-6">
                            <table class="table">
                                <tr>
                                    <td>Subtotal</td>
                                    <td class="text-end" id="subtotal">0.00</td>
                                </tr>
                                <tr>
                                    <td>Descuento</td>
                                    <td class="text-end" id="total-descuento">0.00</td>
                                </tr>
                                <tr>
                                    <td>Subtotal sin Impuestos</td>
                                    <td class="text-end" id="subtotal-sin-impuestos">0.00</td>
                                </tr>
                                <tr>
                                    <td>IVA 12%</td>
                                    <td class="text-end" id="iva">0.00</td>
                                </tr>
                                <tr>
                                    <td><strong>VALOR TOTAL</strong></td>
                                    <td class="text-end"><strong id="total">0.00</strong></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-grid gap-2 col-md-6 mx-auto">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-paper-plane"></i> Generar y Enviar Factura
                </button>
            </div>
        </form>
    </div>

    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>Sistema de Facturación Electrónica SRI</h5>
                    <p>Desarrollado para cumplir con los requisitos del SRI para la emisión de comprobantes electrónicos en Ecuador.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p>Versión 1.0.0</p>
                    <p><?php echo date('Y'); ?> &copy; Todos los derechos reservados</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Variable para rastrear el índice actual de los items
            let currentIndex = 0;

            // Función para calcular subtotales de cada ítem
            function calculateItemSubtotal(row) {
                const cantidad = parseFloat(row.querySelector('.item-cantidad').value) || 0;
                const precio = parseFloat(row.querySelector('.item-precio').value) || 0;
                const descuento = parseFloat(row.querySelector('.item-descuento').value) || 0;
                
                const subtotal = (cantidad * precio) - descuento;
                row.querySelector('.item-subtotal').value = subtotal.toFixed(2);
                
                updateTotals();
            }

            // Función para actualizar los totales
            function updateTotals() {
                let subtotal = 0;
                let totalDescuento = 0;
                
                document.querySelectorAll('.item-row').forEach(row => {
                    const cantidad = parseFloat(row.querySelector('.item-cantidad').value) || 0;
                    const precio = parseFloat(row.querySelector('.item-precio').value) || 0;
                    const descuento = parseFloat(row.querySelector('.item-descuento').value) || 0;
                    
                    subtotal += cantidad * precio;
                    totalDescuento += descuento;
                });
                
                const subtotalSinImpuestos = subtotal - totalDescuento;
                const iva = subtotalSinImpuestos * 0.12;
                const total = subtotalSinImpuestos + iva;
                
                document.getElementById('subtotal').textContent = subtotal.toFixed(2);
                document.getElementById('total-descuento').textContent = totalDescuento.toFixed(2);
                document.getElementById('subtotal-sin-impuestos').textContent = subtotalSinImpuestos.toFixed(2);
                document.getElementById('iva').textContent = iva.toFixed(2);
                document.getElementById('total').textContent = total.toFixed(2);
            }

            // Inicializa el cálculo para el primer ítem
            document.querySelectorAll('.item-row').forEach(row => {
                const inputs = row.querySelectorAll('.item-cantidad, .item-precio, .item-descuento');
                inputs.forEach(input => {
                    input.addEventListener('input', () => calculateItemSubtotal(row));
                });
            });

            // Maneja el clic en el botón de agregar ítem
            document.querySelector('.btn-add-item').addEventListener('click', function() {
                currentIndex++;
                
                const newRow = document.createElement('div');
                newRow.className = 'item-row';
                newRow.dataset.index = currentIndex;
                
                newRow.innerHTML = `
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="item_codigo_${currentIndex}" class="form-label">Código</label>
                            <input type="text" class="form-control" id="item_codigo_${currentIndex}" name="item_codigo[]" required
                                   maxlength="25" placeholder="Código">
                        </div>
                        <div class="col-md-9 mb-3">
                            <label for="item_descripcion_${currentIndex}" class="form-label">Descripción</label>
                            <input type="text" class="form-control" id="item_descripcion_${currentIndex}" name="item_descripcion[]" required
                                   maxlength="300" placeholder="Descripción">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="item_cantidad_${currentIndex}" class="form-label">Cantidad</label>
                            <input type="number" class="form-control item-cantidad" id="item_cantidad_${currentIndex}" name="item_cantidad[]" required
                                   step="0.01" min="0.01" placeholder="0.00">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="item_precio_${currentIndex}" class="form-label">Precio Unitario</label>
                            <input type="number" class="form-control item-precio" id="item_precio_${currentIndex}" name="item_precio[]" required
                                   step="0.01" min="0.01" placeholder="0.00">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="item_descuento_${currentIndex}" class="form-label">Descuento</label>
                            <input type="number" class="form-control item-descuento" id="item_descuento_${currentIndex}" name="item_descuento[]"
                                   step="0.01" min="0" placeholder="0.00" value="0.00">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="item_subtotal_${currentIndex}" class="form-label">Subtotal</label>
                            <input type="text" class="form-control item-subtotal" id="item_subtotal_${currentIndex}" readonly
                                   placeholder="0.00">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-12">
                            <button type="button" class="btn btn-danger btn-sm btn-remove-item">
                                <i class="fas fa-trash"></i> Eliminar
                            </button>
                        </div>
                    </div>
                `;
                
                document.getElementById('items-container').appendChild(newRow);
                
                // Agrega eventos a los nuevos campos
                const inputs = newRow.querySelectorAll('.item-cantidad, .item-precio, .item-descuento');
                inputs.forEach(input => {
                    input.addEventListener('input', () => calculateItemSubtotal(newRow));
                });
                
                // Agrega evento al botón de eliminar
                newRow.querySelector('.btn-remove-item').addEventListener('click', function() {
                    newRow.remove();
                    updateTotals();
                });
            });

            // Maneja la selección del tipo de identificación
            const tipoIdentificacionSelect = document.getElementById('tipo_identificacion');
            const identificacionInput = document.getElementById('identificacion');
            const razonSocialInput = document.getElementById('razon_social');
            
            tipoIdentificacionSelect.addEventListener('change', function() {
                if (this.value === '07') { // Consumidor Final
                    identificacionInput.value = '9999999999999';
                    razonSocialInput.value = 'CONSUMIDOR FINAL';
                } else {
                    if (identificacionInput.value === '9999999999999') {
                        identificacionInput.value = '';
                    }
                    if (razonSocialInput.value === 'CONSUMIDOR FINAL') {
                        razonSocialInput.value = '';
                    }
                }
            });
        });
    </script>
</body>
</html>
