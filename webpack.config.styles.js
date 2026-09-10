const fs = require('fs');
const path = require('path');

// Liste des dossiers à inclure sous assets/styles
const includedFolders = ['backOffice', 'frontOffice'];

function addCssEntriesRecursively(Encore, dir, baseDir) {
    fs.readdirSync(dir, { withFileTypes: true }).forEach(entry => {
        const fullPath = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            addCssEntriesRecursively(Encore, fullPath, baseDir);
        } else if (entry.isFile() && entry.name.endsWith('.css')) {
            // Nom d'entrée basé sur le chemin relatif, sans extension .css
            const relPath = path.relative(baseDir, fullPath).replace(/\\/g, '/');
            const entryName = relPath.replace(/\.css$/, '').replace(/\//g, '_');
            Encore.addStyleEntry(entryName, `./assets/styles/${relPath}`);
        }
    });
}

module.exports = (Encore) => {
    // Entrées statiques existantes
    Encore
        .addStyleEntry('register', './assets/styles/main/registration/register.css')
        .addStyleEntry('resettingReset', './assets/styles/main/resetting/reset.css')
        .addStyleEntry('partenaireList', './assets/styles/backOffice/partenaire/list.css')
        .addStyleEntry('ratingView', './assets/styles/backOffice/rating/view.css')

    // Ajout récursif pour chaque dossier inclus
    const stylesBaseDir = path.resolve(__dirname, 'assets/styles');
    includedFolders.forEach(folder => {
        const folderPath = path.join(stylesBaseDir, folder);
        if (fs.existsSync(folderPath) && fs.statSync(folderPath).isDirectory()) {
            addCssEntriesRecursively(Encore, folderPath, stylesBaseDir);
        }
    });
};
