const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

module.exports = (env, argv) => {
    const isProduction = argv.mode === 'production';

    return {
        entry: {
            admin: './assets/js/admin.js',
            upload: './assets/js/upload.js',
            'admin-style': './assets/css/admin.scss',
            'frontend-style': './assets/css/frontend.scss'
        },
        output: {
            path: path.resolve(__dirname, 'assets/dist'),
            filename: 'js/[name].js',
            clean: true
        },
        module: {
            rules: [
                {
                    test: /\.js$/,
                    exclude: /node_modules/,
                    use: {
                        loader: 'babel-loader',
                        options: {
                            presets: ['@babel/preset-env']
                        }
                    }
                },
                {
                    test: /\.scss$/,
                    use: [
                        MiniCssExtractPlugin.loader,
                        'css-loader',
                        'sass-loader'
                    ]
                },
                {
                    test: /\.(png|jpg|jpeg|gif|svg)$/,
                    type: 'asset/resource',
                    generator: {
                        filename: 'images/[name][ext]'
                    }
                }
            ]
        },
        plugins: [
            new MiniCssExtractPlugin({
                filename: 'css/[name].css'
            })
        ],
        devtool: isProduction ? false : 'source-map',
        optimization: {
            minimize: isProduction
        },
        externals: {
            jquery: 'jQuery'
        }
    };
};
