# Reglas de trabajo para DomiPizzas

Estas reglas se aplican a todo el proyecto.

## Alcance y autorización

- Modificar únicamente los archivos y funcionalidades autorizados para la solicitud.
- No modificar archivos adicionales ni funcionalidades ajenas a la solicitud sin autorización previa del usuario.
- No realizar cambios destructivos sin autorización explícita del usuario.
- Si la solución requiere ampliar el alcance, explicar el motivo y solicitar autorización antes de realizar esos cambios.

## Modelo PostgreSQL

- Respetar el modelo PostgreSQL del proyecto y revisar el esquema correspondiente antes de modificar código que acceda a la base de datos.
- Mantener coherencia con los nombres reales de tablas, columnas, tipos de datos, claves y relaciones.
- No alterar el esquema, eliminar datos ni cambiar relaciones o restricciones fuera del alcance autorizado.
- Informar si los cambios afectan tablas PostgreSQL, consultas o funcionalidades relacionadas.

## Informe obligatorio al finalizar

Después de cada modificación, entregar un resumen detallado y verificable que incluya:

1. Los archivos modificados, creados o eliminados, indicando sus rutas.
2. Qué se cambió específicamente en cada archivo.
3. Las líneas de código agregadas, modificadas o eliminadas mediante un diff cuando sea posible.
4. Por qué fue necesario realizar cada cambio.
5. Si los cambios afectan otras funcionalidades o tablas de PostgreSQL, explicando el impacto o indicando que no hay impacto.
6. Qué verificaciones o pruebas se ejecutaron y sus resultados. Indicar expresamente las pruebas que no se pudieron realizar y sus limitaciones.

No finalizar una tarea diciendo únicamente que está corregida. No afirmar que una prueba pasó si no se ejecutó.
