<?php
namespace Tests\Feature;
use Tests\TestCase;
class HomePageTest extends TestCase {
 public function test_homepage_returns_successful_response(): void {
  $this->get('/')->assertOk()->assertSee('زیبایی');
 }
 public function test_shop_page_returns_successful_response(): void {
  $this->get('/shop')->assertOk();
 }
}