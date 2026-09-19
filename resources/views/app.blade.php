<!DOCTYPE >
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl" style="direction: rtl;">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title inertia>{{ \App\Support\Branding::name() }}</title>
        <link rel="stylesheet" href="https://fonts.bunny.net/css2?family=Nunito:wght@400;600;700&display=swap">
        <script type="module" src="https://cdn.jsdelivr.net/npm/@duetds/date-picker@1.3.0/dist/duet/duet.esm.js"></script>
<script nomodule src="https://cdn.jsdelivr.net/npm/@duetds/date-picker@1.3.0/dist/duet/duet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@revolist/revo-dropdown@latest/dist/revo-dropdown/revo-dropdown.js"></script>
        @routes
        <script>window.__PUBLIC_ASSET_PREFIX__ = @json(\App\Helpers\Help::publicWebPrefix());</script>
        @vite('resources/js/app.js')
        @inertiaHead
        <style>
            .spaner{
                display: flex;
                justify-content: center;
                margin: 25px ;
            }
            html, body {
                scrollbar-width: thin;
                scrollbar-color: #94a3b8 #e2e8f0;
            }
            html::-webkit-scrollbar,
            body::-webkit-scrollbar,
            .hydrated::-webkit-scrollbar {
                width: 10px;
                height: 10px;
            }
            html::-webkit-scrollbar-track,
            body::-webkit-scrollbar-track,
            .hydrated::-webkit-scrollbar-track {
                background: #e2e8f0;
            }
            html::-webkit-scrollbar-thumb,
            body::-webkit-scrollbar-thumb,
            .hydrated::-webkit-scrollbar-thumb {
                background: #94a3b8;
                border-radius: 999px;
                border: 2px solid #e2e8f0;
            }
            html.dark,
            html.dark body {
                scrollbar-color: #475569 #0b1220;
            }
            html.dark::-webkit-scrollbar-track,
            html.dark body::-webkit-scrollbar-track,
            html.dark .hydrated::-webkit-scrollbar-track {
                background: #0b1220;
            }
            html.dark::-webkit-scrollbar-thumb,
            html.dark body::-webkit-scrollbar-thumb,
            html.dark .hydrated::-webkit-scrollbar-thumb {
                background: #475569;
                border-color: #0b1220;
            }
            html.dark::-webkit-scrollbar-thumb:hover,
            html.dark body::-webkit-scrollbar-thumb:hover {
                background: #64748b;
            }
            .hydrated {
                scrollbar-width: thin;
                scrollbar-color: #94a3b8 #e2e8f0;
            }
            html.dark .hydrated {
                scrollbar-color: #475569 #0b1220;
            }
            .overflow-x-auto,
            .overflow-y-auto,
            .overflow-auto {
                scrollbar-width: thin;
                scrollbar-color: #94a3b8 #e2e8f0;
            }
            .overflow-x-auto::-webkit-scrollbar,
            .overflow-y-auto::-webkit-scrollbar,
            .overflow-auto::-webkit-scrollbar {
                width: 10px;
                height: 10px;
            }
            html.dark .overflow-x-auto,
            html.dark .overflow-y-auto,
            html.dark .overflow-auto {
                scrollbar-color: #475569 #0f172a;
            }
            html.dark .overflow-x-auto::-webkit-scrollbar-track,
            html.dark .overflow-y-auto::-webkit-scrollbar-track,
            html.dark .overflow-auto::-webkit-scrollbar-track {
                background: #0f172a;
            }
            html.dark .overflow-x-auto::-webkit-scrollbar-thumb,
            html.dark .overflow-y-auto::-webkit-scrollbar-thumb,
            html.dark .overflow-auto::-webkit-scrollbar-thumb {
                background: #475569;
                border-radius: 999px;
            }
                .Vue-Toastification__container {
                width: unset !important;
                }
                .duet-date__dialog {
                direction: ltr;
                    right: 0;
                    top: 44px;
                }
                .header-rgRow{
                text-align: center;
                }
                .rgRow > div {
                text-align: center !important;
                }
                .rgCell.disabled {
                    background-color: unset !important;
                }
                .rgCell{
                padding-top: 7px !important;
                }
                    .ui.fluid.search.selection.dropdown{
                    justify-content: revert!important;
                    display: flex!important;
                    min-height: 40px!important;
                }
                .ui.dropdown .menu .selected.item{
                    background-color: #e012035d !important;
                }
                .ui.dropdown .menu>.item {
                    text-align: right !important;
                }
        </style>
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
