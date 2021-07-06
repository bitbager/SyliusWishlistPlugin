<?php

declare(strict_types=1);

namespace Tests\BitBag\SyliusWishlistPlugin\Behat\Context\Api;

use Behat\Behat\Context\Context;
use BitBag\SyliusWishlistPlugin\Entity\WishlistInterface;
use BitBag\SyliusWishlistPlugin\Repository\WishlistRepositoryInterface;
use GuzzleHttp\ClientInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Webmozart\Assert\Assert;

final class WishlistContext implements Context
{
    private WishlistRepositoryInterface $wishlistRepository;

    private UserRepositoryInterface $userRepository;

    private ClientInterface $client;

    private WishlistInterface $wishlist;

    private ?ShopUserInterface $user;

    private ?string $token;

    private const PATCH = 'PATCH';

    private const POST = 'POST';

    public function __construct(
        WishlistRepositoryInterface $wishlistRepository,
        UserRepositoryInterface $userRepository,
        ClientInterface $client
    )
    {
        $this->client = $client;
        $this->wishlistRepository = $wishlistRepository;
        $this->userRepository = $userRepository;
    }

    private function getOptions(string $method, $body = null): array
    {
        if ($method === self::PATCH) {
            $contentType = 'application/merge-patch+json';
        } else {
            $contentType = 'application/ld+json';
        }

        $options = [
            'headers' => [
                'Accept' => 'application/ld+json',
                'Content-Type' => $contentType
            ],
        ];

        if(isset($body)) {
            $options['body'] = json_encode($body);
        }

        if (isset($this->token)) {
            $options['headers']['Authorization'] = 'Bearer ' . $this->token;
        }

        return $options;
    }

    /** @Given user :email :password is authenticated */
    public function userIsAuthenticated(string $email, string $password)
    {
        $uri = 'nginx:80/api/v2/shop/authentication-token';

        $body = [
            'email' => $email,
            'password' => $password
        ];

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ];

        $response = $this->client->request(
            self::POST,
            $uri,
            [
                'headers' => $headers,
                'body' => json_encode($body)
            ]
        );

        Assert::eq($response->getStatusCode(), 200);

        $json = json_decode((string)$response->getBody());

        $this->user = $this->userRepository->findOneByEmail($email);
        $this->token = (string)$json->token;
    }

    /** @Given user has a wishlist */
    public function userHasAWishlist(): void
    {
        $uri = 'nginx:80/api/v2/shop/wishlists';

        $response = $this->client->request(
            self::POST,
            $uri,
            $this->getOptions(self::POST, [])
        );

        $jsonBody = json_decode((string)$response->getBody());

        /** @var WishlistInterface $wishlist */
        $wishlist = $this->wishlistRepository->find((int)$jsonBody->id);
        $this->wishlist = $wishlist;
    }

    /** @When user adds product :product to the wishlist */
    public function userAddsProductToTheWishlist(ProductInterface $product)
    {
        $uri = sprintf('nginx:80/api/v2/shop/wishlists/%s/product', $this->wishlist->getToken());

        $body = [
            'productId' => $product->getId()
        ];

        $response = $this->client->request(self::PATCH,
            $uri,
            $this->getOptions(self::PATCH, $body)
        );

        Assert::eq($response->getStatusCode(), 200);
    }

    /** @Then user should have product :product in the wishlist */
    public function userHasProductInTheWishlist(ProductInterface $product)
    {
        /** @var WishlistInterface $wishlist */

        if (isset($this->user)) {
            $wishlist = $this->wishlistRepository->findByShopUser($this->user);
        } else {
            $wishlist = $this->wishlistRepository->find($this->wishlist->getId());
        }

        foreach ($wishlist->getProducts() as $wishlistProduct) {
            if ($product->getId() === $wishlistProduct->getId()) {
                return true;
            }
        }

        throw new \Exception(
            sprintf('Product %s was not found in the wishlist',
                $product->getName()
            )
        );
    }

    /** @When user adds :variant product variant to the wishlist */
    public function userAddsProductVariantToWishlist(ProductVariantInterface $variant)
    {
        $uri = sprintf('nginx:80/api/v2/shop/wishlists/%s/variant', $this->wishlist->getToken());

        $body = [
            'productVariantId' => $variant->getId()
        ];

        $response = $this->client->request(self::PATCH,
            $uri,
            $this->getOptions(self::PATCH, $body)
        );


        Assert::eq($response->getStatusCode(), 200);
    }

    public function userHasProductVariantInTheWishlist(ProductVariantInterface $variant)
    {
        /** @var WishlistInterface $wishlist */
        $wishlist = $this->wishlistRepository->find($this->wishlist->getId());

        foreach ($wishlist->getWishlistProducts() as $wishlistProduct) {
            if ($variant->getId() === $wishlistProduct->getVariant()->getId()) {
                return true;
            }
        }

        throw new \Exception(
            sprintf('Product variant %s was not found in the wishlist',
                $variant->getName()
            )
        );
    }
}
