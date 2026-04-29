# 🧠 Arquitetura Laravel Profissional (Clean + Service Layer)

## 📌 Objetivo

Criar uma arquitetura escalável, testável e organizada:

- Controllers finos
- Regras de negócio em Services
- Validação em Form Requests
- Views Blade recebem dados prontos
- Código preparado para crescimento

---

## 🏗️ Estrutura de Pastas
app/
├── Http/
│ ├── Controllers/
│ │ └── ProductController.php
│ ├── Requests/
│ │ └── Product/
│ │ ├── StoreProductRequest.php
│ │ └── UpdateProductRequest.php
│
├── Services/
│ └── ProductService.php
│
├── Repositories/
│ └── ProductRepository.php
│
├── DTOs/
│ └── ProductData.php
│
├── Models/
│ └── Product.php

---

## 🎯 Princípios

### 1. Controller NÃO tem regra de negócio

❌ Errado:
```php
if ($request->price > 1000) {
    // lógica aqui
}

✅ Correto:

$this->productService->create($data);
```

### 2. Service contém a inteligência

Responsável por:

- Regras de negócio
- Processamento
- Integrações externas
- Decisões

### 3. Request valida tudo
- Nada de validação na controller
- Evitar Validator::make

---

## 🔄 Fluxo da Requisição
```php
Request → Controller → Service → Model → DB
                         ↓
                      Blade View;
```

---

## 📦 Exemplo Completo
### 📌 1. Form Request
```php
namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
        ];
    }
}
```

### 📌 4. Service
```php
namespace App\Services;

class ProductService
{
    public function create(StoreProductRequest $data)
    {
        // Regra de negócio
        if ($data->price > 10000) {
            throw new \Exception("Preço muito alto");
        }

        return Product::create([
            'name' => $data->name,
            'price' => $data->price,
        ]);
    }

    public function list()
    {
        return Product::all();
    }
}
```

### 📌 5. Controller (FINO)
```php
namespace App\Http\Controllers;

use App\Services\ProductService;
use App\Http\Requests\Product\StoreProductRequest;

class ProductController extends Controller
{
    public function index(ProductService $service)
    {
        $products = $service->list();

        return view('admin.products.index', compact('products'));
    }

    public function store(StoreProductRequest $request, ProductService $service)
    {
        $service->create($request);

        return redirect()->back()->with('success', 'Criado com sucesso');
    }
}
```