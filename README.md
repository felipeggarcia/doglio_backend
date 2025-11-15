# Doglio E-commerce Backend API

A RESTful API built with Laravel 12 for an e-commerce system with product catalog, categories, and order management.

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
- Sample categories and products

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

---

## Project Structure

```
app/
├── Http/
│   ├── Controllers/V1/
│   │   ├── AuthController.php
│   │   ├── ProductController.php
│   │   ├── CategoryController.php
│   │   └── UserController.php
│   ├── Requests/V1/Admin/
│   │   ├── CategoryStoreRequest.php
│   │   └── CategoryUpdateRequest.php
│   ├── Resources/
│   │   ├── ProductResource.php
│   │   ├── CategoryResource.php
│   │   ├── UserResource.php
│   │   └── ProductImageResource.php
│   └── Middleware/
│       ├── AdminMiddleware.php
│       └── ForceJsonResponse.php
├── Models/
│   ├── User.php
│   ├── Product.php
│   ├── ProductImage.php
│   ├── Category.php
│   ├── Order.php
│   └── OrderItem.php
└── Traits/
    └── UsesHashids.php
```

## Database Schema

- **users**: User accounts (customers and admins)
  - Added: `is_active` (boolean) - Account status
  - Supports soft deletes
- **categories**: Product categories
- **products**: Product catalog
- **product_images**: Product images (up to 6 per product)
  - `is_primary`: Marks the main product image
  - `order`: Display order
- **category_product**: Many-to-many pivot table
- **orders**: Customer orders
- **order_items**: Order line items

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

- ✅ Autenticação JWT com Laravel Sanctum
- ✅ CRUD completo de Produtos
  - ✅ Upload múltiplo de imagens (até 6 por produto)
  - ✅ Filtros avançados (preço, estoque, categorias, busca)
  - ✅ Ordenação inteligente (destacados primeiro, depois por estoque)
  - ✅ Esconde produtos sem estoque por padrão
- ✅ CRUD completo de Categorias
- ✅ CRUD completo de Usuários (Admin)
  - ✅ Ativar/desativar usuários
  - ✅ Soft deletes
  - ✅ Filtros por role e status
- ✅ Sistema de Pedidos
- ✅ Relacionamento muitos-para-muitos (Produtos ↔ Categorias)
- ✅ Soft Deletes em todas as tabelas
- ✅ Controle de acesso por roles (admin/customer)
- ✅ Versionamento de API (v1)
- ✅ Form Requests para validação
- ✅ API Resources para formatação de resposta
- ✅ Hashids configurável (via .env)
- ✅ ForceJsonResponse middleware
- ✅ Tratamento de erros de autenticação em JSON

## Usuários de Teste

Após rodar `php artisan db:seed`:

**Administrador:**
- Email: `admin@doglio.com`
- Senha: `password`

**Cliente:**
- Email: `client@doglio.com`
- Senha: `password`

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

## Changelog (15/11/2025)

### Adicionado
- Sistema de múltiplas imagens por produto (até 6)
- Filtros avançados em produtos:
  - Busca por nome/descrição
  - Filtro por faixa de preço
  - Filtro por estoque
  - Filtro por categoria
  - Ordenação customizável
- CRUD completo de usuários (admin)
- Campo `is_active` em usuários
- Soft deletes em usuários
- Middleware ForceJsonResponse
- Sistema de Hashids configurável via `.env`
- Tratamento de erros de autenticação em JSON
- Rota GET /api/v1/user para perfil do usuário

### Modificado
- ProductController index com 10+ filtros
- Produtos sem estoque ficam ocultos por padrão
- Ordenação padrão: destacados primeiro, depois por estoque
- AuthController retorna JSON em vez de redirect
- Resources agora usam $this->hashid (respeita configuração)

### Corrigido
- Erro "Route [login] not defined" em rotas protegidas
- IDs agora podem ser normais ou hashids (configurável)

## License

MIT License
