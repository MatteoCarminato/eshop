<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'brand_id' => $this->brand_id ?: null,
            'category_id' => $this->category_id ?: null,
            'active' => filter_var($this->active, FILTER_VALIDATE_BOOLEAN),
            'featured' => filter_var($this->featured, FILTER_VALIDATE_BOOLEAN),
            'stock' => $this->stock === null || $this->stock === '' ? 0 : $this->stock,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'brand_id' => 'nullable|exists:brands,id',
            'category_id' => 'nullable|exists:categories,id',
            'name' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0',
            'wholesale_price' => 'nullable|numeric|min:0',
            'web_price' => 'nullable|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'min_price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'image_url' => 'nullable|string|max:2048',
            'active' => 'nullable|boolean',
            'featured' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome do produto é obrigatório.',
            'name.max' => 'O nome não pode ter mais de 255 caracteres.',
            'short_name.max' => 'O nome curto não pode ter mais de 255 caracteres.',
            'brand_id.exists' => 'A marca selecionada é inválida.',
            'category_id.exists' => 'A categoria selecionada é inválida.',
            'price.required' => 'O preço é obrigatório.',
            'price.numeric' => 'O preço deve ser um valor numérico.',
            'price.min' => 'O preço não pode ser negativo.',
            'wholesale_price.numeric' => 'O preço de atacado deve ser um valor numérico.',
            'web_price.numeric' => 'O preço web deve ser um valor numérico.',
            'sale_price.numeric' => 'O preço promocional deve ser um valor numérico.',
            'min_price.numeric' => 'O preço mínimo deve ser um valor numérico.',
            'stock.numeric' => 'O estoque deve ser um valor numérico.',
            'image_url.max' => 'A URL da imagem não pode ter mais de 2048 caracteres.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'brand_id' => 'marca',
            'category_id' => 'categoria',
            'name' => 'nome',
            'short_name' => 'nome curto',
            'price' => 'preço',
            'wholesale_price' => 'preço de atacado',
            'web_price' => 'preço web',
            'sale_price' => 'preço promocional',
            'min_price' => 'preço mínimo',
            'stock' => 'estoque',
            'description' => 'descrição',
            'image_url' => 'imagem (URL)',
        ];
    }
}
