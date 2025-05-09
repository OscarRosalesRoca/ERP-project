document.addEventListener('DOMContentLoaded', function () {
    const tabCompra = document.getElementById('tabCompra');
    const tabVenta = document.getElementById('tabVenta');
    const formCompra = document.getElementById('formCompra');
    const formVenta = document.getElementById('formVenta');

    tabCompra.addEventListener('click', () => {
        // Mostrar el formulario de compras
        tabCompra.classList.add('activo');
        tabVenta.classList.remove('activo');
        formCompra.style.display = 'block';
        formVenta.style.display = 'none';
    });

    tabVenta.addEventListener('click', () => {
        // Mostrar el formulario de ventas
        tabVenta.classList.add('activo');
        tabCompra.classList.remove('activo');
        formVenta.style.display = 'block';
        formCompra.style.display = 'none';
    });
});