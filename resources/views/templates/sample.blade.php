<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>テンプレート詳細 | Canovia</title>
</head>
<body>
    <h1>テンプレート詳細</h1>

    <h2>応用情報技術者試験</h2>

    <p>応用情報技術者試験の学習計画を立てるためのテンプレートです。</p>

    <h2>含まれるタスク</h2>

    <ul>
        <li>午前問題対策：30時間</li>
        <li>テクノロジ系：25時間</li>
        <li>マネジメント系：15時間</li>
        <li>ストラテジ系：15時間</li>
        <li>午後問題対策：35時間</li>
        <li>苦手分野復習：10時間</li>
    </ul>

    <form>
        <p>
            <label>このテンプレートから作る計画の期限</label><br>
            <input type="date">
        </p>

        <p>
            <a href="{{ route('plans.sample') }}">このテンプレートで計画を作成する（仮）</a>
        </p>
    </form>

    <p><a href="{{ route('templates.index') }}">テンプレート一覧へ戻る</a></p>
    <p><a href="{{ route('home') }}">ホームへ戻る</a></p>
</body>
</html>