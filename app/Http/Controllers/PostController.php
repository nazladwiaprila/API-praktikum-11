<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PostController extends Controller
{
    public function index()
    {
        $posts = Post::latest()->get();

        return response()->json($posts);
    }

    public function show($id)
    {
        $post = $this->findPost($id);

        return response()->json($post);
    }

    public function store(StorePostRequest $request)
    {
        $data = $request->validated();

        $file = $data['file'] ?? null;
        unset($data['file']);

        $post = $request->user()->posts()->create($data);

        if ($file) {
            $post->update($this->storeFile($file));
        }

        return response()->json($post, 201);
    }

    public function update(UpdatePostRequest $request, $id)
    {
        $post = $this->findOwnedPost($request, $id);

        $data = $request->validated();

        $file = $data['file'] ?? null;
        $removeFile = $data['remove_file'] ?? false;

        unset($data['file'], $data['remove_file']);

        if ($file) {
            $this->deleteFile($post);
            $data = array_merge($data, $this->storeFile($file));
        } elseif ($removeFile) {
            $this->deleteFile($post);

            $data['file_path'] = null;
            $data['file_name'] = null;
        }

        $post->update($data);

        return response()->json($post);
    }

    public function destroy(Request $request, $id)
    {
        $post = $this->findOwnedPost($request, $id);

        $this->deleteFile($post);
        $post->delete();

        return response()->json([
            'message' => 'Post berhasil dihapus'
        ]);
    }

    public function file(Request $request, $id): StreamedResponse
    {
        $post = $this->findOwnedPost($request, $id);

        abort_unless(
            $post->file_path &&
            Storage::disk('local')->exists($post->file_path),
            404,
            'File tidak ditemukan'
        );

        return Storage::disk('local')->download(
            $post->file_path,
            $post->file_name
        );
    }

    private function storeFile(UploadedFile $file): array
    {
        $path = $file->store('posts', 'local');

        return [
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
        ];
    }

    private function deleteFile(Post $post): void
    {
        if ($post->file_path) {
            Storage::disk('local')->delete($post->file_path);
        }
    }

    private function findPost($id)
    {
        return Post::findOrFail($id);
    }

    private function findOwnedPost(Request $request, $id)
    {
        $post = $this->findPost($id);

        if ($post->user_id != $request->user()->id) {
            abort(403, 'Anda tidak memiliki izin untuk mengubah post ini');
        }

        return $post;
    }
}
