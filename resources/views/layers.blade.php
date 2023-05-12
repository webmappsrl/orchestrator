<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <!-- Fonts -->
        <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">

        <!-- Styles -->
        <style>
            /*! normalize.css v8.0.1 | MIT License | github.com/necolas/normalize.css */
            html {
                line-height: 1.15;
                -webkit-text-size-adjust: 100%
                height:300px;
            }

            body {
                margin: 0
            }

            a {
                background-color: transparent
            }

            [hidden] {
                display: none
            }

            html {
                font-family: system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica Neue, Arial, Noto Sans, sans-serif, Apple Color Emoji, Segoe UI Emoji, Segoe UI Symbol, Noto Color Emoji;
                line-height: 1.5
            }

            *,
            :after,
            :before {
                box-sizing: border-box;
                border: 0 solid #e2e8f0
            }

            a {
                color: inherit;
                text-decoration: inherit
            }

            svg,
            video {
                display: block;
                vertical-align: middle
            }

            video {
                max-width: 100%;
                height: auto
            }

            .bg-white {
                --bg-opacity: 1;
                background-color: #fff;
                background-color: rgba(255, 255, 255, var(--bg-opacity))
            }

            .bg-gray-100 {
                --bg-opacity: 1;
                background-color: #f7fafc;
                background-color: rgba(247, 250, 252, var(--bg-opacity))
            }

            .border-gray-200 {
                --border-opacity: 1;
                border-color: #edf2f7;
                border-color: rgba(237, 242, 247, var(--border-opacity))
            }

            .border-t {
                border-top-width: 1px
            }

            .flex {
                display: flex
            }

            .grid {
                display: grid
            }

            .hidden {
                display: none
            }

            .items-center {
                align-items: center
            }

            .justify-center {
                justify-content: center
            }

            .font-semibold {
                font-weight: 600
            }

            .h-5 {
                height: 1.25rem
            }

            .h-8 {
                height: 2rem
            }

            .h-16 {
                height: 4rem
            }

            .text-sm {
                font-size: .875rem
            }

            .text-lg {
                font-size: 1.125rem
            }

            .leading-7 {
                line-height: 1.75rem
            }

            .mx-auto {
                margin-left: auto;
                margin-right: auto
            }

            .ml-1 {
                margin-left: .25rem
            }

            .mt-2 {
                margin-top: .5rem
            }

            .mr-2 {
                margin-right: .5rem
            }

            .ml-2 {
                margin-left: .5rem
            }

            .mt-4 {
                margin-top: 1rem
            }

            .ml-4 {
                margin-left: 1rem
            }

            .mt-8 {
                margin-top: 2rem
            }

            .ml-12 {
                margin-left: 3rem
            }

            .-mt-px {
                margin-top: -1px
            }

            .max-w-6xl {
                max-width: 72rem
            }

            .min-h-screen {
                min-height: 100vh
            }

            .overflow-hidden {
                overflow: hidden
            }

            .p-6 {
                padding: 1.5rem
            }

            .py-4 {
                padding-top: 1rem;
                padding-bottom: 1rem
            }

            .px-6 {
                padding-left: 1.5rem;
                padding-right: 1.5rem
            }

            .pt-8 {
                padding-top: 2rem
            }

            .fixed {
                position: fixed
            }

            .relative {
                position: relative
            }

            .top-0 {
                top: 0
            }

            .right-0 {
                right: 0
            }

            .shadow {
                box-shadow: 0 1px 3px 0 rgba(0, 0, 0, .1), 0 1px 2px 0 rgba(0, 0, 0, .06)
            }

            .text-center {
                text-align: center
            }

            .text-gray-200 {
                --text-opacity: 1;
                color: #edf2f7;
                color: rgba(237, 242, 247, var(--text-opacity))
            }

            .text-gray-300 {
                --text-opacity: 1;
                color: #e2e8f0;
                color: rgba(226, 232, 240, var(--text-opacity))
            }

            .text-gray-400 {
                --text-opacity: 1;
                color: #cbd5e0;
                color: rgba(203, 213, 224, var(--text-opacity))
            }

            .text-gray-500 {
                --text-opacity: 1;
                color: #a0aec0;
                color: rgba(160, 174, 192, var(--text-opacity))
            }

            .text-gray-600 {
                --text-opacity: 1;
                color: #718096;
                color: rgba(113, 128, 150, var(--text-opacity))
            }

            .text-gray-700 {
                --text-opacity: 1;
                color: #4a5568;
                color: rgba(74, 85, 104, var(--text-opacity))
            }

            .text-gray-900 {
                --text-opacity: 1;
                color: #1a202c;
                color: rgba(26, 32, 44, var(--text-opacity))
            }

            .underline {
                text-decoration: underline
            }

            .antialiased {
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale
            }

            .w-5 {
                width: 1.25rem
            }

            .w-8 {
                width: 2rem
            }

            .w-auto {
                width: auto
            }

            .grid-cols-1 {
                grid-template-columns: repeat(1, minmax(0, 1fr))
            }

            @media (min-width:640px) {
                .sm\:rounded-lg {
                    border-radius: .5rem
                }

                .sm\:block {
                    display: block
                }

                .sm\:items-center {
                    align-items: center
                }

                .sm\:justify-start {
                    justify-content: flex-start
                }

                .sm\:justify-between {
                    justify-content: space-between
                }

                .sm\:h-20 {
                    height: 5rem
                }

                .sm\:ml-0 {
                    margin-left: 0
                }

                .sm\:px-6 {
                    padding-left: 1.5rem;
                    padding-right: 1.5rem
                }

                .sm\:pt-0 {
                    padding-top: 0
                }

                .sm\:text-left {
                    text-align: left
                }

                .sm\:text-right {
                    text-align: right
                }
            }

            @media (min-width:768px) {
                .md\:border-t-0 {
                    border-top-width: 0
                }

                .md\:border-l {
                    border-left-width: 1px
                }

                .md\:grid-cols-2 {
                    grid-template-columns: repeat(2, minmax(0, 1fr))
                }
            }

            @media (min-width:1024px) {
                .lg\:px-8 {
                    padding-left: 2rem;
                    padding-right: 2rem
                }
            }

            @media (prefers-color-scheme:dark) {
                .dark\:bg-gray-800 {
                    --bg-opacity: 1;
                    background-color: #2d3748;
                    background-color: rgba(45, 55, 72, var(--bg-opacity))
                }

                .dark\:bg-gray-900 {
                    --bg-opacity: 1;
                    background-color: #1a202c;
                    background-color: rgba(26, 32, 44, var(--bg-opacity))
                }

                .dark\:border-gray-700 {
                    --border-opacity: 1;
                    border-color: #4a5568;
                    border-color: rgba(74, 85, 104, var(--border-opacity))
                }

                .dark\:text-white {
                    --text-opacity: 1;
                    color: #fff;
                    color: rgba(255, 255, 255, var(--text-opacity))
                }

                .dark\:text-gray-400 {
                    --text-opacity: 1;
                    color: #cbd5e0;
                    color: rgba(203, 213, 224, var(--text-opacity))
                }
            }
        </style>

        <style>
            body {
                font-family: 'Nunito', sans-serif;
            }
            .comment {
                color:green;
                background-color:white;
            }
            .workin {
                color:white;
                background-color:green;
            }
        </style>
 <script type="text/javascript">
            window.copyLayer =   function() {
             /* Get the text field */
             var copyText = 'ciao'
                   console.log('ciao')
           
              /* Copy the text inside the text field */
             navigator.clipboard.writeText(copyText);
           
             /* Alert the copied text */
             alert("Copied the text: " + copyText);
           }
        </script>
    </head>


            <div class="max-w-12xl mx-auto sm:px-12 lg:px-12">
                <div class="mt-8 bg-white dark:bg-gray-800 overflow-hidden shadow sm:rounded-lg">
                    <div class="grid grid-cols-1">
                        <div class="p-12">
       <!--              <div class="flex items-center">
                                <div class="ml-12 text-lg leading-7 font-semibold">
                                    <button class="w-full btn btn-default btn-primary hover:bg-primary-dark"onclick="window.copyLayer()">
                                        copy Layers
                                    </button>
                                </div>
                            </div> -->
                            <div class="ml-12">
                                <div class="mt-12 text-gray-600 dark:text-gray-400 text-sm">
                                    <code>
                                    {
                                        "HOME": [<br>
                                        <span class="comment">**"box_type": "title" permette di inserire un testo che verra visualizzato dentro un h1**</span><br>
                                        <span style="margin-left:10px">
                                            {"box_type": "title","title": "Titolo"},<br>
                                        </span>
                                        <span class="comment">**"box_type": "layer" permette di inserire box layer, di seguito sono generati tutti i box layers della app**</span><br>
                                        @foreach($layers as $layer)
                                        <span style="margin-left:10px">
                                            {
                                                "box_type": "layer",
                                                "title": "{{ $layer->title }}",
                                                "layer": {{ $layer->id }}
                                            },<br>
                                        </span>
                                            @endforeach
                                        <span class="workin">**WEBAPP**</span><br>
                                        <span class="comment">**"box_type": "slug" permette di inserire la pagina project**</span><br>
                                        <span style="margin-left:10px">
                                            {<br>
                                                "box_type": "slug",<br>
                                            "title": "pagina progetto",<br>
                                            "slug":"project", //non modificare<br>
                                            "image_url": "https://webmapp.it/wp-content/uploads/2022/02/mappadigitalesentieroitalia-min.gif"<br>
                                            },<br>
                                        </span>
                                        <span class="comment">**"box_type": "external_url" permette di inserire un box che aprirà un url**</span><br>
                                        <span style="margin-left:10px">
                                            {<br>
                                                "box_type": "external_url",<br>
                                                "title": "Chi siamo",<br>
                                                "image_url": "https://ecmedia.s3.eu-central-1.amazonaws.com/EcMedia/Resize/225x100/615_225x100.jpg",<br>
                                                "url": "http://www.fumaiolosentieri.it/chi-siamo/"<br>
                                              },<br>
                                        </span>
                                        <span class="comment">**"box_type": "base" permette di inserire gallerie di tracks a scorrimento laterale**</span><br>
                                        <span style="margin-left:10px">
                                            {<br>
                                                "box_type": "base",<br>
                                                "title": "Itinerari scelti",<br>
                                                "items": [{<br>
                                                    "title": "La Sorgente del Tevere e il Giro dei Rifugi",<br>
                                                    "image_url": "https://ecmedia.s3.eu-central-1.amazonaws.com/EcMedia/Resize/225x100/639_225x100.jpg",<br>
                                                    "track_id": 1559<br>
                                                  },<br>
                                                  {<br>
                                                    "title": "La cascata dell'Alferello",<br>
                                                    "image_url": "https://ecmedia.s3.eu-central-1.amazonaws.com/EcMedia/Resize/225x100/642_225x100.jpg",<br>
                                                    "track_id": 1561<br>
                                                  },<br>
                                                  {<br>
                                                    "title": "I luoghi dello spirito",<br>
                                                    "image_url": "https://ecmedia.s3.eu-central-1.amazonaws.com/EcMedia/Resize/225x100/638_225x100.jpg",<br>
                                                    "track_id": 1558<br>
                                                  }]<br>
                                              }<br>
                                        </span>
                                            ]}
                                    </code>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
</html>