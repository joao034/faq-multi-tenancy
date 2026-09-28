# FAQ multi-tenant

Una API de preguntas frecuentes pensada para atender a varios negocios a la vez. Cuando llega un mensaje, identifica el negocio por el número de WhatsApp de destino, busca la respuesta en los documentos de ese negocio y la redacta con Laravel AI SDK.

## Qué necesitas

- PHP 8.3 o superior
- Composer
- SQLite
- Una clave de Gemini, para generar embeddings y consultar el modelo

## Instalación

Los comandos funcionan igual en macOS, Linux y Windows (PowerShell, CMD, Git Bash o WSL). Desde la carpeta del proyecto:

```bash
composer install
composer setup
```

`composer setup` es un script definido en `composer.json` que crea el archivo `.env` si no existe, genera la clave de la aplicación, crea `database/database.sqlite` si falta y ejecuta las migraciones con los seeders.

Después, abre `.env` y pon tu `GEMINI_API_KEY`. Con la clave lista, genera los embeddings y levanta el servidor:

```bash
php artisan documents:embed
php artisan serve
```

`documents:embed` genera el embedding de los documentos que todavía no lo tienen, así que necesita una clave de Gemini válida. Tanto la API como los tests usan SQLite.

## Probar el chat

Envía una solicitud a `POST /api/v1/chat`:

```bash
curl -X POST http://127.0.0.1:8000/api/v1/chat \
  -H "Content-Type: application/json" \
  -d '{"to": "+593981111111", "from": "+593990000000", "message": "¿A qué hora abren?"}'
```

Funciona en macOS, Linux y Git Bash. En PowerShell puedes usar esta alternativa:

```powershell
$body = @{
    to = "+593981111111"
    from = "+593990000000"
    message = "¿A qué hora abren?"
} | ConvertTo-Json

Invoke-RestMethod -Method Post `
    -Uri "http://127.0.0.1:8000/api/v1/chat" `
    -ContentType "application/json" `
    -Body $body
```

El número `to` debe estar activo en `whatsapp_numbers`. El negocio se deduce de ese número, por lo que el cliente nunca envía un `business_id`.

Cuando todo sale bien, la API responde con HTTP 200 y un campo `source` que indica cómo se generó la respuesta:

- `llm`: la redactó el modelo a partir del contexto recuperado.
- `fallback`: el LLM falló, así que se devolvió la respuesta guardada del primer documento recuperado que tuviera una respuesta no vacía.
- `no_context`: ningún documento tuvo la similitud suficiente, o el fallback no encontró una respuesta guardada que se pudiera usar.

También hay errores controlados:

- **404**: el número no corresponde a un negocio activo.
- **422**: faltan campos requeridos.
- **429**: el negocio agotó su límite mensual.

Cualquier error inesperado devuelve HTTP 500.

## Agregar nuevo conocimiento (preguntas y respuestas del negocio)

Los documentos de ejemplo están en `database/seeders/DocumentSeeder.php`. Para sumar uno nuevo, agrega un registro con un título nuevo, el `business_id`, la `question` y la `answer` del negocio. Después ejecuta:

```bash
php artisan db:seed --class=DocumentSeeder
php artisan documents:embed
```

El comando solo crea embeddings para los documentos cuyo campo `embedding` está vacío.

## Configuración

Puedes ajustar estos valores en `.env`:

| Variable | Valor inicial | Para qué sirve |
| --- | --- | --- |
| `GEMINI_API_KEY` | — | Clave para las llamadas a Gemini |
| `LLM_MODEL` | `gemini-3.1-flash-lite` | Modelo que genera las respuestas |
| `EMBEDDING_MODEL` | `gemini-embedding-001` | Modelo de embeddings |
| `EMBEDDING_DIMENSIONS` | `768` | Dimensiones de los embeddings |
| `RETRIEVAL_TOP_K` | `3` | Máximo de documentos candidatos |
| `RETRIEVAL_SIMILARITY_THRESHOLD` | `0.75` | Similitud mínima para usar un documento |
| `DEFAULT_MONTHLY_REQUEST_LIMIT` | `500` | Límite mensual inicial para negocios nuevos |

El proveedor y los modelos de IA se configuran en `config/ai.php`. Los parámetros de retrieval y el límite inicial están en `config/chat.php`. Cada negocio guarda su propio `monthly_request_limit` en la base de datos.

## Ejecutar los tests

```bash
php artisan test --compact
```

Los tests de chat usan SQLite en memoria y reemplazan los embeddings y las respuestas del LLM por dobles de prueba. Por eso no necesitan clave de Gemini ni hacen llamadas externas.

Cubren, entre otras cosas: la resolución del negocio y el aislamiento de sus documentos, el retrieval con contenido relevante e irrelevante, los límites mensuales, los errores de embedding, el fallback del LLM y la validación de la solicitud.

## Cómo funciona y por qué se hizo así

1. `to` identifica un número activo y, con él, al negocio.
2. Se revisa el límite mensual y se guarda la solicitud aceptada.
3. Laravel AI SDK genera el embedding de la pregunta.
4. El retrieval filtra por `business_id`, ordena por similitud coseno, toma los `top_k` candidatos y aplica el umbral.
5. Si ningún documento supera el umbral, se devuelve una respuesta controlada sin llamar al LLM.
6. Si hay contexto, el LLM recibe solo esos documentos y la instrucción de responder en español sin inventar datos.
7. Si la llamada al LLM falla, el fallback devuelve la primera respuesta guardada no vacía entre los documentos recuperados. Si no hay ninguna, devuelve la respuesta controlada de `no_context`.

Para esta primera versión elegí lo más simple: una base SQLite compartida, con `business_id` para separar los datos de cada negocio. Los embeddings se guardan como JSON y la similitud se calcula en la aplicación.

Toda solicitud aceptada cuenta para la cuota, aunque termine como `no_context`, `fallback` o `failed`. La cuota limita solicitudes, no tokens ni costo exacto.

Para más contexto sobre estas decisiones, revisa la [ficha técnica](./ficha-tecnica.md).

## Qué no incluye esta versión

Esta versión no trae la integración con la API oficial de WhatsApp, un panel administrativo, edición de documentos desde una pantalla, historial de conversaciones, base vectorial ni proveedores alternativos. El campo `from` se conserva para cuando se implemente el historial.

## Qué haría después con más tiempo

Lo primero sería cambiar la simulación de WhatsApp por la API oficial, con autenticación y verificación de webhooks.

Después agregaría una interfaz para que cada negocio administre su propia base de conocimiento, guardaría el historial de conversaciones por cliente y sumaría métricas sobre la calidad del retrieval y de las respuestas.

Si el volumen crece, migraría los embeddings a PostgreSQL con `pgvector` y reforzaría el control de costos, el rate limiting, la observabilidad y el fallback entre proveedores y modelos.

La prioridad sería siempre proteger el aislamiento entre negocios y la confiabilidad de las respuestas antes de sumar complejidad a la plataforma.