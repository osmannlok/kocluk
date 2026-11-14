import { defineConfig } from 'vite';
      import laravel from 'laravel-vite-plugin';

      export default defineConfig({
          plugins: [
              laravel({
                  input: [
                      'resources/js/main.js',
                      'resources/sass/app.scss',
                      'resources/css/style.css' // Eğer style.css ayrı bir dosya olarak kullanılacaksa
                  ],
                  refresh: true,
              }),
          ],
      });
