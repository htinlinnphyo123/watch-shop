<?php

namespace App\Http\Controllers;

use App\Models\PreOrder;
use App\Models\PreOrderAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PreOrderAttachmentController extends Controller
{
    private const TYPES = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'heic' => 'image/heic', 'heif' => 'image/heif',
        'pdf' => 'application/pdf', 'txt' => 'text/plain', 'csv' => 'text/csv',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'zip' => 'application/zip',
    ];

    public function presign(Request $request, PreOrder $preOrder)
    {
        $data = $request->validate([
            'name' => 'required|string|max:200',
            'size' => 'required|integer|min:1|max:20971520',
        ]);
        $extension = strtolower(pathinfo($data['name'], PATHINFO_EXTENSION));
        if (! isset(self::TYPES[$extension])) {
            throw ValidationException::withMessages(['name' => 'Choose an image, PDF, Word, Excel, CSV, text, or ZIP file.']);
        }
        abort_unless(config('filesystems.disks.order_attachments.bucket'), 503, 'Attachment storage is not configured.');

        return DB::transaction(function () use ($request, $preOrder, $data, $extension) {
            PreOrder::whereKey($preOrder->id)->lockForUpdate()->firstOrFail();
            $count = PreOrderAttachment::where('pre_order_id', $preOrder->id)
                ->where(fn ($query) => $query->whereNotNull('uploaded_at')->orWhere('expires_at', '>', now()))->count();
            if ($count >= 20) {
                throw ValidationException::withMessages(['files' => 'A pre-order can have up to 20 attachments.']);
            }
            $id = (string) Str::uuid();
            $path = "pre-order-attachments/{$preOrder->id}/{$id}.{$extension}";
            $expires = now()->addMinutes(10);
            $mime = self::TYPES[$extension];
            $upload = Storage::disk('order_attachments')->temporaryUploadUrl($path, $expires, [
                'ContentType' => $mime,
                'ContentLength' => $data['size'],
            ]);
            PreOrderAttachment::create([
                'id' => $id, 'pre_order_id' => $preOrder->id, 'user_id' => $request->user()->id,
                'name' => basename(str_replace('\\', '/', $data['name'])),
                'path' => $path, 'mime_type' => $mime, 'size' => $data['size'], 'expires_at' => $expires,
            ]);

            return response()->json([
                'id' => $id, 'url' => $upload['url'], 'headers' => $upload['headers'], 'content_type' => $mime, 'expires_at' => $expires->toIso8601String(),
            ]);
        });
    }

    public function complete(Request $request, PreOrder $preOrder, PreOrderAttachment $attachment)
    {
        return DB::transaction(function () use ($request, $preOrder, $attachment) {
            PreOrder::whereKey($preOrder->id)->lockForUpdate()->firstOrFail();
            $attachment = PreOrderAttachment::whereKey($attachment->id)->lockForUpdate()->firstOrFail();
            abort_unless($attachment->pre_order_id === $preOrder->id, 404);
            abort_unless($attachment->user_id === $request->user()->id, 403);
            if ($attachment->uploaded_at) {
                return response()->json($attachment);
            }
            abort_if($attachment->expires_at->isPast(), 422, 'This upload expired. Please select the file again.');
            $disk = Storage::disk('order_attachments');
            if (! $disk->exists($attachment->path) || $disk->size($attachment->path) !== $attachment->size) {
                throw ValidationException::withMessages(['file' => 'The upload is missing or incomplete. Please retry.']);
            }
            $attachment->update(['uploaded_at' => now()]);

            return response()->json($attachment);
        });
    }

    public function download(Request $request, PreOrder $preOrder, PreOrderAttachment $attachment)
    {
        abort_unless($attachment->pre_order_id === $preOrder->id && $attachment->uploaded_at, 404);
        $preview = $request->boolean('preview') && in_array($attachment->mime_type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true);
        $disposition = $preview ? 'inline' : 'attachment';
        $url = Storage::disk('order_attachments')->temporaryUrl($attachment->path, now()->addMinutes(5), [
            'ResponseContentType' => $preview ? $attachment->mime_type : 'application/octet-stream',
            'ResponseContentDisposition' => $disposition."; filename=\"attachment\"; filename*=UTF-8''".rawurlencode($attachment->name),
        ]);

        return redirect()->away($url)->header('Cache-Control', 'private, no-store');
    }
}
