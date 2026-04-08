const fs = require('fs');
const path = require('path');

// Liste des dossiers à inclure sous assets/js
const includedFolders = ['backOffice', 'frontOffice'];

function addJsEntriesRecursively(Encore, dir, baseDir) {
    fs.readdirSync(dir, { withFileTypes: true }).forEach(entry => {
        const fullPath = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            addJsEntriesRecursively(Encore, fullPath, baseDir);
        } else if (entry.isFile() && entry.name.endsWith('.js')) {
            // Nom d'entrée basé sur le chemin relatif, sans extension .js
            const relPath = path.relative(baseDir, fullPath).replace(/\\/g, '/');
            const entryName = relPath.replace(/\.js$/, '').replace(/\//g, '_');
            Encore.addEntry(entryName, `./assets/js/${path.relative(baseDir, fullPath).replace(/\\/g, '/')}`);
        }
    });
}

module.exports = (Encore) => {
    // Entrées statiques existantes
    Encore
        .addEntry('main', './assets/main.js')
        .addEntry('backOffice', './assets/backOffice.js')
        .addEntry('frontOffice', './assets/frontOffice.js')
        .addEntry('app', './assets/app.js')
        .addEntry('coreList', './assets/js/backOffice/core_list/index.js')
        // .addEntry('coreList', './assets/js/backOffice/newsletter/index.js')
;
    // Ajout récursif pour chaque dossier inclus
    const jsBaseDir = path.resolve(__dirname, 'assets/js');
    includedFolders.forEach(folder => {
        const folderPath = path.join(jsBaseDir, folder);
        if (fs.existsSync(folderPath) && fs.statSync(folderPath).isDirectory()) {
            addJsEntriesRecursively(Encore, folderPath, jsBaseDir);
        }
    });
};
