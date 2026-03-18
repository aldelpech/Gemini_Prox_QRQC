# Changelog - Gemini QRQC Problem Solver

## Version 1.3
**18/03/2026 - Changement de version du modèle d'IA (était gemini-2.0-flash-exp devient gemini-1.5-flash en V1 **

### 🔧 **Système de maintenance automatique**
- **Détection intelligente des causes présupposées** : éviter de prendre les caus


## Version 1.2.3
**02/09/2025 - Amélioration des instructions à Gemini : un véritable assistant !**

### 🔧 **Système de maintenance automatique**
- **Détection intelligente des causes présupposées** : éviter de prendre les causes énoncées par l'utilisateur pour argent comptant trop tôt
- **Validation de la précision du problème initial** : évaluaer la précision, demander des exemples concrets, proposer d'aller recueillir des éléments complémentaires
- **QQOQCP adaptatif et intelligent** : questions uniquement sur les éléments manquants
- **Gestion optimisée des impacts QCDSM** : priorisation par l'utilisateur puis questionnement seulement sur les éléments manquants
- **Anti-piège "erreur humaine"** : creuser systématiquement les causes systémiques sous-jacentes lorsqu'il est question d'erreur humaine
- **Génération créative d'hypothèses** : proposer aussi des questions provocatrices (et si ...?)
- **Alerte données insuffisantes** : détection du manque de données factuelles
- 

## Version 1.2.2
**15/08/2025 - Système de maintenance automatique pour gestion quota API**

### 🔧 **Système de maintenance automatique**
- **Activation automatique** : Se déclenche automatiquement lors d'erreurs 429 (quota API dépassé)
- **Page de maintenance élégante** : Interface utilisateur informative avec compte à rebours en temps réel
- **Programmation intelligente** : Fin automatique à 1h du matin (remise à zéro des quotas Gemini)
- **Gestion manuelle** : Les administrateurs peuvent activer/désactiver le mode maintenance
- **Accès administrateur** : Les admins peuvent utiliser l'application même en mode maintenance

### 📧 **Notifications de maintenance**
- **Email automatique** : Notification immédiate de l'administrateur lors de l'activation
- **Détails complets** : Raison, durée prévue, liens d'administration
- **Types de maintenance** : Distinction entre activation automatique (quota) et manuelle

### 🎨 **Interface de maintenance**
- **Design professionnel** : Page avec animations, compte à rebours, et conseils pratiques
- **Responsive** : Adaptation mobile et desktop
- **Actualisation intelligente** : Détection automatique de la fin de maintenance
- **Sauvegarde temporaire** : Conservation automatique des conversations en cours

### ⚙️ **Administration du mode maintenance**
- **Panneau de contrôle** : Interface dédiée dans les paramètres administrateur
- **Durées configurables** : Maintenance de 1h à 48h
- **Statut en temps réel** : Affichage du temps restant avec compte à rebours
- **Logs détaillés** : Enregistrement de toutes les activations/désactivations

### 🤖 **Gestion côté utilisateur**
- **Messages spécialisés** : Textes différenciés pour les erreurs de quota
- **Récupération automatique** : Proposition de restaurer les conversations interrompues
- **Alerte visuelle** : Styling spécial pour les messages de maintenance
- **Redirection douce** : Proposition d'actualiser vers la page de maintenance

### 🔄 **Fonctionnement automatique**
```
Erreur 429 détectée 
    ↓
Activation immédiate du mode maintenance
    ↓  
Email envoyé à l'administrateur
    ↓
Page de maintenance affichée aux utilisateurs
    ↓
Désactivation automatique à 1h du matin
    ↓
Retour en service normal
```

### 📋 **Fonctionnalités techniques**
- **Détection d'erreur 429** : Reconnaissance automatique du code "RESOURCE_EXHAUSTED"
- **Programmation cron** : Vérification horaire du statut de maintenance
- **Gestion timezone** : Calcul correct pour 1h du matin locale
- **Fallback sécurisé** : Accès admin toujours garanti
- **Performance** : Impact minimal sur les performances générales

---

## Version 1.2.1
**15/08/2025 - Corrections critiques du monitoring et de la gestion d'erreurs**

### 🐛 **Corrections de bugs critiques**
- **Statistiques manquantes** : Correction de l'erreur "Undefined array key api_requests" dans stats-tracker.php
- **Messages d'erreur non affichés** : Les erreurs sont maintenant correctement transmises à l'utilisateur avec des messages compréhensibles
- **Logs non enregistrés** : Correction de l'enregistrement des erreurs et mise à jour des statistiques en temps réel
- **Gestion des clés manquantes** : Initialisation par défaut de toutes les métriques (0 si non définies)

---

## Version 1.2.0
**15/08/2025 - Améliorations majeures de robustesse et monitoring**

### 🚨 Nouvelles fonctionnalités de monitoring
- **Gestion d'erreurs avancée** : Logging automatique de toutes les erreurs avec contexte détaillé
- **Notifications email** : Envoi automatique d'emails à l'administrateur en cas d'erreur
- **Suivi statistiques** : Dashboard complet des métriques d'utilisation
- **Logs d'erreur** : Interface d'administration pour consulter et filtrer les erreurs
- **Configuration email** : Paramètre pour définir l'email administrateur recevant les alertes

---

## Version 1.1.2
**11/08/2025 12h15 - Améliorations UX avec Claude (Sonnet 4)**

### 🎨 Améliorations de l'interface utilisateur
- **Modification du style des boutons** : Design plus moderne et cohérent
- **Largeur des boutons réduite** : Meilleur rendu sur mobile et desktop
- **Tooltip explicative** : Aide contextuelle pour la sauvegarde de discussion
- **Message détaillé après sauvegarde** : Instructions complètes pour reprendre l'analyse

---

## Cas d'usage du système de maintenance

### 🕐 **Scénario typique (quota API dépassé)**
1. **9h du matin** : Utilisateur intensive de l'application
2. **14h30** : Quota API Gemini gratuit atteint (erreur 429)
3. **14h30** : Activation automatique du mode maintenance
4. **14h31** : Email envoyé à l'administrateur
5. **14h32** : Page de maintenance affichée aux nouveaux visiteurs
6. **01h00 (lendemain)** : Désactivation automatique
7. **01h01** : Application de nouveau disponible

### 🔧 **Scénario maintenance manuelle**
1. **Administrateur** : Active maintenance pour 6h (mise à jour serveur)
2. **Utilisateurs** : Voient la page de maintenance avec compte à rebours
3. **Administrateur** : Continue à utiliser l'application normalement
4. **Fin programmée** : Désactivation automatique après 6h
5. **Alternative** : Désactivation manuelle anticipée possible

### 📱 **Expérience utilisateur**
- **Message clair** : Explication de la situation (quota/maintenance)
- **Informations utiles** : Conseils pour préparer l'analyse en attendant
- **Compte à rebours** : Temps restant affiché en temps réel
- **Pas de frustration** : Explication pédagogique du fonctionnement

---

## Tests recommandés pour v1.2.2

### ✅ **Test du mode maintenance automatique**
```bash
# Simuler une erreur 429 en modifiant temporairement la clé API
1. Modifier la clé API pour provoquer une erreur
2. Démarrer une analyse QRQC
3. Vérifier l'activation automatique du mode maintenance
4. Contrôler l'email reçu par l'administrateur
5. Tester l'accès admin pendant la maintenance
6. Vérifier la page de maintenance pour les utilisateurs normaux
```

### ✅ **Test du mode maintenance manuel**
```bash
1. Aller dans Paramètres > Mode maintenance
2. Activer pour 1 heure
3. Vérifier la page de maintenance côté utilisateur
4. Tester l'accès admin (doit fonctionner)
5. Désactiver manuellement
6. Vérifier le retour en service normal
```

### ✅ **Test de la récupération de conversation**
```bash
1. Démarrer une analyse QRQC (quelques échanges)
2. Activer le mode maintenance manuellement
3. Recharger la page
4. Vérifier la proposition de récupération automatique
5. Tester la restauration de la conversation
```

---

## Support et dépannage

### 🆘 **En cas de problème avec la maintenance**
- **Maintenance bloquée** : Désactivation manuelle via l'administration
- **Emails non reçus** : Vérifier la configuration SMTP de WordPress
- **Page maintenance non affichée** : Vider le cache du site
- **Récupération conversation** : Utiliser la fonction de sauvegarde manuelle

### 📞 **Contacts en cas d'urgence**
- Accès admin toujours disponible via `/wp-admin/`
- Logs complets dans l'administration WordPress
- Possibilité de désactiver le plugin temporairement si nécessaire

---

*Dernière mise à jour : 15 août 2025*