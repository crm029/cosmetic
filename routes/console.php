<?php
use Illuminate\\Support\\Facades\\Artisan;
Artisan::command('store:about', function () {
 $this->comment('Cosmetic Store - Laravel storefront');
})->purpose('Display store project information');