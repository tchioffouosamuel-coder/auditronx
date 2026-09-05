<?php

namespace Database\Seeders;

use App\Models\Accreditation;
use App\Models\Classe;
use App\Models\Discipline;
use App\Models\Enseignant;
use App\Models\Ferie;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Import ponctuel de l'ancien système (RFID + fingerprint, base
 * "u332279927_auditron") vers le schéma QR actuel — à lancer une seule fois :
 *
 *   php artisan db:seed --class=LegacyImportSeeder
 *
 * Le dump legacy (database/seeders/legacy/auditron_dump.sql, réencodé et
 * préfixé `legacy_*`) est d'abord chargé tel quel dans des tables de transit,
 * puis transformé ligne à ligne vers les modèles actuels (Accreditation,
 * Classe, Discipline, Enseignant, Ferie, User) — les tables de transit sont
 * supprimées à la fin, qu'il s'agisse d'un succès ou d'une erreur.
 *
 * Champs de l'ancien système sans équivalent dans le schéma QR (rfid_uid,
 * anciennete, prise_de_service, dob, pob, region_or, dept_or, arr_or,
 * sit_mat, `section` des classes, `description`/`isTP` des disciplines) sont
 * volontairement ignorés : ce système n'en a pas l'usage.
 *
 * Idempotent : rejouable sans dupliquer (updateOrCreate partout, clé sur
 * l'identifiant "naturel" le plus fiable — matricule/tel/email selon le cas).
 */
class LegacyImportSeeder extends Seeder
{
    public function run(): void
    {
        $this->loadLegacyDump();

        try {
            $accreditationMap = $this->importAccreditations();
            $classesCount = $this->importClasses();
            $disciplinesCount = $this->importDisciplines();
            $feriesCount = $this->importFeries();
            $enseignantsCount = $this->importEnseignants();
            $usersCount = $this->importUsers($accreditationMap);

            $this->command?->info("Accréditations : ".count($accreditationMap));
            $this->command?->info("Classes : {$classesCount}");
            $this->command?->info("Disciplines : {$disciplinesCount}");
            $this->command?->info("Fériés : {$feriesCount}");
            $this->command?->info("Enseignants : {$enseignantsCount} (mot de passe par défaut ChangeMe123! pour ceux sans compte préexistant)");
            $this->command?->info("Comptes backoffice (users) : {$usersCount} (mots de passe d'origine conservés)");
        } finally {
            $this->dropLegacyTables();
        }
    }

    private function loadLegacyDump(): void
    {
        $this->createLegacyTables();

        $path = __DIR__.'/legacy/auditron_dump.sql';
        $sql = file_get_contents($path);

        // Le fichier mysqldump ne contient que du DML standard pour les INSERT
        // (identifiants entre backticks, acceptés par SQLite aussi) — seul le
        // DDL MySQL des CREATE TABLE (bigint(20) UNSIGNED, ENGINE=...) n'est
        // pas portable : les tables de transit sont donc créées via le Schema
        // builder ci-dessous, et seules les lignes INSERT INTO sont rejouées.
        $statements = array_filter(
            array_map('trim', explode(";\n", $sql)),
            fn ($s) => str_starts_with($s, 'INSERT INTO')
        );

        foreach ($statements as $statement) {
            DB::unprepared($statement.';');
        }
    }

    private function createLegacyTables(): void
    {
        $this->dropLegacyTables();

        Schema::create('legacy_accreditations', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('nom');
            $table->string('groupe');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('legacy_classes', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('nom');
            $table->string('code')->nullable();
            $table->string('niveau')->nullable();
            $table->string('specialite')->nullable();
            $table->integer('effectif')->nullable()->default(0);
            $table->string('section')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('legacy_disciplines', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('nom');
            $table->string('code')->nullable();
            $table->decimal('coefficient', 5, 2)->nullable()->default(1);
            $table->text('description')->nullable();
            $table->string('departement')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->integer('isTP')->default(0);
        });

        Schema::create('legacy_enseignants', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('nom');
            $table->string('email')->nullable();
            $table->string('rfid_uid')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('fonction')->nullable();
            $table->string('specialite')->nullable();
            $table->string('matricule')->nullable();
            $table->string('section')->nullable();
            $table->string('grade')->nullable();
            $table->integer('anciennete')->nullable();
            $table->date('prise_de_service')->nullable();
            $table->string('dob')->nullable();
            $table->string('pob')->nullable();
            $table->string('region_or')->nullable();
            $table->string('dept_or')->nullable();
            $table->string('arr_or')->nullable();
            $table->string('prise_de_service2')->nullable();
            $table->string('tel')->nullable();
            $table->string('sit_mat')->nullable();
            $table->integer('poste')->default(1);
        });

        Schema::create('legacy_feries', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->date('date');
            $table->string('libelle');
            $table->text('description')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('legacy_users', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name');
            $table->string('email');
            $table->unsignedBigInteger('accreditation_id')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('remember_token')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('tel')->nullable();
        });
    }

    private function dropLegacyTables(): void
    {
        foreach (['accreditations', 'classes', 'disciplines', 'enseignants', 'feries', 'users'] as $table) {
            Schema::dropIfExists("legacy_{$table}");
        }
    }

    /** @return array<int, Accreditation> ancien id -> modèle créé */
    private function importAccreditations(): array
    {
        $map = [];
        foreach (DB::table('legacy_accreditations')->get() as $row) {
            $map[$row->id] = Accreditation::updateOrCreate(
                ['label' => $row->nom],
                ['groupe' => $row->groupe]
            );
        }

        return $map;
    }

    private function importClasses(): int
    {
        $used = [];
        $n = 0;
        foreach (DB::table('legacy_classes')->orderBy('id')->get() as $row) {
            $code = $this->uniqueCode($row->code, $row->nom, $row->id, $used);

            Classe::updateOrCreate(
                ['nom' => $row->nom],
                [
                    'code' => $code,
                    'niveau' => $row->niveau,
                    'specialite' => $row->specialite,
                    'effectif' => $row->effectif ?? 0,
                ]
            );
            $n++;
        }

        return $n;
    }

    private function importDisciplines(): int
    {
        $used = [];
        $n = 0;
        foreach (DB::table('legacy_disciplines')->orderBy('id')->get() as $row) {
            $code = $this->uniqueCode($row->code, $row->nom, $row->id, $used);

            Discipline::updateOrCreate(
                ['nom' => $row->nom],
                [
                    'code' => $code,
                    'coefficient' => (int) round((float) $row->coefficient),
                    'departement' => $row->departement,
                ]
            );
            $n++;
        }

        return $n;
    }

    private function importFeries(): int
    {
        $n = 0;
        foreach (DB::table('legacy_feries')->get() as $row) {
            Ferie::updateOrCreate(
                ['date' => $row->date, 'libelle' => $row->libelle],
                ['description' => $row->description]
            );
            $n++;
        }

        return $n;
    }

    private function importEnseignants(): int
    {
        $usedMatricules = Enseignant::pluck('matricule')->flip()->all();
        $usedEmails = Enseignant::whereNotNull('email')->pluck('email')->flip()->all();
        $usedTels = Enseignant::whereNotNull('tel')->pluck('tel')->flip()->all();

        $n = 0;
        foreach (DB::table('legacy_enseignants')->orderBy('id')->get() as $row) {
            $matricule = $this->sanitizeMatricule($row->matricule, $row->id, $usedMatricules);
            $email = $this->sanitizeUnique($row->email, $usedEmails, fn ($v) => str_contains($v, '@') && str_contains($v, '.'));
            $tel = $this->sanitizeUnique($this->sanitizeTel($row->tel), $usedTels);

            Enseignant::updateOrCreate(
                ['matricule' => $matricule],
                [
                    'nom' => trim($row->nom),
                    'email' => $email,
                    'fonction' => $row->fonction,
                    'section' => $row->section,
                    'grade' => $row->grade,
                    'tel' => $tel,
                    'poste' => (string) $row->poste,
                    // Ancien système : authentification par carte RFID, pas de mot de
                    // passe. Le mobile Auditron X exige tel+password : mot de passe
                    // par défaut à faire changer par l'enseignant à sa première connexion.
                    'password' => Hash::make('ChangeMe123!'),
                ]
            );
            $n++;
        }

        return $n;
    }

    /** @param array<int, Accreditation> $accreditationMap */
    private function importUsers(array $accreditationMap): int
    {
        $n = 0;
        foreach (DB::table('legacy_users')->get() as $row) {
            User::updateOrCreate(
                ['email' => $row->email],
                [
                    'name' => $row->name,
                    // Hash bcrypt déjà au format Laravel ($2y$) : le cast 'hashed' du
                    // modèle ne le re-hash pas (Hash::isHashed() le détecte).
                    'password' => $row->password,
                    'accreditation_id' => $accreditationMap[$row->accreditation_id]->id ?? null,
                    'email_verified_at' => $row->email_verified_at,
                ]
            );
            $n++;
        }

        return $n;
    }

    /** @param array<string, true> $used */
    private function uniqueCode(?string $code, string $nom, int $legacyId, array &$used): string
    {
        $candidate = $code ?: Str::upper(Str::slug($nom, '_'));
        $candidate = Str::limit($candidate, 40, '');

        if ($candidate === '' || isset($used[$candidate])) {
            $candidate = 'LEG-'.$legacyId;
        }

        $used[$candidate] = true;

        return $candidate;
    }

    /** @param array<string, true> $used */
    private function sanitizeMatricule(?string $matricule, int $legacyId, array &$used): string
    {
        $candidate = trim((string) $matricule);

        if ($candidate === '' || isset($used[$candidate])) {
            $candidate = 'LEGACY-'.$legacyId;
        }

        $used[$candidate] = true;

        return $candidate;
    }

    /** @param array<string, true> $used */
    private function sanitizeUnique(?string $value, array &$used, ?callable $isValid = null): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || ($isValid && ! $isValid($value)) || isset($used[$value])) {
            return null;
        }

        $used[$value] = true;

        return $value;
    }

    /** Ne garde que le premier numéro d'un champ tel parfois "double" ("a/b"). */
    private function sanitizeTel(?string $tel): ?string
    {
        if (! $tel) {
            return null;
        }

        return trim(explode('/', $tel)[0]);
    }
}
