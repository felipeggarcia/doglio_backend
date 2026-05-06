# Doglio E-commerce Backend API

A RESTful API built with Laravel 12 for an e-commerce system with full support for product catalog, cart, checkout, orders, promotions, addresses, and payments.

## Key Features

- **JWT Authentication** with Laravel Sanctum
- **Product Management** with advanced filtering and multi-image support (up to 6 images)
- **Category System** with many-to-many relationships
- **User Management** (Admin) with role-based access control
- **Cart System** - Sync-based cart with price/promotion snapshot
- **Promotions Module** - Percentage and fixed discounts, linked to products
- **Checkout & Orders** - Full checkout flow with delivery or pickup
- **Addresses** - Saved delivery addresses with primary flag
- **Payments** - PIX, credit card, boleto; per-order payment record
- **Cart Snapshots** - Audit trail saved on checkout and cart purge
- **Advanced Filtering** - 10+ filter options for products
- **Soft Deletes** on all major tables
- **Hashids Support** - Configurable ID obfuscation
- **RESTful Standards** - Consistent response format across all endpoints
- **API Versioning** (v1)
- **Smart Defaults** - Hides out-of-stock products, prioritizes highlighted items

## Quick Start

### Prerequisites

- PHP 8.2 or higher
- Composer
- MySQL/MariaDB
- Git

### Installation

1. **Clone the repository**
```bash
git clone https://github.com/felipeggarcia/doglio_backend.git
cd doglio_backend
```

2. **Install dependencies**
```bash
composer install
```

3. **Environment configuration**
```bash
cp .env.example .env
```
Edit `.env` file with your database credentials:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=doglio_backend
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Hashids (optional)
APP_USE_HASHIDS=true  # Set to false to use plain integer IDs
```

4. **Generate application key**
```bash
php artisan key:generate
```

5. **Run migrations**
```bash
php artisan migrate
```

6. **Seed database (optional)**
```bash
php artisan db:seed
```
This creates:
- Admin user: `admin@doglio.com` / `password`
- Customer user: `client@doglio.com` / `password`
- Sample categories, products, and product images
- PIX payment method
- Active promotion: 10% off "Ração Super Premium"
- Expired promotion: Black Friday fixed discount
- 3 saved addresses for the test customer

7. **Create storage link**
```bash
php artisan storage:link
```

8. **Start development server**
```bash
php artisan serve
```

API will be available at: `http://localhost:8000/api/v1`

---

## API Documentation

### Authentication

**Register**
```
POST /api/v1/register
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "city": "São Paulo",
  "state": "SP"
}
```

**Login**
```
POST /api/v1/login
{
  "email": "admin@doglio.com",
  "password": "password"
}
```

**Logout** (requires authentication)
```
POST /api/v1/logout
Headers: Authorization: Bearer {token}
```

**Get current user** (requires authentication)
```
GET /api/v1/user
Headers: Authorization: Bearer {token}
```

### Products (Public)

**List products** (with advanced filtering)
```
GET /api/v1/products?category_id=1&is_highlighted=true&search=ração

# Filter parameters:
- category_id: Filter by category
- is_highlighted: Filter highlighted products (true/false)
- name: Search by product name (partial match)
- description: Search by description (partial match)
- search: Search in name OR description
- price_min: Minimum price
- price_max: Maximum price
- price_from & price_to: Price range
- stock_min: Minimum stock quantity
- stock_max: Maximum stock quantity
- in_stock: Show only products in stock (true)
- out_of_stock: Show out of stock products (true)
- sort_by: name, price, stock_quantity, created_at, updated_at
- sort_order: asc, desc
- per_page: Items per page (default: 15)

# Default behavior:
- Hides out-of-stock products (unless out_of_stock=true)
- Orders by: is_highlighted DESC, stock_quantity DESC
```

**Get product**
```
GET /api/v1/products/{id}
```

### Categories (Public)

**List categories**
```
GET /api/v1/categories?is_highlighted=true
```

**Get category**
```
GET /api/v1/categories/{id}
```

### Admin Routes (Requires admin role)

**User Management (Admin only)**
```
# List users
GET /api/v1/users?role=customer&search=john&is_active=true

# Create user
POST /api/v1/users
{
  "name": "User Name",
  "email": "user@example.com",
  "password": "password123",
  "role": "customer",
  "city": "São Paulo",
  "state": "SP",
  "is_active": true
}

# Get user
GET /api/v1/users/{id}

# Update user
PUT /api/v1/users/{id}
{
  "name": "Updated Name",
  "is_active": false
}

# Delete user (soft delete)
DELETE /api/v1/users/{id}
```

**Create Product**
```
POST /api/v1/products
Headers: Authorization: Bearer {token}
Content-Type: multipart/form-data
{
  "name": "Product Name",
  "description": "Description",
  "price": 99.99,
  "stock_quantity": 100,
  "is_highlighted": true,
  "category_ids": ["hashid1", "hashid2"],
  "images": [file1, file2, ...] // Max 6 images
}
```

**Update Product**
```
PUT /api/v1/products/{id}
Headers: Authorization: Bearer {token}
{
  "name": "Updated Name",
  "price": 149.99,
  "remove_images": ["image_hashid1"], // Optional: remove specific images
  "images": [newFile1, newFile2] // Optional: add new images
}
```

**Delete Product**
```
DELETE /api/v1/products/{id}
```

**Create Category**
```
POST /api/v1/categories
{
  "name": "Category Name",
  "is_highlighted": true
}
```

**Update Category**
```
PUT /api/v1/categories/{id}
```

**Delete Category**
```
DELETE /api/v1/categories/{id}
```

### Promotions (Public read)

**List active promotions**
```
GET /api/v1/promotions
```

**Get promotion**
```
GET /api/v1/promotions/{id}
```

### Cart (Requires authentication)

The cart is **sync-based**: the client sends the full current state on every change (debounced). On sync, the server snapshots the current price and active promotion for each item.

**Sync cart**
```
POST /api/v1/cart/sync
Headers: Authorization: Bearer {token}
{
  "items": [
    { "product_id": "jR", "quantity": 2 },
    { "product_id": "k5", "quantity": 1 }
  ]
}
```

**Get cart**
```
GET /api/v1/cart
Headers: Authorization: Bearer {token}
```
Response includes `price_changed` and `stock_warning` flags per item, plus global `has_price_change` and `has_stock_warning`.

**Validate cart**
```
GET /api/v1/cart/validate
Headers: Authorization: Bearer {token}
```
Returns `{ valid: bool, changes: [] }` with change types: `price_changed`, `promotion_expired`, `out_of_stock`, `stock_reduced`.

**Clear cart**
```
DELETE /api/v1/cart
Headers: Authorization: Bearer {token}
```

### Checkout (Requires authentication)

The cart must have items. On checkout, a `CartSnapshot` is saved and the cart is cleared.

**Pickup (no address needed)**
```
POST /api/v1/checkout
{
  "payment_method_id": "jR",
  "delivery_type": "pickup"
}
```

**Delivery with saved address**
```
POST /api/v1/checkout
{
  "payment_method_id": "jR",
  "delivery_type": "delivery",
  "address_id": "jR"
}
```

**Delivery with manual address**
```
POST /api/v1/checkout
{
  "payment_method_id": "jR",
  "delivery_type": "delivery",
  "shipping_street": "Rua das Flores",
  "shipping_number": "142",
  "shipping_complement": "Apto 31",
  "shipping_city": "São Paulo",
  "shipping_state": "SP",
  "shipping_zip": "01310100"
}
```
> `shipping_*` fields are only required when `delivery_type` is `delivery` **and** no `address_id` is provided.

### Orders (Requires authentication)

**List orders**
```
GET /api/v1/orders
Headers: Authorization: Bearer {token}
```

**Get order**
```
GET /api/v1/orders/{id}
Headers: Authorization: Bearer {token}
```

### Addresses (Requires authentication)

**List addresses**
```
GET /api/v1/addresses
Headers: Authorization: Bearer {token}
```

**Create address**
```
POST /api/v1/addresses
Headers: Authorization: Bearer {token}
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

**Update address**
```
PUT /api/v1/addresses/{id}
Headers: Authorization: Bearer {token}
```

**Delete address**
```
DELETE /api/v1/addresses/{id}
Headers: Authorization: Bearer {token}
```

**Set as primary**
```
PATCH /api/v1/addresses/{id}/primary
Headers: Authorization: Bearer {token}
```

### Admin: Promotions

**Create promotion**
```
POST /api/v1/promotions
Headers: Authorization: Bearer {token}
{
  "name": "Black Friday",
  "description": "20% off everything",
  "type": "percentage",
  "discount_value": 20.00,
  "starts_at": "2026-11-28T00:00:00",
  "ends_at": "2026-11-28T23:59:59",
  "is_active": true,
  "min_quantity": 1,
  "max_uses": 1000,
  "product_ids": ["jR", "k5"]
}
```
> `type`: `percentage` or `fixed`. For `percentage`, `discount_value` must be ≤ 100.

**Update promotion**
```
PUT /api/v1/promotions/{id}
```

**Delete promotion**
```
DELETE /api/v1/promotions/{id}
```

**Attach products to promotion**
```
POST /api/v1/promotions/{id}/products
{ "product_ids": ["jR", "k5"] }
```

**Detach products from promotion**
```
DELETE /api/v1/promotions/{id}/products
{ "product_ids": ["jR"] }
```

---

## Project Structure

```
app/
├── Http/
│   ├── Controllers/V1/
│   │   ├── AuthController.php
│   │   ├── ProductController.php
│   │   ├── CategoryController.php
│   │   ├── UserController.php
│   │   ├── CartController.php
│   │   ├── OrderController.php
│   │   ├── UserAddressController.php
│   │   └── PromotionController.php
│   ├── Requests/V1/Admin/
│   │   ├── CategoryStoreRequest.php
│   │   └── CategoryUpdateRequest.php
│   ├── Resources/
│   │   ├── ProductResource.php
│   │   ├── CategoryResource.php
│   │   ├── UserResource.php
│   │   ├── ProductImageResource.php
│   │   ├── CartItemResource.php
│   │   ├── OrderResource.php
│   │   ├── OrderItemResource.php
│   │   ├── PromotionResource.php
│   │   ├── UserAddressResource.php
│   │   ├── PaymentResource.php
│   │   └── PaymentMethodResource.php
│   └── Middleware/
│       ├── AdminMiddleware.php
│       └── ForceJsonResponse.php
├── Models/
│   ├── User.php
│   ├── Product.php
│   ├── ProductImage.php
│   ├── Category.php
│   ├── Order.php
│   ├── OrderItem.php
│   ├── CartItem.php
│   ├── CartSnapshot.php
│   ├── Promotion.php
│   ├── UserAddress.php
│   ├── Payment.php
│   └── PaymentMethod.php
└── Traits/
    └── UsesHashids.php
```

## Database Schema

- **users**: User accounts (customers and admins); `role`, `city`, `state`, `last_login_at`, soft deletes
- **categories**: Product categories; `is_highlighted`
- **products**: Product catalog; `price`, `stock_quantity`, `is_highlighted`, soft deletes
- **product_images**: Product images (up to 6 per product); `is_primary`, `order`
- **category_product**: Many-to-many pivot (products ↔ categories)
- **promotions**: Discount campaigns; `type` (percentage/fixed), `discount_value`, `starts_at`, `ends_at`, `is_active`, `min_quantity`, `max_uses`, `uses_count`, soft deletes
- **product_promotion**: Many-to-many pivot (products ↔ promotions)
- **payment_methods**: Available payment methods; `type` (pix/credit_card/boleto), `is_active`
- **cart_items**: Per-user cart; `unit_price` and `promotion_id` snapshots current price at sync time
- **cart_snapshots**: Audit log saved on checkout and cart purge; stores full cart JSON + `trigger_type` + `total_value`
- **user_addresses**: Saved delivery addresses per user; `is_primary`, soft deletes
- **orders**: Customer orders; `delivery_type` (delivery/pickup), shipping address snapshot fields, `address_id` FK, soft deletes
- **order_items**: Order line items; `unit_price` at time of purchase
- **payments**: One per order; `status` (pending/paid/failed/refunded), PIX/boleto/card fields, soft deletes

All tables support soft deletes.

## Authentication

Uses Laravel Sanctum for API token authentication.

**User Roles:**
- `customer` (default)
- `admin` (full access)

**Account Status:**
- Users have an `is_active` field
- Inactive users cannot login (returns 403)
- Admins can activate/deactivate user accounts

**Hashids:**
- Model IDs can be obfuscated using Hashids
- Toggle with `APP_USE_HASHIDS=true/false` in `.env`
- When disabled, returns plain integer IDs
- Run `php artisan config:clear` after changing

---

# Documentação em Português

## Instalação Rápida

1. **Clone o repositório**
```bash
git clone https://github.com/felipeggarcia/doglio_backend.git
cd doglio_backend
```

2. **Instale as dependências**
```bash
composer install
```

3. **Configure o ambiente**
```bash
cp .env.example .env
```
Edite o arquivo `.env` com suas credenciais do banco de dados.

4. **Execute as migrations**
```bash
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
```

5. **Inicie o servidor**
```bash
php artisan serve
```

## Funcionalidades

- Autenticação JWT com Laravel Sanctum
- CRUD completo de Produtos
  - Upload múltiplo de imagens (até 6 por produto)
  - Filtros avançados (preço, estoque, categorias, busca)
  - Ordenação inteligente (destacados primeiro, depois por estoque)
  - Esconde produtos sem estoque por padrão
- CRUD completo de Categorias
- CRUD completo de Usuários (Admin)
  - Ativar/desativar usuários
  - Soft deletes
  - Filtros por role e status
- **Módulo de Promoções**
  - Descontos percentuais e fixos
  - Vinculadas a produtos específicos
  - Controle de validade (starts_at / ends_at)
  - Limite de usos (max_uses)
  - Leitura pública de promoções ativas; CRUD restrito ao admin
- **Carrinho (sync-based)**
  - Cliente envia o estado completo a cada alteração (debounce ~800ms)
  - Servidor salva snapshot de preço e promoção no momento do sync
  - Endpoint `/cart/validate` detecta mudanças de preço, promoção expirada, sem estoque
- **Checkout**
  - Opções: entrega (com endereço salvo ou manual) ou retirada
  - Valida estoque antes de confirmar
  - Salva `CartSnapshot` com o conteúdo completo do carrinho
  - Cria pedido + pagamento em transação única
- **Pedidos** — histórico por usuário com itens e pagamento
- **Endereços** — múltiplos endereços salvos por usuário com flag de principal
- **Pagamentos** — registro por pedido; suporte a PIX, boleto e cartão de crédito
- **Cart Snapshots** — histórico de carrinhos no checkout e no purge
- Relacionamento muitos-para-muitos (Produtos ↔ Categorias, Produtos ↔ Promoções)
- Soft Deletes em todas as tabelas
- Controle de acesso por roles (admin/customer)
- Versionamento de API (v1)
- API Resources para formatação de resposta
- Hashids configurável (via .env)
- ForceJsonResponse middleware
- **Tratamento de erros RESTful padronizado**

## Usuários e Dados de Teste

Após rodar `php artisan db:seed`:

**Administrador:**
- Email: `admin@doglio.com`
- Senha: `password`

**Cliente:**
- Email: `client@doglio.com`
- Senha: `password`

**Dados seedados:**
- Método de pagamento: PIX (`is_active = true`)
- Promoção ativa: "Lançamento Ração Premium" — 10% off no produto "Ração Super Premium" (sem data de expiração)
- Promoção expirada: "Black Friday 2025" — R$15,00 off na "Coleira Anti-pulgas"
- 3 endereços salvos para o cliente: Casa (principal), Trabalho, Casa da Mãe

---

## TODO — Testes

Os arquivos existentes cobrem: `AuthTest`, `CategoryTest`, `ProductFilterTest`, `ProductImageTest`, `ProductErrorHandlingTest`, `UserTest`.

Os módulos abaixo **não têm testes ainda**:

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
- `test_list_active_promotions`
- `test_show_promotion`
- `test_admin_create_promotion`
- `test_admin_create_promotion_percentage_over_100_fails`
- `test_admin_update_promotion`
- `test_admin_delete_promotion`
- `test_admin_attach_products`
- `test_admin_detach_products`
- `test_non_admin_cannot_create_promotion`

### `tests/Feature/ReviewTest.php`
- `test_list_product_reviews`
- `test_create_review_requires_purchase`
- `test_create_review_after_delivered_order`
- `test_cannot_review_twice`
- `test_delete_own_review`
- `test_cannot_delete_other_user_review`

### `tests/Feature/FavoriteTest.php`
- `test_list_favorites`
- `test_add_favorite`
- `test_cannot_add_duplicate_favorite`
- `test_remove_favorite`
- `test_toggle_notify`

### `tests/Feature/PushTokenTest.php`
- `test_register_push_token`
- `test_register_same_token_updates_user`
- `test_remove_push_token`

### `tests/Unit/ProductObserverTest.php`
- `test_dispatches_restock_job_when_stock_goes_from_zero`
- `test_dispatches_low_stock_job_when_stock_drops_below_threshold`
- `test_does_not_dispatch_when_stock_unchanged`

---

## TODO — Melhorias de Qualidade

### Rate Limiting (`AppServiceProvider` + `routes/api.php`)
Definir limiters nomeados (`api_public`, `api_auth`, `login`) e aplicar nas rotas com `throttle:nome`.
Retorna `429 Too Many Requests` com header `Retry-After` automaticamente.
- `throttle:login` → 10 req/min por IP (brute force protection)
- `throttle:api_public` → 60 req/min por IP
- `throttle:api_auth` → 120 req/min por usuário autenticado

### Policies (`app/Policies/` + controllers)
Centralizar regras de ownership em vez de `if ($model->user_id !== $user->id) abort(403)` espalhado.
Policies a criar: `ReviewPolicy`, `OrderPolicy`, `UserAddressPolicy`, `UserFavoritePolicy`.
Nos controllers substituir por `$this->authorize('delete', $review)`.

### Cache em rotas de leitura pública (`ProductController`, `CategoryController`, `PromotionController`)
Usar `Cache::remember($chave, $ttl, fn() => query)` nos métodos `index`.
Chave dinâmica com `md5(json_encode($request->all()))` para cachear cada combinação de filtros.
Invalidar nos métodos de escrita (store/update/destroy).
Driver: `file` local, `redis` em produção (só muda o `.env`).

### Queue driver `database` (`.env` + README)
Trocar `QUEUE_CONNECTION=sync` para `QUEUE_CONNECTION=database`.
A tabela `jobs` já existe. Worker local: `php artisan queue:work`.
Em produção configurar Supervisor para manter o worker vivo.

---

## TODO — Firebase / FCM (Push Notifications)

As notificações push estão arquitetadas e prontas no backend (`PushNotificationService`, `NotifyRestockJob`, `NotifyLowStockJob`, `ProductObserver`), mas requerem configuração do Firebase Cloud Messaging antes de enviar mensagens reais.

**Passos para ativar:**

1. Acesse [console.firebase.google.com](https://console.firebase.google.com) e crie (ou acesse) seu projeto
2. Vá em **Project Settings → Cloud Messaging**
3. Copie a **Server Key** (legacy) ou configure o **service account** para FCM HTTP v1
4. Adicione ao `.env`:
```env
FCM_SERVER_KEY=sua_server_key_aqui
```
5. Adicione ao `config/services.php`:
```php
'fcm' => [
    'server_key' => env('FCM_SERVER_KEY'),
],
```
6. No Flutter, integre o pacote `firebase_messaging` e registre o token do dispositivo via `POST /api/v1/push-tokens`

> **Sem a chave configurada**, o `PushNotificationService` apenas loga a notificação e não lança erros. O restante do sistema funciona normalmente.

---

## Comandos Úteis

```bash
# Limpar cache de configuração
php artisan config:clear

# Limpar todos os caches
php artisan optimize:clear

# Ver rotas
php artisan route:list

# Rodar testes
php artisan test

# Recriar banco de dados
php artisan migrate:fresh --seed

# Gerar IDE Helper
php artisan ide-helper:generate
```

---

## API Response Format (RESTful Standard)

All API responses follow a consistent RESTful format:

### Success Response
```json
{
  "success": true,
  "message": "Operation successful",
  "data": {
    // Response data
  }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error description",
  "error": {
    "code": "ERROR_CODE",
    "details": "Detailed error information or validation errors object"
  }
}
```

### HTTP Status Codes
- `200` - Success (GET, PUT, DELETE)
- `201` - Created (POST)
- `401` - Unauthenticated
- `403` - Forbidden / Account Inactive
- `404` - Resource Not Found / Endpoint Not Found
- `405` - Method Not Allowed
- `422` - Validation Error
- `500` - Internal Server Error

### Error Codes
- `UNAUTHENTICATED` - User must be authenticated
- `FORBIDDEN` - User lacks permissions
- `INVALID_CREDENTIALS` - Wrong email/password
- `ACCOUNT_INACTIVE` - User account is deactivated
- `RESOURCE_NOT_FOUND` - Model not found or soft deleted
- `ENDPOINT_NOT_FOUND` - API route doesn't exist
- `METHOD_NOT_ALLOWED` - HTTP method not supported for this endpoint
- `VALIDATION_ERROR` - Request validation failed (details contains field errors)
- `IMAGE_LIMIT_EXCEEDED` - Product image limit exceeded (max 6)
- `CART_EMPTY` - Checkout attempted with an empty cart
- `INSUFFICIENT_STOCK` - One or more cart items exceed available stock
- `INTERNAL_ERROR` - Server error (with debug details in development mode)

### Examples

**Successful Login**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": "abc123",
      "name": "John Doe",
      "email": "john@example.com"
    },
    "token": "2|randomtoken...",
    "token_type": "Bearer"
  }
}
```

**Product Not Found**
```json
{
  "success": false,
  "message": "Product not found",
  "error": {
    "code": "RESOURCE_NOT_FOUND",
    "details": "The requested product does not exist or has been deleted"
  }
}
```

**Validation Error**
```json
{
  "success": false,
  "message": "Validation failed",
  "error": {
    "code": "VALIDATION_ERROR",
    "details": {
      "email": ["The email field is required."],
      "password": ["The password must be at least 8 characters."]
    }
  }
}
```

**Image Limit Exceeded**
```json
{
  "success": false,
  "message": "Image limit exceeded",
  "error": {
    "code": "IMAGE_LIMIT_EXCEEDED",
    "details": "Maximum limit of 6 images per product exceeded",
    "current_count": 4,
    "max_allowed": 6
  }
}
```

---

## Testing

The project includes a comprehensive test suite with 72 tests covering:
- Authentication flows (register, login, logout)
- Product CRUD operations
- Error handling (404, 401, 422, etc.)
- Soft deletes
- Image upload validation
- User management
- Advanced product filtering

**Run all tests:**
```bash
php artisan test
```

**Run specific test file:**
```bash
php artisan test --filter=ProductErrorHandlingTest
```

**Run with coverage:**
```bash
php artisan test --coverage
```

---

## Changelog (15/11/2025)

### Adicionado
- **API Response Standardization (RESTful)**
  - Consistent response format for all endpoints
  - 10 standardized error codes
  - Proper HTTP status codes for all scenarios
  - Distinguished error types (RESOURCE_NOT_FOUND vs ENDPOINT_NOT_FOUND)
- Sistema de múltiplas imagens por produto (até 6)
- Filtros avançados em produtos (10+ filtros)
- CRUD completo de usuários (admin)
- Campo `is_active` em usuários
- Soft deletes em usuários
- Middleware ForceJsonResponse
- Sistema de Hashids configurável via `.env`
- **Comprehensive test suite (72 tests, 285 assertions)**
- Tratamento de erros de autenticação em JSON
- Rota GET /api/v1/user para perfil do usuário

### Modificado
- **Exception handling in bootstrap/app.php**
  - Smart detection of ModelNotFoundException via getPrevious()
  - Handles Laravel's automatic exception conversion
- **All controller responses standardized**
  - AuthController: register, login, logout
  - ProductController: image limit errors, delete responses
  - UserController & CategoryController: delete responses
- ProductController index com 10+ filtros
- Produtos sem estoque ficam ocultos por padrão
- Ordenação padrão: destacados primeiro, depois por estoque
- **UsesHashids trait**: Properly throws ModelNotFoundException for soft deleted models
- Resources agora usam $this->hashid (respeita configuração)
- **All test files updated to match new RESTful format**

### Corrigido
- Erro "Route [login] not defined" em rotas protegidas
- **Product soft delete now returns proper error message**
  - Before: "Endpoint not found"
  - After: "Product not found - The requested product does not exist or has been deleted"
- IDs agora podem ser normais ou hashids (configurável)
- **Exception conversion handling** (ModelNotFoundException → NotFoundHttpException)
- **Validation error responses** now include error code and proper structure

## License

MIT License
