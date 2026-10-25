# Etapa final: modularización y preparación para nube

Esta etapa se planifica después de las mejoras de comunicación, inventario y navegación. No implica desplegar ni migrar el proyecto a otro framework.

## Base implementada

- Stock permite crear productos, registrar compras y consultar su historial con pagos y saldos. Los movimientos conservan transacciones, lotes e identificadores de operación para evitar duplicados.
- Las lecturas de inventario usan una caché de 20 segundos, separada por conexión y usuario, invalidada después de las escrituras. Pedidos, movimientos y compras se consultan en páginas de 25 registros; la búsqueda se aplica antes de paginar.
- Los cambios de catálogo sincronizan el registro y sus relaciones necesarias. La sincronización completa permanece como reconciliación periódica y manual.
- Publicar encola el trabajo y devuelve el control al panel. Las solicitudes pendientes se agrupan porque la exportación contiene todas las páginas activas. El estado se conserva en un almacén de archivos separado de la caché habitual, dentro del volumen de runtime. Un envío al repositorio no confirma un despliegue de Cloudflare.
- El navegador reutiliza recursos estáticos y puede recuperar borradores del editor durante 24 horas. Los borradores son locales al usuario/ruta, excluyen archivos subidos y se descartan al guardar o cerrar sesión. El inventario del servidor sigue siendo la fuente de verdad: no se registran compras sin conexión.
- Blade separa las vistas compiladas por UID para evitar que mantenimiento ejecutado como root bloquee PHP-FPM. Ejecutar habitualmente Artisan como www-data sigue siendo la práctica recomendada.

## 1. Separar responsabilidades sin romper los flujos

Mantener la estructura Laravel/Filament y extraer progresivamente el contenido de BusinessLedger:

| Área | Destino propuesto | Responsabilidad |
| --- | --- | --- |
| Inventario | app/Actions/Inventory y app/Queries/Inventory | Recepción, ajustes, reservas, producción y lecturas paginadas |
| Compras | app/Actions/Purchases | Compras, pagos, documentos y saldos |
| Catálogo | app/Support/Catalog | Lectura local, sincronización parcial y reconciliación |
| Publicación | app/Actions/Publication y app/Jobs | Exportación, envío y seguimiento del despliegue |
| Interfaz | resources/views/filament/components por área | Formularios, filtros, historial y estados compartidos |

Extraer un caso de uso por cambio, mantener temporalmente BusinessLedger como fachada y conservar las pruebas de negocio. El objetivo es reducir acoplamiento, no repartir el mismo código en más carpetas. No convertir cada consulta en una llamada remota ni añadir microservicios para este volumen de trabajo.

## 2. Condiciones de salida a nube

1. Construir una imagen reproducible con dependencias y recursos compilados, sin montar el código de OneDrive. Fijar versiones de imágenes y establecer UID y permisos de almacenamiento en la imagen/despliegue.
2. Alojar aplicación y base en regiones cercanas. Medir latencia real antes de modificar conexiones. Verificar el modo de Supavisor usado, compatibilidad con transacciones/prepared statements y límite de conexiones; el pooling administra conexiones, no elimina consultas redundantes.
3. Antes de usar varias instancias, sustituir la cola local de publicaciones por una cola duradera compartida, con identificadores de trabajo, exclusión mutua, tiempo máximo, recuperación e idempotencia. Compartir también caché e invalidaciones. No escalar horizontalmente el almacén de archivos actual.
4. Mantener sesiones y archivos necesarios fuera de instancias efímeras. Definir copias de seguridad, retención y una restauración probada de compras, lotes, pagos y movimientos.
5. Añadir comprobación del despliegue de Cloudflare vinculada al commit enviado, con estados construyendo/publicada/fallida. Consultar únicamente cuando exista un trabajo activo, con espera creciente y límite de tiempo.
6. Configurar alertas sobre errores, cola detenida, antigüedad de la réplica, conexiones y consultas lentas. Proteger secretos y desactivar debug en producción.

## 3. Medición y aceptación

Registrar antes y después, con el mismo conjunto de datos y condiciones de red: tiempo p50/p95 al abrir Stock, cambiar filtro, guardar página y confirmar compra; consultas SQL, tamaño de respuesta y errores. Separar primera carga de navegación con caché.

Metas iniciales para validar, no resultados ya medidos: navegación con caché inferior a 500 ms p95 y confirmación de compras inferior a 1,5 s p95 bajo carga habitual. Ajustarlas con la línea base y la región elegida. Una escritura debe reflejarse en la lectura posterior y ningún reintento debe duplicar stock o pagos.

Probar concurrencia de compras/reservas con PostgreSQL, desconexión durante confirmación, expiración de caché, reinicio del trabajador y despliegue fallido. Mantener las operaciones de inventario transaccionales, incluso si la interfaz muestra datos temporalmente almacenados.

## Operación local

El servicio publication-worker se inicia junto con Compose y procesa únicamente publicaciones solicitadas. En modo Supabase usar ambos archivos Compose, igual que para app y sync-worker. No eliminar el volumen runtime_data para limpiar cachés: contiene también el estado de trabajos.

Para mantenimiento rutinario: `docker compose exec -T -u www-data app php artisan view:cache`. La configuración del almacén de publicaciones se controla con CLICKTAP_PUBLICATION_STORE; el valor publications es local a un único host. Las pruebas utilizan array y no publican en servicios externos.

Las comprobaciones visuales quedan omitidas por petición del usuario. La disposición de escritorio y móvil requiere una revisión visual posterior antes de considerarla aprobada para producción.
