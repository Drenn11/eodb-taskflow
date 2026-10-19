<?php
/**
 * EODB-TaskFlow - My Profile (all roles)
 *
 * Every user can:
 *   - view their account details (name, Employee ID, role, department, and
 *     position are managed by the Administrator)
 *   - change their email address
 *   - upload or remove a profile photo (JPG, PNG, or WEBP up to 2 MB)
 *   - change their password (current password required)
 * Each change is recorded in the activity log as "update_profile".
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/user_functions.php';
require_login();

const PHOTO_MAX_BYTES = 2 * 1024 * 1024;
const PHOTO_MAX_PIXELS = 4000;
const PHOTO_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

$user = current_user();
$userId = (int) $user['id'];
$errors = [];
$emailValue = $user['email'];

/** Deletes a stored profile photo file (only plain file names inside the upload folder). */
function delete_profile_photo_file(?string $photo): void
{
    if ($photo !== null && $photo !== '' && basename($photo) === $photo) {
        $path = PROFILE_UPLOAD_DIR . '/' . $photo;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

if (is_post()) {
    require_csrf();
    $action = post_str('action', 20);

    // ---- Email ---------------------------------------------------------------
    if ($action === 'email') {
        $emailValue = mb_strtolower(post_str('email', 150));

        if ($emailValue === '') {
            $errors['email'] = 'Email address is required.';
        } elseif (!filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        } elseif (user_value_taken('email', $emailValue, $userId)) {
            $errors['email'] = 'This email address is already used by another account.';
        } elseif ($emailValue === $user['email']) {
            set_flash('info', 'Your email address is unchanged.');
            redirect('profile/index.php');
        } else {
            db()->prepare('UPDATE users SET email = ? WHERE id = ?')->execute([$emailValue, $userId]);
            log_activity($userId, 'update_profile', 'Changed email address.');
            set_flash('success', 'Your email address was updated.');
            redirect('profile/index.php');
        }
    }

    // ---- Profile photo upload ------------------------------------------------
    elseif ($action === 'photo') {
        $file = $_FILES['photo'] ?? null;

        if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
            $errors['photo'] = 'Please choose an image file.';
        } elseif ($file['error'] === UPLOAD_ERR_NO_FILE) {
            $errors['photo'] = 'Please choose an image file.';
        } elseif (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $file['size'] > PHOTO_MAX_BYTES) {
            $errors['photo'] = 'The image is too large. The maximum size is 2 MB.';
        } elseif ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            $errors['photo'] = 'The upload failed. Please try again.';
        } else {
            // Check the real file content, not the name or browser-reported type
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $size = @getimagesize($file['tmp_name']);

            if (!isset(PHOTO_TYPES[$mime]) || $size === false) {
                $errors['photo'] = 'Only JPG, PNG, or WEBP images are allowed.';
            } elseif ($size[0] > PHOTO_MAX_PIXELS || $size[1] > PHOTO_MAX_PIXELS || $size[0] < 32 || $size[1] < 32) {
                $errors['photo'] = 'The image must be between 32 and ' . PHOTO_MAX_PIXELS . ' pixels wide and high.';
            } else {
                if (!is_dir(PROFILE_UPLOAD_DIR)) {
                    mkdir(PROFILE_UPLOAD_DIR, 0755, true);
                }
                // Random file name chosen by the server, never the uploaded name
                $fileName = 'u' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . PHOTO_TYPES[$mime];

                if (!move_uploaded_file($file['tmp_name'], PROFILE_UPLOAD_DIR . '/' . $fileName)) {
                    error_log('[EODB-TaskFlow] Could not move uploaded profile photo for user ' . $userId);
                    $errors['photo'] = 'The photo could not be saved. Please try again.';
                } else {
                    db()->prepare('UPDATE users SET profile_photo = ? WHERE id = ?')->execute([$fileName, $userId]);
                    delete_profile_photo_file($user['profile_photo']);
                    log_activity($userId, 'update_profile', 'Updated profile photo.');
                    set_flash('success', 'Your profile photo was updated.');
                    redirect('profile/index.php');
                }
            }
        }
    }

    // ---- Remove photo ----------------------------------------------------------
    elseif ($action === 'remove_photo') {
        if ($user['profile_photo']) {
            db()->prepare('UPDATE users SET profile_photo = NULL WHERE id = ?')->execute([$userId]);
            delete_profile_photo_file($user['profile_photo']);
            log_activity($userId, 'update_profile', 'Removed profile photo.');
            set_flash('success', 'Your profile photo was removed.');
        }
        redirect('profile/index.php');
    }

    // ---- Password --------------------------------------------------------------
    elseif ($action === 'password') {
        $current = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
        $new     = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
        $confirm = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';

        $stmt = db()->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $hash = (string) $stmt->fetchColumn();

        if ($current === '' || !password_verify($current, $hash)) {
            $errors['current_password'] = 'Your current password is incorrect.';
        } elseif ($new === '') {
            $errors['new_password'] = 'Please enter a new password.';
        } elseif ($policy = password_policy_errors($new, $user['username'])) {
            $errors['new_password'] = implode(' ', $policy);
        } elseif (password_verify($new, $hash)) {
            $errors['new_password'] = 'The new password must be different from your current password.';
        } elseif (!hash_equals($new, $confirm)) {
            $errors['confirm_password'] = 'The new passwords do not match.';
        } else {
            db()->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);

            // Sign out "remembered" devices and issue a fresh session ID
            delete_remember_tokens_for_user($userId);
            clear_remember_cookie();
            session_regenerate_id(true);
            $_SESSION['last_regenerated'] = time();

            log_activity($userId, 'update_profile', 'Changed password.');
            set_flash('success', 'Your password was changed. Other remembered devices have been signed out.');
            redirect('profile/index.php');
        }
    } else {
        $errors['general'] = 'Invalid request.';
    }
}

$photo = profile_photo_url($user['profile_photo']);
$invalid = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$feedback = static function (string $field) use ($errors): string {
    return isset($errors[$field]) ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>' : '';
};

$pageTitle  = 'My Profile';
$activeMenu = 'profile';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">My Profile</h1>
        <p class="page-subtitle">Your account information. Contact the administrator to change your name, role, department, or position.</p>
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <i class="fa-solid fa-circle-exclamation me-1"></i>
        <?= e($errors['general'] ?? 'Please correct the highlighted field.') ?>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="profile-cover"></div>
            <div class="card-body px-4 pb-4 pt-0 text-center">
                <?php if ($photo): ?>
                    <img src="<?= e($photo) ?>" alt="Profile photo of <?= e($user['full_name']) ?>" class="avatar avatar-lg profile-avatar mb-3">
                <?php else: ?>
                    <span class="avatar avatar-lg profile-avatar mb-3"><?= e(initials($user['full_name'])) ?></span>
                <?php endif; ?>
                <h2 class="h5 mb-1"><?= e($user['full_name']) ?></h2>
                <div class="text-muted small mb-2"><?= e($user['position'] ?: '—') ?></div>
                <?= role_badge($user['role']) ?>
            </div>
            <div class="card-body border-top">
                <dl class="detail-list">
                    <dt>Employee ID</dt><dd><?= e($user['employee_id']) ?></dd>
                    <dt>Username</dt><dd><?= e($user['username']) ?></dd>
                    <dt>Email</dt><dd><?= e($user['email']) ?></dd>
                    <dt>Department</dt><dd><?= e($user['department'] ?? '—') ?></dd>
                    <dt>Position</dt><dd><?= e($user['position'] ?: '—') ?></dd>
                    <dt>Role</dt><dd><?= e(role_label($user['role'])) ?></dd>
                    <dt>Last login</dt><dd><?= e(format_datetime($user['last_login_at'])) ?></dd>
                    <dt>Member since</dt><dd><?= e(format_date($user['created_at'], 'F d, Y')) ?></dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header">Profile Photo</div>
            <div class="card-body">
                <form method="post" action="<?= e(url('profile/index.php')) ?>" enctype="multipart/form-data" novalidate data-once>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="photo">
                    <input type="hidden" name="MAX_FILE_SIZE" value="<?= PHOTO_MAX_BYTES ?>">
                    <label for="photo" class="form-label">Upload a new photo</label>
                    <div class="input-group has-validation">
                        <input type="file" class="form-control<?= $invalid('photo') ?>" id="photo" name="photo"
                               accept="image/jpeg,image/png,image/webp" required>
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-upload me-1"></i> Upload</button>
                        <?= $feedback('photo') ?>
                    </div>
                    <div class="form-text">JPG, PNG, or WEBP, up to 2 MB. A square photo looks best.</div>
                </form>
                <?php if ($user['profile_photo']): ?>
                    <form method="post" action="<?= e(url('profile/index.php')) ?>" class="mt-2" data-confirm="Remove your profile photo?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="remove_photo">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash me-1"></i> Remove photo</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Email Address</div>
            <div class="card-body">
                <form method="post" action="<?= e(url('profile/index.php')) ?>" novalidate data-once>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="email">
                    <label for="email" class="form-label">Email</label>
                    <div class="input-group has-validation">
                        <input type="email" class="form-control<?= $invalid('email') ?>" id="email" name="email"
                               value="<?= e($emailValue) ?>" maxlength="150" required autocomplete="email">
                        <button type="submit" class="btn btn-primary">Save</button>
                        <?= $feedback('email') ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Change Password</div>
            <div class="card-body">
                <form method="post" action="<?= e(url('profile/index.php')) ?>" novalidate data-once>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="password">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <div class="input-group has-validation">
                            <input type="password" class="form-control<?= $invalid('current_password') ?>" id="current_password"
                                   name="current_password" maxlength="255" required autocomplete="current-password">
                            <button type="button" class="btn btn-outline-secondary" data-toggle-password="#current_password" aria-label="Show password">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                            <?= $feedback('current_password') ?>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="new_password" class="form-label">New Password</label>
                            <div class="input-group has-validation">
                                <input type="password" class="form-control<?= $invalid('new_password') ?>" id="new_password"
                                       name="new_password" maxlength="72" required autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary" data-toggle-password="#new_password" aria-label="Show password">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                                <?= $feedback('new_password') ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <div class="input-group has-validation">
                                <input type="password" class="form-control<?= $invalid('confirm_password') ?>" id="confirm_password"
                                       name="confirm_password" maxlength="72" required autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary" data-toggle-password="#confirm_password" aria-label="Show password">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                                <?= $feedback('confirm_password') ?>
                            </div>
                        </div>
                    </div>
                    <div class="form-text mb-3">At least 8 characters with upper case, lower case, a number, and a special character.</div>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-key me-1"></i> Change Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
