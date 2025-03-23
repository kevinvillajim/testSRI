<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Facturación Electrónica SRI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            background-color: #f5f5f5;
        }
        .main-container {
            margin-top: 50px;
            margin-bottom: 50px;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .card-icon {
            font-size: 48px;
            margin-bottom: 20px;
            color: #0d6efd;
        }
        .btn-primary {
            border-radius: 20px;
            padding: 8px 20px;
        }
        .header {
            background-color: #343a40;
            color: white;
            padding: 20px 0;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="row">
                <div class="col-md-8">
                    <h1><i class="fas fa-file-invoice"></i> Sistema de Facturación Electrónica SRI</h1>
                    <p class="lead">Emisión de comprobantes electrónicos bajo esquema off-line</p>
                </div>
                <div class="col-md-4 text-end">
                    <p class="mt-2">Ambiente: <?php echo $config['ambiente'] == 1 ? 'PRUEBAS' : 'PRODUCCIÓN'; ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="container main-container">
        <div class="row justify-content-center mb-4">
            <div class="col-md-8">
                <div class="alert alert-info" role="alert">
                    <h4 class="alert-heading"><i class="fas fa-info-circle"></i> Información</h4>
                    <p>Este sistema permite la emisión de comprobantes electrónicos bajo el esquema off-line establecido por el SRI. Asegúrese de configurar correctamente el certificado digital y los datos del emisor.</p>
                </div>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-4 mb-4">
                <div class="card h-100 text-center p-4">
                    <div class="card-body">
                        <div class="card-icon">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <h4 class="card-title">Factura</h4>
                        <p class="card-text">Generar y enviar facturas electrónicas al SRI para su autorización.</p>
                        <a href="?accion=generar-factura" class="btn btn-primary">Crear Factura</a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card h-100 text-center p-4">
                    <div class="card-body">
                        <div class="card-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h4 class="card-title">Nota de Crédito</h4>
                        <p class="card-text">Generar y enviar notas de crédito electrónicas al SRI para su autorización.</p>
                        <a href="?accion=generar-nota-credito" class="btn btn-primary">Crear Nota de Crédito</a>
                    </div>
                </div>
            </div>
        </div>
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
</body>
</html>
