# Doglio API — Guia de Integração Flutter

> Versão: v1 | Base URL: `https://<seu-dominio>/api/v1`

---

## 1. Configuração Base

### Headers obrigatórios em toda requisição
```
Content-Type: application/json
Accept: application/json
```

### Rotas autenticadas (adicionar)
```
Authorization: Bearer <token>
```

O token é obtido no login e deve ser persistido com `FlutterSecureStorage` (nunca `SharedPreferences`).

---

## 2. Formato Padrão de Resposta

### Sucesso
```json
{
  "success": true,
  "message": "Texto da operação",
  "data": { ... }
}
```

### Sucesso com lista paginada
```json
{
  "data": [ ... ],
  "links": {
    "first": "https://.../api/v1/products?page=1",
    "last":  "https://.../api/v1/products?page=5",
    "prev":  null,
    "next":  "https://.../api/v1/products?page=2"
  },
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 73,
    "from": 1,
    "to": 15
  }
}
```

### Erro
```json
{
  "success": false,
  "message": "Mensagem de erro em português",
  "error": {
    "code": "CODIGO_DO_ERRO",
    "details": { ... }
  }
}
```

### Erro de validação (422)
```json
{
  "success": false,
  "message": "Falha na validação.",
  "errors": {
    "email": ["O campo email é obrigatório."],
    "password": ["A senha deve ter pelo menos 8 caracteres."]
  }
}
```

### Códigos HTTP usados
| Código | Significado |
|---|---|
| 200 | OK |
| 201 | Criado com sucesso |
| 204 | Sem conteúdo (ex: delete) |
| 401 | Não autenticado (token ausente ou inválido) |
| 403 | Sem permissão (conta inativa, não é admin) |
| 404 | Recurso não encontrado |
| 422 | Erro de validação |
| 429 | Rate limit atingido |
| 500 | Erro interno |

> **Todos os IDs são strings (hashids), nunca inteiros.**

---

## 3. Autenticação

### POST `/register`
**Público | Rate limit: 10/min**

Request:
```json
{
  "name": "João Silva",
  "email": "joao@email.com",
  "password": "senha123",
  "password_confirmation": "senha123",
  "cpf_cnpj": "123.456.789-09",
  "birth_date": "1990-05-15",
  "city": "São Paulo",
  "state": "SP"
}
```

> `cpf_cnpj`, `birth_date`, `city`, `state` são opcionais.

Response `201`:
```json
{
  "success": true,
  "message": "Usuário criado com sucesso.",
  "data": {
    "user": {
      "id": "abc123",
      "name": "João Silva",
      "email": "joao@email.com",
      "role": "customer",
      "city": "São Paulo",
      "state": "SP",
      "cpf_cnpj": "123.456.789-09",
      "birth_date": "1990-05-15"
    },
    "token": "1|xxxxxxxxxxxxxxxxxxx",
    "token_type": "Bearer"
  }
}
```

---

### POST `/login`
**Público | Rate limit: 10/min**

Request:
```json
{
  "email": "joao@email.com",
  "password": "senha123",
  "push_token": "fcm_token_aqui",
  "platform": "android"
}
```

> `push_token` e `platform` (`android` | `ios`) são opcionais — enviar para ativar notificações push.

Response `200`:
```json
{
  "success": true,
  "message": "Login realizado com sucesso.",
  "data": {
    "user": {
      "id": "abc123",
      "name": "João Silva",
      "email": "joao@email.com",
      "role": "customer",
      "city": "São Paulo",
      "state": "SP",
      "cpf_cnpj": "123.456.789-09",
      "birth_date": "1990-05-15"
    },
    "token": "1|xxxxxxxxxxxxxxxxxxx",
    "token_type": "Bearer"
  }
}
```

Erros possíveis:
- `401` — credenciais inválidas
- `403` — conta desativada (`"Sua conta foi desativada. Entre em contato com o suporte."`)

---

### POST `/logout`
**Autenticado**

Body (opcional — remove push token do dispositivo):
```json
{ "push_token": "fcm_token_aqui" }
```

Response `200`:
```json
{
  "success": true,
  "message": "Logout realizado com sucesso."
}
```

---

### GET `/user`
**Autenticado**

Response `200`:
```json
{
  "data": {
    "id": "abc123",
    "name": "João Silva",
    "email": "joao@email.com",
    "role": "customer",
    "city": "São Paulo",
    "state": "SP",
    "cpf_cnpj": "123.456.789-09",
    "birth_date": "1990-05-15"
  }
}
```

---

## 4. Produtos

### GET `/products`
**Público**

Query params opcionais:
| Parâmetro | Tipo | Exemplo |
|---|---|---|
| `search` | string | `?search=ração` — busca em nome **e** descrição |
| `name` | string | `?name=ração` — busca só no nome |
| `description` | string | `?description=adultos` — busca só na descrição |
| `category_id` | hashid | `?category_id=abc` |
| `on_promotion` | bool | `?on_promotion=1` |
| `is_highlighted` | bool | `?is_highlighted=1` |
| `in_stock` | bool | `?in_stock=1` |
| `out_of_stock` | bool | `?out_of_stock=1` |
| `price_min` | number | `?price_min=10.00` |
| `price_max` | number | `?price_max=200.00` |
| `price_from` | number | `?price_from=10.00&price_to=200.00` — sintaxe alternativa |
| `price_to` | number | (usar junto com `price_from`) |
| `sort_by` | string | `?sort_by=price` (`name`, `price`, `stock_quantity`, `created_at`, `updated_at`) |
| `sort_order` | string | `?sort_order=asc` ou `desc` |
| `per_page` | number | `?per_page=20` |

Response `200` (paginado):
```json
{
  "data": [
    {
      "id": "abc123",
      "name": "Ração Premium",
      "description": "Ração para cães adultos...",
      "price": "89.90",
      "original_price": null,
      "effective_price": null,
      "discount_amount": null,
      "promotion": null,
      "in_stock": true,
      "is_highlighted": true,
      "is_active": true,
      "images": [
        {
          "id": "img1",
          "url": "https://.../storage/products/foto.jpg",
          "is_primary": true,
          "order": 0
        }
      ],
      "primary_image": {
        "id": "img1",
        "url": "https://.../storage/products/foto.jpg",
        "is_primary": true,
        "order": 0
      },
      "categories": [
        {
          "id": "cat1",
          "name": "Rações",
          "slug": "racoes",
          "is_highlighted": false,
          "is_active": true,
          "products_count": 12
        }
      ],
      "average_rating": 4.5,
      "reviews_count": 12
    }
  ],
  "links": { ... },
  "meta": { "current_page": 1, "last_page": 3, "per_page": 15, "total": 42 }
}
```

> `stock_quantity` (integer) só aparece se o usuário autenticado for **admin**.
> Quando `on_promotion=1`, os campos `original_price`, `effective_price`, `discount_amount` e `promotion` são preenchidos.

---

### GET `/products/{id}`
**Público**

Response `200`:
```json
{
  "data": {
    "id": "abc123",
    "name": "Ração Premium",
    "description": "Ração para cães adultos...",
    "price": "89.90",
    "original_price": "89.90",
    "effective_price": "76.42",
    "discount_amount": "13.48",
    "promotion": {
      "id": "promo1",
      "name": "Semana Pet",
      "type": "percentage",
      "discount_value": 15.0
    },
    "in_stock": true,
    "is_highlighted": true,
    "is_active": true,
    "images": [ ... ],
    "primary_image": { ... },
    "categories": [ ... ],
    "average_rating": 4.5,
    "reviews_count": 12
  }
}
```

---

### GET `/products/{id}/reviews`
**Público | Paginado**

Response `200`:
```json
{
  "data": [
    {
      "id": "rev1",
      "rating": 5,
      "comment": "Produto excelente!",
      "user": { "id": "usr1", "name": "Maria" },
      "created_at": "2026-05-10T14:23:00+00:00"
    }
  ],
  "meta": { ... }
}
```

---

## 5. Categorias

### GET `/categories`
**Público**

Response `200`:
```json
{
  "data": [
    {
      "id": "cat1",
      "name": "Rações",
      "slug": "racoes",
      "is_highlighted": true,
      "is_active": true,
      "products_count": 12
    }
  ]
}
```

> Não há campo `description` em categorias.

---

## 6. Carrinho

> O carrinho é **sync-based**: o app envia o estado completo a cada mudança. Use debounce de ~500ms no Flutter.

### POST `/cart/sync`
**Autenticado**

Request:
```json
{
  "items": [
    { "product_id": "abc123", "quantity": 2 },
    { "product_id": "def456", "quantity": 1 }
  ]
}
```

Response `200`:
```json
{
  "success": true,
  "message": "Carrinho sincronizado.",
  "data": {
    "items": [
      {
        "id": "item1",
        "quantity": 2,
        "unit_price": "76.42",
        "current_price": "76.42",
        "price_changed": false,
        "subtotal": "152.84",
        "promotion": {
          "id": "promo1",
          "name": "Semana Pet",
          "type": "percentage",
          "discount_value": 15.0,
          "is_still_active": true
        },
        "product": {
          "id": "abc123",
          "name": "Ração Premium",
          "original_price": "89.90",
          "effective_price": "76.42",
          "stock_quantity": 50,
          "in_stock": true,
          "primary_image": {
            "id": "img1",
            "url": "https://.../storage/products/foto.jpg",
            "is_primary": true,
            "order": 0
          }
        },
        "stock_warning": false
      }
    ],
    "total": "152.84",
    "items_count": 1,
    "has_stock_warning": false,
    "has_price_change": false
  }
}
```

> `unit_price` = preço salvo no sync. `current_price` = preço atual do produto.
> Se `stock_warning: true`, a quantidade pedida supera o estoque disponível.
> `has_stock_warning` e `has_price_change` são flags globais do carrinho (conveniência).

---

### GET `/cart`
**Autenticado**

Retorna o carrinho atual. Mesma estrutura do sync (sem `message`).

---

### GET `/cart/validate`
**Autenticado**

Detecta o que mudou desde o último sync. Response `200`:

**Carrinho válido (sem mudanças):**
```json
{
  "success": true,
  "message": "Carrinho válido.",
  "data": {
    "valid": true,
    "changes": []
  }
}
```

**Carrinho com mudanças:**
```json
{
  "success": true,
  "message": "Seu carrinho teve alterações.",
  "data": {
    "valid": false,
    "changes": [
      {
        "type": "price_changed",
        "product_id": "abc123",
        "product_name": "Ração Premium",
        "old_price": "89.90",
        "new_price": "76.42",
        "promotion_id": "promo1",
        "promotion_name": "Semana Pet"
      },
      {
        "type": "promotion_expired",
        "product_id": "def456",
        "product_name": "Petisco",
        "promotion_name": "Black Friday"
      },
      {
        "type": "out_of_stock",
        "product_id": "ghi789",
        "product_name": "Brinquedo"
      },
      {
        "type": "stock_reduced",
        "product_id": "jkl012",
        "product_name": "Coleira",
        "requested_quantity": 5,
        "available_quantity": 2
      },
      {
        "type": "product_unavailable",
        "cart_item_id": "item99"
      }
    ]
  }
}
```

**Tipos de mudança (`type`):**
| Tipo | Descrição |
|---|---|
| `price_changed` | Preço mudou — `old_price` / `new_price` disponíveis |
| `promotion_expired` | A promoção que estava aplicada expirou |
| `out_of_stock` | Produto sem estoque |
| `stock_reduced` | Estoque ficou menor que a quantidade pedida |
| `product_unavailable` | Produto foi removido/desativado |

---

### DELETE `/cart`
**Autenticado**

Sem body. Response `200`:
```json
{
  "success": true,
  "message": "Carrinho limpo com sucesso."
}
```

---

## 7. Métodos de Pagamento

### GET `/payment_methods`
**Público**

Retorna os métodos de pagamento ativos. Use para obter o `payment_method_id` correto antes de chamar `/checkout`.

Response `200`:
```json
{
  "data": [
    {
      "id": "pm_abc123",
      "name": "PIX",
      "type": "pix",
      "is_active": true
    }
  ]
}
```

**Tipos possíveis (`type`):** `pix` | `credit_card` | `boleto`

> Chame este endpoint uma vez na inicialização do app e cache o resultado localmente. O `id` retornado é o valor a usar em `payment_method_id` no checkout.

---

## 8. Checkout

### POST `/checkout`
**Autenticado**

**Retirada na loja:**
```json
{
  "payment_method_id": "pm1",
  "delivery_type": "pickup"
}
```

**Entrega com endereço salvo:**
```json
{
  "payment_method_id": "pm1",
  "delivery_type": "delivery",
  "address_id": "addr1"
}
```

**Entrega com endereço manual:**
```json
{
  "payment_method_id": "pm1",
  "delivery_type": "delivery",
  "shipping_street": "Rua das Flores",
  "shipping_number": "142",
  "shipping_complement": "Apto 31",
  "shipping_city": "São Paulo",
  "shipping_state": "SP",
  "shipping_zip": "01310100"
}
```

> `shipping_zip` deve ter **exatamente 8 dígitos** (sem hífen).

Response `201`:
```json
{
  "success": true,
  "message": "Pedido realizado com sucesso.",
  "data": {
    "id": "ord1",
    "status": "pending",
    "total_amount": "152.84",
    "delivery_type": "delivery",
    "shipping_address": {
      "street": "Rua das Flores",
      "number": "142",
      "complement": "Apto 31",
      "city": "São Paulo",
      "state": "SP",
      "zip": "01310100"
    },
    "items": [
      {
        "id": "oi1",
        "quantity": 2,
        "unit_price": "76.42",
        "subtotal": "152.84",
        "product": {
          "id": "abc123",
          "name": "Ração Premium",
          "primary_image": {
            "id": "img1",
            "url": "https://.../storage/products/foto.jpg",
            "is_primary": true,
            "order": 0
          }
        }
      }
    ],
    "payment": {
      "id": "pay1",
      "status": "pending",
      "amount": "152.84",
      "payment_method": { "id": "pm1", "name": "PIX", "type": "pix", "is_active": true },
      "pix_code": "00020126...",
      "pix_expires_at": "2026-05-20T15:00:00+00:00",
      "boleto_code": null,
      "boleto_expires_at": null,
      "card_last_four": null,
      "card_brand": null,
      "installments": null,
      "paid_at": null
    },
    "created_at": "2026-05-20T14:00:00+00:00"
  }
}
```

**Erros possíveis:**
- `422` + `code: CART_EMPTY` — carrinho vazio
- `422` + `code: INSUFFICIENT_STOCK` — estoque insuficiente, `details` lista os produtos afetados
- `422` + `code: VALIDATION_ERROR` — payment_method_id inválido ou campos de endereço faltando

---

## 9. Pedidos

### GET `/orders`
**Autenticado | Paginado**

Response: lista de pedidos com `items` e `payment` (estrutura igual ao checkout).

---

### GET `/orders/{id}`
**Autenticado**

Response `200`: pedido completo com `status_history`:
```json
{
  "data": {
    "id": "ord1",
    "status": "delivered",
    "total_amount": "152.84",
    "delivery_type": "delivery",
    "shipping_address": { ... },
    "items": [ ... ],
    "payment": { ... },
    "status_history": [
      { "status": "pending",    "created_at": "2026-05-20T14:00:00+00:00" },
      { "status": "processing", "created_at": "2026-05-20T15:00:00+00:00" },
      { "status": "shipped",    "created_at": "2026-05-21T09:00:00+00:00" },
      { "status": "delivered",  "created_at": "2026-05-22T11:00:00+00:00" }
    ],
    "created_at": "2026-05-20T14:00:00+00:00"
  }
}
```

**Status possíveis (em ordem):** `pending` → `processing` → `shipped` → `delivered` | `cancelled`

---

## 10. Endereços

### GET `/addresses`
**Autenticado**

Response `200`:
```json
{
  "data": [
    {
      "id": "addr1",
      "label": "Casa",
      "street": "Rua das Flores",
      "number": "142",
      "complement": "Apto 31",
      "city": "São Paulo",
      "state": "SP",
      "zip": "01310100",
      "is_primary": true
    }
  ]
}
```

### POST `/addresses`
**Autenticado**

Request:
```json
{
  "label": "Casa",
  "street": "Rua das Flores",
  "number": "142",
  "complement": "Apto 31",
  "city": "São Paulo",
  "state": "SP",
  "zip": "01310100",
  "is_primary": true
}
```

### PUT `/addresses/{id}`
**Autenticado** — mesmos campos do POST.

### DELETE `/addresses/{id}`
**Autenticado** — Response `200` com mensagem de confirmação.

### PATCH `/addresses/{id}/primary`
**Autenticado** — Sem body. Define este como principal (desmarca os outros automaticamente).

---

## 11. Avaliações

### POST `/products/{id}/reviews`
**Autenticado** — Exige que o usuário tenha uma compra com status `delivered` para esse produto.

Request:
```json
{
  "rating": 5,
  "comment": "Produto excelente!"
}
```

Response `201`:
```json
{
  "success": true,
  "message": "Avaliação criada com sucesso.",
  "data": {
    "id": "rev1",
    "rating": 5,
    "comment": "Produto excelente!",
    "user": { "id": "usr1", "name": "João" },
    "created_at": "2026-05-20T14:00:00+00:00"
  }
}
```

### DELETE `/reviews/{id}`
**Autenticado** — Só pode deletar a própria avaliação.

---

## 12. Favoritos

### GET `/favorites`
**Autenticado** (não paginado — retorna todos)

Response `200`:
```json
{
  "data": [
    {
      "id": "fav1",
      "notify_on_restock": true,
      "product": {
        "id": "abc123",
        "name": "Ração Premium",
        "price": "89.90",
        "in_stock": false,
        "primary_image": { ... }
      },
      "created_at": "2026-05-10T14:00:00+00:00"
    }
  ]
}
```

### POST `/favorites`
**Autenticado**

Request:
```json
{
  "product_id": "abc123",
  "notify_on_restock": true
}
```

> `notify_on_restock` é opcional (padrão: `true`).

### DELETE `/favorites/{id}`
**Autenticado** — `{id}` é o ID do **favorito** (não do produto).

### PATCH `/favorites/{id}/notify`
**Autenticado** — Sem body. Alterna o valor de `notify_on_restock`.

---

## 13. Regras de Negócio Importantes

### IDs
- Todos os IDs são **strings hashid**, nunca inteiros
- Use como string opaca — não tente decodificar

### Preços
- Todos os valores monetários são **strings** (ex: `"89.90"`)
- Use `double.parse(price)` no Dart para calcular

### Imagens
- `primary_image` → thumbnail principal
- `images[]` → lista completa ordenada por `order`

### Paginação
- Campos úteis: `meta.current_page`, `meta.last_page`, `meta.total`
- Query params: `?page=N&per_page=15`

### Token
- Não expira automaticamente (Sanctum)
- Salvar com `FlutterSecureStorage`
- No logout, o token é invalidado no servidor

### Rate limits
- Login/register: **10 req/min por IP** — implemente backoff em 429
- Rotas públicas: **60 req/min**
- Rotas autenticadas: **120 req/min**

### Carrinho
- Enviar o estado completo a cada mudança (não incremental)
- Debounce de ~500ms antes de chamar `/cart/sync`
- Verificar `has_stock_warning` e `has_price_change` no retorno do sync
- Antes do checkout, chamar `/cart/validate` e mostrar alerta se `valid: false`

### Promoções
- O campo `promotion` em cada produto indica a promoção ativa naquele momento
- Uma promoção pode expirar entre o sync e o checkout — o validate detecta isso
- `is_still_active` no item do carrinho indica se a promoção ainda vale

---

## 14. Fluxo de Checkout Recomendado

```
0. GET /payment_methods (na inicialização do app)
   └─ cachear localmente → usar o id do método desejado no checkout

1. GET /cart/validate
   ├─ valid: false → mostrar mudanças ao usuário por tipo:
   │    price_changed     → "Preço de X mudou de R$A para R$B"
   │    promotion_expired → "Promoção de X expirou"
   │    out_of_stock      → "X está sem estoque"
   │    stock_reduced     → "X tem só N unidades disponíveis"
   └─ valid: true → prosseguir

2. Usuário confirma → POST /checkout  (com payment_method_id do passo 0)
   ├─ 422 CART_EMPTY         → "Seu carrinho está vazio"
   ├─ 422 INSUFFICIENT_STOCK → listar produtos com problema
   └─ 201 → navegar para tela de pedido confirmado

3. GET /orders/{id} → exibir detalhes e info de pagamento
   ├─ payment.type = "pix"    → exibir QR Code com pix_code
   └─ payment.type = "boleto" → exibir código de barras boleto_code
```

---

## 15. Módulo Admin

> Todas as rotas abaixo exigem autenticação **e** `role: admin`.
> Headers: `Authorization: Bearer <token>` + `Content-Type: application/json`

---

### 15.1 Usuários

#### GET `/admin/users`
**Paginado**

Query params opcionais:
| Parâmetro | Tipo | Exemplo |
|---|---|---|
| `role` | string | `?role=admin` ou `?role=customer` |
| `is_active` | bool | `?is_active=1` |
| `search` | string | `?search=joao` — busca em nome e email |
| `per_page` | number | `?per_page=20` |

Response `200`:
```json
{
  "data": [
    {
      "id": "abc123",
      "name": "João Silva",
      "email": "joao@email.com",
      "role": "customer",
      "city": "São Paulo",
      "state": "SP",
      "cpf_cnpj": "123.456.789-09",
      "birth_date": "1990-05-15",
      "is_active": true,
      "last_login": "2026-06-07 10:30:00",
      "email_verified_at": "2026-05-01 08:00:00",
      "created_at": "2026-05-01 08:00:00",
      "updated_at": "2026-06-07 10:30:00"
    }
  ],
  "links": { ... },
  "meta": { "current_page": 1, "last_page": 2, "per_page": 15, "total": 28 }
}
```

---

#### POST `/admin/users`

Request:
```json
{
  "name": "João Silva",
  "email": "joao@email.com",
  "password": "senha123",
  "role": "customer",
  "city": "São Paulo",
  "state": "SP",
  "cpf_cnpj": "123.456.789-09",
  "birth_date": "1990-05-15",
  "is_active": true
}
```

> `role` é obrigatório (`admin` | `customer`). `cpf_cnpj`, `birth_date`, `city`, `state`, `is_active` são opcionais (padrão `is_active: true`).
> `cpf_cnpj` pode ser enviado com ou sem máscara — é normalizado automaticamente.

Response `201`:
```json
{
  "success": true,
  "message": "Usuário criado com sucesso.",
  "data": { ...user completo com campos admin... }
}
```

---

#### GET `/admin/users/{id}`

Response `200`:
```json
{
  "data": { ...user completo com campos admin... }
}
```

---

#### PUT `/admin/users/{id}`

Mesmos campos do POST, todos opcionais (`sometimes`). Para alterar a senha, enviar `password`.

Response `200`:
```json
{
  "success": true,
  "message": "Usuário atualizado com sucesso.",
  "data": { ...user atualizado... }
}
```

---

#### DELETE `/admin/users/{id}`

Response `200`:
```json
{
  "success": true,
  "message": "Usuário removido com sucesso."
}
```

---

### 15.2 Produtos (Admin)

#### GET `/admin/products`
**Paginado — inclui produtos inativos**

Query params opcionais:
| Parâmetro | Tipo | Exemplo |
|---|---|---|
| `is_active` | bool | `?is_active=0` |
| `is_highlighted` | bool | `?is_highlighted=1` |
| `search` | string | `?search=ração` |
| `category_ids` | array | `?category_ids[]=abc&category_ids[]=def` — produto deve estar em TODAS as categorias |
| `out_of_stock` | bool | `?out_of_stock=1` |
| `price_min` | number | `?price_min=10` |
| `price_max` | number | `?price_max=200` |
| `date_from` | date | `?date_from=2026-01-01` |
| `date_to` | date | `?date_to=2026-06-30` |
| `sort_by` | string | `?sort_by=stock_quantity` (`name`, `price`, `stock_quantity`, `created_at`, `updated_at`) |
| `sort_order` | string | `?sort_order=asc` (padrão: `desc`) |
| `per_page` | number | `?per_page=20` |

> Diferente da listagem pública, retorna `stock_quantity` e inclui produtos com `is_active: false`. Ordenação padrão: `created_at desc`.

---

#### POST `/admin/products`
**multipart/form-data** (por causa do upload de imagens)

Campos:
| Campo | Obrigatório | Tipo | Notas |
|---|---|---|---|
| `name` | sim | string | max 255 |
| `description` | sim | string | |
| `price` | sim | number | min 0 |
| `is_highlighted` | não | boolean | padrão false |
| `category_ids[]` | não | array de hashids | |
| `images[]` | não | arquivos | max 6 imagens, jpeg/png/jpg/webp, max 2 MB cada |

> Estoque inicial é **sempre 0**. Use `POST /admin/products/{id}/stock` para abastecer.

Response `201`:
```json
{
  "success": true,
  "message": "Produto criado com sucesso.",
  "data": {
    "id": "abc123",
    "name": "Ração Premium",
    "description": "...",
    "price": "89.90",
    "stock_quantity": 0,
    "is_highlighted": false,
    "is_active": true,
    "images": [ ... ],
    "primary_image": { ... },
    "categories": [ ... ]
  }
}
```

---

#### PUT `/admin/products/{id}`
**multipart/form-data**

Campos: mesmos do POST, todos opcionais (`sometimes`), mais:

| Campo | Tipo | Notas |
|---|---|---|
| `is_active` | boolean | ativar/desativar |
| `remove_images[]` | array de hashids | IDs das imagens a remover |
| `images[]` | arquivos | novas imagens a adicionar (limite total: 6) |

Erros possíveis:
- `422` + `code: IMAGE_LIMIT_EXCEEDED` — soma de imagens existentes + novas ultrapassaria 6

Response `200`:
```json
{
  "success": true,
  "message": "Produto atualizado com sucesso.",
  "data": { ...produto atualizado... }
}
```

---

#### DELETE `/admin/products/{id}`

Soft delete. Response `200`:
```json
{
  "success": true,
  "message": "Produto removido com sucesso."
}
```

---

#### GET `/admin/products/{id}/stock`
**Paginado — histórico de movimentações de estoque**

Response `200`:
```json
{
  "data": [
    {
      "id": 1,
      "type": "in",
      "quantity": 50,
      "stock_before": 0,
      "stock_after": 50,
      "reason": "purchase",
      "notes": "Recebimento NF 1234",
      "reference": null,
      "performed_by": "Admin Silva",
      "created_at": "2026-06-01T10:00:00+00:00"
    },
    {
      "id": 2,
      "type": "out",
      "quantity": 2,
      "stock_before": 50,
      "stock_after": 48,
      "reason": "sale",
      "notes": null,
      "reference": { "type": "order", "id": "ord_abc" },
      "performed_by": "system",
      "created_at": "2026-06-02T14:00:00+00:00"
    }
  ],
  "meta": { ... }
}
```

**Valores de `reason`:** `purchase` | `return` | `manual_adjustment` | `loss` | `sale`
**Valores de `performed_by`:** nome do admin que fez o ajuste, ou `"system"` quando gerado por pedido/cancelamento.

---

#### POST `/admin/products/{id}/stock`

Dois modos de operação:

**Modo delta** (entrada ou saída relativa):
```json
{
  "type": "in",
  "quantity": 50,
  "reason": "purchase",
  "notes": "Recebimento NF 1234"
}
```

**Modo absoluto** (define o estoque final exato):
```json
{
  "absolute": 100,
  "reason": "manual_adjustment",
  "notes": "Correção de inventário"
}
```

> `reason` é opcional (padrão: `manual_adjustment`). `notes` é opcional.
> No modo delta com `type: out`, retorna `422 INSUFFICIENT_STOCK` se `quantity > stock_atual`.
> No modo absoluto, se o valor enviado for igual ao estoque atual, retorna `200` sem criar movimentação.

| Campo | Modo | Valores |
|---|---|---|
| `type` | delta (obrigatório) | `in` \| `out` |
| `quantity` | delta (obrigatório) | inteiro ≥ 1 |
| `absolute` | absoluto (obrigatório) | inteiro ≥ 0 |
| `reason` | ambos (opcional) | `purchase` \| `return` \| `manual_adjustment` \| `loss` |
| `notes` | ambos (opcional) | string max 500 |

Response `201`:
```json
{
  "success": true,
  "message": "Movimentação de estoque registrada.",
  "data": { ...StockMovement... }
}
```

---

### 15.3 Pedidos (Admin)

#### GET `/admin/orders`
**Paginado — todos os pedidos do sistema**

Query params opcionais:
| Parâmetro | Tipo | Exemplo |
|---|---|---|
| `status` | string | `?status=pending` |
| `user_id` | hashid(s) | `?user_id=abc123` ou `?user_id=abc,def` |
| `delivery_type` | string | `?delivery_type=delivery` ou `pickup` |
| `date_from` | date | `?date_from=2026-06-01` |
| `date_to` | date | `?date_to=2026-06-30` |
| `payment_method_id` | hashid(s) | `?payment_method_id=pm_abc` |
| `per_page` | number | `?per_page=20` |

> Múltiplos hashids em `user_id` e `payment_method_id` podem ser separados por vírgula ou enviados como array.

Response `200` — igual à listagem de pedidos do cliente, mas cada pedido inclui o campo `customer`:
```json
{
  "data": [
    {
      "id": "ord1",
      "order_number": "00001",
      "status": "pending",
      "total_amount": "152.84",
      "delivery_type": "delivery",
      "shipping_address": { ... },
      "customer": {
        "id": "usr1",
        "name": "João Silva",
        "email": "joao@email.com"
      },
      "items": [ ... ],
      "payment": { ... },
      "created_at": "2026-05-20T14:00:00+00:00"
    }
  ],
  "meta": { ... }
}
```

---

#### GET `/admin/orders/{id}`

Igual ao detalhe do cliente, mas inclui `customer` e `status_history`:
```json
{
  "data": {
    "id": "ord1",
    "order_number": "00001",
    "status": "delivered",
    "customer": {
      "id": "usr1",
      "name": "João Silva",
      "email": "joao@email.com"
    },
    "status_history": [
      { "status": "pending",    "notes": null,              "created_at": "2026-05-20T14:00:00+00:00" },
      { "status": "confirmed",  "notes": "Pagamento PIX ok", "created_at": "2026-05-20T15:00:00+00:00" },
      { "status": "preparing",  "notes": null,              "created_at": "2026-05-21T08:00:00+00:00" },
      { "status": "delivered",  "notes": null,              "created_at": "2026-05-22T11:00:00+00:00" }
    ],
    ...
  }
}
```

---

#### PATCH `/admin/orders/{id}/status`

Request:
```json
{
  "status": "confirmed",
  "notes": "Pagamento PIX confirmado"
}
```

> `notes` é opcional. Quando `status: cancelled`, o estoque de todos os itens é devolvido automaticamente e uma movimentação de `type: in, reason: return` é registrada para cada produto.

**Status válidos:** `pending` | `confirmed` | `preparing` | `out_for_delivery` | `delivered` | `cancelled`

Response `200`:
```json
{
  "success": true,
  "message": "Status do pedido atualizado.",
  "data": { ...pedido completo com status_history atualizado... }
}
```

---

### 15.4 Categorias (Admin)

#### GET `/admin/categories`
**Sem paginação — retorna todas**

Query params opcionais:
| Parâmetro | Tipo |
|---|---|
| `is_active` | bool |
| `search` | string |

> Inclui categorias inativas. Sempre retorna `products_count`. Ordenação: ativas primeiro, depois destacadas, depois alfabética.

Response `200`:
```json
{
  "data": [
    {
      "id": "cat1",
      "name": "Rações",
      "slug": "racoes",
      "is_highlighted": true,
      "is_active": true,
      "products_count": 12
    }
  ]
}
```

---

#### POST `/admin/categories`

Request:
```json
{
  "name": "Petiscos",
  "is_highlighted": false,
  "is_active": true
}
```

> `is_highlighted` e `is_active` são opcionais. O `slug` é gerado automaticamente a partir do `name`. Nome deve ser único.

Response `201`:
```json
{
  "success": true,
  "message": "Categoria criada com sucesso.",
  "data": {
    "id": "cat2",
    "name": "Petiscos",
    "slug": "petiscos",
    "is_highlighted": false,
    "is_active": true,
    "products_count": 0
  }
}
```

---

#### PUT `/admin/categories/{id}`

Mesmos campos do POST, todos opcionais. Se `name` for alterado, o `slug` é atualizado automaticamente.

Response `200`:
```json
{
  "success": true,
  "message": "Categoria atualizada com sucesso.",
  "data": { ...categoria atualizada... }
}
```

---

#### DELETE `/admin/categories/{id}`

Soft delete. Response `200`:
```json
{
  "success": true,
  "message": "Categoria removida com sucesso."
}
```

---

### 15.5 Promoções (Admin)

#### GET `/admin/promotions`
**Paginado — todas as promoções**

Query params opcionais:
| Parâmetro | Tipo | Exemplo |
|---|---|---|
| `is_active` | bool | `?is_active=1` |
| `expired` | bool | `?expired=1` — retorna apenas com `ends_at` no passado |
| `search` | string | `?search=black` — busca em nome e descrição |
| `product_ids[]` | hashids | `?product_ids[]=abc` — promoções que contêm ao menos um desses produtos |

Response `200`:
```json
{
  "data": [
    {
      "id": "promo1",
      "name": "Semana Pet",
      "description": "Descontos especiais",
      "type": "percentage",
      "discount_value": 15.0,
      "starts_at": "2026-06-01T00:00:00+00:00",
      "ends_at": "2026-06-07T23:59:59+00:00",
      "is_active": true,
      "is_currently_active": true,
      "min_quantity": null,
      "products": [
        {
          "id": "abc123",
          "name": "Ração Premium",
          "original_price": "89.90",
          "effective_price": "76.42",
          "discount_amount": "13.48",
          "is_currently_active": true,
          "use_limit": 100,
          "uses_count": 23,
          "primary_image": "products/foto.jpg"
        }
      ]
    }
  ],
  "meta": { ... }
}
```

> `is_currently_active` na promoção: `true` se a promoção está ativa **e** há pelo menos um produto com ela como promoção vigente.
> `is_currently_active` no produto: `true` se esta promoção é a atualmente aplicada a esse produto.

---

#### GET `/admin/promotions/{id}`

Detalhe de qualquer promoção (independente de estado). Mesma estrutura do item acima.

---

#### POST `/admin/promotions`

Request:
```json
{
  "name": "Semana Pet",
  "description": "Descontos especiais da semana",
  "type": "percentage",
  "discount_value": 15,
  "starts_at": "2026-06-01T00:00:00",
  "ends_at": "2026-06-07T23:59:59",
  "is_active": true,
  "min_quantity": null,
  "product_ids": [
    { "id": "abc123", "use_limit": 100 },
    { "id": "def456", "use_limit": null }
  ]
}
```

| Campo | Obrigatório | Notas |
|---|---|---|
| `name` | sim | |
| `type` | sim | `percentage` \| `fixed` |
| `discount_value` | sim | Para `percentage`: 0.01–100. Para `fixed`: qualquer valor > 0 |
| `starts_at` | sim | |
| `description` | não | |
| `ends_at` | não | Deve ser posterior a `starts_at` |
| `is_active` | não | Padrão: `true` |
| `min_quantity` | não | Quantidade mínima de itens para aplicar desconto |
| `product_ids` | não | Array de objetos `{id, use_limit}`. `use_limit: null` = sem limite |

Response `201`:
```json
{
  "success": true,
  "message": "Promoção criada com sucesso.",
  "data": { ...promoção com produtos... }
}
```

Erros possíveis:
- `422` + `code: VALIDATION_ERROR` — `discount_value > 100` quando `type: percentage`

---

#### PUT `/admin/promotions/{id}`

Mesmos campos do POST (exceto `product_ids`), todos opcionais. Para gerenciar produtos use os endpoints `/products` abaixo.

Response `200`:
```json
{
  "success": true,
  "message": "Promoção atualizada com sucesso.",
  "data": { ...promoção atualizada... }
}
```

---

#### DELETE `/admin/promotions/{id}`

Soft delete. Response `200`:
```json
{
  "success": true,
  "message": "Promoção removida com sucesso."
}
```

---

#### POST `/admin/promotions/{id}/products`

Vincula (ou atualiza) produtos à promoção sem remover os já vinculados.

Request:
```json
{
  "products": [
    { "id": "abc123", "use_limit": 50 },
    { "id": "def456", "use_limit": null }
  ]
}
```

> `use_limit: null` = sem limite de usos por produto. Se o produto já estava vinculado, o `use_limit` é atualizado.

Response `200`:
```json
{
  "success": true,
  "message": "Produtos vinculados à promoção com sucesso."
}
```

---

#### DELETE `/admin/promotions/{id}/products`

Desvincula produtos da promoção.

Request:
```json
{
  "product_ids": ["abc123", "def456"]
}
```

Response `200`:
```json
{
  "success": true,
  "message": "Produtos desvinculados da promoção com sucesso."
}
```