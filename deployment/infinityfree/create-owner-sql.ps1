$ErrorActionPreference = 'Stop'

$php = (Get-Command php -ErrorAction Stop).Source
$name = (Read-Host 'Name for the first administrator').Trim()
$email = (Read-Host 'Email for the first administrator').Trim()

if ([string]::IsNullOrWhiteSpace($name) -or $name.Length -gt 255) {
    throw 'Enter a name between 1 and 255 characters.'
}

try {
    $parsedEmail = [System.Net.Mail.MailAddress]::new($email)
    if ($parsedEmail.Address -ne $email -or $email.Length -gt 255) { throw 'Invalid email.' }
}
catch {
    throw 'Enter a valid email address no longer than 255 characters.'
}

$password = Read-Host 'Choose a unique password (12 to 64 characters)' -AsSecureString
$confirmation = Read-Host 'Confirm the password' -AsSecureString
$passwordPointer = [IntPtr]::Zero
$confirmationPointer = [IntPtr]::Zero

try {
    $passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($password)
    $confirmationPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($confirmation)
    $plainPassword = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
    $plainConfirmation = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($confirmationPointer)

    if ($plainPassword.Length -lt 12 -or $plainPassword.Length -gt 64) {
        throw 'The password must be between 12 and 64 characters.'
    }
    if ($plainPassword -cne $plainConfirmation) {
        throw 'The passwords do not match.'
    }

    # Encode the password before piping it to PHP. Windows PowerShell 5.1 can
    # strip nested double quotes from native command arguments, which breaks
    # PHP -r code containing quoted strings. Base64 keeps stdin ASCII-only and
    # avoids putting the password in PHP's command line.
    $passwordBytes = [Text.Encoding]::UTF8.GetBytes($plainPassword)
    $encodedPassword = [Convert]::ToBase64String($passwordBytes)
    [Array]::Clear($passwordBytes, 0, $passwordBytes.Length)

    $hashScript = '$password = base64_decode(stream_get_contents(STDIN)); $options = array(chr(99).chr(111).chr(115).chr(116) => 12); echo password_hash($password, PASSWORD_BCRYPT, $options);'
    $hash = ($encodedPassword | & $php -r $hashScript) -join ''
    $phpExitCode = $LASTEXITCODE
    $encodedPassword = $null

    if ($phpExitCode -ne 0 -or $hash -notmatch '^\$2[aby]\$12\$[./A-Za-z0-9]{53}$') {
        throw 'PHP could not create the password hash.'
    }

    $nameHex = [BitConverter]::ToString([Text.Encoding]::UTF8.GetBytes($name)).Replace('-', '')
    $emailHex = [BitConverter]::ToString([Text.Encoding]::UTF8.GetBytes($email)).Replace('-', '')
    $sqlTemplate = @'
START TRANSACTION;

INSERT INTO `teams` (`name`, `slug`, `is_personal`, `created_at`, `updated_at`)
VALUES (CONVERT(0x__NAME_HEX__ USING utf8mb4), 'lnu-youngfield-crossing', 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);
SET @crossguard_first_team_id = LAST_INSERT_ID();

INSERT INTO `users` (`name`, `email`, `email_verified_at`, `password`, `current_team_id`, `created_at`, `updated_at`)
VALUES (CONVERT(0x__NAME_HEX__ USING utf8mb4), CONVERT(0x__EMAIL_HEX__ USING utf8mb4), CURRENT_TIMESTAMP, '__PASSWORD_HASH__', @crossguard_first_team_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);
SET @crossguard_first_owner_id = LAST_INSERT_ID();

INSERT INTO `team_members` (`team_id`, `user_id`, `role`, `created_at`, `updated_at`)
VALUES (@crossguard_first_team_id, @crossguard_first_owner_id, 'owner', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);

COMMIT;
'@
    $sql = $sqlTemplate.Replace('__NAME_HEX__', $nameHex).Replace('__EMAIL_HEX__', $emailHex).Replace('__PASSWORD_HASH__', $hash)
    $sqlPath = Join-Path $PSScriptRoot 'initial-owner.sql'
    [IO.File]::WriteAllText($sqlPath, $sql, [Text.UTF8Encoding]::new($false))
    Write-Host "Created $sqlPath. Import it into the new, empty InfinityFree database through phpMyAdmin."
    Write-Host 'The password is not stored in the SQL file; keep the password you entered safe.'
}
finally {
    if ($passwordPointer -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer) }
    if ($confirmationPointer -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($confirmationPointer) }
    $plainPassword = $null
    $plainConfirmation = $null
}
