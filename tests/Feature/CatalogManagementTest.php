<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'مدیر کاتالوگ',
            'mobile' => '09123456787',
            'password' => 'a-secure-test-password',
            'is_admin' => true,
        ]);
    }

    public function test_deleting_category_preserves_products_and_clears_their_category_reference(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $image = 'categories/skincare.jpg';
        Storage::disk('public')->put($image, 'category-image');
        $category = Category::create([
            'name' => 'مراقبت پوست',
            'slug' => 'skin-care-delete-test',
            'image' => $image,
            'is_active' => true,
        ]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'سرم تست',
            'slug' => 'test-serum-category-delete',
            'price' => 120000,
            'stock' => 3,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.categories.delete', $category))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => null]);
        Storage::disk('public')->assertMissing($image);
    }

    public function test_invalid_glb_upload_is_rejected_without_creating_a_product(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'name' => 'مدل سه‌بعدی نامعتبر',
            'slug' => 'invalid-glb-product',
            'price' => 150000,
            'stock' => 1,
            'is_active' => true,
            'model_3d' => UploadedFile::fake()->create('malicious.glb', 1, 'application/octet-stream'),
        ])->assertSessionHasErrors('model_3d');

        $this->assertDatabaseMissing('products', ['slug' => 'invalid-glb-product']);
    }

    public function test_valid_glb_2_model_can_be_uploaded_and_attached_to_product(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $json = json_encode(['asset' => ['version' => '2.0']]);
        $json = str_pad($json, (int) (ceil(strlen($json) / 4) * 4), ' ');
        $chunkLength = strlen($json);
        $totalLength = 20 + $chunkLength;
        $glb = 'glTF' . pack('V', 2) . pack('V', $totalLength) . pack('V', $chunkLength) . 'JSON' . $json;

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'name' => 'محصول با مدل سه‌بعدی',
            'slug' => 'valid-glb-product',
            'price' => 150000,
            'stock' => 1,
            'is_active' => true,
            'model_3d' => UploadedFile::fake()->createWithContent('valid-model.glb', $glb),
        ])->assertRedirect(route('admin.products'))->assertSessionHasNoErrors();

        $product = Product::where('slug', 'valid-glb-product')->firstOrFail();
        $this->assertNotEmpty($product->model_3d);
        Storage::disk('public')->assertExists($product->model_3d);
    }
}
