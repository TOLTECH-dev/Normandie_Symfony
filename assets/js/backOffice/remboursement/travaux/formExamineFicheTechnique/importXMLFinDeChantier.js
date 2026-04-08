$(document).ready(function()
{   
    var idDocumentXMLFinDeChantier = $('#whitelabel_backofficebundle_remboursement__remboursement_travaux_ficheTechnique_ficheTechnique_finChantier_ficheTechniqueDocument');
    var idBoutonValider            = $("#whitelabel_backofficebundle_remboursement__valider");    
    var messageDocumentXML         = $("#custom-control_documentXML_finChantier");
    
    idDocumentXMLFinDeChantier.change(function(e){ 
        var file   = e.target.files[0];
        
        if (file && !validateFile(file, messageDocumentXML, idBoutonValider, 'xml')) {
            var reader = new FileReader();
            reader.readAsText(file); 
            
            reader.onload = function(e) {          
                var contentXML = e.target.result;
                var parserXML  = new DOMParser();
                var xmlDoc     = parserXML.parseFromString(contentXML,"text/xml");
                
                /*=============================================================*
                 *               GET DATA IN XMLDocument                       *
                 *=============================================================*/
                var surfaceSrt = xmlDoc
                    .getElementsByTagName("feuillet_batiment")[0]
                    .getElementsByTagName("donnees_generales")[0]
                    .getElementsByTagName("descriptif")[0]
                    .getElementsByTagName("shon")[0].firstChild;

                var surfaceHabitable = xmlDoc
                    .getElementsByTagName("feuillet_batiment")[0]
                    .getElementsByTagName("donnees_generales")[0]
                    .getElementsByTagName("descriptif")[0]
                    .getElementsByTagName("surface_utile")[0].firstChild;
                                
                var surfaceMur = xmlDoc
                    .getElementsByTagName("feuillet_batiment")[0]                                        
                    .getElementsByTagName("enveloppe_thermique")[0]
                    .getElementsByTagName("descriptif_parois")[0]                                        
                    .getElementsByTagName("surf_totales")[0]                                        
                    .getElementsByTagName("a1")[0]
                    .getElementsByTagName("projet")[0].firstChild;
                                
                var surfaceVert = xmlDoc
                    .getElementsByTagName("feuillet_batiment")[0]                                        
                    .getElementsByTagName("enveloppe_thermique")[0]
                    .getElementsByTagName("descriptif_parois")[0]                                        
                    .getElementsByTagName("surf_totales")[0]                                        
                    .getElementsByTagName("parois_vitrees_vert")[0]
                    .getElementsByTagName("projet")[0].firstChild;
                                
                var surfaceHor = xmlDoc
                    .getElementsByTagName("feuillet_batiment")[0]                                        
                    .getElementsByTagName("enveloppe_thermique")[0]
                    .getElementsByTagName("descriptif_parois")[0]                                        
                    .getElementsByTagName("surf_totales")[0]                                        
                    .getElementsByTagName("parois_vitrees_hor")[0]
                    .getElementsByTagName("projet")[0].firstChild;
                                
                var surfaceMenuiserie = parseFloat(surfaceVert.nodeValue)+parseFloat(surfaceHor.nodeValue);
                
                var surfacePlancher = xmlDoc
                    .getElementsByTagName("feuillet_batiment")[0]                                        
                    .getElementsByTagName("enveloppe_thermique")[0]
                    .getElementsByTagName("descriptif_parois")[0]                                        
                    .getElementsByTagName("surf_totales")[0]                                        
                    .getElementsByTagName("a2")[0]
                    .getElementsByTagName("projet")[0].firstChild;                
                
                var traitementPermeabilite = xmlDoc
                    .getElementsByTagName("feuillet_batiment")[0]                                        
                    .getElementsByTagName("enveloppe_thermique")[0]
                    .getElementsByTagName("descriptif_parois")[0]                                        
                    .getElementsByTagName("surf_totales")[0]                                        
                    .getElementsByTagName("coef_permea_air")[0]
                    .getElementsByTagName("projet")[0].firstChild;
                           
                var coeffCEP = xmlDoc
                    .getElementsByTagName("feuillet_batiment")[0]
                    .getElementsByTagName("donnees_generales")[0]
                    .getElementsByTagName("resultat_calcul_C")[0]
                    .getElementsByTagName("coeff_cep")[0]
                    .getElementsByTagName("projet")[0].firstChild; 
                        
                var resultatUbat = xmlDoc.getElementsByTagName("feuillet_batiment")[0]
                    .getElementsByTagName("enveloppe_thermique")[0]
                    .getElementsByTagName("descriptif_parois")[0]  
                    .getElementsByTagName("transmission_surfacique")[0]
                    .getElementsByTagName("Ubat")[0]
                    .getElementsByTagName("projet")[0].firstChild;             
              
                /*=============================================================*
                 *     FILL IN THE FORM FIELD (Fin de Chantier)                *
                 *=============================================================*/
                $("#whitelabel_backofficebundle_remboursement__remboursement_travaux_ficheTechnique_ficheTechnique_finChantier_surfaceSRT").val(surfaceSrt.nodeValue);
                $("#whitelabel_backofficebundle_remboursement__remboursement_travaux_ficheTechnique_ficheTechnique_finChantier_surfaceHabitable").val(surfaceHabitable.nodeValue);
                $("#whitelabel_backofficebundle_remboursement__remboursement_travaux_ficheTechnique_ficheTechnique_finChantier_mursSurface").val(surfaceMur.nodeValue);
                $("#whitelabel_backofficebundle_remboursement__remboursement_travaux_ficheTechnique_ficheTechnique_finChantier_menuiseriesExterieuresSurface").val(surfaceMenuiserie);
                $("#whitelabel_backofficebundle_remboursement__remboursement_travaux_ficheTechnique_ficheTechnique_finChantier_plancherBasSurface").val(surfacePlancher.nodeValue);
                $("#whitelabel_backofficebundle_remboursement__remboursement_travaux_ficheTechnique_ficheTechnique_finChantier_CEP").val(coeffCEP.nodeValue);
                $("#whitelabel_backofficebundle_remboursement__remboursement_travaux_ficheTechnique_ficheTechnique_finChantier_CEPUbat").val(resultatUbat.nodeValue);
                $("#whitelabel_backofficebundle_remboursement__remboursement_travaux_ficheTechnique_ficheTechnique_finChantier_CEPQ4Pa_surf").val(traitementPermeabilite.nodeValue);

                calculCEP();
                calculUbat();
            };
        }          
    });
});



