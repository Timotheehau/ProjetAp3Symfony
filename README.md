

#  Guide de Tests API - Sports Platform

##  Configuration de base

**Base URL** : `http://localhost:8000`

Pour chaque requête dans Postman :
1. Headers → Add `Content-Type: application/json`
2. Body → Sélectionnez "raw" et "JSON"

---

##  1. USERS - Gestion des utilisateurs

###  GET - Liste des utilisateurs
```
GET http://localhost:8000/api/users
```

**Headers** : Aucun
**Body** : Aucun
**Réponse attendue** : Liste de tous les utilisateurs

---

###  GET - Détails d'un utilisateur
```
GET http://localhost:8000/api/users/1
```

**Headers** : Aucun
**Body** : Aucun
**Réponse attendue** : Détails de l'utilisateur ID 1

---

###  POST - Créer un utilisateur
```
POST http://localhost:8000/api/users
```

**Headers** :
```
Content-Type: application/json
```

**Body (JSON)** :
```json
{
  "email": "nouveau@example.com",
  "password": "password123",
  "firstName": "Nouveau",
  "lastName": "Utilisateur",
  "phone": "0612345678",
  "userType": "particular"
}
```

**Réponse attendue** :
- Status Code: 201 Created
- Utilisateur créé avec mot de passe hashé

---

###  PUT - Modifier un utilisateur
```
PUT http://localhost:8000/api/users/1
```

**Headers** :
```
Content-Type: application/json
```

**Body (JSON)** :
```json
{
  "firstName": "Jean Modifié",
  "lastName": "Dupont Modifié",
  "phone": "0698765432"
}
```

**Réponse attendue** : Utilisateur mis à jour

---

###  DELETE - Supprimer un utilisateur
```
DELETE http://localhost:8000/api/users/10
```

**Headers** : Aucun
**Body** : Aucun
**Réponse attendue** :
- Status Code: 200 OK
- Message de confirmation

---

##  2. PROFILES - Profils professionnels

###  GET - Liste des profils (sans filtre)
```
GET http://localhost:8000/api/profiles
```

**Réponse attendue** : Liste de tous les profils vérifiés et actifs

---

###  GET - Profils filtrés par sport
```
GET http://localhost:8000/api/profiles?sport=1
```

**Réponse attendue** : Profils liés au sport ID 1 (Football)

---

###  GET - Profils filtrés par spécialité
```
GET http://localhost:8000/api/profiles?specialty=coach
```

**Valeurs possibles** : `coach`, `referee`, `health_specialist`

---

###  GET - Profils filtrés par niveau
```
GET http://localhost:8000/api/profiles?level=pro
```

**Valeurs possibles** : `pro`, `semi-pro`, `amateur`

---

###  GET - Profils filtrés par ville
```
GET http://localhost:8000/api/profiles?city=Paris
```

---

###  GET - Profils avec note minimale
```
GET http://localhost:8000/api/profiles?minRating=4
```

---

###  GET - Filtres combinés
```
GET http://localhost:8000/api/profiles?specialty=coach&level=pro&city=Paris&minRating=4
```

---

###  GET - Détails d'un profil
```
GET http://localhost:8000/api/profiles/1
```

**Réponse attendue** : Détails complets du profil avec coordonnées GPS, certifications, etc.

---

###  POST - Créer un profil professionnel
```
POST http://localhost:8000/api/profiles
```

**Body (JSON)** :
```json
{
  "userId": 2,
  "specialty": "coach",
  "level": "pro",
  "bio": "Coach professionnel avec 10 ans d'expérience",
  "yearsOfExperience": 10,
  "hourlyRate": 50,
  "city": "Paris",
  "address": "10 Rue du Sport",
  "latitude": 48.8566,
  "longitude": 2.3522,
  "certifications": ["BPJEPS", "Diplôme d'État"],
  "diplomas": ["Master STAPS"],
  "sports": [1, 2, 3]
}
```

**Réponse attendue** :
- Status Code: 201 Created
- Profil créé (isVerified: false, en attente de validation admin)

---

###  PUT - Modifier un profil
```
PUT http://localhost:8000/api/profiles/1
```

**Body (JSON)** :
```json
{
  "bio": "Bio mise à jour avec plus de détails",
  "hourlyRate": 60,
  "yearsOfExperience": 12,
  "sports": [1, 3, 5]
}
```

---

###  POST - Vérifier un profil (Admin uniquement)
```
POST http://localhost:8000/api/profiles/1/verify
```

**Body** : Aucun
**Réponse attendue** : Profil marqué comme vérifié

---

###  DELETE - Supprimer un profil
```
DELETE http://localhost:8000/api/profiles/1
```

---

##  3. BOOKINGS - Réservations

###  GET - Liste de toutes les réservations
```
GET http://localhost:8000/api/bookings
```

---

###  GET - Réservations d'un client
```
GET http://localhost:8000/api/bookings?userId=1
```

---

###  GET - Réservations d'un professionnel
```
GET http://localhost:8000/api/bookings?profileId=1
```

---

###  GET - Réservations par statut
```
GET http://localhost:8000/api/bookings?status=confirmed
```

**Valeurs possibles** : `pending`, `confirmed`, `cancelled`, `completed`

---

###  GET - Détails d'une réservation
```
GET http://localhost:8000/api/bookings/1
```

---

###  POST - Créer une réservation
```
POST http://localhost:8000/api/bookings
```

**Body (JSON)** :
```json
{
  "clientId": 1,
  "profileId": 1,
  "venueId": 1,
  "startTime": "2025-01-20 10:00:00",
  "endTime": "2025-01-20 12:00:00",
  "notes": "Séance de coaching intensif"
}
```

**Réponse attendue** :
- Status Code: 201 Created
- Réservation créée avec prix calculé automatiquement
- Vérification de disponibilité automatique

**Cas d'erreur** :
- Si le professionnel n'est pas disponible → Status 409 Conflict

---

###  PATCH - Changer le statut d'une réservation
```
PATCH http://localhost:8000/api/bookings/1/status
```

**Body (JSON)** :
```json
{
  "status": "confirmed"
}
```

**Pour annuler** :
```json
{
  "status": "cancelled",
  "cancellationReason": "Indisponibilité du client"
}
```

**Pour compléter** :
```json
{
  "status": "completed"
}
```

---

###  PUT - Modifier une réservation
```
PUT http://localhost:8000/api/bookings/1
```

**Body (JSON)** :
```json
{
  "startTime": "2025-01-21 14:00:00",
  "endTime": "2025-01-21 16:00:00",
  "notes": "Horaire modifié"
}
```

**Note** : Impossible de modifier une réservation complétée ou annulée

---

###  DELETE - Annuler une réservation
```
DELETE http://localhost:8000/api/bookings/1
```

**Réponse attendue** : Réservation marquée comme annulée (pas supprimée)

---

##  4. TESTS DE VALIDATION

###  TEST 1 : Créer un utilisateur sans email
```
POST http://localhost:8000/api/users
```

**Body (JSON)** :
```json
{
  "password": "password123",
  "firstName": "Test",
  "lastName": "User"
}
```

**Réponse attendue** :
- Status Code: 400 Bad Request
- Message d'erreur : "L'email est obligatoire"

---

###  TEST 2 : Créer une réservation avec conflit d'horaire
```
POST http://localhost:8000/api/bookings
```

1. Créez d'abord une réservation :
```json
{
  "clientId": 1,
  "profileId": 1,
  "startTime": "2025-01-25 10:00:00",
  "endTime": "2025-01-25 12:00:00"
}
```

2. Essayez d'en créer une autre qui chevauche :
```json
{
  "clientId": 2,
  "profileId": 1,
  "startTime": "2025-01-25 11:00:00",
  "endTime": "2025-01-25 13:00:00"
}
```

**Réponse attendue** :
- Status Code: 409 Conflict
- Message : "Le professionnel n'est pas disponible à cette période"

---

###  TEST 3 : Email en doublon
```
POST http://localhost:8000/api/users
```

**Body (JSON)** :
```json
{
  "email": "user1@example.com",
  "password": "password123",
  "firstName": "Doublon",
  "lastName": "Test"
}
```

**Réponse attendue** :
- Status Code: 400 Bad Request
- Erreur de validation sur l'email

---

## 📝 5. SCÉNARIOS COMPLETS

### Scénario 1 : Parcours client complet

**1. Rechercher des coachs de football à Paris**
```
GET http://localhost:8000/api/profiles?specialty=coach&city=Paris&sport=1
```

**2. Voir le profil détaillé d'un coach**
```
GET http://localhost:8000/api/profiles/1
```

**3. Créer une réservation**
```
POST http://localhost:8000/api/bookings
Body: {
  "clientId": 1,
  "profileId": 1,
  "startTime": "2025-01-22 14:00:00",
  "endTime": "2025-01-22 16:00:00"
}
```

**4. Vérifier ses réservations**
```
GET http://localhost:8000/api/bookings?userId=1
```

**5. Modifier la réservation**
```
PATCH http://localhost:8000/api/bookings/[ID]/status
Body: { "status": "confirmed" }
```

---

### Scénario 2 : Parcours professionnel

**1. Créer un compte utilisateur pro**
```
POST http://localhost:8000/api/users
Body: {
  "email": "moncoach@example.com",
  "password": "password123",
  "firstName": "Pierre",
  "lastName": "Martin",
  "userType": "professional"
}
```

**2. Créer son profil professionnel**
```
POST http://localhost:8000/api/profiles
Body: {
  "userId": [ID retourné],
  "specialty": "coach",
  "level": "pro",
  ...
}
```

**3. Voir ses réservations**
```
GET http://localhost:8000/api/bookings?profileId=[ID]
```

**4. Confirmer une réservation**
```
PATCH http://localhost:8000/api/bookings/[ID]/status
Body: { "status": "confirmed" }
```

**5. Marquer comme complétée**
```
PATCH http://localhost:8000/api/bookings/[ID]/status
Body: { "status": "completed" }
```

---

##  CHECKLIST DE TESTS

### Users 
- [ ] GET liste des users
- [ ] GET un user spécifique
- [ ] POST créer un user
- [ ] PUT modifier un user
- [ ] DELETE supprimer un user
- [ ] Validation : email obligatoire
- [ ] Validation : email unique

### Profiles 
- [ ] GET liste des profils
- [ ] GET avec filtres (sport, specialty, level, city)
- [ ] GET profil détaillé
- [ ] POST créer un profil
- [ ] PUT modifier un profil
- [ ] POST vérifier un profil
- [ ] DELETE supprimer un profil

### Bookings 
- [ ] GET liste des réservations
- [ ] GET avec filtres (userId, profileId, status)
- [ ] GET réservation détaillée
- [ ] POST créer une réservation
- [ ] Vérification automatique de disponibilité
- [ ] Calcul automatique du prix
- [ ] PATCH changer le statut
- [ ] PUT modifier une réservation
- [ ] DELETE annuler une réservation
- [ ] Validation : pas de modification si complétée
- [ ] Validation : conflit d'horaire

---

##  DEBUG : En cas d'erreur

### Erreur 404 - Route not found
```bash
# Vérifier les routes disponibles
php bin/console debug:router

# Vérifier une route spécifique
php bin/console debug:router users_list
```

### Erreur 500 - Internal Server Error
```bash
# Voir les logs détaillés
tail -f var/log/dev.log

# Ou activer le mode debug
# Dans .env : APP_ENV=dev
```

### Vérifier la base de données
```bash
# Lister les utilisateurs
php bin/console dbal:run-sql "SELECT * FROM user LIMIT 5"

# Compter les profils
php bin/console dbal:run-sql "SELECT COUNT(*) as total FROM profile"
```

---

##  Importer dans Postman

Vous pouvez créer une collection Postman et organiser vos requêtes par dossier :

```
 Sports Platform API
   Users
     GET All Users
     GET User by ID
     POST Create User
     PUT Update User
     DELETE User
   Profiles
     GET All Profiles
     GET Profile by ID
     POST Create Profile
     ...
   Bookings
     GET All Bookings
     ...
```

---

## Prochaines étapes

Une fois tous les tests passés :
1.  Implémenter l'authentification JWT
2.  Créer les controllers restants (Availabilities, SessionHistory, Admin)
3.  Ajouter la pagination
4.  Documenter l'API avec Swagger
