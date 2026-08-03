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
            font-size: 13px;
            margin: 0;
            padding: 20px;
        }
        .header {
            border-bottom: 2px solid #4f46e5;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #4f46e5;
            margin: 0 0 5px 0;
            font-size: 26px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 11px;
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
            margin-bottom: 25px;
        }
        .details-table th, .details-table td {
            text-align: left;
            padding: 8px 10px;
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
            font-size: 16px;
            font-weight: bold;
            margin-top: 25px;
            margin-bottom: 12px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 5px;
        }
        .comments-table {
            width: 100%;
            border-collapse: collapse;
        }
        .comments-table th, .comments-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: top;
        }
        .comments-table th {
            background-color: #f3f4f6;
            color: #374151;
            font-size: 11px;
            text-transform: uppercase;
        }
        .timestamp, .coordinate {
            font-family: monospace;
            background-color: #f3f4f6;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 11px;
        }
        .resolved-badge {
            color: #059669;
            font-weight: bold;
        }
        .rejected-badge {
            color: #d97706;
            font-weight: bold;
        }
        .unresolved-badge {
            color: #dc2626;
            font-weight: bold;
        }
        .footer {
            margin-top: 40px;
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
        <p style="margin: 0; color: #6b7280;">{{ ucfirst($project->media_type ?? 'video') }} Review & Approval Audit Certificate</p>
    </div>

    <table class="details-table">
        <tr>
            <th>Project Name</th>
            <td>{{ $project->name }}</td>
        </tr>
        <tr>
            <th>Media Type</th>
            <td><strong>{{ strtoupper($project->media_type ?? 'video') }} REVIEW</strong></td>
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
            <th>Approved Draft</th>
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
            <tr>
                <th>Verification IP</th>
                <td>{{ $approval->ip_address ?? '127.0.0.1' }}</td>
            </tr>
        @endif
    </table>

    @if($project->isPhoto() && $draft->items->count() > 0)
        <div class="section-title">Approved Photo Collection ({{ $draft->items->count() }} {{ Str::plural('photo', $draft->items->count()) }})</div>
        <table class="comments-table" style="margin-bottom: 20px;">
            <thead>
                <tr>
                    <th style="width: 10%;">#</th>
                    <th style="width: 45%;">Filename</th>
                    <th style="width: 25%;">Resolution</th>
                    <th style="width: 20%;">Size</th>
                </tr>
            </thead>
            <tbody>
                @foreach($draft->items as $index => $item)
                    <tr>
                        <td><strong>Photo {{ $index + 1 }}</strong></td>
                        <td>{{ $item->original_filename }}</td>
                        <td>{{ $item->width && $item->height ? "{$item->width} x {$item->height} px" : 'N/A' }}</td>
                        <td>{{ $item->file_size ? number_format($item->file_size / 1024, 1) . ' KB' : 'N/A' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="section-title">Revision & Feedback Audit Trail (v{{ $draft->version_number }})</div>

    @if($comments->isEmpty())
        <p style="color: #6b7280; font-style: italic;">No comments or revision requests were logged on this draft.</p>
    @else
        <table class="comments-table">
            <thead>
                <tr>
                    <th style="width: 20%;">Location / Marker</th>
                    <th style="width: 20%;">Author</th>
                    <th style="width: 40%;">Comment Content</th>
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
                            @elseif($comment->position_x !== null && $comment->position_y !== null)
                                <span class="coordinate">
                                    Pin ({{ round($comment->position_x, 1) }}%, {{ round($comment->position_y, 1) }}%)
                                </span>
                            @else
                                <span style="color: #9ca3af; font-size: 11px;">General Feedback</span>
                            @endif
                        </td>
                        <td>{{ $comment->author_name ?? $comment->user->name ?? 'Client' }}</td>
                        <td>
                            {{ $comment->content }}
                            @if($comment->is_rejected && $comment->rejection_reason)
                                <div style="font-size: 11px; color: #d97706; margin-top: 3px;">
                                    <strong>Decline Reason:</strong> {{ $comment->rejection_reason }}
                                </div>
                            @endif
                        </td>
                        <td>
                            @if($comment->is_resolved)
                                <span class="resolved-badge">✓ Resolved</span>
                            @elseif($comment->is_rejected)
                                <span class="rejected-badge">✕ Declined</span>
                            @else
                                <span class="unresolved-badge">● Open</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        This is a system-generated verification record from RevisionRoom. Audit trail recorded on {{ now()->format('Y-m-d H:i:s') }}.
    </div>

</body>
</html>
