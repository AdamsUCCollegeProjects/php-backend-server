<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\AdminCategoryController;
use App\Controllers\AdminDashboardController;
use App\Controllers\AdminOrderController;
use App\Controllers\AdminProductController;
use App\Controllers\AuthController;
use App\Controllers\CartController;
use App\Controllers\CategoryController;
use App\Controllers\CheckoutController;
use App\Controllers\FileController;
use App\Controllers\HealthController;
use App\Controllers\OrderController;
use App\Controllers\PayWayWebhookController;
use App\Controllers\ProductController;
use App\Controllers\ProfileController;
use App\Middleware\AdminMiddleware;
use App\Middleware\AuthMiddleware;
use App\Repositories\AdminStatsRepository;
use App\Repositories\CartRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\FileRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use App\Repositories\UserRepository;
use App\Services\AdminDashboardService;
use App\Services\AuthService;
use App\Services\CartService;
use App\Services\CategoryService;
use App\Services\FileService;
use App\Services\ImageProcessor;
use App\Services\JwtService;
use App\Services\HttpPayWayService;
use App\Services\MockPayWayService;
use App\Services\OrderService;
use App\Services\PayWayHashGenerator;
use App\Services\PayWayServiceInterface;
use App\Services\ProductService;
use App\Services\UserService;
use App\Storage\Providers\LocalStorageProvider;

final class RouteDependencies
{
    public function __construct(
        public readonly HealthController $healthController,
        public readonly AuthController $authController,
        public readonly ProfileController $profileController,
        public readonly CategoryController $categoryController,
        public readonly AdminCategoryController $adminCategoryController,
        public readonly ProductController $productController,
        public readonly AdminProductController $adminProductController,
        public readonly CartController $cartController,
        public readonly CheckoutController $checkoutController,
        public readonly PayWayWebhookController $payWayWebhookController,
        public readonly OrderController $orderController,
        public readonly AdminDashboardController $adminDashboardController,
        public readonly AdminOrderController $adminOrderController,
        public readonly FileController $fileController,
        public readonly AuthMiddleware $authMiddleware,
        public readonly AdminMiddleware $adminMiddleware,
    ) {
    }

    public static function create(): self
    {
        $pdo = Database::getConnection();
        $userRepository = new UserRepository($pdo);
        $categoryRepository = new CategoryRepository($pdo);
        $productRepository = new ProductRepository($pdo);
        $cartRepository = new CartRepository($pdo);
        $orderRepository = new OrderRepository($pdo, $productRepository, $cartRepository);
        $adminStatsRepository = new AdminStatsRepository($pdo);
        $fileRepository = new FileRepository($pdo);
        $validator = new Validator();
        $logger = Logger::getInstance();

        /** @var array{
         *     path: string,
         *     max_upload_bytes: int,
         *     allowed_mime_types: list<string>,
         *     image_max_dimension: int,
         *     image_thumbnail_dimension: int,
         *     image_webp_quality: int,
         * } $storageConfig */
        $storageConfig = require dirname(__DIR__) . '/config/storage.php';

        $storageProvider = new LocalStorageProvider($storageConfig['path']);

        /** @var array{secret: string, ttl_seconds: int, algorithm: string} $jwtConfig */
        $jwtConfig = require dirname(__DIR__) . '/config/jwt.php';

        $jwtService = new JwtService(
            $jwtConfig['secret'],
            $jwtConfig['ttl_seconds'],
            $jwtConfig['algorithm'],
        );

        $authService = new AuthService($userRepository, $jwtService, $validator, $logger);
        $userService = new UserService($userRepository);
        $categoryService = new CategoryService($categoryRepository, $validator);
        $productService = new ProductService($productRepository, $categoryRepository, $fileRepository, $validator);
        $cartService = new CartService($cartRepository, $productRepository, $validator);

        /** @var array{
         *     mock: bool,
         *     merchant_id: string,
         *     public_key: string,
         *     checkout_url: string,
         *     success_url_template: string,
         *     webhook_url: string,
         *     payment_option: string,
         * } $paywayConfig */
        $paywayConfig = require dirname(__DIR__) . '/config/payway.php';

        $payWayService = self::createPayWayService($paywayConfig);
        $orderService = new OrderService(
            $orderRepository,
            $cartRepository,
            $userRepository,
            $payWayService,
            $validator,
        );
        $adminDashboardService = new AdminDashboardService($adminStatsRepository, $orderService);
        $imageProcessor = new ImageProcessor($storageConfig);
        $fileService = new FileService(
            $fileRepository,
            $storageProvider,
            $imageProcessor,
            $storageConfig,
            $logger,
        );

        return new self(
            new HealthController(),
            new AuthController($authService),
            new ProfileController($userService),
            new CategoryController($categoryService),
            new AdminCategoryController($categoryService),
            new ProductController($productService),
            new AdminProductController($productService),
            new CartController($cartService),
            new CheckoutController($orderService),
            new PayWayWebhookController($orderService),
            new OrderController($orderService),
            new AdminDashboardController($adminDashboardService),
            new AdminOrderController($orderService),
            new FileController($fileService),
            new AuthMiddleware($jwtService),
            new AdminMiddleware(),
        );
    }

    /**
     * @param array{
     *     mock: bool,
     *     merchant_id: string,
     *     public_key: string,
     *     checkout_url: string,
     *     success_url_template: string,
     *     webhook_url: string,
     *     payment_option: string,
     * } $paywayConfig
     */
    private static function createPayWayService(array $paywayConfig): PayWayServiceInterface
    {
        if ($paywayConfig['mock']) {
            return new MockPayWayService([
                'webhook_url' => $paywayConfig['webhook_url'],
                'success_url_template' => $paywayConfig['success_url_template'],
            ]);
        }

        return new HttpPayWayService($paywayConfig, new PayWayHashGenerator());
    }
}
