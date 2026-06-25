const path = require('path')
const { VueLoaderPlugin } = require('vue-loader')

module.exports = {
  mode:   process.env.NODE_ENV || 'production',
  entry: {
    'ncaclmanager-files':    path.join(__dirname, 'src', 'files.js'),
    'ncaclmanager-settings': path.join(__dirname, 'src', 'settings.js'),
  },
  output: {
    path:     path.resolve(__dirname, 'js'),
    filename: '[name].js',
    clean:    true,
  },
  resolve: {
    extensions: ['.js', '.vue'],
    alias: {
      // NC предоставляет Vue глобально, но для сборки нам нужен локальный
      vue: path.resolve(__dirname, 'node_modules/vue/dist/vue.esm-bundler.js'),
    },
  },
  module: {
    rules: [
      {
        test: /\.vue$/,
        loader: 'vue-loader',
      },
      {
        test: /\.js$/,
        loader: 'babel-loader',
        exclude: /node_modules/,
        options: {
          presets: ['@babel/preset-env'],
        },
      },
      {
        test: /\.css$/,
        use: ['style-loader', 'css-loader'],
      },
      {
        test: /\.svg$/,
        type: 'asset/inline',
      },
    ],
  },
  plugins: [new VueLoaderPlugin()],
  // NC уже предоставляет axios и другие библиотеки глобально
  // не бандлим их — берём снаружи
  externals: {
    '@nextcloud/axios':   'OC.requestToken !== undefined ? window.axios : undefined',
    '@nextcloud/router':  'OC',
    '@nextcloud/l10n':    'OC',
    '@nextcloud/auth':    'OC',
    '@nextcloud/dialogs': 'OC.dialogs',
  },
}
