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
        $json = json_encode([
            'asset' => ['version' => '2.0'],
            'buffers' => [['byteLength' => 4]],
        ]);
        $json = str_pad($json, (int) (ceil(strlen($json) / 4) * 4), ' ');
        $chunkLength = strlen($json);
        $binary = 'DATA';
        $totalLength = 20 + $chunkLength + 8 + strlen($binary);
        $glb = 'glTF' . pack('V', 2) . pack('V', $totalLength)
            . pack('V', $chunkLength) . 'JSON' . $json
            . pack('V', strlen($binary)) . "BIN\0" . $binary;

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

    public function test_category_image_replacement_keeps_new_file_and_removes_old_file(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $oldImage = 'categories/old-category.jpg';
        Storage::disk('public')->put($oldImage, 'old-image');
        $category = Category::create([
            'name' => 'دسته قبلی',
            'slug' => 'category-image-replacement',
            'image' => $oldImage,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->put(route('admin.categories.update', $category), [
            'name' => 'دسته جدید',
            'slug' => $category->slug,
            'is_active' => true,
            'image' => UploadedFile::fake()->image('new-category.jpg'),
        ])->assertRedirect(route('admin.categories'))->assertSessionHasNoErrors();

        $updated = $category->fresh();
        $this->assertSame('دسته جدید', $updated->name);
        $this->assertNotSame($oldImage, $updated->image);
        Storage::disk('public')->assertMissing($oldImage);
        Storage::disk('public')->assertExists($updated->image);
    }

    public function test_product_detail_exposes_uploaded_glb_to_the_three_dimensional_viewer(): void
    {
        $product = Product::create([
            'name' => 'محصول سه‌بعدی',
            'slug' => 'three-dimensional-product-view',
            'price' => 180000,
            'stock' => 2,
            'model_3d' => 'models/sample.glb',
            'is_active' => true,
        ]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('product-3d-viewer', false)
            ->assertSee(asset('storage/models/sample.glb'), false)
            ->assertSee('نمایش سه‌بعدی محصول', false);
    }



    public function test_category_image_can_be_removed_without_replacing_it(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $image = 'categories/remove-me.jpg';
        Storage::disk('public')->put($image, 'image');
        $category = Category::create([
            'name' => 'دسته قابل ویرایش',
            'slug' => 'category-remove-image',
            'image' => $image,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->put(route('admin.categories.update', $category), [
            'name' => $category->name,
            'slug' => $category->slug,
            'is_active' => true,
            'remove_image' => 1,
        ])->assertRedirect(route('admin.categories'))->assertSessionHasNoErrors();

        $this->assertNull($category->fresh()->image);
        Storage::disk('public')->assertMissing($image);
    }

    public function test_product_image_and_glb_can_be_removed_from_admin_editor(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $image = 'products/remove-image.jpg';
        $model = 'models/remove-model.glb';
        Storage::disk('public')->put($image, 'image');
        Storage::disk('public')->put($model, 'model');
        $product = Product::create([
            'name' => 'محصول قابل پاکسازی',
            'slug' => 'product-remove-media',
            'price' => 90000,
            'stock' => 2,
            'image' => $image,
            'model_3d' => $model,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->put(route('admin.products.update', $product), [
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => $product->price,
            'stock' => $product->stock,
            'is_active' => true,
            'remove_image' => 1,
            'remove_model_3d' => 1,
        ])->assertRedirect(route('admin.products'))->assertSessionHasNoErrors();

        $updated = $product->fresh();
        $this->assertNull($updated->image);
        $this->assertNull($updated->model_3d);
        Storage::disk('public')->assertMissing($image);
        Storage::disk('public')->assertMissing($model);
    }


}
