<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>テンプレート作成 | Canovia</title>
</head>
<body>
    <h1>テンプレート作成</h1>

    <p>この画面では、公開テンプレートを作成します。</p>

    @if ($errors->any())
        <div>
            <h2>入力内容を確認してください</h2>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.templates.store') }}" method="POST">
        @csrf

        <h2>テンプレート情報</h2>

        <p>
            <label for="title">テンプレート名</label><br>
            <input id="title" type="text" name="title" value="{{ old('title') }}" required>
        </p>

        <p>
            <label for="description">説明</label><br>
            <textarea id="description" name="description">{{ old('description') }}</textarea>
        </p>

        <p>
            <label for="category">カテゴリ</label><br>
            <select id="category" name="category">
                <option value="資格学習">資格学習</option>
                <option value="個人開発">個人開発</option>
                <option value="制作活動">制作活動</option>
                <option value="ゲーム開発">ゲーム開発</option>
                <option value="その他">その他</option>
            </select>
        </p>

        <p>
            <label for="estimated_days">想定日数</label><br>
            <input id="estimated_days" type="number" name="estimated_days" value="{{ old('estimated_days', 30) }}" min="0" required>
        </p>

        <h2>テンプレートタスク</h2>

        @for ($i = 0; $i < 5; $i++)
            <fieldset>
                <legend>タスク{{ $i + 1 }}</legend>

                <p>
                    <label>タスク名</label><br>
                    <input type="text" name="tasks[{{ $i }}][title]" value="{{ old("tasks.$i.title") }}" {{ $i === 0 ? 'required' : '' }}>
                </p>

                <p>
                    <label>説明</label><br>
                    <textarea name="tasks[{{ $i }}][description]">{{ old("tasks.$i.description") }}</textarea>
                </p>

                <p>
                    <label>想定作業時間（分）</label><br>
                    <input type="number" name="tasks[{{ $i }}][estimated_minutes]" value="{{ old("tasks.$i.estimated_minutes", 60) }}" min="0">
                </p>
            </fieldset>
        @endfor

        <button type="submit">テンプレートを作成する</button>
    </form>

    <p><a href="{{ route('templates.index') }}">テンプレート一覧へ戻る</a></p>
</body>
</html>