document.addEventListener('DOMContentLoaded', function () {
    
    // Verificar si la variable global BASE_URL_JS fue inyectada por PHP
    const baseUrl = (typeof BASE_URL_JS !== 'undefined') ? BASE_URL_JS : '';
    const apiPathCompra = `${baseUrl}/includes/functions/facturas/obtener_productos_proveedor.php`;
    const apiPathVenta = `${baseUrl}/includes/functions/facturas/obtener_productos_venta.php`;


    // Variables Comunes
    const tabCompra = document.getElementById('tabCompra');
    const tabVenta = document.getElementById('tabVenta');
    const formCompra = document.getElementById('formCompra');
    const formVenta = document.getElementById('formVenta');

    // Lógica de Pestañas
    if (tabCompra && tabVenta && formCompra && formVenta) {
        tabCompra.addEventListener('click', () => {
            tabCompra.classList.add('activo'); tabVenta.classList.remove('activo');
            formCompra.style.display = 'block'; formVenta.style.display = 'none';
        });
        tabVenta.addEventListener('click', () => {
            tabVenta.classList.add('activo'); tabCompra.classList.remove('activo');
            formVenta.style.display = 'block'; formCompra.style.display = 'none';
        });
    }

    // Lógica para formulario de compra
    const proveedorSelectCompra = document.getElementById('proveedorFactura');
    const contenedorLineasCompra = document.getElementById('contenedorLineasFacturaCompra');
    const botonAnadirLineaCompra = document.getElementById('botonAnadirLineaCompra');
    const totalFacturaInputCompra = document.getElementById('totalFacturaCompra');
    let numeroSiguienteLineaCompra = contenedorLineasCompra ? (contenedorLineasCompra.querySelectorAll('.linea_factura[data-tipo-linea="compra"]').length + 1) : 1;

    function anadirNuevaLineaCompra() {
        if (!contenedorLineasCompra) return;
        const nuevaLinea = document.createElement('div');
        nuevaLinea.classList.add('linea_factura');
        nuevaLinea.setAttribute('data-tipo-linea', 'compra');
        nuevaLinea.setAttribute('data-linea-num', numeroSiguienteLineaCompra);
        const opcionesAlmacenCompra = (typeof almacenesDataGlobal !== 'undefined' && Array.isArray(almacenesDataGlobal)) 
            ? almacenesDataGlobal.map(almacen => `<option value="${almacen.cod_almacen}" data-nombre-almacen="${almacen.ubicacion}">${almacen.ubicacion}</option>`).join('')
            : '<option value="">Error al cargar almacenes</option>';

        nuevaLinea.innerHTML = `
            <span class="numero_linea">${numeroSiguienteLineaCompra}.</span>
            <div class="form_grupo"><label for="producto_linea_compra_${numeroSiguienteLineaCompra}">Producto:</label><select name="lineas[${numeroSiguienteLineaCompra}][cod_producto]" id="producto_linea_compra_${numeroSiguienteLineaCompra}" class="select_producto_linea" required><option value="">-- Seleccione Proveedor --</option></select></div>
            <div class="form_grupo"><label for="cantidad_linea_compra_${numeroSiguienteLineaCompra}">Cantidad:</label><input type="number" name="lineas[${numeroSiguienteLineaCompra}][cantidad]" id="cantidad_linea_compra_${numeroSiguienteLineaCompra}" class="input_cantidad_linea" min="1" value="1" required></div>
            <div class="form_grupo"><label for="almacen_linea_compra_${numeroSiguienteLineaCompra}">Almacén Destino:</label><select name="lineas[${numeroSiguienteLineaCompra}][cod_almacen]" id="almacen_linea_compra_${numeroSiguienteLineaCompra}" class="select_almacen_linea" required><option value="">-- Seleccione Almacén --</option>${opcionesAlmacenCompra}</select></div>
            <div class="form_grupo"><label for="precio_unitario_linea_compra_${numeroSiguienteLineaCompra}">Precio Unit. (€):</label><input type="text" name="lineas[${numeroSiguienteLineaCompra}][precio_unitario]" id="precio_unitario_linea_compra_${numeroSiguienteLineaCompra}" class="input_precio_unitario_linea" readonly></div>
            <div class="form_grupo"><label for="precio_total_linea_compra_${numeroSiguienteLineaCompra}">Total Línea (€):</label><input type="text" name="lineas[${numeroSiguienteLineaCompra}][precio_total]" id="precio_total_linea_compra_${numeroSiguienteLineaCompra}" class="input_precio_total_linea" readonly></div>
            <button type="button" class="boton_eliminar_linea">Eliminar</button>`;
        contenedorLineasCompra.appendChild(nuevaLinea);
        if (proveedorSelectCompra && proveedorSelectCompra.value) {
            cargarProductosParaLineaCompra(nuevaLinea.querySelector('.select_producto_linea'), proveedorSelectCompra.value);
        }
        configurarEventListenersLinea(nuevaLinea);
        actualizarVisibilidadBotonesEliminar(contenedorLineasCompra);
        numeroSiguienteLineaCompra++;
    }

    async function cargarProductosParaLineaCompra(selectProductoElement, codProveedor) { 
        return new Promise(async (resolve, reject) => { 
            selectProductoElement.innerHTML = '<option value="">Cargando productos...</option>';
            try {
                // Ruta modificada con apiPathCompra
                const response = await fetch(`${apiPathCompra}?cod_proveedor=${codProveedor}`);
                if (!response.ok) throw new Error(`Error HTTP: ${response.status}`);
                const productos = await response.json();
                selectProductoElement.innerHTML = '<option value="">-- Seleccione un Producto --</option>';
                if (productos.error) { console.error("Error del servidor al cargar productos compra:", productos.error); selectProductoElement.innerHTML = `<option value="">${productos.error}</option>`; }
                else if (productos.length > 0) { productos.forEach(producto => { const option = document.createElement('option'); option.value = producto.cod_producto; option.textContent = producto.nombre; option.dataset.precioCompra = producto.precio_compra; option.dataset.nombreProducto = producto.nombre; selectProductoElement.appendChild(option); });}
                else { selectProductoElement.innerHTML = '<option value="">No hay productos para este proveedor</option>';}
                resolve();
            } catch (error) { console.error('Excepción al cargar productos compra:', error); selectProductoElement.innerHTML = '<option value="">Error al cargar</option>'; reject(error); }
        });
    }
    function cargarProductosGlobalCompra(codProveedor) { 
        const promesas = [];
        if (!contenedorLineasCompra) return Promise.resolve();
        contenedorLineasCompra.querySelectorAll('.select_producto_linea').forEach(selectProducto => {
            const linea = selectProducto.closest('.linea_factura');
            if(linea) { linea.querySelector('.input_precio_unitario_linea').value = ''; linea.querySelector('.input_precio_total_linea').value = '';}
            promesas.push(cargarProductosParaLineaCompra(selectProducto, codProveedor));
        });
        return Promise.all(promesas).then(() => calcularTotalFacturaCompra());
    }
    function calcularTotalLineaCompra(linea) { 
        const cantidad = parseFloat(linea.querySelector('.input_cantidad_linea').value) || 0;
        const precioUnitario = parseFloat(linea.querySelector('.input_precio_unitario_linea').value) || 0;
        linea.querySelector('.input_precio_total_linea').value = (cantidad * precioUnitario).toFixed(2);
        calcularTotalFacturaCompra();
    }
    function calcularTotalFacturaCompra() { 
        let totalGeneral = 0;
        if (!contenedorLineasCompra) return;
        contenedorLineasCompra.querySelectorAll('.linea_factura[data-tipo-linea="compra"]').forEach(linea => { totalGeneral += parseFloat(linea.querySelector('.input_precio_total_linea').value) || 0; });
        if(totalFacturaInputCompra) totalFacturaInputCompra.value = totalGeneral.toFixed(2);
    }

    if (botonAnadirLineaCompra) botonAnadirLineaCompra.addEventListener('click', anadirNuevaLineaCompra);
    if (proveedorSelectCompra) proveedorSelectCompra.addEventListener('change', function() { this.value ? cargarProductosGlobalCompra(this.value) : limpiarLineasProducto(contenedorLineasCompra, '-- Seleccione Proveedor --'); });
    
    if (typeof proveedorSeleccionadoAlCargarCompra !== 'undefined' && proveedorSeleccionadoAlCargarCompra && contenedorLineasCompra) {
        cargarProductosGlobalCompra(proveedorSeleccionadoAlCargarCompra).then(() => {
            if (typeof lineasConErrorCompra !== 'undefined' && lineasConErrorCompra !== null) {
                const lineasArray = Array.isArray(lineasConErrorCompra) ? lineasConErrorCompra : Object.values(lineasConErrorCompra);
                lineasArray.forEach((lineaDataError, index) => {
                    const lineaHtmlElement = contenedorLineasCompra.querySelectorAll('.linea_factura[data-tipo-linea="compra"]')[index];
                    if (lineaHtmlElement && lineaDataError.cod_producto) {
                        const selectProducto = lineaHtmlElement.querySelector('.select_producto_linea');
                        const hiddenSelectedProduct = lineaHtmlElement.querySelector('.selected_product_on_error');
                        if (selectProducto && hiddenSelectedProduct && hiddenSelectedProduct.value) {
                            setTimeout(() => { selectProducto.value = hiddenSelectedProduct.value; selectProducto.dispatchEvent(new Event('change'));}, 100);
                        }
                    }
                });
            }
        });
    }

    // Lógica para formulario de venta
    const clienteSelectVenta = document.getElementById('clienteFactura'); 
    const contenedorLineasVenta = document.getElementById('contenedorLineasFacturaVenta');
    const botonAnadirLineaVenta = document.getElementById('botonAnadirLineaVenta');
    const totalFacturaInputVenta = document.getElementById('totalFacturaVenta');
    let numeroSiguienteLineaVenta = contenedorLineasVenta ? (contenedorLineasVenta.querySelectorAll('.linea_factura[data-tipo-linea="venta"]').length + 1) : 1;
    let productosDisponiblesVenta = []; 

    async function precargarProductosVenta() {
        try {
            console.log("JS: Intentando precargar productos para venta...");
            // Ruta modificada con apiPathVenta
            const response = await fetch(apiPathVenta);
            if (!response.ok) {
                const errorText = await response.text();
                console.error(`JS: Error HTTP al precargar productos de venta: ${response.status}, ${errorText}`);
                throw new Error(`Error HTTP: ${response.status}`);
            }
            const data = await response.json();
            console.log("JS: Datos recibidos de obtener_productos_venta.php:", data);
            if (data.error) {
                console.error("JS: Error del servidor al precargar productos de venta:", data.error);
                productosDisponiblesVenta = [];
            } else {
                productosDisponiblesVenta = data;
                console.log("JS: Productos disponibles para venta actualizados:", productosDisponiblesVenta);
            }
        } catch (error) {
            console.error('JS: Excepción al precargar productos de venta:', error);
            productosDisponiblesVenta = [];
        }
    }

    function anadirNuevaLineaVenta() {
        if (!contenedorLineasVenta) {
            console.error("JS: Contenedor de líneas de venta no encontrado.");
            return;
        }
        console.log("JS: Añadiendo nueva línea de venta. Número actual:", numeroSiguienteLineaVenta);
        const nuevaLinea = document.createElement('div');
        nuevaLinea.classList.add('linea_factura');
        nuevaLinea.setAttribute('data-tipo-linea', 'venta');
        nuevaLinea.setAttribute('data-linea-num', numeroSiguienteLineaVenta);
        nuevaLinea.innerHTML = `
            <span class="numero_linea">${numeroSiguienteLineaVenta}.</span>
            <div class="form_grupo"><label for="producto_linea_venta_${numeroSiguienteLineaVenta}">Producto:</label><select name="lineas[${numeroSiguienteLineaVenta}][cod_producto]" id="producto_linea_venta_${numeroSiguienteLineaVenta}" class="select_producto_linea" required><option value="">-- Seleccione Producto --</option></select><input type="hidden" name="lineas[${numeroSiguienteLineaVenta}][iva_producto]" class="input_iva_producto" value="0"></div>
            <div class="form_grupo"><label for="almacen_linea_venta_${numeroSiguienteLineaVenta}">Almacén Origen:</label><select name="lineas[${numeroSiguienteLineaVenta}][cod_almacen_origen]" id="almacen_linea_venta_${numeroSiguienteLineaVenta}" class="select_almacen_origen_linea"><option value="">-- Seleccione Producto Primero --</option></select></div>
            <div class="form_grupo"><label for="cantidad_linea_venta_${numeroSiguienteLineaVenta}">Cantidad:</label><input type="number" name="lineas[${numeroSiguienteLineaVenta}][cantidad]" id="cantidad_linea_venta_${numeroSiguienteLineaVenta}" class="input_cantidad_linea" min="1" value="1" required></div>
            <div class="form_grupo"><label for="precio_unitario_linea_venta_${numeroSiguienteLineaVenta}">Precio Unit. (sin IVA) (€):</label><input type="text" name="lineas[${numeroSiguienteLineaVenta}][precio_unitario_sin_iva]" id="precio_unitario_linea_venta_${numeroSiguienteLineaVenta}" class="input_precio_unitario_linea" readonly></div>
            <div class="form_grupo"><label for="precio_total_linea_venta_${numeroSiguienteLineaVenta}">Total Línea (IVA incl.) (€):</label><input type="text" name="lineas[${numeroSiguienteLineaVenta}][precio_total_con_iva]" id="precio_total_linea_venta_${numeroSiguienteLineaVenta}" class="input_precio_total_linea" readonly></div>
            <button type="button" class="boton_eliminar_linea">Eliminar</button>`;
        contenedorLineasVenta.appendChild(nuevaLinea);
        poblarSelectProductoVenta(nuevaLinea.querySelector('.select_producto_linea'));
        configurarEventListenersLinea(nuevaLinea);
        actualizarVisibilidadBotonesEliminar(contenedorLineasVenta);
        numeroSiguienteLineaVenta++;
    }

    function poblarSelectProductoVenta(selectProductoElement) {
        selectProductoElement.innerHTML = '<option value="">-- Seleccione Producto --</option>';
        console.log("JS: Poblando select de producto venta. Productos disponibles:", productosDisponiblesVenta);
        if (productosDisponiblesVenta && productosDisponiblesVenta.length > 0) {
            productosDisponiblesVenta.forEach(producto => {
                const option = document.createElement('option');
                option.value = producto.cod_producto;
                option.textContent = `${producto.nombre} (P.V. ${parseFloat(producto.precio_venta).toFixed(2)}€)`;
                option.dataset.precioVenta = producto.precio_venta;
                option.dataset.iva = producto.iva;
                option.dataset.nombreProducto = producto.nombre;
                option.dataset.almacenesConStock = JSON.stringify(producto.almacenes_con_stock);
                selectProductoElement.appendChild(option);
            });
        } else {
            selectProductoElement.innerHTML = '<option value="">No hay productos para venta o error</option>';
            console.warn("JS: No hay productos disponibles para poblar el select de venta.");
        }
    }
    
    function poblarSelectAlmacenVenta(selectAlmacenElement, almacenesConStock, cantidadSolicitada = 1) {
        selectAlmacenElement.innerHTML = '<option value="">-- Seleccione Almacén --</option>'; // Opción por defecto
        let algunaOpcionValida = false;
        console.log("JS: Poblando almacenes para venta. Almacenes con stock del producto:", almacenesConStock, "Cantidad solicitada:", cantidadSolicitada);
        if (almacenesConStock && almacenesConStock.length > 0) {
            almacenesConStock.forEach(almacen => {
                if (almacen.cantidad >= cantidadSolicitada) { 
                    const option = document.createElement('option');
                    option.value = almacen.cod_almacen;
                    option.textContent = `${almacen.ubicacion} (Stock: ${almacen.cantidad})`;
                    option.dataset.stockDisponible = almacen.cantidad;
                    selectAlmacenElement.appendChild(option);
                    algunaOpcionValida = true;
                }
            });
            if (!algunaOpcionValida) { 
                selectAlmacenElement.innerHTML = '<option value="">Stock insuficiente en almacenes</option>';
            }
        } else {
            selectAlmacenElement.innerHTML = '<option value="">No hay stock para este producto</option>';
        }
          // Limpiar validación custom al repoblar
        selectAlmacenElement.setCustomValidity('');
    }

    function calcularTotalLineaVenta(linea) {
        const cantidadInput = linea.querySelector('.input_cantidad_linea');
        const precioUnitarioSinIvaInput = linea.querySelector('.input_precio_unitario_linea');
        const ivaProductoInput = linea.querySelector('.input_iva_producto'); 
        const precioTotalConIvaInput = linea.querySelector('.input_precio_total_linea');
        const selectAlmacen = linea.querySelector('.select_almacen_origen_linea');

        const cantidad = parseFloat(cantidadInput.value) || 0;
        const precioUnitarioSinIva = parseFloat(precioUnitarioSinIvaInput.value) || 0;
        const porcentajeIva = parseFloat(ivaProductoInput.value) || 0;

        const subtotalSinIva = cantidad * precioUnitarioSinIva;
        const valorIva = subtotalSinIva * (porcentajeIva / 100);
        const totalLineaConIva = subtotalSinIva + valorIva;

        if (precioTotalConIvaInput) {
            precioTotalConIvaInput.value = totalLineaConIva.toFixed(2);
        }
        
        // Validación de stock y selección de almacén
        if (selectAlmacen) {
            const selectedAlmacenOption = selectAlmacen.options[selectAlmacen.selectedIndex];
            if (cantidad > 0 && (!selectedAlmacenOption || !selectedAlmacenOption.value)) {
                // Si hay cantidad pero no almacén seleccionado (y el select no está vacío de opciones válidas)
                if (selectAlmacen.options.length > 1 && selectAlmacen.options[0].value === "" && selectAlmacen.options[0].textContent.startsWith("-- Seleccione Almacén --")) {
                    selectAlmacen.setCustomValidity('Debe seleccionar un almacén de origen.');
                } else {
                    // Caso: no hay almacenes válidos para seleccionar ("Stock insuficiente...")
                    selectAlmacen.setCustomValidity('No hay almacén válido con stock suficiente.');
                }
            } else if (selectedAlmacenOption && selectedAlmacenOption.value) {
                const stockDisponible = parseInt(selectedAlmacenOption.dataset.stockDisponible, 10);
                if (cantidad > stockDisponible) {
                    selectAlmacen.setCustomValidity(`Stock insuficiente. Disponible: ${stockDisponible}`);
                    cantidadInput.setCustomValidity(`Stock insuficiente en ${selectedAlmacenOption.textContent}. Disponible: ${stockDisponible}`);
                } else {
                    selectAlmacen.setCustomValidity(''); 
                    cantidadInput.setCustomValidity(''); 
                }
            } else {
                 selectAlmacen.setCustomValidity(''); // No hay cantidad o no hay almacén para validar
            }
        }
        calcularTotalFacturaVenta();
    }

    function calcularTotalFacturaVenta() {
        let totalGeneralVenta = 0;
        if (!contenedorLineasVenta) return;
        contenedorLineasVenta.querySelectorAll('.linea_factura[data-tipo-linea="venta"]').forEach(linea => {
            const precioTotalLineaInput = linea.querySelector('.input_precio_total_linea');
            totalGeneralVenta += parseFloat(precioTotalLineaInput.value) || 0;
        });
        if (totalFacturaInputVenta) {
            totalFacturaInputVenta.value = totalGeneralVenta.toFixed(2);
        }
    }
    
    if (botonAnadirLineaVenta) {
        botonAnadirLineaVenta.addEventListener('click', anadirNuevaLineaVenta);
    } else {
        console.warn("JS: Botón 'Añadir Línea de Venta' no encontrado.");
    }


    // Funciones genéricas para líneas (compra/venta)
    function configurarEventListenersLinea(linea) {
        const tipoLinea = linea.dataset.tipoLinea; 
        const selectProducto = linea.querySelector('.select_producto_linea');
        const cantidadInput = linea.querySelector('.input_cantidad_linea');
        const botonEliminar = linea.querySelector('.boton_eliminar_linea');

        if (selectProducto) {
            selectProducto.addEventListener('change', function() {
                console.log(`JS: Producto cambiado en línea ${tipoLinea} #${linea.dataset.lineaNum}. Nuevo producto ID: ${this.value}`);
                const selectedOption = this.options[this.selectedIndex];
                if (!selectedOption || !selectedOption.value) { 
                    if (tipoLinea === 'compra') {
                        linea.querySelector('.input_precio_unitario_linea').value = '';
                        linea.querySelector('.input_precio_total_linea').value = '';
                        calcularTotalLineaCompra(linea);
                    } else if (tipoLinea === 'venta') {
                        linea.querySelector('.input_precio_unitario_linea').value = '';
                        linea.querySelector('.input_iva_producto').value = '0';
                        linea.querySelector('.select_almacen_origen_linea').innerHTML = '<option value="">-- Seleccione Producto Primero --</option>';
                        calcularTotalLineaVenta(linea);
                    }
                    return;
                }

                if (tipoLinea === 'compra') {
                    const precioUnitarioInput = linea.querySelector('.input_precio_unitario_linea');
                    const precioCompra = selectedOption.dataset.precioCompra || '0';
                    if (precioUnitarioInput) precioUnitarioInput.value = parseFloat(precioCompra).toFixed(2);
                    calcularTotalLineaCompra(linea);
                } else if (tipoLinea === 'venta') {
                    const precioUnitarioInput = linea.querySelector('.input_precio_unitario_linea');
                    const ivaProductoInput = linea.querySelector('.input_iva_producto');
                    const selectAlmacenOrigen = linea.querySelector('.select_almacen_origen_linea');
                    
                    const precioVenta = selectedOption.dataset.precioVenta || '0';
                    const iva = selectedOption.dataset.iva || '0';
                    const almacenesConStock = selectedOption.dataset.almacenesConStock ? JSON.parse(selectedOption.dataset.almacenesConStock) : [];
                    console.log("JS: Datos del producto de venta seleccionado:", { precioVenta, iva, almacenesConStock });

                    if (precioUnitarioInput) precioUnitarioInput.value = parseFloat(precioVenta).toFixed(2);
                    if (ivaProductoInput) ivaProductoInput.value = parseFloat(iva).toFixed(2); 
                    if (selectAlmacenOrigen) poblarSelectAlmacenVenta(selectAlmacenOrigen, almacenesConStock, parseFloat(cantidadInput.value) || 1);
                    
                    calcularTotalLineaVenta(linea);
                }
            });
        }

        if (cantidadInput) {
            cantidadInput.addEventListener('input', function() {
                console.log(`JS: Cantidad cambiada en línea ${tipoLinea} #${linea.dataset.lineaNum} a: ${this.value}`);
                if (tipoLinea === 'compra') calcularTotalLineaCompra(linea);
                else if (tipoLinea === 'venta') {
                    const selectProducto = linea.querySelector('.select_producto_linea');
                    const selectedProdOption = selectProducto.options[selectProducto.selectedIndex];
                    const selectAlmacenOrigen = linea.querySelector('.select_almacen_origen_linea');
                    if(selectedProdOption && selectedProdOption.value && selectAlmacenOrigen){
                        const almacenesConStock = selectedProdOption.dataset.almacenesConStock ? JSON.parse(selectedProdOption.dataset.almacenesConStock) : [];
                        const almacenSeleccionadoAntes = selectAlmacenOrigen.value; 
                        poblarSelectAlmacenVenta(selectAlmacenOrigen, almacenesConStock, parseFloat(this.value) || 1);
                        
                        const opcionRestaurada = Array.from(selectAlmacenOrigen.options).find(opt => opt.value === almacenSeleccionadoAntes);
                        if (opcionRestaurada) {
                            selectAlmacenOrigen.value = almacenSeleccionadoAntes;
                        } else {
                            selectAlmacenOrigen.value = ""; 
                        }
                        console.log(`JS: Almacén seleccionado después de cambiar cantidad: ${selectAlmacenOrigen.value}`);
                    }
                    calcularTotalLineaVenta(linea);
                }
            });
        }
        
        const selectAlmacenOrigen = linea.querySelector('.select_almacen_origen_linea');
        if (selectAlmacenOrigen && tipoLinea === 'venta') {
            selectAlmacenOrigen.addEventListener('change', function() {
                console.log(`JS: Almacén origen cambiado en línea venta #${linea.dataset.lineaNum} a: ${this.value}`);
                calcularTotalLineaVenta(linea); 
            });
        }

        if (botonEliminar) {
            botonEliminar.addEventListener('click', function() {
                const lineaAEliminar = this.closest('.linea_factura');
                const contenedor = tipoLinea === 'compra' ? contenedorLineasCompra : contenedorLineasVenta;
                if (lineaAEliminar && contenedor) { 
                    lineaAEliminar.remove();
                    renumerarLineas(contenedor);
                    actualizarVisibilidadBotonesEliminar(contenedor);
                    if (tipoLinea === 'compra') calcularTotalFacturaCompra();
                    else if (tipoLinea === 'venta') calcularTotalFacturaVenta();
                }
            });
        }
    }

    function renumerarLineas(contenedor) {
        if (!contenedor) return;
        const tipoLinea = contenedor === contenedorLineasCompra ? 'compra' : 'venta';
        let contadorLineas = 1;
        contenedor.querySelectorAll('.linea_factura').forEach((linea, index) => {
            const nuevoNumero = index + 1;
            linea.setAttribute('data-linea-num', nuevoNumero);
            linea.querySelector('.numero_linea').textContent = `${nuevoNumero}.`;
            linea.querySelectorAll('select, input').forEach(input => {
                const oldId = input.id; const oldName = input.name;
                if (oldId) input.id = oldId.replace(/_linea_(compra|venta)_\d+/, `_linea_${tipoLinea}_${nuevoNumero}`);
                if (oldName) input.name = oldName.replace(/\[\d+\]/, `[${nuevoNumero}]`);
            });
            contadorLineas = nuevoNumero + 1;
        });
        if (tipoLinea === 'compra') numeroSiguienteLineaCompra = contadorLineas;
        else if (tipoLinea === 'venta') numeroSiguienteLineaVenta = contadorLineas;
    }

    function actualizarVisibilidadBotonesEliminar(contenedor) {
        if (!contenedor) return;
        const lineas = contenedor.querySelectorAll('.linea_factura');
        lineas.forEach((linea) => {
            const botonEliminar = linea.querySelector('.boton_eliminar_linea');
            if (botonEliminar) botonEliminar.style.display = lineas.length > 1 ? 'inline-block' : 'none';
        });
    }
    
    function limpiarLineasProducto(contenedor, mensajeDefaultOption) {
        if (!contenedor) return;
        contenedor.querySelectorAll('.select_producto_linea').forEach(select => {
            select.innerHTML = `<option value="">${mensajeDefaultOption}</option>`;
            const linea = select.closest('.linea_factura');
            if (linea) {
                const tipoLinea = linea.dataset.tipoLinea;
                if (tipoLinea === 'compra') {
                    linea.querySelector('.input_precio_unitario_linea').value = '';
                    linea.querySelector('.input_precio_total_linea').value = '';
                } else if (tipoLinea === 'venta') {
                    linea.querySelector('.input_precio_unitario_linea').value = '';
                    linea.querySelector('.input_iva_producto').value = '0';
                    linea.querySelector('.input_precio_total_linea').value = '';
                    linea.querySelector('.select_almacen_origen_linea').innerHTML = '<option value="">-- Seleccione Producto Primero --</option>';
                }
            }
        });
        if (contenedor === contenedorLineasCompra) calcularTotalFacturaCompra();
        else if (contenedor === contenedorLineasVenta) calcularTotalFacturaVenta();
    }

    // Inicialización de ambos formularios
    if (contenedorLineasCompra) {
        document.querySelectorAll('#contenedorLineasFacturaCompra .linea_factura').forEach(configurarEventListenersLinea);
        actualizarVisibilidadBotonesEliminar(contenedorLineasCompra);
        if(totalFacturaInputCompra && !totalFacturaInputCompra.value && contenedorLineasCompra.children.length > 0) calcularTotalFacturaCompra();
    }

    precargarProductosVenta().then(() => { 
        if (contenedorLineasVenta) {
            document.querySelectorAll('#contenedorLineasFacturaVenta .linea_factura').forEach(lineaVenta => {
                poblarSelectProductoVenta(lineaVenta.querySelector('.select_producto_linea'));
                configurarEventListenersLinea(lineaVenta);
            });
            actualizarVisibilidadBotonesEliminar(contenedorLineasVenta);
            if(totalFacturaInputVenta && !totalFacturaInputVenta.value && contenedorLineasVenta.children.length > 0) calcularTotalFacturaVenta();

            if (typeof lineasConErrorVenta !== 'undefined' && lineasConErrorVenta !== null) {
                const lineasVentaArray = Array.isArray(lineasConErrorVenta) ? lineasConErrorVenta : Object.values(lineasConErrorVenta);
                lineasVentaArray.forEach((lineaDataError, index) => {
                    const lineaHtmlElement = contenedorLineasVenta.querySelectorAll('.linea_factura[data-tipo-linea="venta"]')[index];
                    if (lineaHtmlElement && lineaDataError.cod_producto) {
                        const selectProducto = lineaHtmlElement.querySelector('.select_producto_linea');
                        const hiddenSelectedProduct = lineaHtmlElement.querySelector('.selected_product_on_error'); 
                        const hiddenSelectedAlmacen = lineaHtmlElement.querySelector('.selected_almacen_on_error'); 

                        if (selectProducto && hiddenSelectedProduct && hiddenSelectedProduct.value) {
                            setTimeout(() => {
                                selectProducto.value = hiddenSelectedProduct.value;
                                selectProducto.dispatchEvent(new Event('change')); 

                                if(hiddenSelectedAlmacen && hiddenSelectedAlmacen.value){
                                    setTimeout(() => { 
                                        const selectAlmacen = lineaHtmlElement.querySelector('.select_almacen_origen_linea');
                                        if(selectAlmacen) {
                                            selectAlmacen.value = hiddenSelectedAlmacen.value;
                                            selectAlmacen.dispatchEvent(new Event('change')); 
                                        }
                                    }, 150);
                                }
                            }, 100);
                        }
                    }
                });
            }
        }
    });
});