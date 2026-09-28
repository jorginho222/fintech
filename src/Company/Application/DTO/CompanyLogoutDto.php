<?php

declare(strict_types=1);

namespace App\Company\Application\DTO;

use Symfony\Component\HttpFoundation\Request;

final class CompanyLogoutDto
{
    public readonly ?string $refreshToken;

    public function __construct(Request $request)
    {
        $content = $request->getContent();
        $data    = $content === '' ? [] : json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        $refreshToken       = $data['refreshToken'] ?? null;
        $this->refreshToken = is_string($refreshToken) && $refreshToken !== '' ? $refreshToken : null;
    }
}
