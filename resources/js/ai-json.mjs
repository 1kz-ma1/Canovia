const OPEN_TO_CLOSE = { '{': '}', '[': ']' };
const CLOSERS = new Set(Object.values(OPEN_TO_CLOSE));

function stripBom(value) {
    return String(value ?? '').replace(/^\uFEFF/, '').trim();
}

function fencedCandidates(text) {
    const candidates = [];
    const pattern = /```(?:json)?\s*([\s\S]*?)\s*```/gi;
    let match;
    while ((match = pattern.exec(text)) !== null) {
        const candidate = String(match[1] || '').trim();
        if (candidate) candidates.push(candidate);
    }
    return candidates;
}

function balancedJsonCandidate(text) {
    let start = -1;
    const stack = [];
    let inString = false;
    let escaped = false;

    for (let i = 0; i < text.length; i += 1) {
        const char = text[i];

        if (start < 0) {
            if (char === '{' || char === '[') {
                start = i;
                stack.push(OPEN_TO_CLOSE[char]);
            }
            continue;
        }

        if (inString) {
            if (escaped) {
                escaped = false;
            } else if (char === '\\') {
                escaped = true;
            } else if (char === '"') {
                inString = false;
            }
            continue;
        }

        if (char === '"') {
            inString = true;
            continue;
        }

        if (char === '{' || char === '[') {
            stack.push(OPEN_TO_CLOSE[char]);
            continue;
        }

        if (CLOSERS.has(char)) {
            if (stack.length === 0 || stack[stack.length - 1] !== char) {
                throw new Error('JSONの括弧の対応が崩れています。');
            }
            stack.pop();
            if (stack.length === 0) return text.slice(start, i + 1).trim();
        }
    }

    if (start < 0) throw new Error('JSONオブジェクト（{ ... }）を見つけられませんでした。');
    if (inString) throw new Error('JSON内の文字列を閉じる引用符（"）が不足しています。');
    throw new Error('JSONの閉じ括弧が不足しています。');
}

function stripJsonComments(text) {
    let output = '';
    let inString = false;
    let escaped = false;

    for (let i = 0; i < text.length; i += 1) {
        const char = text[i];
        const next = text[i + 1];

        if (inString) {
            output += char;
            if (escaped) escaped = false;
            else if (char === '\\') escaped = true;
            else if (char === '"') inString = false;
            continue;
        }

        if (char === '"') {
            inString = true;
            output += char;
            continue;
        }

        if (char === '/' && next === '/') {
            i += 2;
            while (i < text.length && text[i] !== '\n' && text[i] !== '\r') i += 1;
            if (i < text.length) output += text[i];
            continue;
        }

        if (char === '/' && next === '*') {
            i += 2;
            while (i < text.length && !(text[i] === '*' && text[i + 1] === '/')) i += 1;
            if (i < text.length) i += 1;
            continue;
        }

        output += char;
    }

    return output;
}

function stripTrailingCommas(text) {
    let output = '';
    let inString = false;
    let escaped = false;

    for (let i = 0; i < text.length; i += 1) {
        const char = text[i];

        if (inString) {
            output += char;
            if (escaped) escaped = false;
            else if (char === '\\') escaped = true;
            else if (char === '"') inString = false;
            continue;
        }

        if (char === '"') {
            inString = true;
            output += char;
            continue;
        }

        if (char === ',') {
            let cursor = i + 1;
            while (cursor < text.length && /\s/.test(text[cursor])) cursor += 1;
            if (text[cursor] === '}' || text[cursor] === ']') continue;
        }

        output += char;
    }

    return output;
}

function escapeControlCharactersInStrings(text) {
    let output = '';
    let inString = false;
    let escaped = false;

    for (let i = 0; i < text.length; i += 1) {
        const char = text[i];

        if (!inString) {
            output += char;
            if (char === '"') inString = true;
            continue;
        }

        if (escaped) {
            output += char;
            escaped = false;
            continue;
        }

        if (char === '\\') {
            output += char;
            escaped = true;
            continue;
        }

        if (char === '"') {
            output += char;
            inString = false;
            continue;
        }

        const code = char.charCodeAt(0);
        if (code < 0x20) {
            if (code === 10) output += '\\n';
            else if (code === 13) output += '\\r';
            else if (code === 9) output += '\\t';
            else output += `\\u${code.toString(16).padStart(4, '0')}`;
            continue;
        }

        output += char;
    }

    return output;
}

function parseCandidate(candidate) {
    const withoutComments = stripJsonComments(candidate);
    const withoutTrailingCommas = stripTrailingCommas(withoutComments).trim();
    const escapedControls = escapeControlCharactersInStrings(withoutTrailingCommas);
    const parsed = JSON.parse(escapedControls);
    if (!parsed || typeof parsed !== 'object') {
        throw new Error('JSONの最上位はオブジェクトまたは配列にしてください。');
    }
    return { text: escapedControls, parsed };
}

export function normalizeAiJsonText(value) {
    const text = stripBom(value);
    if (!text) throw new Error('AIの最後の回答を貼り付けてください。');

    const candidates = [...fencedCandidates(text)];
    try {
        candidates.push(balancedJsonCandidate(text));
    } catch (error) {
        if (candidates.length === 0) throw error;
    }
    candidates.push(text);

    let lastError = null;
    const seen = new Set();
    for (const rawCandidate of candidates) {
        const candidate = String(rawCandidate || '').trim();
        if (!candidate || seen.has(candidate)) continue;
        seen.add(candidate);
        try {
            const balanced = balancedJsonCandidate(candidate);
            return parseCandidate(balanced);
        } catch (error) {
            lastError = error;
        }
    }

    const detail = lastError?.message && !/^Unexpected token|^Expected |^Unterminated string|^Unexpected end/i.test(lastError.message)
        ? lastError.message
        : 'JSONとして構文解析できませんでした。引用符・カンマ・括弧を確認してください。';
    throw new Error(detail);
}

export function buildAiJsonRepairPrompt(errorMessage, originalJson) {
    const error = String(errorMessage || 'JSONを読み込めませんでした。').trim();
    const original = String(originalJson || '').trim();
    return [
        'Canoviaに貼り付けたJSONでエラーが発生しました。',
        '以下のエラー内容と元のJSONを確認し、元の意図・数値・ID・進捗情報をできるだけ保持したまま、エラー解消に必要な箇所だけ修正してください。',
        '情報不足で安全に修正できない場合は、JSONを作らず必要な確認質問だけをしてください。',
        '修正できる場合は、返答前にJSONとして構文解析できることを確認し、説明文・Markdown・コードフェンスを付けず、有効なJSONだけを最後の回答として返してください。',
        '',
        '【Canoviaのエラー】',
        error,
        '',
        '【元のJSON】',
        original || '（入力内容を取得できませんでした）',
    ].join('\n');
}
