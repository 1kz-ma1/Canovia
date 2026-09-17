@extends('layouts.app')

@section('title', '関連資料 | Canovia')

@section('content')
    @php
        $providerIcons = [
            'google_drive' => '△',
            'onedrive' => '☁',
            'github' => '⌘',
            'device' => '↑',
        ];
        $preferred = old('provider', $preferredProvider);
    @endphp

    <section class="mb-7 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-cyan-300">PLAN RESOURCES</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-50">関連資料</h1>
            <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-400">
                {{ $plan->title }}で使う資料を一度登録し、必要なタスクへ自由に付け替えられます。
                @if ($plan->is_collaborative)<span class="block text-amber-200/80">共同計画では、登録した資料名とURLを参加メンバーも閲覧できます。</span>@endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('plans.artifacts.index', $plan) }}" class="btn-secondary">制作ファイル</a>
            <a href="{{ route('plans.show', $plan) }}" class="btn-secondary">計画へ戻る</a>
        </div>
    </section>

    @if (session('success'))
        <div class="assistant-notice assistant-notice-success mb-6">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="assistant-notice mb-6 border border-rose-400/30 bg-rose-500/10 text-rose-100">
            <ul class="list-inside list-disc space-y-1 text-sm">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if ($canEdit)
        <section class="page-card mb-7 p-5 sm:p-6" data-resource-add-shell data-server-preferred-provider="{{ $preferred }}">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-cyan-300">資料を追加</p>
                    <h2 class="mt-1 text-xl font-bold text-slate-50">どこから追加しますか？</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-400">初回はすべて表示し、次回からは前回使った追加元を優先します。</p>
                </div>
            </div>

            <form method="POST" action="{{ route('plans.resources.store', $plan) }}" class="mt-5 space-y-5" data-resource-add-form>
                @csrf
                <input type="hidden" name="provider" value="{{ old('provider') }}" data-resource-provider-input>
                <input type="hidden" name="resource_type" value="{{ old('resource_type') }}" data-resource-type-input>

                <div data-resource-provider-step>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" data-resource-provider-all>
                        @foreach ($providers as $providerKey => $providerLabel)
                            <button
                                type="button"
                                class="rounded-2xl border border-white/10 bg-white/[0.04] p-4 text-left transition hover:border-cyan-300/50 hover:bg-cyan-300/[0.06] {{ $providerKey === 'device' ? 'opacity-75' : '' }}"
                                data-resource-provider="{{ $providerKey }}"
                            >
                                <span class="text-xl" aria-hidden="true">{{ $providerIcons[$providerKey] ?? '•' }}</span>
                                <strong class="mt-3 block text-sm text-slate-50">{{ $providerLabel }}</strong>
                                <small class="mt-1 block text-xs leading-5 text-slate-500">
                                    {{ $providerKey === 'device' ? '直接アップロードはストレージ連携後' : '共有URLで登録' }}
                                </small>
                            </button>
                        @endforeach
                    </div>

                    <div class="hidden" data-resource-provider-returning>
                        <button type="button" class="w-full rounded-2xl border border-cyan-300/25 bg-cyan-300/[0.06] p-4 text-left" data-resource-preferred-button>
                            <span class="text-xs font-bold uppercase tracking-[0.14em] text-cyan-300">前回利用</span>
                            <strong class="mt-1 block text-base text-slate-50" data-resource-preferred-label></strong>
                        </button>
                        <details class="mt-3 rounded-2xl border border-white/8 bg-white/[0.025] p-3">
                            <summary class="cursor-pointer text-sm font-semibold text-slate-300">その他の追加元</summary>
                            <div class="mt-3 grid gap-2 sm:grid-cols-3" data-resource-provider-other></div>
                        </details>
                    </div>
                </div>

                <div class="hidden" data-resource-type-step>
                    <button type="button" class="mb-4 text-xs font-semibold text-slate-400 hover:text-slate-200" data-resource-back-provider>← 追加元を選び直す</button>
                    <p class="text-sm font-semibold text-slate-300"><span data-resource-selected-provider></span>から何を追加しますか？</p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <button type="button" class="rounded-2xl border border-white/10 bg-white/[0.04] p-5 text-left hover:border-cyan-300/50" data-resource-type="file">
                            <strong class="text-slate-50">ファイルを追加</strong>
                            <span class="mt-1 block text-xs text-slate-500">PDF、資料、画像などの共有URL</span>
                        </button>
                        <button type="button" class="rounded-2xl border border-white/10 bg-white/[0.04] p-5 text-left hover:border-cyan-300/50" data-resource-type="folder">
                            <strong class="text-slate-50">フォルダを追加</strong>
                            <span class="mt-1 block text-xs text-slate-500">Driveフォルダ、GitHubリポジトリ等</span>
                        </button>
                    </div>
                </div>

                <div class="hidden" data-resource-details-step>
                    <button type="button" class="mb-4 text-xs font-semibold text-slate-400 hover:text-slate-200" data-resource-back-type>← ファイル / フォルダを選び直す</button>
                    <div class="rounded-2xl border border-white/8 bg-white/[0.03] p-4">
                        <p class="text-sm font-semibold text-slate-200"><span data-resource-details-provider></span> · <span data-resource-details-type></span></p>
                        <p class="mt-1 text-xs text-slate-500">今はクラウド選択画面との直接連携をせず、共有URLを登録します。</p>
                    </div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="text-sm font-semibold text-slate-300">資料名</span>
                            <input type="text" name="title" maxlength="255" value="{{ old('title') }}" class="form-control mt-2" placeholder="例：応用情報シラバス" required>
                        </label>
                        <label class="block">
                            <span class="text-sm font-semibold text-slate-300">共有URL</span>
                            <input type="url" name="url" maxlength="2048" value="{{ old('url') }}" class="form-control mt-2" placeholder="https://..." required>
                        </label>
                    </div>

                    @if ($plan->tasks->isNotEmpty())
                        <fieldset class="mt-5">
                            <legend class="text-sm font-semibold text-slate-300">最初から紐づけるタスク（任意）</legend>
                            <p class="mt-1 text-xs text-slate-500">後からいつでも付け替えできます。</p>
                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                @foreach ($plan->tasks as $task)
                                    <label class="flex gap-3 rounded-xl border border-white/8 bg-white/[0.025] p-3 text-sm text-slate-300">
                                        <input type="checkbox" name="task_ids[]" value="{{ $task->id }}" class="mt-1" @checked(in_array($task->id, old('task_ids', [])))>
                                        <span>{{ $task->title }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endif

                    <button type="submit" class="btn-primary mt-5">関連資料として登録</button>
                </div>

                <div class="hidden rounded-2xl border border-amber-300/20 bg-amber-300/[0.06] p-4" data-resource-device-notice>
                    <p class="font-bold text-amber-100">デバイスからの直接アップロードはまだ使いません</p>
                    <p class="mt-2 text-sm leading-6 text-amber-100/75">Cloudflare R2などの保存先を決めるまでは、ファイル本体をCanoviaに保存しません。Drive / OneDrive / GitHubの共有URLで登録できます。</p>
                    <button type="button" class="btn-secondary mt-4" data-resource-back-device>追加元へ戻る</button>
                </div>
            </form>
        </section>
    @endif

    <section class="page-card p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-violet-300">RESOURCE LIBRARY</p>
                <h2 class="mt-1 text-2xl font-bold text-slate-50">計画の資料ライブラリ</h2>
                <p class="mt-2 text-sm leading-6 text-slate-400">資料本体ではなく参照先を保持するので、現段階ではストレージ容量をほとんど消費しません。</p>
            </div>
            @if ($canEdit && $plan->resources->isNotEmpty() && $plan->tasks->isNotEmpty())
                <a href="{{ route('plans.resources.assistant', $plan) }}" class="btn-secondary">✨ AIで資料を整理</a>
            @endif
        </div>

        @if ($plan->resources->isEmpty())
            <div class="empty-state mt-5">まだ関連資料はありません。</div>
        @else
            <div class="mt-5 space-y-4">
                @foreach ($plan->resources as $resource)
                    <article class="rounded-2xl border border-white/8 bg-white/[0.03] p-4 sm:p-5">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="badge badge-slate">{{ $resource->providerLabel() }}</span>
                                    <span class="badge badge-slate">{{ $resource->resourceTypeLabel() }}</span>
                                </div>
                                <h3 class="mt-3 break-words text-lg font-bold text-slate-50">{{ $resource->title }}</h3>
                                <a href="{{ $resource->url }}" target="_blank" rel="noopener noreferrer" class="mt-2 block break-all text-sm text-cyan-300 hover:text-cyan-200">{{ $resource->url }}</a>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @forelse ($resource->tasks as $task)
                                        <span class="rounded-full border border-cyan-300/15 bg-cyan-300/[0.05] px-3 py-1 text-xs text-cyan-100">{{ $task->title }}</span>
                                    @empty
                                        <span class="text-xs text-slate-500">まだタスクに紐づいていません</span>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        @if ($canEdit)
                            <details class="mt-4 rounded-xl border border-white/8 bg-black/10 p-3">
                                <summary class="cursor-pointer text-sm font-semibold text-slate-300">編集・タスクへ付け替え</summary>
                                <form method="POST" action="{{ route('plans.resources.update', [$plan, $resource]) }}" class="mt-4 space-y-4">
                                    @csrf
                                    @method('PUT')
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <label class="block">
                                            <span class="text-xs font-semibold text-slate-400">追加元</span>
                                            <select name="provider" class="form-control mt-2">
                                                @foreach ($providers as $key => $label)
                                                    @if ($key !== 'device')<option value="{{ $key }}" @selected($resource->provider === $key)>{{ $label }}</option>@endif
                                                @endforeach
                                            </select>
                                        </label>
                                        <label class="block">
                                            <span class="text-xs font-semibold text-slate-400">種類</span>
                                            <select name="resource_type" class="form-control mt-2">
                                                @foreach ($resourceTypes as $key => $label)<option value="{{ $key }}" @selected($resource->resource_type === $key)>{{ $label }}</option>@endforeach
                                            </select>
                                        </label>
                                        <label class="block">
                                            <span class="text-xs font-semibold text-slate-400">資料名</span>
                                            <input type="text" name="title" value="{{ $resource->title }}" class="form-control mt-2" required>
                                        </label>
                                        <label class="block">
                                            <span class="text-xs font-semibold text-slate-400">共有URL</span>
                                            <input type="url" name="url" value="{{ $resource->url }}" class="form-control mt-2" required>
                                        </label>
                                    </div>

                                    @if ($plan->tasks->isNotEmpty())
                                        <fieldset>
                                            <legend class="text-xs font-semibold text-slate-400">紐づけるタスク</legend>
                                            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                                @php $linkedTaskIds = $resource->tasks->pluck('id')->all(); @endphp
                                                @foreach ($plan->tasks as $task)
                                                    <label class="flex gap-3 rounded-xl border border-white/8 p-3 text-sm text-slate-300">
                                                        <input type="checkbox" name="task_ids[]" value="{{ $task->id }}" class="mt-1" @checked(in_array($task->id, $linkedTaskIds))>
                                                        <span>{{ $task->title }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </fieldset>
                                    @endif

                                    <div class="flex flex-wrap gap-2">
                                        <button type="submit" class="btn-primary">更新</button>
                                    </div>
                                </form>
                                <form method="POST" action="{{ route('plans.resources.destroy', [$plan, $resource]) }}" class="mt-3" onsubmit="return confirm('この関連資料を削除しますか？タスクとの紐づけも解除されます。');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold text-rose-300 hover:text-rose-200">この資料を削除</button>
                                </form>
                            </details>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    @if ($canEdit)
        <script>
            (() => {
                const shell = document.querySelector('[data-resource-add-shell]');
                if (!shell) return;

                const providerLabels = @json($providers);
                const typeLabels = @json($resourceTypes);
                const allBox = shell.querySelector('[data-resource-provider-all]');
                const returningBox = shell.querySelector('[data-resource-provider-returning]');
                const otherBox = shell.querySelector('[data-resource-provider-other]');
                const preferredButton = shell.querySelector('[data-resource-preferred-button]');
                const preferredLabel = shell.querySelector('[data-resource-preferred-label]');
                const providerStep = shell.querySelector('[data-resource-provider-step]');
                const typeStep = shell.querySelector('[data-resource-type-step]');
                const detailsStep = shell.querySelector('[data-resource-details-step]');
                const deviceNotice = shell.querySelector('[data-resource-device-notice]');
                const providerInput = shell.querySelector('[data-resource-provider-input]');
                const typeInput = shell.querySelector('[data-resource-type-input]');
                const selectedProvider = shell.querySelector('[data-resource-selected-provider]');
                const detailsProvider = shell.querySelector('[data-resource-details-provider]');
                const detailsType = shell.querySelector('[data-resource-details-type]');
                const serverPreferred = shell.dataset.serverPreferredProvider || '';
                const localPreferred = localStorage.getItem('canovia:last-resource-provider') || '';
                const preferred = providerLabels[serverPreferred] ? serverPreferred : (providerLabels[localPreferred] ? localPreferred : '');

                const cloneProviderButton = (key) => {
                    const source = shell.querySelector(`[data-resource-provider="${key}"]`);
                    if (!source) return null;
                    const clone = source.cloneNode(true);
                    clone.classList.remove('p-4');
                    clone.classList.add('p-3');
                    return clone;
                };

                const renderProviderLanding = () => {
                    providerStep.classList.remove('hidden');
                    typeStep.classList.add('hidden');
                    detailsStep.classList.add('hidden');
                    deviceNotice.classList.add('hidden');

                    if (!preferred) {
                        allBox.classList.remove('hidden');
                        returningBox.classList.add('hidden');
                        return;
                    }

                    allBox.classList.add('hidden');
                    returningBox.classList.remove('hidden');
                    preferredLabel.textContent = `${providerLabels[preferred]}から追加`;
                    preferredButton.dataset.resourceProvider = preferred;
                    otherBox.innerHTML = '';
                    Object.keys(providerLabels).filter((key) => key !== preferred).forEach((key) => {
                        const button = cloneProviderButton(key);
                        if (button) otherBox.appendChild(button);
                    });
                };

                const chooseProvider = (provider) => {
                    if (!providerLabels[provider]) return;
                    providerInput.value = provider;
                    if (provider === 'device') {
                        providerStep.classList.add('hidden');
                        typeStep.classList.add('hidden');
                        detailsStep.classList.add('hidden');
                        deviceNotice.classList.remove('hidden');
                        return;
                    }
                    selectedProvider.textContent = providerLabels[provider];
                    providerStep.classList.add('hidden');
                    typeStep.classList.remove('hidden');
                    detailsStep.classList.add('hidden');
                    deviceNotice.classList.add('hidden');
                };

                shell.addEventListener('click', (event) => {
                    const providerButton = event.target.closest('[data-resource-provider]');
                    if (providerButton) {
                        chooseProvider(providerButton.dataset.resourceProvider);
                        return;
                    }

                    const typeButton = event.target.closest('[data-resource-type]');
                    if (typeButton) {
                        const type = typeButton.dataset.resourceType;
                        typeInput.value = type;
                        detailsProvider.textContent = providerLabels[providerInput.value] || providerInput.value;
                        detailsType.textContent = typeLabels[type] || type;
                        typeStep.classList.add('hidden');
                        detailsStep.classList.remove('hidden');
                        return;
                    }

                    if (event.target.closest('[data-resource-back-provider]') || event.target.closest('[data-resource-back-device]')) {
                        providerInput.value = '';
                        typeInput.value = '';
                        renderProviderLanding();
                        return;
                    }

                    if (event.target.closest('[data-resource-back-type]')) {
                        typeInput.value = '';
                        detailsStep.classList.add('hidden');
                        typeStep.classList.remove('hidden');
                    }
                });

                const oldProvider = providerInput.value;
                const oldType = typeInput.value;
                if (oldProvider && providerLabels[oldProvider] && oldProvider !== 'device') {
                    chooseProvider(oldProvider);
                    if (oldType && typeLabels[oldType]) {
                        typeInput.value = oldType;
                        detailsProvider.textContent = providerLabels[oldProvider];
                        detailsType.textContent = typeLabels[oldType];
                        typeStep.classList.add('hidden');
                        detailsStep.classList.remove('hidden');
                    }
                } else {
                    renderProviderLanding();
                }

                shell.querySelector('[data-resource-add-form]')?.addEventListener('submit', () => {
                    if (providerInput.value && providerInput.value !== 'device') {
                        localStorage.setItem('canovia:last-resource-provider', providerInput.value);
                    }
                });
            })();
        </script>
    @endif
@endsection
