<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, onMounted, nextTick } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { QuillEditor } from '@vueup/vue-quill';
import '@vueup/vue-quill/dist/vue-quill.snow.css';

const props = defineProps({ articles: Object });
const page = usePage();

// ─── Quill toolbar config ─────────────────────────────────────────────────────
const quillToolbar = [
    [{ header: [1, 2, 3, false] }],
    ['bold', 'italic', 'underline', 'strike'],
    [{ color: [] }, { background: [] }],
    [{ list: 'ordered' }, { list: 'bullet' }],
    [{ align: [] }],
    ['blockquote', 'code-block'],
    ['link', 'image'],
    ['clean'],
];

// ─── Editor state ─────────────────────────────────────────────────────────────
const quillRef     = ref(null);
const isModalOpen  = ref(false);
const editingArticle = ref(null);
const coverInput   = ref(null);
const coverPreview = ref(null);

const form = useForm({
    title:        '',
    slug:         '',
    content:      '',
    cover_image:  null,
    is_published: false,
    published_at: '',
    sort_order:   0,
});

// ─── Image upload handler (called by Quill's image button) ───────────────────
const imageUploadHandler = () => {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.click();

    input.onchange = async () => {
        const file = input.files[0];
        if (!file) return;

        const fd = new FormData();
        fd.append('image', file);

        try {
            // window.axios has XSRF-TOKEN cookie handled automatically by Laravel's bootstrap
            const { data } = await window.axios.post(
                route('articles.upload-image'),
                fd,
                { headers: { 'Content-Type': 'multipart/form-data' } }
            );

            const editor = quillRef.value?.getQuill();
            if (editor && data.url) {
                const range = editor.getSelection(true);
                editor.insertEmbed(range.index, 'image', data.url, 'user');
                editor.setSelection(range.index + 1, 0, 'silent');
            }
        } catch (e) {
            console.error('Image upload failed', e);
            alert('Image upload failed. Please try again.');
        }
    };
};

// ─── Quill ready: swap the image handler once the editor mounts ───────────────
const onEditorReady = (quill) => {
    quill.getModule('toolbar').addHandler('image', imageUploadHandler);
};

// ─── Cover image ──────────────────────────────────────────────────────────────
const onFileChange = (e) => {
    const file = e.target.files[0];
    form.cover_image = file;
    if (file) {
        const reader = new FileReader();
        reader.onload = (ev) => { coverPreview.value = ev.target.result; };
        reader.readAsDataURL(file);
    } else {
        coverPreview.value = null;
    }
};

// ─── Open / Close modal ───────────────────────────────────────────────────────
const openModal = async (article = null) => {
    editingArticle.value = article;
    coverPreview.value   = null;

    if (article) {
        form.title        = article.title        || '';
        form.slug         = article.slug         || '';
        form.content      = article.content      || '';
        form.cover_image  = null;
        form.is_published = Boolean(article.is_published);
        form.published_at = article.published_at ? article.published_at.substring(0, 16) : '';
        form.sort_order   = article.sort_order   || 0;
    } else {
        form.reset();
    }

    isModalOpen.value = true;

    // Wait for modal DOM, then set Quill content
    await nextTick();
    quillRef.value?.setHTML(article?.content || '');
};

const closeModal = () => {
    isModalOpen.value    = false;
    editingArticle.value = null;
    coverPreview.value   = null;
    form.reset();
    if (coverInput.value) coverInput.value.value = null;
};

// ─── Submit ───────────────────────────────────────────────────────────────────
const submit = () => {
    // Grab the latest HTML from Quill before submitting
    form.content = quillRef.value?.getHTML() || '';

    const payload = {
        title:        form.title,
        slug:         form.slug,
        content:      form.content,
        cover_image:  form.cover_image,
        is_published: form.is_published ? 1 : 0,
        published_at: form.published_at || null,
        sort_order:   form.sort_order,
    };

    if (editingArticle.value) {
        router.post(route('articles.update', editingArticle.value.id), { _method: 'put', ...payload }, {
            onSuccess: () => closeModal(),
        });
    } else {
        router.post(route('articles.store'), payload, {
            onSuccess: () => closeModal(),
        });
    }
};

// ─── Delete ───────────────────────────────────────────────────────────────────
const deleteArticle = (article) => {
    if (confirm(`Delete article "${article.title}"?`)) {
        useForm({}).delete(route('articles.destroy', article.id));
    }
};

const formatDate = (iso) => {
    if (!iso) return '-';
    return new Date(iso).toLocaleDateString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric',
    });
};
</script>

<template>
    <Head title="Articles" />

    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Articles</h2>
        </template>

        <!-- Page header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Articles</h1>
                <p class="text-sm text-gray-500 mt-1">Manage blog posts and editorial content</p>
            </div>
            <PrimaryButton @click="openModal()" class="bg-gold-500 hover:bg-gold-600 border-none text-dark-900 font-bold">
                + New Article
            </PrimaryButton>
        </div>

        <!-- Table -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cover</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title / Slug</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Published At</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="article in articles.data" :key="article.id" class="hover:bg-gray-50 transition-colors">

                        <!-- Cover -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <img
                                v-if="article.cover_image"
                                :src="$page.props.storage_url + '/' + article.cover_image"
                                class="h-14 w-24 object-cover rounded border border-gray-200"
                            />
                            <div v-else class="h-14 w-24 bg-gray-100 rounded border border-gray-200 flex items-center justify-center">
                                <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                        </td>

                        <!-- Title / Slug -->
                        <td class="px-6 py-4">
                            <div class="text-sm font-semibold text-gray-900 max-w-xs truncate">{{ article.title }}</div>
                            <div class="text-xs text-gray-400 mt-0.5 font-mono">/{{ article.slug }}</div>
                        </td>

                        <!-- Status -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span
                                :class="article.is_published
                                    ? 'bg-green-100 text-green-800'
                                    : 'bg-yellow-100 text-yellow-800'"
                                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full uppercase"
                            >
                                {{ article.is_published ? 'Published' : 'Draft' }}
                            </span>
                        </td>

                        <!-- Published At -->
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ formatDate(article.published_at) }}
                        </td>

                        <!-- Actions -->
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-3">
                            <button @click="openModal(article)" class="text-gold-600 hover:text-gold-800">Edit</button>
                            <button @click="deleteArticle(article)" class="text-red-600 hover:text-red-800">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="articles.data.length === 0">
                        <td colspan="5" class="px-6 py-12 text-center text-gray-400">
                            <svg class="w-10 h-10 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 12h6m-6-4h6"/>
                            </svg>
                            No articles yet. Click <strong>+ New Article</strong> to get started.
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                <div class="hidden sm:flex sm:items-center sm:justify-between">
                    <p class="text-sm text-gray-700">
                        Showing
                        <span class="font-medium">{{ articles.from ?? 0 }}</span>
                        to
                        <span class="font-medium">{{ articles.to ?? 0 }}</span>
                        of
                        <span class="font-medium">{{ articles.total }}</span>
                        results
                    </p>
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                        <Link
                            v-for="(link, k) in articles.links"
                            :key="k"
                            :href="link.url || '#'"
                            v-html="link.label"
                            :class="{
                                'z-10 bg-gold-50 border-gold-500 text-gold-600': link.active,
                                'bg-white border-gray-300 text-gray-500 hover:bg-gray-50': !link.active,
                                'cursor-not-allowed': !link.url,
                            }"
                            class="relative inline-flex items-center px-4 py-2 border text-sm font-medium"
                        />
                    </nav>
                </div>
            </div>
        </div>

        <!-- ─── Create / Edit Modal ──────────────────────────────────────── -->
        <Modal :show="isModalOpen" @close="closeModal" max-width="2xl" :sidebar-offset="true">
            <div class="p-6 bg-white text-gray-900">
                <h2 class="text-lg font-semibold text-gray-900 mb-5">
                    {{ editingArticle ? 'Edit Article' : 'New Article' }}
                </h2>

                <form @submit.prevent="submit" class="space-y-4" enctype="multipart/form-data">

                    <!-- Title -->
                    <div>
                        <InputLabel for="title" value="Title *" class="text-gray-700" />
                        <TextInput
                            id="title"
                            type="text"
                            class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 focus:border-gold-500 focus:ring-gold-500"
                            v-model="form.title"
                            autofocus
                            required
                        />
                        <InputError class="mt-1" :message="form.errors.title" />
                    </div>

                    <!-- Slug -->
                    <div>
                        <InputLabel for="slug" value="Slug (auto-generated if empty)" class="text-gray-700" />
                        <TextInput
                            id="slug"
                            type="text"
                            class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 focus:border-gold-500 focus:ring-gold-500 font-mono text-sm"
                            v-model="form.slug"
                            placeholder="e.g. top-5-luxury-watches"
                        />
                        <InputError class="mt-1" :message="form.errors.slug" />
                    </div>

                    <!-- Rich-text Content (Quill) -->
                    <div>
                        <InputLabel value="Content *" class="text-gray-700 mb-1" />
                        <div class="quill-wrapper border border-gray-300 rounded-md overflow-hidden focus-within:ring-1 focus-within:ring-gold-500 focus-within:border-gold-500">
                            <QuillEditor
                                ref="quillRef"
                                theme="snow"
                                :toolbar="quillToolbar"
                                content-type="html"
                                placeholder="Write your article here…"
                                style="min-height: 320px; font-size: 14px;"
                                @ready="onEditorReady"
                            />
                        </div>
                        <p class="text-xs text-gray-400 mt-1">
                            Click the 📷 image icon in the toolbar to upload and embed an image directly into the content.
                        </p>
                        <InputError class="mt-1" :message="form.errors.content" />
                    </div>

                    <!-- Cover Image -->
                    <div>
                        <InputLabel for="cover_image" value="Cover Image" class="text-gray-700" />
                        <div v-if="coverPreview" class="mt-2 mb-3">
                            <img :src="coverPreview" class="h-32 w-full object-cover rounded border border-gray-300" />
                            <p class="text-xs text-green-600 mt-1">New image selected</p>
                        </div>
                        <div v-else-if="editingArticle && editingArticle.cover_image" class="mt-2 mb-3">
                            <img
                                :src="$page.props.storage_url + '/' + editingArticle.cover_image"
                                class="h-32 w-full object-cover rounded border border-gray-300"
                            />
                            <p class="text-xs text-gray-500 mt-1">Current cover image</p>
                        </div>
                        <input
                            type="file"
                            accept="image/*"
                            @change="onFileChange"
                            ref="coverInput"
                            class="mt-1 block w-full text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-gold-500 file:text-dark-900 hover:file:bg-gold-600"
                        />
                        <InputError class="mt-1" :message="form.errors.cover_image" />
                    </div>

                    <!-- Publish options -->
                    <div class="flex space-x-4 items-end">
                        <div class="flex-1">
                            <InputLabel for="published_at" value="Publish Date (leave empty for now)" class="text-gray-700" />
                            <TextInput
                                id="published_at"
                                type="datetime-local"
                                class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 focus:border-gold-500 focus:ring-gold-500"
                                v-model="form.published_at"
                            />
                            <InputError class="mt-1" :message="form.errors.published_at" />
                        </div>
                        <div class="flex-1">
                            <InputLabel for="sort_order" value="Sort Order" class="text-gray-700" />
                            <TextInput
                                id="sort_order"
                                type="number"
                                class="mt-1 block w-full bg-gray-50 border-gray-300 text-gray-900 focus:border-gold-500 focus:ring-gold-500"
                                v-model="form.sort_order"
                            />
                        </div>
                        <div class="pb-1">
                            <label class="flex items-center cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="form.is_published"
                                    class="rounded border-gray-300 text-gold-600 shadow-sm focus:border-gold-300 focus:ring focus:ring-gold-200 focus:ring-opacity-50"
                                />
                                <span class="ml-2 text-gray-700 font-medium">Publish</span>
                            </label>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="mt-6 flex justify-end space-x-3">
                        <SecondaryButton @click="closeModal" class="bg-white text-gray-700 border-gray-300 hover:bg-gray-50">
                            Cancel
                        </SecondaryButton>
                        <PrimaryButton
                            class="bg-gold-500 hover:bg-gold-600 border-none text-dark-900 font-bold"
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing"
                        >
                            {{ editingArticle ? 'Update Article' : 'Save Article' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AdminLayout>
</template>

<style>
/* Override Quill snow theme borders to match the admin design */
.quill-wrapper .ql-toolbar.ql-snow {
    border: none;
    border-bottom: 1px solid #e5e7eb;
    background: #f9fafb;
    padding: 8px 12px;
}
.quill-wrapper .ql-container.ql-snow {
    border: none;
    font-family: inherit;
}
.quill-wrapper .ql-editor {
    min-height: 320px;
    padding: 14px 16px;
    color: #111827;
    font-size: 14px;
    line-height: 1.7;
}
.quill-wrapper .ql-editor img {
    max-width: 100%;
    height: auto;
    border-radius: 6px;
    margin: 8px 0;
}
.quill-wrapper .ql-editor.ql-blank::before {
    color: #9ca3af;
    font-style: normal;
}
/* Gold accent on active toolbar buttons */
.quill-wrapper .ql-snow .ql-toolbar button.ql-active .ql-stroke,
.quill-wrapper .ql-snow .ql-toolbar button:hover .ql-stroke {
    stroke: #b7903a;
}
.quill-wrapper .ql-snow .ql-toolbar button.ql-active .ql-fill,
.quill-wrapper .ql-snow .ql-toolbar button:hover .ql-fill {
    fill: #b7903a;
}
.quill-wrapper .ql-snow .ql-toolbar .ql-picker-label.ql-active,
.quill-wrapper .ql-snow .ql-toolbar .ql-picker-label:hover {
    color: #b7903a;
}
</style>
