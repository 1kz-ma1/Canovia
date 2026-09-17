<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>テンプレート管理ログイン | Canovia</title>
</head>
<body>
    <h1>テンプレート管理ログイン</h1>

    <p>この画面は管理者専用です。</p>

    @if ($errors->any())
        <div>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.templates.authenticate') }}" method="POST">
        @csrf

        <p>
            <label for="password">管理用パスワード</label><br>
            <input id="password" type="password" name="password" required>
        </p>

        <button type="submit">ログイン</button>
    </form>

    <p><a href="{{ route('home') }}">ホームへ戻る</a></p>
</body>
</html>