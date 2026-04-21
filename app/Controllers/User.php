<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\CategoryModel;
use App\Models\CartItemModel;
use App\Models\OrderItem;
use App\Models\OrderModel;
use App\Models\ProductModel;
use App\Models\UserModel;
use CodeIgniter\Database\Exceptions\DatabaseException;

class User extends BaseController
{
    public function dashboard()
    {
        if ($redirect = $this->ensureLoggedIn()) {
            return $redirect;
        }

        $productModel = new ProductModel();
        $categoryModel = new CategoryModel();
        $orderModel = new OrderModel();
        $cartSummary = $this->buildCartSummary();
        $selectedCategory = trim((string) $this->request->getGet('category'));
        $selectedPriceRange = trim((string) $this->request->getGet('price_range'));
        $currentPage = max(1, (int) $this->request->getGet('page'));
        $perPage = 15;
        $allProducts = $productModel->getProductsWithCategory();
        $categories = $categoryModel->orderBy('name', 'ASC')->findAll();
        $featuredProducts = $allProducts;

        if ($selectedCategory !== '') {
            $featuredProducts = array_values(array_filter(
                $featuredProducts,
                static fn (array $product): bool => strcasecmp((string) ($product['category_name'] ?? ''), $selectedCategory) === 0
            ));
        }

        if ($selectedPriceRange !== '') {
            $featuredProducts = array_values(array_filter(
                $featuredProducts,
                static function (array $product) use ($selectedPriceRange): bool {
                    $price = (float) ($product['price'] ?? 0);

                    return match ($selectedPriceRange) {
                        'under_500' => $price < 500,
                        '500_1000' => $price >= 500 && $price <= 1000,
                        '1000_5000' => $price > 1000 && $price <= 5000,
                        'above_5000' => $price > 5000,
                        default => true,
                    };
                }
            ));
        }

        $totalFeaturedProducts = count($featuredProducts);
        $totalPages = max(1, (int) ceil($totalFeaturedProducts / $perPage));
        $currentPage = min($currentPage, $totalPages);
        $featuredProducts = array_slice($featuredProducts, ($currentPage - 1) * $perPage, $perPage);
        $recentOrders = $orderModel
            ->where('user_id', session()->get('id'))
            ->orderBy('created_at', 'DESC')
            ->findAll(3);

        return view('user/dashboard', [
            'pageTitle' => 'Dashboard',
            'username' => session()->get('username'),
            'role' => session()->get('role'),
            'totalProducts' => $productModel->countAll(),
            'totalCategories' => count($categories),
            'cartCount' => $cartSummary['itemCount'],
            'totalOrders' => $orderModel->where('user_id', session()->get('id'))->countAllResults(),
            'featuredProducts' => $featuredProducts,
            'recentOrders' => $recentOrders,
            'categories' => $categories,
            'selectedCategory' => $selectedCategory,
            'selectedPriceRange' => $selectedPriceRange,
            'cartSummary' => $cartSummary,
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
        ]);
    }

    public function profile()
    {
        if ($redirect = $this->ensureLoggedIn()) {
            return $redirect;
        }

        $userModel = new UserModel();
        $user = $userModel->find((int) session()->get('id'));
        if (!$user) {
            return redirect()->to('auth/logout');
        }

        $orderModel = new OrderModel();
        $recentOrders = $orderModel
            ->where('user_id', session()->get('id'))
            ->orderBy('created_at', 'DESC')
            ->findAll(5);

        $cartSummary = $this->buildCartSummary();

        return view('user/profile', [
            'pageTitle' => 'My Profile',
            'username' => session()->get('username'),
            'role' => session()->get('role'),
            'cartCount' => $cartSummary['itemCount'],
            'user' => $user,
            'recentOrders' => $recentOrders,
        ]);
    }

    public function updateProfile()
    {
        if ($redirect = $this->ensureLoggedIn()) {
            return $redirect;
        }

        $userId = (int) session()->get('id');
        $userModel = new UserModel();
        $user = $userModel->find($userId);

        if (!$user) {
            return redirect()->to('auth/logout');
        }

        $data = [
            'first_name' => trim((string) $this->request->getPost('first_name')),
            'last_name' => trim((string) $this->request->getPost('last_name')),
            'email' => trim((string) $this->request->getPost('email')),
            'contact' => trim((string) $this->request->getPost('contact')),
            'date_of_birth' => trim((string) $this->request->getPost('date_of_birth')),
            'address' => trim((string) $this->request->getPost('address')),
            'zip_code' => trim((string) $this->request->getPost('zip_code')),
            'country' => trim((string) $this->request->getPost('country')),
        ];
        $currentPassword = (string) $this->request->getPost('current_password');
        $newPassword = (string) $this->request->getPost('new_password');
        $confirmPassword = (string) $this->request->getPost('confirm_password');
        $isChangingPassword = $currentPassword !== '' || $newPassword !== '' || $confirmPassword !== '';

        $rules = [
            'first_name' => 'required|min_length[2]|max_length[100]',
            'last_name' => 'required|min_length[2]|max_length[100]',
            'email' => 'required|valid_email|max_length[255]|is_unique[users.email,id,' . $userId . ']',
            'contact' => 'required|min_length[7]|max_length[30]',
            'date_of_birth' => 'required|valid_date[Y-m-d]',
            'address' => 'required|min_length[5]|max_length[500]',
            'zip_code' => 'required|min_length[3]|max_length[20]',
            'country' => 'required|min_length[2]|max_length[100]',
        ];

        if (! $this->validateData($data, $rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        if ($isChangingPassword) {
            $passwordErrors = [];

            if ($currentPassword === '' || !password_verify($currentPassword, (string) ($user['password_hash'] ?? ''))) {
                $passwordErrors['current_password'] = 'Your current password is incorrect.';
            }

            if (mb_strlen($newPassword) < 8) {
                $passwordErrors['new_password'] = 'Your new password must be at least 8 characters long.';
            }

            if ($newPassword !== $confirmPassword) {
                $passwordErrors['confirm_password'] = 'Your new password confirmation does not match.';
            }

            if (!empty($passwordErrors)) {
                return redirect()->back()->withInput()->with('errors', $passwordErrors);
            }

            $data['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        $data['id'] = $userId;

        if (! $userModel->save($data)) {
            return redirect()->back()->withInput()->with('errors', $userModel->errors());
        }

        return redirect()->to('user/profile')->with(
            'success',
            $isChangingPassword
                ? 'Your profile and password have been updated.'
                : 'Your profile has been updated.'
        );
    }

    public function products()
    {
        if ($redirect = $this->ensureLoggedIn()) {
            return $redirect;
        }

        $productModel = new ProductModel();
        $categoryModel = new CategoryModel();
        $search = trim((string) $this->request->getGet('search'));
        $selectedCategory = trim((string) $this->request->getGet('category'));
        $cartSummary = $this->buildCartSummary();
        $allProducts = $productModel->getProductsWithCategory($search);
        $categories = $categoryModel->orderBy('name', 'ASC')->findAll();
        $categoryCards = [];

        foreach ($categories as $category) {
            $categoryName = (string) ($category['name'] ?? '');
            $categoryProducts = array_values(array_filter(
                $allProducts,
                static fn (array $product): bool => strcasecmp((string) ($product['category_name'] ?? ''), $categoryName) === 0
            ));

            $categoryCards[] = [
                'id' => (int) ($category['id'] ?? 0),
                'name' => $categoryName,
                'description' => (string) ($category['description'] ?? ''),
                'product_count' => count($categoryProducts),
                'image_path' => $categoryProducts[0]['image_path'] ?? null,
            ];
        }

        $products = $allProducts;
        if ($selectedCategory !== '') {
            $products = array_values(array_filter(
                $products,
                static fn (array $product): bool => strcasecmp((string) ($product['category_name'] ?? ''), $selectedCategory) === 0
            ));
        }

        return view('user/products', [
            'pageTitle' => 'Products',
            'username' => session()->get('username'),
            'role' => session()->get('role'),
            'search' => $search,
            'selectedCategory' => $selectedCategory,
            'cartCount' => $cartSummary['itemCount'],
            'cartSummary' => $cartSummary,
            'categories' => $categoryCards,
            'products' => $products,
        ]);
    }

    public function productsRedirect()
    {
        if ($redirect = $this->ensureLoggedIn()) {
            return $redirect;
        }

        $query = (string) $this->request->getServer('QUERY_STRING');
        $target = 'user/categories';

        if ($query !== '') {
            $target .= '?' . $query;
        }

        return redirect()->to($target);
    }

    public function cart()
    {
        if ($redirect = $this->ensureLoggedIn()) {
            return $redirect;
        }

        $cartSummary = $this->buildCartSummary();

        return view('user/cart', [
            'pageTitle' => 'My Cart',
            'username' => session()->get('username'),
            'role' => session()->get('role'),
            'cartCount' => $cartSummary['itemCount'],
            'cartSummary' => $cartSummary,
        ]);
    }

    public function addToCart()
    {
        if ($redirect = $this->ensureLoggedIn()) {
            return $redirect;
        }

        if ($redirect = $this->ensureCartStorageReady()) {
            return $redirect;
        }

        $productId = (int) $this->request->getPost('product_id');
        $quantity = max(1, (int) $this->request->getPost('quantity'));

        $productModel = new ProductModel();
        $product = $productModel->find($productId);

        if (!$product) {
            return redirect()->back()->with('error', 'Product not found.');
        }

        $availableStock = max(0, (int) ($product['stock_quantity'] ?? 0));
        if ($availableStock < 1) {
            return redirect()->back()->with('error', 'This product is currently out of stock.');
        }

        $cartModel = new CartItemModel();
        $cartItem = $cartModel
            ->where('user_id', session()->get('id'))
            ->where('product_id', $productId)
            ->first();

        $currentQuantity = (int) ($cartItem['quantity'] ?? 0);
        if ($currentQuantity >= $availableStock) {
            return redirect()->back()->with('error', 'This item is already at the maximum available stock in your cart.');
        }

        $newQuantity = min($currentQuantity + $quantity, $availableStock);

        if ($cartItem) {
            $cartModel->update($cartItem['id'], ['quantity' => $newQuantity]);
        } else {
            $cartModel->insert([
                'user_id' => session()->get('id'),
                'product_id' => $productId,
                'quantity' => $newQuantity,
            ]);
        }

        return redirect()->back()->with('success', esc($product['name']) . ' added to cart.');
    }

    public function buyNow()
    {
        if ($redirect = $this->ensureLoggedIn()) {
            return $redirect;
        }

        if ($redirect = $this->ensureCartStorageReady()) {
            return $redirect;
        }

        $productId = (int) $this->request->getPost('product_id');
        $quantity = max(1, (int) $this->request->getPost('quantity'));

        $productModel = new ProductModel();
        $product = $productModel->find($productId);

        if (!$product) {
            return redirect()->to('user/cart')->with('error', 'Product not found.');
        }

        $availableStock = max(0, (int) ($product['stock_quantity'] ?? 0));
        if ($availableStock < 1) {
            return redirect()->back()->with('error', 'This product is currently out of stock.');
        }

        $cartModel = new CartItemModel();
        $cartItem = $cartModel
            ->where('user_id', session()->get('id'))
            ->where('product_id', $productId)
            ->first();

        $checkoutQuantity = min($quantity, $availableStock);

        if ($cartItem) {
            $cartModel->update($cartItem['id'], ['quantity' => $checkoutQuantity]);
        } else {
            $cartModel->insert([
                'user_id' => session()->get('id'),
                'product_id' => $productId,
                'quantity' => $checkoutQuantity,
            ]);
        }

        return redirect()->to('user/cart')
            ->with('success', esc($product['name']) . ' is ready for checkout.');
    }

    public function updateCart()
    {
        if ($redirect = $this->ensureLoggedIn()) {
            return $redirect;
        }

        if ($redirect = $this->ensureCartStorageReady()) {
            return $redirect;
        }

        $postedQuantities = $this->request->getPost('quantities');
        if (!is_array($postedQuantities)) {
            return redirect()->to('user/cart')->with('error', 'No cart updates were submitted.');
        }

        $cartItemIds = array_values(array_filter(array_map('intval', array_keys($postedQuantities))));
        if (empty($cartItemIds)) {
            return redirect()->to('user/cart')->with('error', 'No valid cart updates were submitted.');
        }

        $productModel = new ProductModel();
        $productsById = [];
        $cartModel = new CartItemModel();
        $cartRows = $cartModel
            ->where('user_id', session()->get('id'))
            ->whereIn('id', $cartItemIds)
            ->findAll();

        $cartRowsById = [];
        $productIds = [];

        foreach ($cartRows as $cartRow) {
            $cartRowsById[(int) $cartRow['id']] = $cartRow;
            $productIds[] = (int) $cartRow['product_id'];
        }

        $products = $productModel->getProductsByIdsWithCategory(array_values(array_unique($productIds)));
        foreach ($products as $product) {
            $productsById[(int) $product['id']] = $product;
        }

        foreach ($postedQuantities as $cartItemId => $quantity) {
            $cartItemId = (int) $cartItemId;
            $quantity = (int) $quantity;

            if (!isset($cartRowsById[$cartItemId])) {
                continue;
            }

            $cartItem = $cartRowsById[$cartItemId];
            $productId = (int) $cartItem['product_id'];

            if ($quantity <= 0 || !isset($productsById[$productId])) {
                $cartModel->delete($cartItemId);
                continue;
            }

            $availableStock = max(0, (int) ($productsById[$productId]['stock_quantity'] ?? 0));
            if ($availableStock < 1) {
                $cartModel->delete($cartItemId);
                continue;
            }

            $cartModel->update($cartItemId, [
                'quantity' => min($quantity, $availableStock),
            ]);
        }

        return redirect()->to('user/cart')->with('success', 'Your cart has been updated.');
    }

    public function removeFromCart($cartItemId)
    {
        if ($redirect = $this->ensureLoggedIn()) {
            return $redirect;
        }

        if ($redirect = $this->ensureCartStorageReady()) {
            return $redirect;
        }

        $cartModel = new CartItemModel();
        $cartItem = $cartModel
            ->where('id', (int) $cartItemId)
            ->where('user_id', session()->get('id'))
            ->first();

        if ($cartItem) {
            $cartModel->delete((int) $cartItemId);
        }

        return redirect()->to('user/cart')->with('success', 'Item removed from cart.');
    }

    public function checkout()
    {
        if ($redirect = $this->ensureLoggedIn()) {
            return $redirect;
        }

        if ($redirect = $this->ensureCartStorageReady()) {
            return $redirect;
        }

        $cartSummary = $this->buildCartSummary();
        if (empty($cartSummary['items'])) {
            return redirect()->to('user/cart')->with('error', 'Your cart is empty.');
        }

        $db = \Config\Database::connect();
        $productModel = new ProductModel();
        $orderModel = new OrderModel();
        $orderItemModel = new OrderItem();
        $cartModel = new CartItemModel();

        $db->transBegin();

        try {
            $freshProducts = $productModel->getProductsByIdsWithCategory(array_column($cartSummary['items'], 'id'));
            $freshProductsById = [];

            foreach ($freshProducts as $product) {
                $freshProductsById[(int) $product['id']] = $product;
            }

            foreach ($cartSummary['items'] as $item) {
                $productId = (int) $item['id'];

                if (!isset($freshProductsById[$productId])) {
                    throw new \RuntimeException('One of the products in your cart is no longer available.');
                }

                if ((int) $freshProductsById[$productId]['stock_quantity'] < (int) $item['quantity']) {
                    throw new \RuntimeException('Some cart quantities are higher than the remaining stock. Please update your cart and try again.');
                }
            }

            $orderId = $orderModel->insert([
                'user_id' => session()->get('id'),
                'customer_name' => session()->get('username'),
                'total_amount' => $cartSummary['subtotal'],
                'status' => 'to_be_packed',
            ], true);

            if (!$orderId) {
                throw new \RuntimeException('Unable to create your order.');
            }

            foreach ($cartSummary['items'] as $item) {
                $productId = (int) $item['id'];
                $quantity = (int) $item['quantity'];
                $unitPrice = (float) $item['price'];

                if (!$orderItemModel->insert([
                    'order_id' => $orderId,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'price_at_time' => $unitPrice,
                    'subtotal' => $quantity * $unitPrice,
                ])) {
                    throw new \RuntimeException('Unable to save your order items.');
                }

                if (!$productModel->update($productId, [
                    'stock_quantity' => (int) $freshProductsById[$productId]['stock_quantity'] - $quantity,
                ])) {
                    throw new \RuntimeException('Unable to update product stock.');
                }
            }

            $cartModel->where('user_id', session()->get('id'))->delete();

            if ($db->transStatus() === false) {
                throw new \RuntimeException('The order could not be completed.');
            }

            $db->transCommit();

            return redirect()->to('user/orders/details/' . $orderId)
                ->with('success', 'Order placed successfully. It is now queued to be packed.');
        } catch (\Throwable $exception) {
            $db->transRollback();

            return redirect()->to('user/cart')->with('error', $exception->getMessage());
        }
    }

    public function orders()
    {
        if ($redirect = $this->ensureLoggedIn()) {
            return $redirect;
        }

        $orderModel = new OrderModel();
        $orders = $orderModel
            ->where('user_id', session()->get('id'))
            ->orderBy('created_at', 'DESC')
            ->findAll();

        $itemCounts = [];
        if (!empty($orders)) {
            $db = \Config\Database::connect();
            $countRows = $db->table('order_items')
                ->select('order_id, SUM(quantity) as total_items')
                ->whereIn('order_id', array_column($orders, 'id'))
                ->groupBy('order_id')
                ->get()
                ->getResultArray();

            foreach ($countRows as $row) {
                $itemCounts[(int) $row['order_id']] = (int) $row['total_items'];
            }
        }

        foreach ($orders as &$order) {
            $order['total_items'] = $itemCounts[(int) $order['id']] ?? 0;
        }
        unset($order);

        $cartSummary = $this->buildCartSummary();

        return view('user/orders', [
            'pageTitle' => 'My Orders',
            'username' => session()->get('username'),
            'role' => session()->get('role'),
            'cartCount' => $cartSummary['itemCount'],
            'orders' => $orders,
        ]);
    }

    public function orderDetails($id)
    {
        if ($redirect = $this->ensureLoggedIn()) {
            return $redirect;
        }

        $orderModel = new OrderModel();
        $order = $orderModel
            ->where('id', (int) $id)
            ->where('user_id', session()->get('id'))
            ->first();

        if (!$order) {
            return redirect()->to('user/orders')->with('error', 'Order not found.');
        }

        $db = \Config\Database::connect();
        $orderItems = $db->table('order_items')
            ->select('order_items.quantity, order_items.price_at_time, order_items.subtotal, products.name as product_name, products.sku, categories.name as category_name')
            ->join('products', 'products.id = order_items.product_id', 'left')
            ->join('categories', 'categories.id = products.category_id', 'left')
            ->where('order_items.order_id', (int) $id)
            ->get()
            ->getResultArray();

        $cartSummary = $this->buildCartSummary();

        return view('user/order_details', [
            'pageTitle' => 'Order Details',
            'username' => session()->get('username'),
            'role' => session()->get('role'),
            'cartCount' => $cartSummary['itemCount'],
            'order' => $order,
            'orderItems' => $orderItems,
        ]);
    }

    public function cancelOrder($id)
    {
        if ($redirect = $this->ensureLoggedIn()) {
            return $redirect;
        }

        $orderModel = new OrderModel();
        $order = $orderModel
            ->where('id', (int) $id)
            ->where('user_id', session()->get('id'))
            ->first();

        if (!$order) {
            return redirect()->to('user/orders')->with('error', 'Order not found.');
        }

        if ($order['status'] !== 'to_be_packed') {
            return redirect()->to('user/orders')->with('error', 'Only orders waiting to be packed can be cancelled.');
        }

        $db = \Config\Database::connect();
        $productModel = new ProductModel();

        $db->transBegin();

        try {
            $orderItems = $db->table('order_items')
                ->select('product_id, quantity')
                ->where('order_id', (int) $id)
                ->get()
                ->getResultArray();

            foreach ($orderItems as $item) {
                $product = $productModel->find((int) $item['product_id']);

                if (!$product) {
                    continue;
                }

                if (!$productModel->update((int) $item['product_id'], [
                    'stock_quantity' => (int) $product['stock_quantity'] + (int) $item['quantity'],
                ])) {
                    throw new \RuntimeException('Unable to restore product stock for the cancelled order.');
                }
            }

            if (!$orderModel->update((int) $id, ['status' => 'cancelled'])) {
                throw new \RuntimeException('Unable to cancel the order right now.');
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('The order could not be cancelled.');
            }

            $db->transCommit();

            return redirect()->to('user/orders')->with('success', 'Your order has been cancelled.');
        } catch (\Throwable $exception) {
            $db->transRollback();

            return redirect()->to('user/orders')->with('error', $exception->getMessage());
        }
    }

    private function ensureLoggedIn()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('buyer/login')->with('error', 'Please login first');
        }

        if ((string) session()->get('role') !== 'user') {
            return match ((string) session()->get('role')) {
                'admin' => redirect()->to('admin/dashboard')->with('error', 'Buyer access only.'),
                'staff' => redirect()->to('staff/dashboard')->with('error', 'Buyer access only.'),
                default => redirect()->to('buyer/login')->with('error', 'Please login as a buyer first'),
            };
        }

        return null;
    }

    private function ensureCartStorageReady()
    {
        if ($this->isTableAvailable('cart_items')) {
            return null;
        }

        return redirect()->back()->with(
            'error',
            'Cart storage is not set up yet. Run the latest database migrations and try again.'
        );
    }

    private function isTableAvailable(string $table): bool
    {
        try {
            return \Config\Database::connect()->tableExists($table);
        } catch (DatabaseException) {
            return false;
        }
    }

    private function buildCartSummary(): array
    {
        if (!$this->isTableAvailable('cart_items')) {
            return [
                'items' => [],
                'itemCount' => 0,
                'productCount' => 0,
                'subtotal' => 0.0,
            ];
        }

        $cartModel = new CartItemModel();
        try {
            $cartRows = $cartModel
                ->where('user_id', session()->get('id'))
                ->findAll();
        } catch (DatabaseException) {
            return [
                'items' => [],
                'itemCount' => 0,
                'productCount' => 0,
                'subtotal' => 0.0,
            ];
        }

        if (empty($cartRows)) {
            return [
                'items' => [],
                'itemCount' => 0,
                'productCount' => 0,
                'subtotal' => 0.0,
            ];
        }

        $productIds = array_values(array_filter(array_map(static fn ($row) => (int) $row['product_id'], $cartRows)));
        $productModel = new ProductModel();
        $products = $productModel->getProductsByIdsWithCategory($productIds);
        $productsById = [];

        foreach ($products as $product) {
            $productsById[(int) $product['id']] = $product;
        }

        $items = [];
        $itemCount = 0;
        $subtotal = 0.0;

        foreach ($cartRows as $cartRow) {
            $productId = (int) $cartRow['product_id'];
            $quantity = max(1, (int) $cartRow['quantity']);

            if (!isset($productsById[$productId])) {
                $cartModel->delete($cartRow['id']);
                continue;
            }

            $product = $productsById[$productId];
            $availableStock = max(0, (int) ($product['stock_quantity'] ?? 0));

            if ($availableStock < 1) {
                $cartModel->delete($cartRow['id']);
                continue;
            }

            $normalizedQuantity = min($quantity, $availableStock);
            if ($normalizedQuantity !== $quantity) {
                $cartModel->update($cartRow['id'], ['quantity' => $normalizedQuantity]);
            }

            $lineTotal = $normalizedQuantity * (float) $product['price'];
            $subtotal += $lineTotal;
            $itemCount += $normalizedQuantity;

            $items[] = array_merge($product, [
                'cart_item_id' => (int) $cartRow['id'],
                'quantity' => $normalizedQuantity,
                'line_total' => $lineTotal,
            ]);
        }

        return [
            'items' => $items,
            'itemCount' => $itemCount,
            'productCount' => count($items),
            'subtotal' => $subtotal,
        ];
    }
}



