# Gemini QRQC Problem Solver

## Description

Cette extension WordPress fournit une application web interactive d'une seule page pour la résolution de problèmes selon la méthodologie QRQC (Quick Response Quality Control).

L'application guide les utilisateurs à travers un dialogue avec l'IA Gemini pour collecter des informations sur un problème d'entreprise, structurer l'analyse et générer un rapport PDF complet incluant un plan d'actions.

## Fonctionnalités

* **Dialogue guidé :** L'IA pose des questions séquentielles basées sur les méthodes QRQC, QQOQPC, QCDSM et 5 Pourquoi.
* **Génération de rapport PDF :** Un rapport d'analyse structuré est généré et téléchargeable au format PDF.
* **Clé API sécurisée :** La clé API est stockée sur le serveur via un fichier PHP, jamais exposée côté client.
* **Stockage des rapports :** L'utilisateur peut consentir au stockage anonyme du rapport sur le site, pour servir de base d'apprentissage future.
* **Avertissement de confidentialité :** Une mise en garde est affichée pour rappeler de ne pas saisir de données sensibles.
* **Analyse flexible :** L'IA peut proposer des hypothèses de causes et d'actions pour aider l'utilisateur à affiner son diagnostic.

## Installation

1.  Téléchargez les fichiers de l'extension.
2.  Dans le dossier `gem_prox_qrqc/includes/`, créez un fichier `api-key.php`.
3.  Dans ce fichier, remplacez `VOTRE_CLE_API_GEMINI_ICI` par votre vraie clé API Gemini.
4.  Uploadez le dossier `gem_prox_qrqc` dans le répertoire `wp-content/plugins/` de votre site WordPress.
5.  Activez l'extension "Gemini QRQC Problem Solver" depuis votre tableau de bord WordPress.
6.  Utilisez le shortcode `[gemini_qrqc_app]` pour afficher l'application sur n'importe quelle page ou article.

## Utilisation

* Sur la page de l'application, décrivez votre problème dans la zone de texte.
* L'IA vous posera des questions pour vous aider à analyser le problème.
* Répondez aux questions pour progresser dans l'analyse.
* Une fois l'analyse terminée, cliquez sur "Générer le rapport PDF".
* Si vous avez coché la case de consentement, le rapport sera stocké sur le serveur et pourra être utilisé anonymement pour des usages de formation.

## Contribution

Ce projet est sous licence GPL2. Les contributions sont les bienvenues.

--- 
Extension créée par Gemini 2.5 Flash
