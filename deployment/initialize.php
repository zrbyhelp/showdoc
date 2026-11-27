<?php

declare(strict_types=1);

use App\Common\Database\Upgrade;
use App\Common\Helper\Security;
use App\Model\User;
use Illuminate\Database\Capsule\Manager as DB;

final class SetupError extends RuntimeException {}

function runChineseInstaller(): void
{
    $request = curl_init('http://127.0.0.1/install/non_interactive.php?lang=zh');
    curl_setopt_array($request, [
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $body = curl_exec($request);
    $status = curl_getinfo($request, CURLINFO_RESPONSE_CODE);
    curl_close($request);

    if ($status !== 200 || $body === false
        || (!str_contains($body, 'install success') && !str_contains($body, '已经安装过'))) {
        throw new SetupError('ShowDoc did not confirm the Chinese installation.');
    }
}

function initializeShowDoc(): void
{
    if (getenv('SHOWDOC_IMPORT_SAMPLE_PROJECTS') !== '0') {
        throw new SetupError('Set SHOWDOC_IMPORT_SAMPLE_PROJECTS=0 before initializing this site.');
    }

    $dataDirectory = '/var/www/html/Sqlite';
    $marker = $dataDirectory . '/.xssg-admin-initialized';
    $lock = fopen($dataDirectory . '/.xssg-admin-initialize.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        throw new SetupError('Could not lock the ShowDoc data directory for initialization.');
    }

    try {
        if (is_file($marker)) {
            echo "ShowDoc is already initialized. The administrator account is unchanged.\n";
            return;
        }

        $password = rtrim(stream_get_contents(STDIN), "\r\n");
        if (strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) {
            throw new SetupError('Provide a new administrator password of 12–72 bytes in the private password file.');
        }

        runChineseInstaller();
        require '/var/www/html/server/vendor/autoload.php';
        require '/var/www/html/server/app/Common/bootstrap.php';
        if (!Upgrade::checkAndUpgrade()) {
            throw new SetupError('ShowDoc could not migrate the SQLite database.');
        }

        $projects = DB::table('item')->where('is_del', 0)->count();
        $pages = DB::table('page')->where('is_del', 0)->count();
        $admin = DB::table('user')->where('username', 'showdoc')->where('groupid', 1)->first();
        if ($projects !== 0 || $pages !== 0 || DB::table('user')->count() !== 1 || !$admin) {
            throw new SetupError('This database already contains site data. Existing accounts were left as-is.');
        }

        $stored = (string) $admin->password;
        $salt = (string) $admin->salt;
        $alreadyMatches = Security::verifyPassword($password, $stored, $salt)['ok'];
        // Check the upstream factory credential before changing an existing administrator.
        $factoryCredential = Security::verifyPassword('123456', $stored, $salt)['ok'];
        if (!$alreadyMatches && !$factoryCredential) {
            throw new SetupError('The administrator already has a different password. It was left as-is.');
        }
        if (!$alreadyMatches) {
            $userId = (int) $admin->uid;
            if (!User::updatePwd($userId, $password) || !User::checkLogin('showdoc', $password)) {
                throw new SetupError('ShowDoc could not set the new administrator password.');
            }
            DB::table('user_token')->where('uid', $userId)->delete();
        }
        if (file_put_contents($marker, "installed\n", LOCK_EX) === false) {
            throw new SetupError('Could not record completed initialization in the ShowDoc volume.');
        }

        echo "Initialized Chinese ShowDoc. Administrator: showdoc. Projects: 0. Pages: 0.\n";
    } finally {
        fclose($lock);
    }
}

try {
    initializeShowDoc();
} catch (SetupError $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
} catch (Throwable) {
    fwrite(STDERR, "Unexpected ShowDoc initialization error. Review the local service state.\n");
    exit(1);
}
