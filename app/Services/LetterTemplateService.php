<?php

namespace App\Services;

use App\Models\LetterTemplate;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LetterTemplateService
{
    /**
     * Get paginated letter templates with search and sorting.
     *
     * The list query excludes `content` for performance — only the show
     * endpoint returns full template content.
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $perPage = $filters['per_page'] ?? 10;
        $sortBy = $filters['sort_by'] ?? 'recently_updated';

        $query = LetterTemplate::query()
            ->select('id', 'title', 'created_by', 'created_at', 'updated_at');

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        // Apply sorting
        [$column, $direction] = match ($sortBy) {
            'title_asc' => ['title', 'asc'],
            'title_desc' => ['title', 'desc'],
            'recently_created' => ['created_at', 'desc'],
            default => ['updated_at', 'desc'],  // recently_updated
        };
        $query->orderBy($column, $direction);

        return $query->paginate($perPage);
    }

    /**
     * Create a new letter template.
     */
    public function create(array $data, User $performedBy): LetterTemplate
    {
        $data['created_by'] = $performedBy->name ?? 'System';
        $data['updated_by'] = $data['created_by'];

        return LetterTemplate::create($data);
    }

    /**
     * Update an existing letter template.
     */
    public function update(LetterTemplate $letterTemplate, array $data, User $performedBy): LetterTemplate
    {
        $data['updated_by'] = $performedBy->name ?? 'System';

        $letterTemplate->update($data);

        return $letterTemplate->fresh();
    }

    /**
     * Delete a letter template.
     */
    public function delete(LetterTemplate $letterTemplate): void
    {
        $letterTemplate->delete();
    }

    /**
     * Generate a PDF from a template by replacing placeholders with provided data.
     *
     * Replaces `{key}` tokens in the template content with the corresponding
     * placeholder values, wraps the result in a PDF-ready HTML document with
     * organisation header/footer, and returns a streamed PDF response.
     *
     * @return \Illuminate\Http\Response
     */
    public function generatePdf(LetterTemplate $letterTemplate, array $placeholders)
    {
        // Get the template body content (Quill editor HTML)
        // Strip Quill embed guard characters (U+FEFF) that PDF renderers show as "?"
        $templateBody = str_replace("\xEF\xBB\xBF", '', $letterTemplate->content);

        // Replace placeholders: {key} → value
        foreach ($placeholders as $key => $value) {
            $templateBody = str_replace('{'.$key.'}', $value ?? '', $templateBody);
        }

        $html = $this->buildPdfHtml($letterTemplate->title, $templateBody);

        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('a4', 'portrait');

        $filename = str_replace(' ', '-', strtolower($letterTemplate->title)).'.pdf';

        return $pdf->stream($filename);
    }

    /**
     * Build the full PDF-ready HTML document with organisation header and footer.
     */
    private function buildPdfHtml(string $title, string $bodyContent): string
    {
        $logoPath = public_path('images/logo.png');
        $orgsPath = public_path('images/orgs.png');

        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>'.e($title).'</title>
    <style>
        @page {
            margin-top: 0.49in;
            margin-bottom: 0.19in;
            margin-left: 0.79in;
            margin-right: 0.79in;
        }
        body {
            font-family: Calibri, sans-serif;
            line-height: 1.5;
            font-size: 12px;
            margin: 0;
            padding: 0;
        }
        p {
            text-align: left;
        }

        /* Quill editor alignment classes */
        .ql-align-center { text-align: center !important; }
        .ql-align-right { text-align: right !important; }
        .ql-align-justify { text-align: justify !important; }

        /* Quill editor font size classes */
        .ql-size-small { font-size: 0.75em; }
        .ql-size-large { font-size: 1.5em; }
        .ql-size-huge { font-size: 2.5em; }

        /* Quill editor font family classes */
        .ql-font-serif { font-family: Georgia, "Times New Roman", serif; }
        .ql-font-monospace { font-family: "Courier New", Courier, monospace; }

        /* Quill editor indentation classes */
        .ql-indent-1 { padding-left: 3em; }
        .ql-indent-2 { padding-left: 6em; }
        .ql-indent-3 { padding-left: 9em; }
        .ql-indent-4 { padding-left: 12em; }
        .ql-indent-5 { padding-left: 15em; }

        .header {
            text-align: center;
            margin-bottom: 10px;
        }
        .header img {
            width: 200px;
        }
        .footer {
            font-size: 10px;
            color: #555;
            text-align: center;
            margin-top: 10px;
            padding-top: 10px;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            width: 100%;
        }
        .footer img {
            width: 150px;
        }
        .footer p {
            margin-top: 10px;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="header">
    <img src="'.$logoPath.'" alt="Logo">
</div>

<div class="template-content">
'.$bodyContent.'
</div>

<div class="footer">
    <img src="'.$orgsPath.'" alt="BHF Logo">
    <p>
        BHF/SMRU Office | 78/1 Moo 5, Mae Ramat Sub-District, Mae Ramat District, Tak Province, 63140 | www.shoklo-unit.com<br>
        Phone: +66 55 532 026
    </p>
</div>

</body>
</html>';
    }
}
