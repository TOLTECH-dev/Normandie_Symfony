const Encore = require('@symfony/webpack-encore');

// Manually configure the runtime environment if not already configured yet by the "encore" command.
// It's useful when you use tools that rely on webpack.config.js file.
if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

// Import des fichiers partiels pour les entrées et les styles
require('./webpack.config.entries')(Encore);
require('./webpack.config.styles')(Encore);

Encore
    // directory where compiled assets will be stored
    .setOutputPath('public/build/')
    // public path used by the web server to access the output path
    .setPublicPath('/build')
    // only needed for CDN's or subdirectory deploy
    //.setManifestKeyPrefix('build/')

    // When enabled, Webpack "splits" your files into smaller pieces for greater optimization.
    .splitEntryChunks()
    .enableSingleRuntimeChunk()
    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    // Noms de fichiers versionnés en développement comme en production : sans cela, les fichiers
    // gardent le même nom d'une compilation à l'autre et les navigateurs servent une ancienne
    // feuille de style ou un ancien script depuis leur cache, y compris après changement de
    // branche. Les chemins sont résolus par public/build/manifest.json (cf. webpack_encore.yaml),
    // donc asset() et encore_entry_*_tags suivent automatiquement.
    .enableVersioning()
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = '3.38';
    })
    .enableSassLoader()
    .enableLessLoader()
    .autoProvidejQuery()
    .copyFiles({
            from: './assets/images', to: 'images/[path][name].[ext]'
        }
    )
    .copyFiles({
            from: './assets/json', to: 'json/[path][name].[ext]'
        }
    );

module.exports = Encore.getWebpackConfig();
