<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// Public storefront for buyers
$routes->get('/', 'Store::index');
$routes->get('browse', 'Store::index');
$routes->get('buyer/login', 'Auth::storeLogin');
$routes->post('buyer/login', 'Auth::storeAuthenticate');
$routes->get('buyer/register', 'Auth::storeRegister');
$routes->post('buyer/register', 'Auth::storeRegistration');

// Auth Routes (Login/Logout)
$routes->get('login', 'Auth::index');
$routes->get('auth/login', 'Auth::index');
$routes->get('register', 'Auth::register');
$routes->get('auth/register', 'Auth::register');
$routes->post('login/authenticate', 'Auth::authenticate');
$routes->post('auth/authenticate', 'Auth::authenticate');
$routes->post('register/store', 'Auth::adminRegistration');
$routes->post('auth/register', 'Auth::adminRegistration');
$routes->get('logout', 'Auth::logout');
$routes->get('auth/logout', 'Auth::logout');

// Admin Route Group
$routes->group('admin', ['filter' => 'roleAuth:admin'], function($routes) {
    $routes->post('authenticate', 'Auth::authenticate');
    $routes->get('dashboard', 'Admin\Inventory::dashboard'); 
    $routes->get('inventory', 'Admin\Inventory::index');     
    $routes->get('inventory/create', 'Admin\Inventory::createProduct');
    $routes->post('inventory/store', 'Admin\Inventory::storeProduct');
    $routes->get('inventory/edit/(:num)', 'Admin\Inventory::edit/$1');
    $routes->post('inventory/update/(:num)', 'Admin\Inventory::update/$1');
    $routes->post('inventory/delete/(:num)', 'Admin\Inventory::delete/$1');
    $routes->get('categories', 'Admin\Inventory::categories');
    $routes->get('categories/create', 'Admin\Inventory::createCategory');
    $routes->post('categories/store', 'Admin\Inventory::storeCategory');
    $routes->get('categories/edit/(:num)', 'Admin\Inventory::editCategory/$1');
    $routes->post('categories/update/(:num)', 'Admin\Inventory::updateCategory/$1');
    $routes->post('categories/delete/(:num)', 'Admin\Inventory::deleteCategory/$1');
    $routes->get('orders', 'Admin\Orders::index');
    $routes->get('orders/details/(:num)', 'Admin\Orders::details/$1');
    $routes->post('orders/updateStatus/(:num)', 'Admin\Orders::updateStatus/$1');
    $routes->get('users', 'Admin\Users::index');
    $routes->get('users/create', 'Admin\Users::create');
    $routes->post('users/store', 'Admin\Users::store');
});

// Staff Route Group
$routes->group('staff', ['filter' => 'roleAuth:staff'], function($routes) {
    // Dashboard
    $routes->get('dashboard', 'Staff\Dashboard::index');
    $routes->get('inventory', 'Staff\Inventory::index');

    // Orders
    $routes->get('orders', 'Staff\Orders::index');
    $routes->get('orders/details/(:num)', 'Staff\Orders::details/$1');
    $routes->post('orders/updateStatus/(:num)', 'Staff\Orders::updateStatus/$1');
});

// User Route Group with security filter
$routes->group('user', ['filter' => 'roleAuth:user'], function($routes) {
    // Dashboard
    $routes->get('dashboard', 'User::dashboard');
    $routes->get('products', 'User::productsRedirect');
    $routes->get('categories', 'User::products');
    $routes->get('profile', 'User::profile');
    $routes->post('profile/update', 'User::updateProfile');
    $routes->get('cart', 'User::cart');
    $routes->post('cart/add', 'User::addToCart');
    $routes->post('buy-now', 'User::buyNow');
    $routes->post('cart/update', 'User::updateCart');
    $routes->post('cart/remove/(:num)', 'User::removeFromCart/$1');
    $routes->post('checkout', 'User::checkout');
    $routes->get('orders', 'User::orders');
    $routes->get('orders/details/(:num)', 'User::orderDetails/$1');
    $routes->post('orders/cancel/(:num)', 'User::cancelOrder/$1');
});

// Global Products Route - accessible to buyers only
$routes->get('products', 'Products::index', ['filter' => 'roleAuth:user']);