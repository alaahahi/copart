<!DOCTYPE >
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" html style="direction: rtl;">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title inertia>Car Sale</title>
        <link rel="stylesheet" href="https://fonts.bunny.net/css2?family=Nunito:wght@400;600;700&display=swap">
        <script type="module" src="https://cdn.jsdelivr.net/npm/@duetds/date-picker@1.3.0/dist/duet/duet.esm.js"></script>
<script nomodule src="https://cdn.jsdelivr.net/npm/@duetds/date-picker@1.3.0/dist/duet/duet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@revolist/revo-dropdown@latest/dist/revo-dropdown/revo-dropdown.js"></script>
        @routes
        @vite('resources/js/app.js')
        @inertiaHead
        <style>
            .spaner{
                display: flex;
                justify-content: center;
                margin: 25px ;
            }
            body::-webkit-scrollbar {
                width: 12px;
                }

                body::-webkit-scrollbar-track {
                background: #f1f1f1;
                }

                body::-webkit-scrollbar-thumb {
                background: #888;
                border-radius: 6px;
                }

                /* Style the scrollbars for Firefox */
                body {
                scrollbar-width: thin;
                scrollbar-color: #888 #f1f1f1;
                }
                .hydrated::-webkit-scrollbar {
                width: 12px;
                }

                .hydrated::-webkit-scrollbar-track {
                background: #f1f1f1;
                }

                .hydrated::-webkit-scrollbar-thumb {
                background: #888;
                border-radius: 6px;
                }
                .scroll-rgCol
                /* Style the scrollbars for Firefox */
                .hydrated {
                scrollbar-width: thin;
                scrollbar-color: #888 #f1f1f1;
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
