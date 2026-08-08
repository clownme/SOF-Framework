<?php
/**
 * SOF Magazine Archive Shortcode
 */

if (!defined('ABSPATH')) {
    exit;
}

class SOF_MagazineArchiveShortcode
{
    public static function register(): void
    {
        add_shortcode('coai_magazine_archive', [self::class, 'render']);
    }

    public static function render(): string
    {
        // -------------------------------------------------
        // Member Access
        // -------------------------------------------------

        if (!is_user_logged_in()) {

            return '
                <div class="coai-magazine-access-required">

                    <h2>Member Access Required</h2>

                    <p>
                        The Calliope Magazine Archive is available
                        only to COAI members.
                    </p>

                    <p>
                        <a class="button"
                           href="' . esc_url(home_url('/')) . '">
                            Membership Options
                        </a>
                    </p>

                </div>
            ';
        }
        
        global $wpdb;

        $table = sof_magazine_table_name();

        $selected = isset($_GET['magazine_id'])
            ? (int) $_GET['magazine_id']
            : 0;

        $magazines = $wpdb->get_results(
            "SELECT *
             FROM {$table}
             ORDER BY display_year DESC, start_month ASC, title ASC"
        );

        if (empty($magazines)) {
            return '<p>No magazines are available yet.</p>';
        }

        ob_start();

        echo '<style>

        .coai-magazine-archive{
            width:calc(100% - 80px);
            max-width:1400px;
            margin:55px auth;
            padding:45px;

            background:
                radial-gradient(
                    ellipse at 50% 20%,
                    #B09A79 0%,
                    #A08260 18%,
                    #87694D 42%,
                    #6B523B 70%,
                    #4E3A2A 100%
                ) !important;
                
            border:8px solid #F7F2EA;
            border-radius:20px;
            
            box-shadow:
                0 2px 8px rgba(255,255,255,.10),
                0 14px 30px rgba(0,0,0,.20),
                0 30px 55px rgba(0,0,0,.12);
        }
        
        .coai-magazine-archive-header{
            text-align:center;
            margin:0 0 60px;
            padding:40px 0 50px;
            border-bottom:1px solid rgba(255,255,255,.08);
        }

        .coai-magazine-archive-title{
            margin:0;
            color:#FFF8E7;
            font-size:42px;
            line-height:1.15;
            font-weight:700;
            text-shadow:0px 1px 2px rgba(0,0,0,.25);
        }

        .coai-magazine-archive-intro{
            max-width:760px;
            margin:12px auto 0;
            color:#F0DFC0;
            font-size:19px;
            line-height:1.5;
        }
        
        .coai-magazine-archive > h2{
            color:#F2C94C;
            font-size:54px;
            font-weight:700;
            
            margin:55px 0 25px;
            padding-top:35px;
            
            border-top:1px solid rgba(255,255,255,.10);
            
            text-shadow:0px 2px 6px rgba(0,0,0,.25);
        }
        
        .coai-magazine-archive > h2:first-of-type{
            border-top:none;
            padding-top:0;
            margin-top:40px;
        }

        .coai-magazine-year{
            display:grid;
            grid-template-columns:repeat(auto-fill,minmax(230px,230px));
            justify-content:start;
            gap:24px;
            margin-bottom:55px;
            align-items:start;
        }

        .coai-magazine-card{
            width:230px;
            background:#FFFDF8;
            border:none;
            border-radius:12px;
            padding:18px;
            
            text-align:left;
            text-decoration:none;
            color:inherit;
            align-self:start;
            
            box-shadow:
                0 8px 20px rgba(0,0,0,.20);
                
            transition:
                transform .25s ease,
                box-shadow .25s ease;
        }
        
        .coai-magazine-card:hover{
            transform:translateY(-6px) rotate(-1.5deg) scale(1.02);
            box-shadow:
                0 3px 5px rgba(0,0,0,.14),
                0 16px 30px rgba(0,0,0,.32),
                0 28px 50px rgba(0,0,0,.18);
        }

        .coai-magazine-card h3{
            margin:0 0 6px;
            font-size:24px;
            line-height:1.15;
            color:#6D001A;
        }

        .coai-magazine-card p{
            margin:0 0 10px;
            font-size:14px;
            line-height:1.25;
        }
        
        .coai-magazine-card-detail{
            margin:-2px 0 10px;
            color:#6f6258;
            font-size:14px;
            font-weight:600;
        }

        .coai-magazine-card .button,
        .coai-magazine-card a.button{
            width:100%;
            text-align:center;
            margin-top:0;
        }
        
        .coai-magazine-cover{
            margin-bottom:0;
        }

        .coai-magazine-cover img{
            width:100%;
            max-width:185px;
            aspect-ratio:3 / 4;
            object-fit:cover;
            display:block;
            margin:0 auto;
            border-radius:8px;
            box-shadow:0 8px 18px rgba(0,0,0,.16);
            transition:transform .18s ease, box-shadow .18s ease;
        }

        .coai-magazine-cover:hover img{
            transform:translateY(-2px);
            box-shadow:0 12px 28px rgba(0,0,0,.22);
        }
        
        .coai-magazine-year-label{

            margin-top:0;
            margin-bottom:12px;
            
            color:#E7C454;
            font-size:24px;
            font-weight:600;
            text-align:left;

        }

        .coai-magazine-publisher{

            margin-top:0;
            margin-bottom:20px;

            color:#E7D6B6;
            font-size:20px;
            text-align:left;

        }
        
        .coai-read-link-wrap{
            text-align:center;
            line-height:1.2;
            margin-top:6px;
        }

        .coai-read-link-wrap a{
            display:inline-block;
            text-decoration:none;
        }

        .coai-magazine-cover-placeholder{
            width:100%;
            aspect-ratio:3 / 4;
            border-radius:8px;
            background:#d9cfb5;
            display:flex;
            align-items:center;
            justify-content:center;
            color:#5a1f2b;
            font-weight:700;
            text-align:center;
            padding:18px;
            box-sizing:border-box;
        }
        
        .coai-magazine-back{
            display:inline-flex;
            align-items:center;
            gap:8px;

            margin-bottom:18px;
            padding:9px 16px;

            color:#4A3525;
            background:#F2D27A;
            border:1px solid rgba(255,255,255,.25);
            border-radius:999px;

            text-decoration:none;
            font-size:16px;
            font-weight:700;

            box-shadow:0 3px 10px rgba(0,0,0,.16);
            transition:
                background-color .2s ease,
                transform .2s ease,
                box-shadow .2s ease;
        }

        .coai-magazine-back:hover{
            color:#3D2B1E;
            background:#FFF1B8;
            text-decoration:none;
            transform:translateY(-1px);
            box-shadow:0 5px 14px rgba(0,0,0,.22);
        }

        .coai-magazine-viewer{
            background:#5A0015;
            border:10px solid #7B6246;
            border-radius:10px;
            padding:15px;
            margin-top:30px;
        }

        .coai-magazine-viewer iframe,
        .coai-magazine-viewer embed,
        .coai-magazine-viewer object{
            background:#dcdcde !important;
        }

        .coai-magazine-viewer .df-bg,
        .coai-magazine-viewer .df-ui-wrapper,
        .coai-magazine-viewer .df-book-wrapper,
        .coai-magazine-viewer .df-container{
            background:#5a1f2b !important;
        }

        .coai-magazine-title{
            margin-top:0;
            font-size:52px;
            font-weight:700;
            
            color:#FFF8E7;
            text-shadow:0 1px 2px rgba(0,0,0,.25);
        }

        .coai-magazine-subtitle{
            margin-top:8px;
            margin-bottom:14px;
            
            color:#E7C454;
            font-size:34px;
            font-weight:500;
        }

        .coai-magazine-meta{
            margin-top:-20px;
            margin-bottom:12px;
            
            color:#E7D686;
            font-size:20px;
            font-weight:500;
        }

        .coai-magazine-description{
            margin-bottom:35px;
            color:#F4EAD7;
            font-size:18px;
            line-height:1.6;
            font-weight:400;
        }

        .coai-magazine-archive .button{
            
            background:none;
            color:#F2D27A;
            border:none;
            padding:0;
            
            font-weight:600;
            text-decoration:none;
        }
        
        .coai-magazine-archive .button:hover{

            color:#FFF4D0;

        }

        .coai-magazine-viewer .df-ui-wrapper,
        .coai-magazine-viewer .df-book-wrapper{
            max-width:100% !important;
        }
        
        .coai-archive-nav{
            display:flex;
            justify-content:flex-start;
            margin-bottom:30px;
        }

        .coai-home-button{
            display:inline-block;
            padding:12px 22px;
            
            background:#F7E7C1;
            color:#5A3C22;
            font-weight:700;
            text-decoration:none;
            border-radius:999px;
            border:1px solid rgba(90,60,34,.15);
            
            box-shadow:0 6px 14px rgba(0,0,0,.16);
            transition:.2s;
        }

        .coai-home-button:hover{
            background:#FFF4DA;
            transform:translateY(-2px);
        }
        
        /* -------------------------------
            Mobile Layout
            
        ----------------------------------*/
        
        @media (max-width:768px){
            .coai-magazine-archive{
                width:calc(100% - 24px);
                margin:20px auto;
                padding:24px;
            }
            
            .coai-magazine-year{
                grid-template-columns:1fr;
                gap:20px;
            }

            .coai-magazine-card{
                max-width:340px;
                margin:0 auto;
            }

            .coai-magazine-archive-title{
                font-size:34px;
            }

            .coai-magazine-archive-intro{
                font-size:17px;
            }
        }
        
        

        </style>';

        if ($selected > 0) {
            foreach ($magazines as $magazine) {
                if ((int) $magazine->id !== $selected) {
                    continue;
                }

                echo '<div class="coai-magazine-archive">';

                echo '<a class="coai-magazine-back" href="'
                    . esc_url(remove_query_arg('magazine_id'))
                    . '">&larr; See All Issues</a>';

                $publication_name = !empty($magazine->publication_name)
                    ? $magazine->publication_name
                    : 'The New Calliope';

                $issue_label = !empty($magazine->issue_label)
                    ? $magazine->issue_label
                    : $magazine->title;

                echo '<h1 class="coai-magazine-title">'
                    . esc_html($publication_name)
                    . '</h1>';

                $meta = self::format_volume_issue($magazine);

                if ($meta !== '') {
                    echo '<div class="coai-magazine-subtitle">';
                    echo esc_html($meta);
                    echo '</div>';
                }

                $display_year = !empty($magazine->display_year)
                    ? $magazine->display_year
                    : $magazine->year_folder;

                echo '<div class="coai-magazine-year-label">';
                echo esc_html($display_year);
                echo '</div>';

                $publisher = !empty($magazine->description)
                    ? $magazine->description
                    : 'Official Publication of Clowns of America International';

                echo '<div class="coai-magazine-publisher">';
                echo esc_html($publisher);
                echo '</div>';

                echo '<div id="magazine-viewer" class="coai-magazine-viewer">';
                echo SOF_MagazineViewerManager::render($magazine);
                echo '</div>';

                echo '</div>';

                return ob_get_clean();
            }
        }

        echo '<div class="coai-magazine-archive">';
        
        echo '<div class="coai-archive-nav">';

        echo '<a class="coai-home-button" href="'
            . esc_url(home_url('/member-portal/'))
            . '">🏠 Member Portal</a>';

        echo '</div>';

        echo '<header class="coai-magazine-archive-header">';
        echo '<h1 class="coai-magazine-archive-title">The Calliope Magazine Archive</h1>';
        echo '<p class="coai-magazine-archive-intro">';
        echo 'Explore more than 40 years of COAI history through every issue of The New Calliope';
        echo '</p>';
        echo '</header>';

        $current_year = null;

        foreach ($magazines as $magazine) {
            $year = $magazine->display_year ?: $magazine->year_folder;

            if ($year !== $current_year) {
                if ($current_year !== null) {
                    echo '</div>';
                }

                echo '<h2>' . esc_html($year) . '</h2>';
                echo '<div class="coai-magazine-year">';
                $current_year = $year;
            }

            $url = add_query_arg(['magazine_id' => (int) $magazine->id]);

            echo '<a class="coai-magazine-card" href="' . esc_url($url) . '#magazine-viewer" target="_self">';

            $meta = self::format_volume_issue($magazine);

            $card_title = $meta !== ''
                ? $meta
                : ($magazine->issue_label ?: $magazine->title);
                
            $card_detail = '';
            
            $source_label = strtolower(
                (string) ($magazine->issue_label ?: $magazine->title)
            );
            
            if (str_contains($source_label, 'front half')) {
                $card_detail = 'Front Half';
            } elseif (str_contains($source_label, 'back half')) {
                $card_detail = 'Back Half';
            
            }

            echo '<h3>' . esc_html($card_title) . '</h3>';
            
            if ($card_detail !== '') {
                echo '<p class="coai-magazine-card-detail">'
                .esc_html($card_detail)
                . '</p>';
            }

            echo '<div class="coai-magazine-cover">';

            if (!empty($magazine->cover_attachment_id)) {
                echo wp_get_attachment_image(
                    (int) $magazine->cover_attachment_id,
                    'medium_large',
                    false,
                    [
                        'alt' => esc_attr($magazine->issue_label ?: $magazine->title),
                    ]
                );
            } else {
                echo '<div class="coai-magazine-cover-placeholder">';
                echo esc_html($magazine->issue_label ?: $magazine->title);
                echo '</div>';
            }

            echo '</div>';

            echo '</a>';
        }

        echo '</div>'; // final year grid
        echo '</div>'; // archive wrapper

        return ob_get_clean();
    }

    public static function format_volume_issue($magazine): string
    {
        $volume = !empty($magazine->volume)
            ? trim((string) $magazine->volume)
            : '';

        $issue_number = !empty($magazine->issue_number)
            ? trim((string) $magazine->issue_number)
            : '';

        /*
         * Fall back to parsing the title or filename when the
         * database metadata was not populated by the scanner.
         *
         * Recognizes examples such as:
         *
         * Calliope 41 1 2025
         * Calliope 41.1 2025
         * Calliope_41-1.pdf
         */
        if ($volume === '' || $issue_number === '') {
            $sources = [
                $magazine->title ?? '',
                $magazine->issue_label ?? '',
                $magazine->file_name ?? '',
            ];

            foreach ($sources as $source) {
                if (
                    preg_match(
                        '/calliope[\s._-]+(\d+)[\s._-]+(\d+)/i',
                        (string) $source,
                        $matches
                    )
               ) {
                    if ($volume === '') {
                        $volume = $matches[1];
                    }

                    if ($issue_number === '') {
                        $issue_number = $matches[2];
                    }

                    break;
                }
            }
        }

        $parts = [];

        if ($volume !== '') {
            $parts[] = 'Volume ' . $volume;
        }

        if ($issue_number !== '') {
            $parts[] = 'Number ' . $issue_number;
        }

        return implode(' • ', $parts);
    }
}
