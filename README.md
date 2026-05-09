# Doglio — E-commerce Backend API

> API RESTful para um sistema de e-commerce pet shop. Cobre o ciclo completo: catálogo, carrinho, checkout, pedidos, promoções e painel admin.

![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?logo=laravel&logoColor=white)
![Sanctum](https://img.shields.io/badge/Auth-Sanctum-orange)
![MySQL](https://img.shields.io/badge/Database-MySQL-4479A1?logo=mysql&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green)

---

## Sobre o Projeto

Doglio é o backend de um e-commerce de produtos pet desenvolvido em Laravel 11. O objetivo foi construir uma API production-ready com autenticação, controle de permissões, carrinho inteligente, sistema de promoções e painel administrativo completo — tudo estruturado para ser consumido por um app mobile ou frontend SPA.

---

## Decisões Técnicas

**Carrinho sync-based**
O cliente é a fonte da verdade do carrinho. A cada alteração, ele envia o estado completo (com debounce). O servidor armazena snapshot de preço e promoção vigentes no momento do sync — eliminando conflitos de estado e simplificando o protocolo cliente-servidor.

**Promoções por produto no pivot**
O `use_limit` e `uses_count` vivem na tabela pivot `product_promotion`, não na promoção em si. Isso permite limites de uso diferentes por produto dentro de uma mesma promoção.

**Promoção mais recente vence**
Um produto pode estar em múltiplas promoções ativas simultaneamente. `getActivePromotion()` ordena por `starts_at` desc, `id` desc — a campanha mais recente tem prioridade. A API expõe `is_currently_active` por produto indicando qual promoção está efetivamente aplicada.

**Hashids configurável**
IDs públicos obfuscados via `vinkla/hashids`. Desabilitável com `APP_USE_HASHIDS=false` sem nenhuma mudança de código.

**Soft deletes em cascata**
Todos os recursos principais usam soft delete. Usuário deletado mantém histórico de pedidos. Produto deletado mantém itens de pedido e avaliações.

---

## Stack

| Camada | Tecnologia |
|---|---|
| Framework | Laravel 11 |
| Auth | Laravel Sanctum (Bearer token) |
| Banco | MySQL / MariaDB |
| IDs públicos | vinkla/hashids |
| Filas | Laravel Queue (database driver) |
| Notificações | Push via FCM (arquitetado, pendente configuração) |

---

## Domínios

| Domínio | O que cobre |
|---|---|
| **Autenticação** | Registro, login, logout, perfil |
| **Produtos** | CRUD, imagens (até 6), filtros avançados, soft delete |
| **Categorias** | CRUD, many-to-many com produtos |
| **Promoções** | Desconto fixo ou percentual, muitos-para-muitos com produtos, controle de validade, `use_limit` por produto no pivot |
| **Carrinho** | Sync-based, snapshot de preço, validação de mudanças |
| **Checkout** | Entrega ou retirada, endereço salvo ou manual, transação atômica |
| **Pedidos** | Histórico por usuário, histórico de status, gerenciamento admin |
| **Endereços** | Múltiplos por usuário, flag de principal |
| **Pagamentos** | PIX, boleto, cartão de crédito; um por pedido |
| **Avaliações** | Exige compra entregue, uma por produto por usuário |
| **Favoritos** | Lista por usuário, notificação de reabastecimento |
| **Estoque** | Histórico de movimentações por produto (admin) |
| **Usuários (admin)** | CRUD, ativar/desativar, filtros por role e status |

---

## Instalação

```bash
git clone https://github.com/felipeggarcia/doglio_backend.git
cd doglio_backend
composer install
cp .env.example .env
```

Configure o `.env`:

```env
DB_CONNECTION=mysql
DB_DATABASE=doglio_backend
DB_USERNAME=root
DB_PASSWORD=

APP_USE_HASHIDS=true
```

```bash
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

API disponível em `http://localhost:8000/api/v1`.

### Credenciais de teste

| Papel | Email | Senha |
|---|---|---|
| Admin | `admin@doglio.com` | `password` |
| Cliente | `client@doglio.com` | `password` |



## Endpoints

Base: `GET /api/v1/...`  
Rotas admin: prefixo `/admin/` + header `Authorization: Bearer {token}` com role `admin`.

### Autenticação (público)

| Método | Rota | Descrição |
|---|---|---|
| `POST` | `/register` | Cadastro |
| `POST` | `/login` | Login → retorna token |
| `POST` | `/logout` | Logout (autenticado) |
| `GET` | `/user` | Dados do usuário logado |

### Produtos (público)

| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/products` | Lista produtos ativos |
| `GET` | `/products/{id}` | Detalhe do produto |
| `GET` | `/products/{id}/reviews` | Avaliações do produto |

**Filtros disponíveis em `GET /products`:**

| Parâmetro | Tipo | Descrição |
|---|---|---|
| `on_promotion` | bool | Só produtos com promoção ativa (`1`) |
| `category_id` | hashid | Filtra por categoria |
| `search` | string | Busca em nome ou descrição |
| `name` | string | Busca por nome (parcial) |
| `is_highlighted` | bool | Só produtos em destaque |
| `in_stock` | bool | Só com estoque |
| `out_of_stock` | bool | Só sem estoque |
| `price_min` / `price_max` | number | Faixa de preço |
| `price_from` / `price_to` | number | Faixa de preço (alternativa) |
| `stock_min` / `stock_max` | number | Faixa de estoque |
| `sort_by` | string | `name`, `price`, `stock_quantity`, `created_at`, `updated_at` |
| `sort_order` | string | `asc` / `desc` |
| `per_page` | number | Itens por página (padrão: 15) |

Quando `on_promotion=1`, a resposta inclui `original_price`, `effective_price`, `discount_amount` e bloco `promotion` em cada produto. Ordenação padrão: destacados primeiro → alfabético → sem estoque por último.

### Categorias (público)

| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/categories` | Lista categorias ativas |
| `GET` | `/categories/{id}` | Detalhe da categoria |

### Carrinho (autenticado)

| Método | Rota | Descrição |
|---|---|---|
| `POST` | `/cart/sync` | Sincroniza o carrinho |
| `GET` | `/cart` | Retorna carrinho atual |
| `GET` | `/cart/validate` | Valida mudanças de preço/estoque |
| `DELETE` | `/cart` | Limpa o carrinho |

**Sync body:**
```json
{
  "items": [
    { "product_id": "hashid", "quantity": 2 }
  ]
}
```

**Validate** retorna por item: `price_changed`, `promotion_expired`, `out_of_stock`, `stock_reduced`.

### Checkout (autenticado)

```
POST /checkout
```

**Retirada:**
```json
{
  "payment_method_id": "hashid",
  "delivery_type": "pickup"
}
```

**Entrega com endereço salvo:**
```json
{
  "payment_method_id": "hashid",
  "delivery_type": "delivery",
  "address_id": "hashid"
}
```

**Entrega com endereço manual:**
```json
{
  "payment_method_id": "hashid",
  "delivery_type": "delivery",
  "shipping_street": "Rua das Flores",
  "shipping_number": "142",
  "shipping_complement": "Apto 31",
  "shipping_city": "São Paulo",
  "shipping_state": "SP",
  "shipping_zip": "01310100"
}
```

### Pedidos (autenticado)

| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/orders` | Lista pedidos do usuário |
| `GET` | `/orders/{id}` | Detalhe do pedido |

### Endereços (autenticado)

| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/addresses` | Lista endereços |
| `POST` | `/addresses` | Cria endereço |
| `PUT` | `/addresses/{id}` | Atualiza endereço |
| `DELETE` | `/addresses/{id}` | Remove endereço |
| `PATCH` | `/addresses/{id}/primary` | Define como principal |

### Avaliações (autenticado)

| Método | Rota | Descrição |
|---|---|---|
| `POST` | `/products/{id}/reviews` | Cria avaliação (exige compra entregue) |
| `DELETE` | `/reviews/{id}` | Remove própria avaliação |

### Favoritos (autenticado)

| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/favorites` | Lista favoritos |
| `POST` | `/favorites` | Adiciona favorito |
| `DELETE` | `/favorites/{id}` | Remove favorito |
| `PATCH` | `/favorites/{id}/notify` | Ativa/desativa notificação de reabastecimento |

### Admin — Promoções

| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/admin/promotions` | Lista todas as promoções (com filtros) |
| `GET` | `/admin/promotions/{id}` | Detalhe da promoção com produtos |
| `POST` | `/admin/promotions` | Cria promoção |
| `PUT` | `/admin/promotions/{id}` | Atualiza promoção |
| `DELETE` | `/admin/promotions/{id}` | Remove promoção (soft delete) |
| `POST` | `/admin/promotions/{id}/products` | Vincula produtos |
| `DELETE` | `/admin/promotions/{id}/products` | Desvincula produtos |

**Filtros em `GET /admin/promotions`:**

| Parâmetro | Tipo | Descrição |
|---|---|---|
| `is_active` | bool | Filtra por ativo/inativo |
| `expired` | bool | `1` = expiradas, `0` = vigentes |
| `search` | string | Busca por nome ou descrição |
| `product_ids[]` | hashid[] | Promoções que contêm qualquer um dos produtos (OR) |

**Criar/atualizar promoção:**
```json
{
  "name": "Promoção Verão 2026",
  "description": "Desconto especial.",
  "type": "percentage",
  "discount_value": 15,
  "starts_at": "2026-05-08T00:00:00",
  "ends_at": "2026-06-30T23:59:59",
  "is_active": true,
  "min_quantity": null,
  "product_ids": [
    { "id": "hashid_A", "use_limit": 30 },
    { "id": "hashid_B", "use_limit": null }
  ]
}
```
> `use_limit: null` = usos ilimitados para aquele produto nessa promoção.

**Vincular produtos:**
```json
{
  "products": [
    { "id": "hashid_A", "use_limit": 30 },
    { "id": "hashid_B", "use_limit": null }
  ]
}
```

**Desvincular produtos:**
```json
{ "product_ids": ["hashid_A", "hashid_B"] }
```

> **Regra de conflito:** um produto pode estar em várias promoções ativas. A promoção com `starts_at` mais recente (ou `id` maior em caso de empate) vence. A resposta de detalhe exibe `is_currently_active` por produto, indicando se esta promoção é a que está efetivamente aplicada.

### Admin — Produtos

| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/admin/products` | Lista todos os produtos (inclui inativos) |
| `POST` | `/admin/products` | Cria produto (multipart/form-data) |
| `PUT` | `/admin/products/{id}` | Atualiza produto |
| `DELETE` | `/admin/products/{id}` | Remove produto (soft delete) |
| `GET` | `/admin/products/{id}/stock` | Histórico de movimentações de estoque |
| `POST` | `/admin/products/{id}/stock` | Registra movimentação de estoque |

### Admin — Pedidos

| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/admin/orders` | Lista todos os pedidos |
| `GET` | `/admin/orders/{id}` | Detalhe do pedido |
| `PATCH` | `/admin/orders/{id}/status` | Atualiza status do pedido |

### Admin — Categorias

| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/admin/categories` | Lista todas as categorias (inclui inativas) |
| `POST` | `/admin/categories` | Cria categoria |
| `PUT` | `/admin/categories/{id}` | Atualiza categoria |
| `DELETE` | `/admin/categories/{id}` | Remove categoria |

### Admin — Usuários

| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/admin/users` | Lista usuários |
| `POST` | `/admin/users` | Cria usuário |
| `GET` | `/admin/users/{id}` | Detalhe do usuário |
| `PUT` | `/admin/users/{id}` | Atualiza usuário |
| `DELETE` | `/admin/users/{id}` | Remove usuário (soft delete) |

---

## Banco de Dados

| Tabela | Descrição |
|---|---|
| `users` | Usuários; `role`, `city`, `state`, `is_active`, `last_login_at`, soft delete |
| `categories` | Categorias; `is_highlighted`, `is_active`, soft delete |
| `products` | Produtos; `price`, `stock_quantity`, `is_highlighted`, `is_active`, soft delete |
| `product_images` | Imagens por produto; `is_primary`, `order` |
| `category_product` | Pivot produtos ↔ categorias |
| `promotions` | Campanhas de desconto; `type`, `discount_value`, `starts_at`, `ends_at`, `is_active`, `min_quantity`, soft delete |
| `product_promotion` | Pivot produtos ↔ promoções; `use_limit`, `uses_count` **por par produto/promoção** |
| `payment_methods` | Métodos de pagamento; `type` (pix/credit_card/boleto) |
| `cart_items` | Carrinho por usuário; snapshot de `unit_price` e `promotion_id` |
| `cart_snapshots` | Histórico de carrinhos; `trigger_type`, `total_value`, JSON completo |
| `user_addresses` | Endereços salvos; `is_primary`, soft delete |
| `orders` | Pedidos; `delivery_type`, snapshot de endereço, soft delete |
| `order_items` | Itens do pedido; `unit_price` no momento da compra |
| `order_status_history` | Histórico de status dos pedidos |
| `payments` | Pagamento por pedido; `status`, soft delete |
| `reviews` | Avaliações; exige compra entregue, soft delete |
| `user_favorites` | Favoritos; `notify_restock` |
| `push_tokens` | Tokens de notificação push |
| `stock_movements` | Movimentações de estoque |

---

## Autenticação e Segurança

- **Laravel Sanctum** — Bearer token
- **Roles:** `customer` (padrão) e `admin`
- **`is_active`:** usuários inativos não conseguem fazer login (403)
- **Throttle:** rotas de auth (10/min), públicas (60/min), autenticadas (120/min)
- **ForceJsonResponse** middleware em todas as rotas de API
- **Hashids:** IDs obfuscados via `vinkla/hashids`, desabilitável com `APP_USE_HASHIDS=false`
- **Respostas de erro padronizadas** em português via `app/Support/ApiMessages.php`

---

## Estrutura do Projeto

```
app/
├── Http/
│   ├── Controllers/V1/
│   │   ├── AuthController.php
│   │   ├── CartController.php
│   │   ├── CategoryController.php
│   │   ├── FavoriteController.php
│   │   ├── OrderController.php
│   │   ├── OrderStatusController.php
│   │   ├── ProductController.php
│   │   ├── PromotionController.php
│   │   ├── PushTokenController.php
│   │   ├── ReviewController.php
│   │   ├── StockMovementController.php
│   │   ├── UserAddressController.php
│   │   └── UserController.php
│   ├── Resources/
│   │   ├── CartItemResource.php
│   │   ├── CategoryResource.php
│   │   ├── OrderItemResource.php
│   │   ├── OrderResource.php
│   │   ├── PaymentMethodResource.php
│   │   ├── PaymentResource.php
│   │   ├── ProductImageResource.php
│   │   ├── ProductResource.php
│   │   ├── PromotionResource.php
│   │   ├── ReviewResource.php
│   │   ├── StockMovementResource.php
│   │   ├── UserAddressResource.php
│   │   └── UserResource.php
│   └── Middleware/
│       ├── AdminMiddleware.php
│       └── ForceJsonResponse.php
├── Models/
│   ├── CartItem.php
│   ├── CartSnapshot.php
│   ├── Category.php
│   ├── Order.php
│   ├── OrderItem.php
│   ├── OrderStatusHistory.php
│   ├── Payment.php
│   ├── PaymentMethod.php
│   ├── Product.php
│   ├── ProductImage.php
│   ├── Promotion.php
│   ├── PushToken.php
│   ├── Review.php
│   ├── StockMovement.php
│   ├── User.php
│   ├── UserAddress.php
│   └── UserFavorite.php
├── Support/
│   └── ApiMessages.php       ← strings de resposta centralizadas
├── Observers/
│   └── ProductObserver.php
├── Policies/
│   ├── OrderPolicy.php
│   ├── ReviewPolicy.php
│   ├── UserAddressPolicy.php
│   └── UserFavoritePolicy.php
├── Jobs/
│   ├── NotifyLowStockJob.php
│   └── NotifyRestockJob.php
└── Traits/
    └── UsesHashids.php
```

---

## Testes

Os módulos abaixo ainda não possuem testes escritos:

### `tests/Feature/CartTest.php`
- `test_sync_cart`
- `test_sync_cart_applies_promotion_price`
- `test_sync_cart_caps_at_stock`
- `test_show_cart`
- `test_validate_cart_detects_price_change`
- `test_validate_cart_detects_out_of_stock`
- `test_validate_cart_detects_promotion_expired`
- `test_clear_cart`

### `tests/Feature/CheckoutTest.php`
- `test_checkout_pickup`
- `test_checkout_delivery_with_saved_address`
- `test_checkout_delivery_with_manual_address`
- `test_checkout_fails_when_cart_empty`
- `test_checkout_fails_when_insufficient_stock`
- `test_checkout_fails_when_delivery_without_address`
- `test_checkout_creates_order_and_payment`
- `test_checkout_creates_cart_snapshot`

### `tests/Feature/OrderTest.php`
- `test_list_orders`
- `test_show_order`
- `test_cannot_see_other_user_order`
- `test_admin_update_order_status`
- `test_update_order_status_records_history`
- `test_non_admin_cannot_update_status`

### `tests/Feature/AddressTest.php`
- `test_list_addresses`
- `test_create_address`
- `test_create_address_sets_others_non_primary`
- `test_update_address`
- `test_cannot_update_other_user_address`
- `test_delete_address`
- `test_set_primary`

### `tests/Feature/PromotionTest.php`
- `test_products_on_promotion_filter`
- `test_product_shows_promotion_prices`
- `test_admin_list_promotions_with_filters`
- `test_admin_create_promotion`
- `test_admin_create_promotion_percentage_over_100_fails`
- `test_admin_update_promotion`
- `test_admin_delete_promotion`
- `test_admin_attach_products_with_use_limit`
- `test_admin_detach_products`
- `test_promotion_use_limit_blocks_active_status`
- `test_newer_promotion_wins_over_older`

### `tests/Feature/ReviewTest.php`
- `test_list_product_reviews`
- `test_create_review_requires_purchase`
- `test_create_review_after_delivered_order`
- `test_cannot_review_twice`
- `test_delete_own_review`
- `test_cannot_delete_other_user_review`

---

## Licença

MIT
