const { spawnSync } = require("node:child_process");

const php = process.env.SW_PHP_BIN;

const result = spawnSync(
    php,
    ["bin/console", "sw:investigate", "--no-ansi"],
    {
        cwd: process.cwd(),
        encoding: "utf8",
        timeout: 60000,
        maxBuffer: 1024 * 1024,
        windowsHide: true
    }
);

if (result.stdout) {
    process.stdout.write(result.stdout);
}

if (result.stderr) {
    process.stderr.write(result.stderr);
}

console.log("AGENT_EXIT=" + result.status);

if (result.error) {
    console.error("PROCESS_ERROR=" + result.error.message);
}

process.exitCode =
    result.status === 0 && !result.error ? 0 : 1;