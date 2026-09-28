# Ficha técnica — Flujo "mensaje → respuesta"

## Objetivo y alcance

Construir un pequeño servicio en Laravel capaz de recibir un mensaje de texto asociado a un negocio, recuperar información relevante exclusivamente de ese negocio y generar una respuesta utilizando un LLM.

La implementación busca demostrar:

* Un flujo completo `mensaje → contexto → LLM → respuesta`.
* Multi-tenancy básico mediante aislamiento lógico por `business_id`.
* Recuperación semántica mediante embeddings y similitud coseno.
* Grounding de la respuesta utilizando únicamente información recuperada.
* Control básico de costo mediante un límite de requests.
* Un mecanismo de fallback cuando el LLM no esté disponible.
* Tests automatizados sobre las reglas de negocio principales.

### Alcance funcional

El sistema tendrá un único endpoint:

```text
POST /api/v1/chat
```

El endpoint simulará la recepción de un mensaje desde WhatsApp.

El número de WhatsApp del negocio permitirá identificar automáticamente el tenant.

El sistema no implementará una integración real con Meta/WhatsApp Business API.

---

## Regla de negocio principal

Un negocio solo puede recibir respuestas generadas utilizando información perteneciente a su propio conjunto de documentos.

El tenant se determina a partir del número de WhatsApp al que fue enviado el mensaje.

Por ejemplo:

```text
to = +593981111111
        ↓
WhatsAppNumber
        ↓
business_id = 1
        ↓
Documentos donde business_id = 1
```

La búsqueda semántica debe realizarse **después de aplicar el filtro por `business_id`**.

Nunca se deben considerar documentos pertenecientes a otro negocio durante el proceso de retrieval.

### Reglas complementarias

**1. Contexto insuficiente**

Si ningún documento alcanza el umbral mínimo de similitud:

```text
similarity < 0.75
```

el sistema no debe solicitar una respuesta al LLM y debe devolver una respuesta controlada:

```text
No encuentro información suficiente para responder esa pregunta.
```

El valor `0.75` será configurable.

**2. Grounding**

Cuando existe contexto relevante, el LLM debe responder únicamente utilizando la información recuperada.

Debe indicar que no dispone de información suficiente cuando el contexto no permite responder.

No debe completar la respuesta utilizando conocimiento externo ni inventar información.

**3. Límite de uso**

Cada negocio tendrá un límite mensual de requests.

Ejemplo:

```text
monthly_request_limit = 500
```

Una request que exceda el límite deberá rechazarse antes de realizar la llamada al LLM.

Para este demo se controlará el número de requests, no el costo monetario exacto por token.

**4. Fallback**

Si el LLM falla después de recuperar contexto válido:

* Si existe un documento con suficiente similitud, se podrá devolver directamente su contenido/answer como respuesta determinística.
* Si no existe una respuesta segura basada en el contexto, se devolverá un mensaje controlado.

El fallback nunca deberá utilizar contexto perteneciente a otro tenant.

---

## Decisiones de diseño

### 1. Multi-tenancy lógico

Se utilizará una base de datos compartida con tablas compartidas.

Los recursos pertenecientes a un negocio estarán asociados mediante:

```text
business_id
```

Esto corresponde a un modelo de **logical/shared-database multi-tenancy**.

No se utilizará una base de datos independiente por negocio en esta primera versión porque introduciría complejidad innecesaria para el alcance del demo.

### 2. Identificación del tenant

El cliente no enviará:

```json
{
  "tenant_id": 1
}
```

En su lugar, el negocio se identificará mediante:

```json
{
  "to": "+593981111111"
}
```

El número recibido se resolverá contra la tabla `whatsapp_numbers`.

Importante:

> El número de WhatsApp identifica al tenant, pero no es el tenant.

Un mismo negocio podría tener múltiples números de WhatsApp.

### 3. Retrieval

Para mantener la solución pequeña se utilizarán embeddings almacenados en SQLite y la similitud coseno calculada en la aplicación.

No se utilizará inicialmente un vector database ni `pgvector`.

La estrategia será:

```text
mensaje
   ↓
embedding
   ↓
filtrar documentos por business_id
   ↓
calcular similitud
   ↓
ordenar por similitud
   ↓
seleccionar Top-K
   ↓
aplicar threshold
```

Parámetros iniciales:

```text
TOP_K = 3
SIMILARITY_THRESHOLD = 0.75
```

Estos valores serán configurables.

### 4. LLM

El proveedor del LLM se abstraerá mediante un servicio para evitar que la lógica de negocio dependa directamente de la implementación del proveedor.

Por ejemplo:

```text
LLMService
```

En este demo se podrá utilizar un único proveedor.

### 5. Fallback

No se implementará inicialmente un segundo proveedor/modelo.

El fallback será determinístico y se apoyará en el contexto recuperado cuando sea suficientemente confiable.

Esto permite demostrar resiliencia sin introducir complejidad innecesaria.

### 6. Historial

No se implementará memoria conversacional en la primera versión.

El campo `from` se conservará en la request para permitir una futura implementación de historial por cliente.

---

## Contrato HTTP

### Endpoint

```http
POST /api/v1/chat
Content-Type: application/json
```

### Request

```json
{
  "to": "+593981111111",
  "from": "+593990000000",
  "message": "¿A qué hora abren?"
}
```

### Campos

| Campo     | Tipo   | Requerido | Descripción                    |
| --------- | ------ | --------- | ------------------------------ |
| `to`      | string | Sí        | Número de WhatsApp del negocio |
| `from`    | string | Sí        | Número del cliente             |
| `message` | string | Sí        | Mensaje enviado por el cliente |

### Response exitosa

```json
{
  "answer": "Abrimos de lunes a sábado de 09:00 a 18:00.",
  "source": "llm"
}
```

`source` permitirá distinguir entre una respuesta generada por el LLM y una respuesta obtenida mediante fallback.

Ejemplo:

```json
{
  "answer": "Nuestro horario es de lunes a sábado de 09:00 a 18:00.",
  "source": "fallback"
}
```

### Respuestas controladas

Tenant no encontrado:

```http
404
```

```json
{
  "message": "Business not found."
}
```

Límite excedido:

```http
429
```

```json
{
  "message": "Monthly request limit exceeded."
}
```

Contexto insuficiente:

```http
200
```

```json
{
  "answer": "No encuentro información suficiente para responder esa pregunta.",
  "source": "no_context"
}
```

Error inesperado:

```http
500
```

```json
{
  "message": "Unable to process request."
}
```

---

### Persistencia y datos de ejemplo
# Se utilizará:

# SQLite
## Fuente de conocimiento del MVP

No se utilizarán archivos externos como PDF, DOCX o un FAQ.json para alimentar el sistema.

El conocimiento ficticio utilizado por el MVP estará definido directamente en seeders de Laravel y será insertado en la tabla documents.

Por ejemplo:

* database/seeders/BusinessSeeder.php
* database/seeders/WhatsAppNumberSeeder.php
* database/seeders/DocumentSeeder.php

El DocumentSeeder podrá contener datos como:

```php
[
    'business_id' => 1,
    'title' => 'Horario de atención',
    'question' => '¿Cuál es el horario de atención?',
    'answer' => 'Atendemos de lunes a sábado de 09:00 a 18:00.',
]
```

Esto permite mantener el proyecto pequeño y reproducible sin implementar un pipeline de carga de documentos.

### businesses
* id
* name
* monthly_request_limit
* created_at
* updated_at

Ejemplo:

```text
1 | Café Andino | 500
2 | Panadería Central | 500
```

### whatsapp_numbers
* id
* business_id
* phone_number
* active
* created_at
* updated_at

Ejemplo:

```text
1 | 1 | +593981111111 | true
2 | 2 | +593982222222 | true
```

### documents
* id
* business_id
* title
* question
* answer
* embedding
* created_at
* updated_at

Ejemplos:

**Business 1 - Café Andino**

**Título:**
Horario de atención

**Pregunta:**
¿Cuál es el horario de atención?

**Respuesta:**
Atendemos de lunes a sábado de 09:00 a 18:00.

**Business 1 - Café Andino**

**Título:**
Delivery

**Pregunta:**
¿Realizan entregas?

**Respuesta:**
Realizamos entregas dentro de Cuenca con un costo de \$2.

## Generación de embeddings

El embedding no se generará sobre un archivo JSON completo.

Para cada registro de documents se construirá un texto a partir de sus campos:

```text
Título: Horario de atención
Pregunta: ¿Cuál es el horario de atención?
Respuesta: Atendemos de lunes a sábado de 09:00 a 18:00.
```

Ese texto será enviado a:

`gemini-embedding-001`

y el vector resultante de 768 dimensiones se almacenará en:

`documents.embedding`

En SQLite podrá almacenarse como JSON:

```json
[0.0123, -0.0487, 0.0912, ...]
```

Cuando llegue una consulta del usuario, se generará otro embedding para el mensaje y se comparará contra los embeddings de los documentos del mismo business_id.

### chat_requests

Para controlar el uso:

* id
* business_id
* from
* message
* model
* tokens
* status
* created_at

Para la primera versión, no se considerará el número de tokens como límite de uso.

El límite mensual puede calcularse mediante:

```sql
COUNT(chat_requests)
WHERE business_id = X
AND created_at >= inicio_del_mes
```

---

## Arquitectura y flujo

### Flujo principal

```text
Cliente
   │
   │ POST /api/v1/chat
   ▼
ChatController
   │
   ▼
BusinessResolver
   │
   │ to → business_id
   ▼
UsageService
   │
   │ ¿Límite disponible?
   ▼
EmbeddingService
   │
   │ embedding del mensaje
   ▼
RetrievalService
   │
   │ WHERE business_id = X
   │ + similarity
   ▼
¿Contexto relevante?
   │
   ├── NO ──► respuesta controlada
   │
   └── SÍ
         │
         ▼
      LLMService
         │
      ┌──┴──────────┐
      │             │
    éxito          error
      │             │
      ▼             ▼
   respuesta     FallbackService
                    │
                    ▼
                 respuesta
```

### Prompt del LLM

El modelo deberá recibir el mensaje y solamente el contexto recuperado.

Conceptualmente:

```text
Eres un asistente de atención al cliente.

Responde únicamente utilizando la información proporcionada en el contexto.

No inventes datos y no utilices conocimiento externo.

Si el contexto no contiene suficiente información para responder,
indica que no tienes información suficiente.

Contexto:
{retrieved_context}

Pregunta:
{user_message}
```

---

## Estructura Laravel

```text
app/
├── Http/
│   └── Controllers/
│       └── ChatController.php
│
├── Models/
│   ├── Business.php
│   ├── WhatsAppNumber.php
│   ├── Document.php
│   └── ChatRequest.php
│
└── Services/
    ├── BusinessResolver.php
    ├── EmbeddingService.php
    ├── RetrievalService.php
    ├── LLMService.php
    ├── UsageService.php
    ├── FallbackService.php
    └── ChatService.php

database/
├── migrations/
└── seeders/

routes/
└── api.php

tests/
├── Feature/
│   └── ChatTest.php
└── Unit/
    ├── RetrievalServiceTest.php
    └── BusinessResolverTest.php

README.md
```

### Responsabilidades

**`ChatController`**

Recibe y valida la request. No contiene lógica de negocio compleja.

**`BusinessResolver`**

Convierte:

```text
WhatsApp number → Business
```

**`UsageService`**

Valida el límite mensual del negocio.

**`EmbeddingService`**

Genera embeddings para la consulta.

**`RetrievalService`**

Obtiene documentos del tenant correspondiente y calcula similitud.

**`LLMService`**

Encapsula la interacción con el proveedor LLM.

**`FallbackService`**

Genera una respuesta segura cuando el LLM falla.

**`ChatService`**

Orquesta el flujo principal.

Esto permite que el dominio de conversación no dependa directamente de WhatsApp.

En el futuro otro canal podría utilizar el mismo servicio:

```text
WhatsApp ──────┐
Web ───────────┼──► ChatService
API ───────────┤
Telegram ──────┘
```

---

## Plan de implementación por fases

### Fase 1 — Bootstrap del proyecto

* Crear proyecto Laravel.
* Configurar SQLite.
* Configurar variables de entorno.
* Crear endpoint.
* Preparar estructura básica de servicios.

**Resultado:** endpoint funcionando y validación básica.

### Fase 2 — Multi-tenancy

* Crear `businesses`.
* Crear `whatsapp_numbers`.
* Crear relaciones Eloquent.
* Implementar `BusinessResolver`.
* Validar que el número `to` corresponda a un negocio activo.

**Resultado:** cada request queda asociada a un tenant.

### Fase 3 — Knowledge base

* Crear `documents`.
* Crear seeders con 2 negocios y varios documentos.
* Generar embeddings para los documentos.
* Persistir embeddings.

**Resultado:** cada negocio posee su propia base de conocimiento.

### Fase 4 — Retrieval

* Generar embedding del mensaje.
* Filtrar documentos por `business_id`.
* Calcular similitud coseno.
* Ordenar por similitud.
* Seleccionar Top-K.
* Aplicar threshold.

**Resultado:** el LLM recibe únicamente contexto relevante y perteneciente al tenant.

### Fase 5 — LLM

* Implementar `LLMService`.
* Crear prompt con grounding.
* Generar respuesta utilizando contexto recuperado.
* Manejar errores y timeouts.

**Resultado:** flujo completo `mensaje → retrieval → LLM → respuesta`.

### Fase 6 — Uso y fallback

* Implementar `ChatRequest`.
* Validar límite mensual.
* Implementar `FallbackService`.
* Evitar llamadas al LLM cuando no exista contexto relevante.

**Resultado:** control básico de costo y resiliencia.

### Fase 7 — Tests y documentación

* Agregar tests de reglas de negocio.
* Crear README.
* Documentar decisiones y posibles mejoras.

---

## Tests y criterios de aceptación

### Tests mínimos

**1. Resolución del tenant**

Dado un número de WhatsApp registrado:

```text
to = +593981111111
```

el sistema debe resolver correctamente el negocio correspondiente.

**2. Aislamiento de tenants**

Si una consulta pertenece al Business A, los documentos del Business B nunca deben formar parte del contexto recuperado.

**3. Retrieval relevante**

Una pregunta relacionada con un documento debe superar el threshold y recuperar ese documento.

**4. Retrieval irrelevante**

Una pregunta no relacionada debe quedar por debajo del threshold y no debe llamar al LLM.

**5. Límite de requests**

Cuando el negocio alcanza su límite mensual, la request debe rechazarse con `429`.

**6. Fallback**

Cuando el LLM genera un error y existe contexto suficientemente relevante, el sistema debe devolver una respuesta mediante fallback.

**7. Validación del request**

Una request sin `to`, `from` o `message` debe devolver un error de validación.

### Criterios de aceptación

El MVP se considera terminado cuando:

* `POST /api/v1/chat` funciona.
* El tenant se determina mediante `to`.
* Los datos se aíslan mediante `business_id`.
* La búsqueda utiliza embeddings y similitud.
* Existe un threshold configurable.
* El LLM recibe únicamente contexto recuperado.
* Las consultas sin contexto relevante no llaman al LLM.
* Existe un límite mensual de requests.
* Existe un fallback ante fallo del LLM.
* Los casos principales están cubiertos por tests.
* El README explica cómo ejecutar el proyecto y las principales decisiones técnicas.

---

## Configuración

Los modelos y parámetros técnicos se mantendrán configurables mediante variables de entorno.

LLM_API_KEY
LLM_MODEL=gemini-3.1-flash-lite

EMBEDDING_MODEL=gemini-embedding-001
EMBEDDING_DIMENSIONS=768

RETRIEVAL_TOP_K=3
RETRIEVAL_SIMILARITY_THRESHOLD=0.75

DEFAULT_MONTHLY_REQUEST_LIMIT=500

Los valores relacionados con retrieval y límites de uso también permanecerán configurables
para poder ajustarlos sin modificar el código.

---

## No alcance de esta versión

Para mantener el proyecto pequeño, no se implementarán inicialmente:

* Integración real con Meta WhatsApp Business API.
* Frontend.
* Autenticación de usuarios administrativos.
* Panel para gestionar documentos.
* Memoria conversacional.
* Streaming.
* Vector database.
* PostgreSQL/pgvector.
* Múltiples proveedores LLM.
* Kubernetes o microservicios.
* Sistema avanzado de billing.
* Observabilidad avanzada.

Estas capacidades forman parte de la evolución posterior y no son necesarias para demostrar el flujo solicitado.

---

