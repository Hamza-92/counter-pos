const mix = require('laravel-mix');


/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

const MomentLocalesPlugin = require('moment-locales-webpack-plugin');
const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const tailwindcss = require('tailwindcss');
const autoprefixer = require('autoprefixer');


mix.js('resources/src/main.js', 'public')
    .js('resources/src/login.js', 'public')
    .js('resources/src/portal.js', 'public')
    .js('resources/src/customer-display.js', 'public')
    // Storefront bundle (Alpine.js + Tailwind). Isolated from admin Vue app.
    .js('resources/src/storefront.js', 'public')
    .postCss('resources/css/storefront.css', 'public/css', [
        tailwindcss('./tailwind.config.js'),
        autoprefixer(),
    ])
    .vue()

    mix.webpackConfig({
        resolve: {
            alias: {
                '@': __dirname + '/resources/src'
            }
        },
        stats: {
            children: true
        },
        output: {
          
            filename:'js/[name].min.js',
            chunkFilename: 'js/bundle/[name].[hash].js',
          },
        module: {
            rules: [
                {
                    test: /\.scss$/,
                    use: [
                        {
                            loader: 'sass-loader',
                            options: {
                                sassOptions: {
                                    quietDeps: true,
                                    silenceDeprecations: ['legacy-js-api', 'import', 'global-builtin', 'color-functions', 'slash-div']
                                }
                            }
                        }
                    ]
                }
            ]
        },
        plugins: [
            new MomentLocalesPlugin(),
            new CleanWebpackPlugin({
                cleanOnceBeforeBuildPatterns: ['./js/*']
              }),
        ]
    });

