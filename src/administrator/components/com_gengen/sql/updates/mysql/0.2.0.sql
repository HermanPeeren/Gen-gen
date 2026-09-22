-- Step 3.4: a generator is written for a metalanguage.

-- Every metalanguage this site has imported. A generator is written *for* one
-- of them - a rule names concepts, and a concept only means anything inside a
-- language - and says which by key and version.
--
-- The files are not in here. They are unpacked under `form_root`, which is what
-- the package's own manifest says it needs; this table is the index over them.
-- Exten-gen keeps its own copy of this table for the same reason it keeps its
-- own projects: two components, two stores, one shared format.
CREATE TABLE IF NOT EXISTS `#__gengen_metalanguages` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `lang_key` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `version` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `root` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `form_root` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `language_file` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    `manifest` mediumtext COLLATE utf8mb4_unicode_ci,
    `imported` datetime DEFAULT NULL,
    `published` tinyint(1) DEFAULT '1',
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_language` (`lang_key`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Which metalanguage this generator is written for. A generator that names no
-- language is one written before 3.4, against whatever the target happened to
-- offer - which is what an empty value means and what 3.6 replaces.
ALTER TABLE `#__gengen_generators`
    ADD COLUMN `metalanguage_key` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    ADD COLUMN `metalanguage_version` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '';
