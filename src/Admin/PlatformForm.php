<?php

declare(strict_types=1);

namespace App\Admin;

use App\Repository\PlatformRepository;
use App\Validation\ValidationException;
use App\Validation\Validator;

final class PlatformForm
{
    public function __construct(private PlatformRepository $platforms)
    {
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     * @throws ValidationException
     */
    public function validate(array $input, bool $creating): array
    {
        $value = static fn (string $key): string => is_string($input[$key] ?? null) ? trim($input[$key]) : '';
        $errors = [];
        $data = [
            'name' => Validator::cleanText($value('name'), 64) ?? '',
            'share_url_template' => $value('share_url_template'),
            'icon' => strtolower($value('icon')),
            'color' => strtoupper($value('color')),
            'sort_order' => $value('sort_order'),
            'is_active' => isset($input['is_active']) ? 1 : 0,
        ];

        if ($creating) {
            $data['slug'] = strtolower($value('slug'));
            if (!Validator::slug($data['slug'])) {
                $errors['slug'] = 'Use 1–32 lowercase letters, numbers, dashes or underscores (e.g. "linkedin").';
            } elseif ($this->platforms->slugExists($data['slug'])) {
                $errors['slug'] = 'Another platform already uses this slug.';
            }
        }

        if ($data['name'] === '') {
            $errors['name'] = 'Name is required.';
        }

        if (($error = Validator::shareTemplateError($data['share_url_template'])) !== null) {
            $errors['share_url_template'] = $error;
        }

        if ($data['icon'] === '') {
            $data['icon'] = 'bi-share-fill';
        } elseif (!Validator::iconClass($data['icon'])) {
            $errors['icon'] = 'Use a Bootstrap Icons class such as "bi-linkedin".';
        }

        if (!Validator::hexColor($data['color'])) {
            $errors['color'] = 'Use a 6-digit hex colour such as #0A66C2.';
        }

        if ($data['sort_order'] === '') {
            $data['sort_order'] = 0;
        } elseif (!ctype_digit($data['sort_order']) || (int) $data['sort_order'] > 9999) {
            $errors['sort_order'] = 'Sort order must be a whole number from 0 to 9999.';
        } else {
            $data['sort_order'] = (int) $data['sort_order'];
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $data;
    }
}
