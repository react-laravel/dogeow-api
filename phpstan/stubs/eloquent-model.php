<?php

// We declare the namespace first; PHP requires namespace declarations to
// appear before executable code.  Inside the namespace we guard the model
// declaration to avoid redeclaration errors when larastan's own stub is
// also loaded.

namespace Illuminate\Database\Eloquent {
    if (! class_exists(Model::class)) {
        /**
         * Minimal stub for PHPStan to understand Eloquent dynamic accessors/methods.
         * PHPStan will load this file only during static analysis.
         *
         * The real Model class is loaded at runtime; we only declare common
         * magic to avoid false positives about undefined properties and methods.
         *
         * @property mixed $id
         * @property mixed $name
         * @property mixed $email
         * @property mixed $user_id
         * @property mixed $path
         * @property mixed $slot
         * @property mixed $skill
         * @property mixed $is_active
         * @property mixed $created_by
         * @property mixed $map_id
         * @property mixed $monster_id
         * @property mixed $victory
         * @property mixed $damage_dealt
         * @property mixed $damage_taken
         * @property mixed $experience_gained
         * @property mixed $copper_gained
         * @property mixed $duration_seconds
         * @property mixed $skills_used
         * @property mixed $thumbnail_url
         * @property mixed $example_sentences
         * @property mixed $educationLevels
         *
         * @method mixed getMonsters()
         * @method mixed getGemStats()
         * @method mixed getExperienceToNextLevel()
         */
        class Model
        {
            public function __get(string $name): mixed {}

            public function __call(string $name, array $arguments): mixed {}

            public function __set(string $name, $value): void {}
        }
    }
}
