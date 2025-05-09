<?php
require_once("../../includes/connection.php");
require_once("../../includes/auth.php");

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Facturas</title>
    <link rel="stylesheet" href="/ERP/assets/css/modules_style/home_style/sections_style/general_sections_style.css">
    <link rel="stylesheet" href="/ERP/assets/css/modules_style/home_style/sections_style/style_facturas.css">
</head>
<body>
<div class="general_container">
    <div class="card">
        <div class="factura_header">
            <div id="tabCompra" class="factura_tab activo">Compras</div>
            <div id="tabVenta" class="factura_tab">Ventas</div>
        </div>

        <div class="factura_body">
            <!-- Formulario de Compra -->
            <div id="formCompra" class="formulario" style="display: block;">
                <form id="formCompraDetails">
                    <p>Formulario de compra (en construcción)</p>
                    <!-- Aquí irán los campos de compra -->
                </form>
            </div>

            <!-- Formulario de Venta -->
            <div id="formVenta" class="formulario" style="display: none;">
                <form id="formVentaDetails">
                    <label for="productoVenta">Producto:</label>
                    <input type="text" id="productoVenta" name="productoVenta"><br><br>

                    <label for="cantidadVenta">Cantidad:</label>
                    <input type="number" id="cantidadVenta" name="cantidadVenta"><br><br>

                    <label for="precioVenta">Precio:</label>
                    <input type="number" id="precioVenta" name="precioVenta"><br><br>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="/ERP/assets/js/facturas.js"></script>
</body>
</html>
