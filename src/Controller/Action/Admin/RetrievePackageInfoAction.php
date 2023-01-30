<?php

declare(strict_types=1);

namespace BitBag\SyliusWishlistPlugin\Controller\Action\Admin;

use Symfony\Component\HttpFoundation\Response;

final class RetrievePackageInfoAction
{
    public function __invoke(): Response
    {
        try {
            file_get_contents('https://intranet.bitbag.shop/retrieve-package-info?packageName="bitbag/wishlist-plugin"');
        } catch (\Exception $exception) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        return new Response();
    }
}
