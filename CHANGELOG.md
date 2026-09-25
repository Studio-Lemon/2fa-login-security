# Changelog

## 2.0.0-beta.1 (2026-09-25)


### ⛰️ Features

* add Controller_TFAuthLS for managing two-factor authentication ([c3effac](https://github.com/Studio-Lemon/2fa-login-security/commit/c3effac48b8e202de9bb8d0fd60e96eb6077dad2))
* add Dutch translation ([71ae98b](https://github.com/Studio-Lemon/2fa-login-security/commit/71ae98b4a719f35d8aca074107bea819833497c0))
* add YahnisElsts PluginUpdateChecker ([def38d9](https://github.com/Studio-Lemon/2fa-login-security/commit/def38d90848bee0577e4046200c58fc4e75784a2))
* cleanup duplicate css so that there is no overlap between the global and admin css. ([713c4a1](https://github.com/Studio-Lemon/2fa-login-security/commit/713c4a1f9e8d438653c0e571e1d43a97a9201f5b))


### 🐛 Bug Fixes

* recover some needed css ([be9f11f](https://github.com/Studio-Lemon/2fa-login-security/commit/be9f11f32a3ba11b6c286bed8dcc52d341689f55))
* update controller file reference and remove copyright notice ([fe6cfe8](https://github.com/Studio-Lemon/2fa-login-security/commit/fe6cfe89f1510ac30484784a710be223e651d0db))
* update PHP requirement from 7.0 to 8.1 for compatibility ([189ed47](https://github.com/Studio-Lemon/2fa-login-security/commit/189ed4770a88ff5b30d4bf7587e01ccd78b78a15))
* update POT-Creation-Date and correct line references in translation file ([7dfb4e8](https://github.com/Studio-Lemon/2fa-login-security/commit/7dfb4e875d387f5c3f9386d8177f92596ec66094))
* update translation object to a name that works ([c91c37f](https://github.com/Studio-Lemon/2fa-login-security/commit/c91c37f058505fa3a14636198302f3481828a755))


### 🚜 Refactor

* complete 2fa-login-security text-domain rename ([02b7fd6](https://github.com/Studio-Lemon/2fa-login-security/commit/02b7fd6ee5d75f2db27cb6e0eba300f8456a4980))
* improve code formatting and consistency in JavaScript controller ([d37955f](https://github.com/Studio-Lemon/2fa-login-security/commit/d37955ffaf9db1814522ce40992e7d6e84d5a58c))
* initialize 2fa fork bootstrap and disable legacy integrations ([eaaafab](https://github.com/Studio-Lemon/2fa-login-security/commit/eaaafab02ba0af8af635bd498b0bbdbf28b61ddd))
* remove allowlist 2fa bypass ([cd84f1b](https://github.com/Studio-Lemon/2fa-login-security/commit/cd84f1b5919be7dde0316e74a5a59582f294c122))
* remove font awesome dependency ([5f611a9](https://github.com/Studio-Lemon/2fa-login-security/commit/5f611a92455b2698e1548f63c4c70e9e96a41369))
* remove recaptcha subsystem ([b03b93f](https://github.com/Studio-Lemon/2fa-login-security/commit/b03b93f0993cd7498f9637afbb7698e4f1daf8d1))
* remove redundant translation string for WooCommerce customer role ([0bdb275](https://github.com/Studio-Lemon/2fa-login-security/commit/0bdb275bb8d6c5a570819a862aba6e30b8d9c14b))
* remove remaining wordfence core references ([c61273e](https://github.com/Studio-Lemon/2fa-login-security/commit/c61273e48ac9141bcda20f319cf3d0472a4372d8))
* remove shortcode management feature ([225c10b](https://github.com/Studio-Lemon/2fa-login-security/commit/225c10b785a00aa53ffd9a48c43355ed11f38a27))
* remove unused '.wordfenceTopTab.active' style for cleaner CSS ([7ed0e4e](https://github.com/Studio-Lemon/2fa-login-security/commit/7ed0e4ec97ce8b6c09e206452772ca29c22cfc8c))
* remove unused assets, classes, and code paths for cleaner project structure ([1fc2e43](https://github.com/Studio-Lemon/2fa-login-security/commit/1fc2e4327095b89ba80970ae0aab65cc693d2e24))
* remove unused Controller_Javascript class and related i18n script files for cleaner project structure ([b66a3e7](https://github.com/Studio-Lemon/2fa-login-security/commit/b66a3e74b8054fed20b3e2d70ea38b76f48bac76))
* remove unused cron controller for cleaner codebase ([7ce1a65](https://github.com/Studio-Lemon/2fa-login-security/commit/7ce1a65ab19ee27b687c14e62e6f550866db997f))
* remove unused CSS file for cleaner codebase ([c0b7613](https://github.com/Studio-Lemon/2fa-login-security/commit/c0b7613a69393611e4fe37ad34d8045af2d28dad))
* remove unused image assets for cleaner project structure ([8041c73](https://github.com/Studio-Lemon/2fa-login-security/commit/8041c73307fb8974f6b6bd285c42be9841818ef9))
* remove unused ionicons font file for cleaner project structure ([c3d90a0](https://github.com/Studio-Lemon/2fa-login-security/commit/c3d90a0dfac4cf242aa7b01212db3293abfe9a5f))
* remove unused javascript controller file for cleaner project structure ([6815e2e](https://github.com/Studio-Lemon/2fa-login-security/commit/6815e2e2b83deaea69cacf48253f857eff31695f))
* remove unused persistent notice dismissal mechanism and related code ([414aa50](https://github.com/Studio-Lemon/2fa-login-security/commit/414aa5036ab2e84d3f913dccd66843d7e3cf7fa7))
* remove unused styles for cleaner CSS ([c066ded](https://github.com/Studio-Lemon/2fa-login-security/commit/c066dedd3fb3c4b2738dd764990f246e65cf601b))
* remove woocommerce integration paths ([8f57bfa](https://github.com/Studio-Lemon/2fa-login-security/commit/8f57bfa49d9862198bcb8a0eb48f5ab6f8573f7a))
* remove XML-RPC options and related code for enhanced security ([a7e9774](https://github.com/Studio-Lemon/2fa-login-security/commit/a7e97741d1350f79d3f3e5cdb9a5c2f0997d5eef))
* rename 'wordfence-ls' to '2fa-ls' for consistency across scripts and styles ([63ac034](https://github.com/Studio-Lemon/2fa-login-security/commit/63ac0340c63f6421e211d5103736087a52969c42))
* rename phpcs and phpcbf scripts for consistency and clarity ([2b301aa](https://github.com/Studio-Lemon/2fa-login-security/commit/2b301aa7b35b6de99905b95f2a871a86eb944413))
* replace vue settings with native admin ui ([284501a](https://github.com/Studio-Lemon/2fa-login-security/commit/284501aca70587e9beeee42bf9d09393b9898122))
* update package names in docblocks and improve code formatting ([c4f0ec7](https://github.com/Studio-Lemon/2fa-login-security/commit/c4f0ec746dbcb6c21f7e5215505ed46b1af11852))
* update support URLs for better clarity and consistency ([28d6fa0](https://github.com/Studio-Lemon/2fa-login-security/commit/28d6fa01530e3a54af7ac3bbde170e05541fffeb))
* update translation object name for consistency ([c393e9a](https://github.com/Studio-Lemon/2fa-login-security/commit/c393e9ab65debf1127b6eb69333c9984bb0e8e30))


### 📚 Documentation

* add how-to guide for enabling two-factor authentication ([2de1b14](https://github.com/Studio-Lemon/2fa-login-security/commit/2de1b148d11ea53cf0f1153b39a8573c9a5493eb))
* update README and how-to guide for clarity and accuracy ([a6ca15c](https://github.com/Studio-Lemon/2fa-login-security/commit/a6ca15c0f238c616a5587302fa8a3a8055d404ef))


### ⚙️ Miscellaneous Tasks

* add rector ([4994a59](https://github.com/Studio-Lemon/2fa-login-security/commit/4994a5973321b2ae678ad11b5e4ae2c035650d17))
* baseline snapshot from wordfence-login-security 1.1.16 ([443fd0b](https://github.com/Studio-Lemon/2fa-login-security/commit/443fd0b6309fa2a17e2ddd240d20176a70a3c53b))
* cleanup ([8c7cf38](https://github.com/Studio-Lemon/2fa-login-security/commit/8c7cf38ce54499c1760983dc8b289a0b7c792914))
* cleanup dead code ([3151b50](https://github.com/Studio-Lemon/2fa-login-security/commit/3151b50f2278b9cad766cd4a3b9524de3a83c780))
* cleanup dead css ([9bf7d63](https://github.com/Studio-Lemon/2fa-login-security/commit/9bf7d63697609248f684590843a5805b8e1a871b))
* remove icon font ([b1f1d41](https://github.com/Studio-Lemon/2fa-login-security/commit/b1f1d41bc043989f0bf8bf1b00168fd771de5eb0))
* remove unused .wfls-notice styles for cleaner CSS ([ebae0c2](https://github.com/Studio-Lemon/2fa-login-security/commit/ebae0c27dec15b0be3048011996664ff1133dbde))
* remove unused constants and update support URL to GitHub ([5380c6d](https://github.com/Studio-Lemon/2fa-login-security/commit/5380c6d39b3a6b105f377d4b991bdcac3b29ec92))
* run rector ([9d73000](https://github.com/Studio-Lemon/2fa-login-security/commit/9d73000a025ea6671241e36778982ac6baa7e8b7))
* run rector ([1cffa9a](https://github.com/Studio-Lemon/2fa-login-security/commit/1cffa9a7a7637f197879655db861e088efac50d3))
* scaffold source css and js assets ([434e821](https://github.com/Studio-Lemon/2fa-login-security/commit/434e8212b4b3e0f3350a4cf5dd0089419e29249e))
* snapshot current refactor and switch to enqueue-based asset versioning ([9486e26](https://github.com/Studio-Lemon/2fa-login-security/commit/9486e2618bb3f6cc318508fa7543a9be612a9862))
* update 2FA code label and tooltip text for clarity ([f4bd100](https://github.com/Studio-Lemon/2fa-login-security/commit/f4bd1002d96e3b1b64e293e180d9fba57cdce583))
* update composer and add phpcs configuration for improved coding standards ([1c8680a](https://github.com/Studio-Lemon/2fa-login-security/commit/1c8680ab4406e294ca659b4a11cc1adb1bea6f38))
* update package.json to include translation scripts ([4988edc](https://github.com/Studio-Lemon/2fa-login-security/commit/4988edc9ffaef441c1ff15e7a482c9fdf8b115d3))
