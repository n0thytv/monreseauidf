# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Mon Réseau IDF is a French public transportation information portal for Île-de-France Mobilités (IDFM). It provides real-time transit information, line schedules, traffic updates, and point-of-sale locations for the Paris region transport network.

## Tech Stack

- **Backend**: Vanilla PHP (no framework), MySQL/MariaDB via PDO
- **Frontend**: HTML5, CSS3 with custom properties, vanilla JavaScript, Leaflet.js for maps
- **External APIs**: IDFM Open Data API, PRIM API for real-time departures
- **Server**: WAMP/LAMP stack

## Development Setup

1. Place files in WAMP webroot (e.g., `C:\wamp64\www\monreseauidf`)
2. Import database schema: `database/schema.sql` into MySQL
3. Configure database credentials in `includes/config.php`
4. Configure IDFM API key via admin panel (`/admin/settings.php`)
5. Access at `http://localhost/monreseauidf`

**Default admin credentials**: admin@monreseauidf.fr / admin123

## Architecture

```
Browser Request → PHP Page → includes/header.php
                          → MySQL via PDO (functions.php)
                          → includes/footer.php → Response

API Request → api/*.php → Check cache (6-hour TTL)
                       → Fetch from IDFM API via cURL
                       → Return JSON with CORS headers
```

### Key Directories

- `admin/` - Admin panel (dashboard, user management, settings)
- `api/` - REST endpoints returning JSON (lignes, horaires, prochains-passages, trace-ligne, points-de-vente)
- `includes/` - Shared code (config.php, functions.php, header/footer templates)
- `database/` - SQL schema
- `cache/` - API response cache
- `pdv/` - Point of sale data files (JSON, GeoJSON, Parquet)

### Core Files

- `includes/config.php` - Database connection, constants, session init
- `includes/functions.php` - Auth helpers, user CRUD, settings CRUD, flash messages
- `css/style.css` - Complete design system with CSS variables (IDFM color palette)

### Database Tables

- `users` - id, username, email, password, role ('user'|'admin'), timestamps
- `settings` - key-value store for configuration (API keys, site metadata)

## Authentication

Session-based authentication with role-based access control:
- `isLoggedIn()` - Check if user is authenticated
- `isAdmin()` - Check if user has admin role
- Password hashing via `password_hash()` / `password_verify()`

## API Endpoints

All endpoints return JSON with CORS headers enabled:
- `GET /api/lignes.php` - All transport lines (cached 6 hours)
- `GET /api/prochains-passages.php?stopId=X` - Real-time next departures
- `GET /api/horaires.php` - Schedule information
- `GET /api/trace-ligne.php?lineId=X` - Line geometry for maps
- `GET /api/points-de-vente.php` - Point of sale locations

## Coding Conventions

- Comments and variables in French
- Procedural PHP (no OOP framework)
- PDO prepared statements for all database queries
- Input sanitization via `sanitize()` function (htmlspecialchars)
- Flash messages for user feedback (`setFlashMessage()`, `getFlashMessage()`)
