# inventory-updater

Actualmente tengo un plugin que actualza la informacion que tengo en una base de datos, cuando es posible, a partir de los datos de un archivo. El plugin funciona en wordpress y woocommerce 3.5.10 que corre en debian buster. En este woocommerce tengo dados de alta muchos articulos, con su titulo, stock, precios, sku y codigo de barras de cada producto. El plugin se puede instalar en este woocommerce y se encarga de actualizar el stock de mi base de datos. Al terminar el proceso de actualizacion, indica que productos de mi base de datos no tienen informado el campo sku o no se ha podido machear con ninguna fila del fichero procesado, así como los articulos actualizados y los que si han matcheado pero no han sufrido ninguna modificacion. El archivo con los datos se colocará en una ruta de wordpress o bien se puede descargar directamente desde una url. El administrador muestra la ruta donde se debe colocar el archivo o muestra un caja de texto donde escribir la url para descargar y ofrecerá un boton para iniciar la actualizacion. Una vez terminado el proceso, en este administrador se muestra un resumen del resultado del proceso.
Ahora mismo el codigo está desarrollado siguiendo principios SOLID y de clean code.


Por defecto, trabaja con un archivo que se llama articulos.txt y su estructura es:
1. SKU - Identificador único para cada producto (ej: 285320)
2. Tipo de producto - L (libro) o P (producto/papelería)
3. Stock - Cantidad disponible (números positivos, negativos o cero)
4. Precio - Valor en euros, usando coma como separador decimal
5. Tipo de IVA - Principalmente 4% (para libros) o 21% (para otros productos)
6. Columna auxiliar - Generalmente vacía
7. Referencia - Código interno o de referencia del proveedor
8. ISBN/Código de barras - Identificador estándar del producto
9. Columna espaciadora - Generalmente vacía o con espacios
10. Título/Descripción - Nombre del producto
11. Editorial - Nombre de la editorial (para libros)
12. Autor/Marca - Autor del libro o marca del producto
13. Distribuidor - Empresa distribuidora del producto
14. Columna sin uso - Siempre vacía en todo el archivo
15. Columna sin uso - Siempre vacía en todo el archivo

Te paso una fila del archivo de articulos.txt:
 285577|P|     4|    29,95|21|      |               |4010168253961|          |THE KEY: SABOTAJE EN EL PARQUE DE ATRACCIONES|||OLD TEDDY S COMPANY S.L|||

En este caso, el sku es 285577, el stock 4 y el precio es 29,95




v1.0.0
Version inicial. Trabaja con un archivo llamado inventory.txt puesto en la carpeta de downloads

v1.1.0
- Permite descargar de una URL
- Actualiza precios


v1.2.0
- Corrige bug en actualizacion de precios
- Permite mantener los descuentos

v1.3.0
- Añade listado de productos no encontrados en el listado

v1.4.0
- Separa los productos actualizados en dos:
 - Productos encontrados y no modificados
 - Productos encontrados y actualizados

v1.5.0
- Reformatea y divide el archivo js inicial

v1.6.0
- Añade enlace al producto en la web pinchando en el titulo
- Añade enlace a la edición del producto en el administrador pinchando en el id
