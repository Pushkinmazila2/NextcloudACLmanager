const webpackConfig = require('@nextcloud/webpack-vue-config')
const path = require('path')

webpackConfig.entry = {
  'ncaclmanager-files':    path.join(__dirname, 'src', 'files.js'),
  'ncaclmanager-settings': path.join(__dirname, 'src', 'settings.js'),
}

module.exports = webpackConfig
