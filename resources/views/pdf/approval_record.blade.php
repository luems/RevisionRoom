<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Approval Record - {{ $project->name }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333333;
            line-height: 1.5;
            font-size: 14px;
            margin: 0;
            padding: 20px;
        }
        .header {
            border-bottom: 2px solid #4f46e5;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #4f46e5;
            margin: 0 0 10px 0;
            font-size: 28px;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 12px;
            text-transform: uppercase;
        }
        .status-approved {
            background-color: #d1fae5;
            color: #065f46;
        }
        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .details-table th, .details-table td {
            text-align: left;
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        .details-table th {
            width: 30%;
            background-color: #f9fafb;
            color: #4b5563;
            font-weight: 600;
        }
        .section-title {
            color: #1f2937;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 5px;
        }
        .comments-table {
            width: 100%;
            border-collapse: collapse;
        }
        .comments-table th, .comments-table td {
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }
        .comments-table th {
            background-color: #f3f4f6;
            color: #374151;
            font-size: 12px;
            text-transform: uppercase;
        }
        .timestamp {
            font-family: monospace;
            background-color: #f3f4f6;
            padding: 2px 4px;
            border-radius: 3px;
        }
        .resolved-badge {
            color: #059669;
            font-weight: bold;
        }
        .unresolved-badge {
            color: #dc2626;
            font-weight: bold;
        }
        .footer {
            margin-top: 50px;
            font-size: 11px;
            color: #9ca3af;
            text-align: center;
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>RevisionRoom</h1>
        <p style="margin: 0; color: #6b7280;">Video Approval & Revision Record</p>
    </div>

    <table class="details-table">
        <tr>
            <th>Project Name</th>
            <td>{{ $project->name }}</td>
        </tr>
        <tr>
            <th>Current Status</th>
            <td>
                @if($approval)
                    <span class="status-badge status-approved">Approved</span>
                @else
                    <span class="status-badge status-pending">Pending Approval</span>
                @endif
            </td>
        </tr>
        <tr>
            <th>Editor</th>
            <td>{{ $project->editor->name }} ({{ $project->editor->email }})</td>
        </tr>
        <tr>
            <th>Client</th>
            <td>{{ $project->client->name ?? 'Guest Client' }} ({{ $project->client->email ?? 'N/A' }})</td>
        </tr>
        <tr>
            <th>Reviewed Draft</th>
            <td>Version {{ $draft->version_number }} ({{ $draft->original_filename }})</td>
        </tr>
        @if($approval)
            <tr>
                <th>Approved By</th>
                <td>{{ $approval->approver_name }}</td>
            </tr>
            <tr>
                <th>Approval Date</th>
                <td>{{ $approval->created_at->format('M d, Y h:i A') }}</td>
            </tr>
            <tr>
                <th>Client Remarks</th>
                <td>{{ $approval->remarks ?? 'None' }}</td>
            </tr>
        @endif
    </table>

    <div class="section-title">Revision & Feedback Checklist (v{{ $draft->version_number }})</div>

    @if($comments->isEmpty())
        <p style="color: #6b7280; font-style: italic;">No comments or revision requests were logged on this draft.</p>
    @else
        <table class="comments-table">
            <thead>
                <tr>
                    <th style="width: 15%;">Timestamp</th>
                    <th style="width: 20%;">Author</th>
                    <th style="width: 45%;">Comment</th>
                    <th style="width: 20%;">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($comments as $comment)
                    <tr>
                        <td>
                            @if($comment->timestamp_seconds !== null)
                                <span class="timestamp">
                                    {{ sprintf('%02d:%02d', floor($comment->timestamp_seconds / 60), $comment->timestamp_seconds % 60) }}
                                </span>
                            @else
                                <span style="color: #9ca3af; font-size: 12px;">General</span>
                            @endif
                        </td>
                        <td>{{ $comment->author_name ?? $comment->user->name ?? 'Client' }}</td>
                        <td>{{ $comment->content }}</td>
                        <td>
                            @if($comment->is_resolved)
                                <span class="resolved-badge">Resolved</span>
                            @else
                                <span class="unresolved-badge">Unresolved</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        This is a system-generated document from RevisionRoom. Authenticity and audit trail recorded on {{ now()->format('Y-m-d H:i:s') }}.
    </div>

</body>
</html>
