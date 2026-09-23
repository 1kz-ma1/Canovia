<?php

return [
    'version' => 1,
    'categories' => [
        'start' => [
            'label' => 'はじめる',
            'description' => 'Canoviaの基本操作',
            'icon' => '✦',
        ],
        'daily' => [
            'label' => '計画を進める',
            'description' => '今日の行動・作業・更新',
            'icon' => '▶',
        ],
        'ai' => [
            'label' => 'AIを使う',
            'description' => '演習や計画更新をAIと進める',
            'icon' => '✧',
        ],
        'together' => [
            'label' => '共同計画',
            'description' => '招待・参加・一緒に進める',
            'icon' => '◎',
        ],
        'assets' => [
            'label' => '資料・成果物',
            'description' => 'Resourceや制作ファイルを整理する',
            'icon' => '◇',
        ],
    ],
    'guides' => [
        'first_plan' => [
            'category' => 'start',
            'title' => '最初の計画を作る',
            'description' => '目標をCanoviaへ登録し、進める土台を作ります。',
            'keywords' => ['計画', 'Plan', '作成', '最初', '目標'],
            'start_path' => '/',
            'steps' => [
                [
                    'path' => '/',
                    'target' => 'create-plan',
                    'title' => '新しい計画から始めます',
                    'copy' => 'ホームの「新しい計画」から、進めたいことを登録します。',
                    'advance' => 'click',
                ],
                [
                    'path' => '/plans/create',
                    'target' => 'plan-form',
                    'title' => '最初はざっくりで大丈夫',
                    'copy' => 'タイトルを中心に入力します。期限や細かい条件は、決まっている分だけで構いません。',
                    'advance' => 'next',
                ],
            ],
        ],
        'today_action' => [
            'category' => 'daily',
            'title' => '今日やることを決める',
            'description' => '優先度と現在地から、今やるTaskを選びます。',
            'keywords' => ['今日', 'Task', 'おすすめ', '優先度', '次'],
            'start_path' => '/navigate',
            'steps' => [
                [
                    'path' => '/navigate',
                    'target' => 'today-start',
                    'title' => 'Canoviaの提案を確認します',
                    'copy' => 'ここには、Plan優先度・Task優先度・期限などから選ばれた今の候補が表示されます。',
                    'advance' => 'next',
                ],
            ],
        ],
        'timer' => [
            'category' => 'daily',
            'title' => 'タイマーで作業する',
            'description' => 'Taskを開始し、作業時間を実績として残します。',
            'keywords' => ['タイマー', '作業', '記録', '時間', '開始'],
            'start_path' => '/navigate',
            'steps' => [
                [
                    'path' => '/navigate',
                    'target' => 'today-start',
                    'title' => 'このTaskを開始します',
                    'copy' => '開始ボタンを押すと、そのTask専用の作業タイマーへ移動します。',
                    'advance' => 'click',
                ],
                [
                    'target' => 'work-timer',
                    'title' => '作業中はここだけ見ればOK',
                    'copy' => '一時停止・再開・記録して終了ができます。終了すると作業実績へ同期されます。',
                    'advance' => 'next',
                ],
            ],
        ],
        'plan_update' => [
            'category' => 'ai',
            'title' => '実績に合わせて計画を更新する',
            'description' => '進捗や予定との差をAIへ渡し、Planを安全に更新します。',
            'keywords' => ['計画更新', 'AI', '実績', '方針', 'JSON'],
            'start_path' => '/roadmap',
            'steps' => [
                [
                    'path' => '/roadmap',
                    'target' => 'plan-update',
                    'title' => '選択中のPlanを更新します',
                    'copy' => '「計画を更新」から、現在のTask・実績・期限をまとめてAIへ相談できます。',
                    'advance' => 'click',
                ],
                [
                    'target' => 'plan-update-input',
                    'title' => '変わったことだけ入力します',
                    'copy' => '作業結果や予定との違いを書けば十分です。Canoviaが現在の計画情報と一緒にAIへ渡します。',
                    'advance' => 'next',
                ],
            ],
        ],
        'study_practice' => [
            'category' => 'ai',
            'title' => 'AI演習を使う',
            'description' => '学習Taskから理解度確認・弱点補強を始めます。',
            'keywords' => ['AI演習', '問題', '資格', '学習', 'Question Bank'],
            'start_path' => '/roadmap',
            'steps' => [
                [
                    'path' => '/roadmap',
                    'target' => 'plan-detail',
                    'title' => 'まず学習Planを開きます',
                    'copy' => 'AI演習はTaskに紐づくため、対象Planの詳細へ進みます。',
                    'advance' => 'click',
                ],
                [
                    'target' => 'study-practice',
                    'title' => 'AI演習を開きます',
                    'copy' => '資格学習Planでは、対象Taskに応じてAI演習を開始できます。',
                    'advance' => 'click',
                ],
                [
                    'target' => 'practice-strategy',
                    'title' => '出題方針はCanoviaが決めます',
                    'copy' => '学習履歴とTask状態から、初回確認・弱点補強・定着確認など今回の方針を自動で選びます。',
                    'advance' => 'next',
                ],
            ],
        ],
        'collaboration_create' => [
            'category' => 'together',
            'title' => '共同計画を作って招待する',
            'description' => '自分のPlanを共同計画にして、URLや参加コードで招待します。',
            'keywords' => ['共同計画', '招待', '共有', 'メンバー', 'URL', '参加コード'],
            'start_path' => '/roadmap',
            'requires_auth' => true,
            'steps' => [
                [
                    'path' => '/roadmap',
                    'target' => 'collaboration-settings',
                    'title' => '共同計画の設定を開きます',
                    'copy' => '選択中のPlanを共同計画にしたり、既存の共同計画を管理できます。',
                    'advance' => 'click',
                ],
                [
                    'target' => 'collaboration-primary',
                    'title' => '共同計画を有効にして招待します',
                    'copy' => '初回は共同計画を有効にします。有効化済みなら、ここで共有URLや参加コードを確認できます。',
                    'advance' => 'next',
                ],
            ],
        ],
        'collaboration_join' => [
            'category' => 'together',
            'title' => '共同計画に参加する',
            'description' => 'もらった参加コードから共同Planへ参加します。',
            'keywords' => ['共同計画', '参加', 'コード', '招待', 'Join'],
            'start_path' => '/my-plans',
            'requires_auth' => true,
            'steps' => [
                [
                    'path' => '/my-plans',
                    'target' => 'collaboration-join',
                    'title' => '共同計画への参加画面を開きます',
                    'copy' => '招待された側は「共同計画に参加」から進みます。',
                    'advance' => 'click',
                ],
                [
                    'target' => 'collaboration-code',
                    'title' => '参加コードを入力します',
                    'copy' => '招待した人から受け取った参加コードを入力します。共有URLを受け取った場合は、そのURLから直接参加できます。',
                    'advance' => 'next',
                ],
            ],
        ],
        'resources' => [
            'category' => 'assets',
            'title' => '関連資料をPlanにまとめる',
            'description' => 'URLや参考資料を登録して、Taskと紐づけます。',
            'keywords' => ['資料', 'Resource', 'URL', '参考', 'Task'],
            'start_path' => '/roadmap',
            'steps' => [
                [
                    'path' => '/roadmap',
                    'target' => 'plan-resources',
                    'title' => '選択中のPlanの関連資料を開きます',
                    'copy' => 'Plan単位で参考URLや資料をまとめ、必要なTaskへ紐づけられます。',
                    'advance' => 'click',
                ],
                [
                    'target' => 'resource-add',
                    'title' => '資料を追加します',
                    'copy' => 'タイトルとURLを登録します。あとからAIでTaskへの割り当ても整理できます。',
                    'advance' => 'next',
                ],
            ],
        ],
    ],
];
