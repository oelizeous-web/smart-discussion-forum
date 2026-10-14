<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $topic->title }} - Export</title>
    <style>
        @page { margin: 28px 32px; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #27272a; /* zinc-800 */
            font-size: 12px;
        }
        .header {
            border-bottom: 2px solid #2F7A54;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        .header h1 {
            font-size: 18px;
            margin: 0 0 4px 0;
            color: #18181b; /* zinc-900 */
        }
        .header .meta {
            font-size: 10px;
            color: #71717a; /* zinc-500 */
        }
        .header .meta span {
            margin-right: 14px;
        }
        .description {
            font-size: 11px;
            color: #52525b; /* zinc-600 */
            margin-top: 8px;
        }
        .message {
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e4e4e7; /* zinc-200 */
        }
        .message .sender {
            font-weight: bold;
            color: #2F7A54;
            font-size: 11px;
        }
        .message .time {
            color: #a1a1aa; /* zinc-400 */
            font-size: 9px;
            font-weight: normal;
            margin-left: 8px;
        }
        .message .text {
            margin-top: 3px;
            white-space: pre-wrap;
            line-height: 1.4;
        }
        .empty {
            text-align: center;
            color: #a1a1aa;
            padding: 40px 0;
            font-style: italic;
        }
        .footer {
            margin-top: 24px;
            padding-top: 8px;
            border-top: 1px solid #e4e4e7;
            font-size: 9px;
            color: #a1a1aa;
            text-align: center;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>{{ $topic->is_private ? '🔒' : '#' }} {{ $topic->title }}</h1>
        <div class="meta">
            <span><strong>Group:</strong> {{ $group->name ?? 'N/A' }}</span>
            <span><strong>Exported by:</strong> {{ $exportedBy }}</span>
            <span><strong>Exported on:</strong> {{ $exportedAt }}</span>
        </div>
        @if($topic->description)
            <p class="description">{{ $topic->description }}</p>
        @endif
    </div>

    @forelse($messages as $message)
        <div class="message">
            <span class="sender">{{ $message['sender'] }}</span>
            <span class="time">{{ $message['time'] }}</span>
            <div class="text">{{ $message['text'] }}</div>
        </div>
    @empty
        <p class="empty">No messages were posted in this topic.</p>
    @endforelse

    <div class="footer">
        Exported from Smart Discussion &middot; This document reflects the topic's message history at the time of export.
    </div>

</body>
</html>