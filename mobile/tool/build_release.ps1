# Builds the production APK: signed with the release key, pointed at the live server.
#   .\tool\build_release.ps1 -ApiUrl https://your-server.example/api/v1
#   .\tool\build_release.ps1 -ApiUrl https://your-server.example/api/v1 -Test
# -Test builds a separate app ("Adinn Tasks (TEST)", id ...adinn_tasks.test, push off) that installs NEXT TO any existing
# copy of the app. Use it to hand a tester an APK that cannot clash with an old install. It is not the release build.
param([Parameter(Mandatory = $true)][string]$ApiUrl, [switch]$Test)

if ($ApiUrl -notmatch '^https://') { throw "The live server must be reached over HTTPS (got '$ApiUrl')." }
if ($ApiUrl -match '10\.0\.2\.2|localhost|127\.0\.0\.1') { throw "'$ApiUrl' is a local address; use the live server's." }
if (-not (Test-Path "$PSScriptRoot\..\android\key.properties")) { throw 'android\key.properties is missing (see RELEASING.md).' }

Push-Location "$PSScriptRoot\.."
$out = 'build\app\outputs\flutter-apk'
try {
  flutter analyze
  if ($LASTEXITCODE -ne 0) { throw 'flutter analyze failed' }
  flutter test
  if ($LASTEXITCODE -ne 0) { throw 'flutter test failed' }
  if ($Test) { $env:ADINN_TEST_BUILD = '1' } else { Remove-Item Env:ADINN_TEST_BUILD -ErrorAction SilentlyContinue }
  flutter build apk --release --dart-define=API_BASE_URL=$ApiUrl
  if ($LASTEXITCODE -ne 0) { throw 'build failed' }
  if ($Test) {
    Copy-Item "$out\app-release.apk" "$out\adinn-tasks-TEST.apk" -Force
    Remove-Item "$out\app-release.apk" -Force
    Write-Host "Built TEST app: $out\adinn-tasks-TEST.apk"
  } else {
    Write-Host "Built: $out\app-release.apk"
  }
} finally { Remove-Item Env:ADINN_TEST_BUILD -ErrorAction SilentlyContinue; Pop-Location }
