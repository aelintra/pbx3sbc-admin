import preset from './vendor/filament/filament/tailwind.config.preset'

/** Filament admin theme (Tailwind v3). Used by: npm run build:filament-theme */
export default {
    presets: [preset],
    content: [
        './app/Filament/**/*.php',
        './resources/views/filament/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
    ],
}
