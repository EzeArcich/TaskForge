<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        h1 { color: #1a1a2e; border-bottom: 2px solid #e94560; padding-bottom: 10px; }
        .task { background: #f8f9fa; border-left: 4px solid #e94560; padding: 12px 16px; margin: 8px 0; border-radius: 0 4px 4px 0; }
        .task-title { font-weight: 600; color: #1a1a2e; }
        .task-time { color: #666; font-size: 0.9em; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #999; font-size: 0.85em; }
    </style>
</head>
<body>
    <h1>Plan del dia - {{ $date }}</h1>
    <p><strong>{{ $plan->normalized_json['title'] ?? 'Your Plan' }}</strong></p>

    @if($tasks->isEmpty())
        <p>No hay tareas programadas para hoy.</p>
    @else
        <p>Tienes {{ $tasks->count() }} tarea(s) para hoy:</p>

        @foreach($tasks as $task)
            <div class="task">
                <div class="task-title">{{ $task->title }}</div>
                <div class="task-time">
                    @if($task->scheduled_start && $task->scheduled_end)
                        {{ $task->scheduled_start }} - {{ $task->scheduled_end }}
                    @endif
                    | Estimado: {{ $task->estimate_hours }}h
                </div>
            </div>
        @endforeach
    @endif

    <div class="footer">
        <p>Enviado por DailyPro</p>
    </div>
</body>
</html>
