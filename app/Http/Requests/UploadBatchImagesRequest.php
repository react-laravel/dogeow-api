<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class UploadBatchImagesRequest extends FormRequest
{
    private const ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif'];

    private const ALLOWED_IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/heic',
        'image/heif',
        'image/heic-sequence',
        'image/heif-sequence',
    ];

    private const HEIC_EXTENSIONS = ['heic', 'heif'];

    private const HEIC_FALLBACK_MIME_TYPES = ['application/octet-stream', 'application/x-heic'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     * 每张图片最大 20MB。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'images.*' => 'required|file|max:20480',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $images = $this->file('images', []);
            if ($images instanceof UploadedFile) {
                $images = [$images];
            }

            foreach ((array) $images as $index => $image) {
                if (! $image) {
                    continue;
                }

                $extension = strtolower($image->getClientOriginalExtension());
                $mimeType = strtolower((string) $image->getMimeType());

                $isAllowedImage = in_array($extension, self::ALLOWED_IMAGE_EXTENSIONS, true)
                    && in_array($mimeType, self::ALLOWED_IMAGE_MIME_TYPES, true);
                $isHeicFallback = in_array($extension, self::HEIC_EXTENSIONS, true)
                    && in_array($mimeType, self::HEIC_FALLBACK_MIME_TYPES, true);

                if (! $isAllowedImage && ! $isHeicFallback) {
                    $validator->errors()->add("images.{$index}", '上传文件必须是图片。');
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'images.*.required' => '请选择要上传的图片。',
            'images.*.file' => '上传文件必须是图片。',
            'images.*.max' => '单张图片不能超过 20MB。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'images.*' => '图片',
        ];
    }
}
