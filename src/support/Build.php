<?php

declare(strict_types=1);

namespace stimmt\craft\Mcp\support;

use Composer\InstalledVersions;
use stimmt\craft\Mcp\Mcp;

/**
 * Which build of this plugin is running.
 *
 * WHY not just the plugin version: the version answers "which release" only
 * when a release installed it. Install from a branch, as every contributor and
 * every staging box does, and Craft reports the constraint (`dev-main`), which
 * is one string for every commit that branch will ever have. Two deploys a week
 * apart are then indistinguishable, and a stale answer looks exactly like a
 * fresh one.
 *
 * That is a reporting annoyance in `get_mcp_info` and a real defect in the
 * discovery cache, which keys on the version outside devMode: on a
 * branch-installed site the key would never turn over, so the first deploy's
 * tool scan would be served forever. Composer records the commit it installed,
 * which does change, so the two together identify a build in both worlds.
 *
 * @author Max van Essen <support@stimmt.digital>
 */
final class Build {
    public const string PACKAGE = 'happy-horizon/craft-mcp';

    private const int SHORT_REFERENCE = 12;

    /** False until looked for; null once looked for and not found. */
    private static string|null|false $checkout = false;

    /**
     * The version Craft and the MCP handshake report: a release tag where one
     * exists, the branch constraint otherwise.
     */
    public static function version(): string {
        return self::installed()
            ? (InstalledVersions::getPrettyVersion(self::PACKAGE) ?? 'unknown')
            : self::declared();
    }

    /**
     * The commit the running code came from, shortened. Null when composer has
     * none, which happens for an install from a dist archive with no source
     * reference; the version alone has to do in that case.
     */
    public static function reference(): ?string {
        $reference = self::checkout()
            ?? (self::installed() ? InstalledVersions::getReference(self::PACKAGE) : null)
            ?? self::stamp();

        return $reference === null ? null : substr($reference, 0, self::SHORT_REFERENCE);
    }

    /**
     * Where reference() got its answer: 'git' when it was read from the code on
     * disk, 'composer' when it came from the record of what was installed, and
     * 'unknown' when neither could say.
     *
     * WHY it is reported at all: composer's record is a claim about the past.
     * A path or symlink install, which is how this plugin is developed and how
     * a monorepo deploys it, runs code composer never re-read, so the recorded
     * branch can be one that is no longer checked out. That is not theoretical;
     * this server spent a day reporting a branch it was not running.
     */
    public static function source(): string {
        if (self::checkout() !== null) {
            return 'git';
        }

        if (self::installed() && InstalledVersions::getReference(self::PACKAGE) !== null) {
            return 'composer';
        }

        return self::stamp() === null ? 'unknown' : 'files';
    }

    /**
     * The branch checked out in the plugin directory, when it is a git working
     * copy and not detached. Composer's recorded version names the branch that
     * was installed, which on a path install is not necessarily the one running.
     */
    public static function branch(): ?string {
        $head = dirname(__DIR__, 2) . '/.git/HEAD';
        if (!is_file($head)) {
            return null;
        }

        $contents = trim((string) file_get_contents($head));

        return str_starts_with($contents, 'ref: refs/heads/')
            ? substr($contents, strlen('ref: refs/heads/'))
            : null;
    }

    /**
     * The commit checked out in the plugin directory, when it is a git working
     * copy. Read from .git directly: no shell, and nothing to install.
     *
     * Memoised because reference() also keys the discovery cache, so it is
     * asked more than once per request. False means "looked, found none",
     * which is different from "not looked at yet".
     */
    private static function checkout(): ?string {
        if (self::$checkout !== false) {
            return self::$checkout;
        }

        self::$checkout = self::readHead(dirname(__DIR__, 2) . '/.git');

        return self::$checkout;
    }

    private static function readHead(string $git): ?string {
        // A worktree or submodule has a .git FILE pointing elsewhere. Following
        // that is more machinery than the answer is worth, so composer's record
        // stays the fallback.
        if (!is_dir($git) || !is_file($git . '/HEAD')) {
            return null;
        }

        $head = trim((string) file_get_contents($git . '/HEAD'));

        if (!str_starts_with($head, 'ref: ')) {
            return self::sha($head);
        }

        $ref = substr($head, 5);

        return is_file($git . '/' . $ref)
            ? self::sha(trim((string) file_get_contents($git . '/' . $ref)))
            : self::packed($git . '/packed-refs', $ref);
    }

    /**
     * A branch whose loose ref file has been packed away lives only in
     * packed-refs, which is the state a freshly cloned repository is in.
     */
    private static function packed(string $path, string $ref): ?string {
        if (!is_file($path)) {
            return null;
        }

        foreach (file($path) ?: [] as $line) {
            $parts = explode(' ', trim($line), 2);
            if (count($parts) === 2 && $parts[1] === $ref) {
                return self::sha($parts[0]);
            }
        }

        return null;
    }

    private static function sha(string $value): ?string {
        return preg_match('/^[0-9a-f]{40}$/', $value) === 1 ? $value : null;
    }

    /**
     * One string identifying this build, for anything that must change when the
     * code does. Falls back to the version alone when there is no reference,
     * which is the same guarantee we had before and no worse.
     */
    public static function revision(): string {
        $reference = self::reference();

        return $reference === null ? self::version() : self::version() . '@' . $reference;
    }

    /**
     * The version the module declares about itself. Dropped in as a module
     * rather than required as a package, composer has no record of this code
     * at all, and 'unknown' was the only answer left.
     */
    private static function declared(): string {
        return Mcp::getInstance()?->version ?? 'unknown';
    }

    /**
     * Last resort when neither composer nor git can identify the build: the
     * newest mtime among the directories discovery actually scans, hashed.
     *
     * WHY it exists: revision() keys the discovery cache outside devMode. On a
     * copied-in module with no .git and no composer record, every other input
     * is a constant, so replacing the files with a newer copy would keep
     * serving the previous scan forever. An mtime changes when the files do.
     */
    private static function stamp(): ?string {
        $root = dirname(__DIR__);

        $mtimes = array_filter([
            @filemtime($root),
            @filemtime($root . '/tools'),
        ]);

        return $mtimes === [] ? null : substr(md5((string) max($mtimes)), 0, self::SHORT_REFERENCE);
    }

    /**
     * Guarded because a plugin can be loaded in contexts where composer's
     * generated map does not know it, and this must never be the thing that
     * breaks a tool call.
     */
    private static function installed(): bool {
        return class_exists(InstalledVersions::class) && InstalledVersions::isInstalled(self::PACKAGE);
    }
}
