Unicode True
!include "MUI2.nsh"
!include "x64.nsh"
!include "WinVer.nsh"
!ifndef APP_EXE
 !error "APP_EXE is required"
!endif
!ifndef BUILD_DIR
 !error "BUILD_DIR is required"
!endif
Name "LA2PLUS Launcher"
OutFile "${BUILD_DIR}\LA2PLUS-Setup-1.1.0.exe"
InstallDir "$LOCALAPPDATA\Programs\LA2PLUS Launcher"
InstallDirRegKey HKCU "Software\LA2PLUS\Launcher" "InstallDir"
RequestExecutionLevel user
SetCompressor /SOLID lzma
VIProductVersion "1.1.0.0"
VIAddVersionKey "ProductName" "LA2PLUS Launcher"
VIAddVersionKey "FileDescription" "LA2PLUS Launcher Setup"
VIAddVersionKey "FileVersion" "1.1.0"
VIAddVersionKey "LegalCopyright" "LA2PLUS"
!define MUI_ICON "../assets/launcher.ico"
!define MUI_UNICON "../assets/launcher.ico"
!define MUI_ABORTWARNING
!define MUI_FINISHPAGE_RUN "$INSTDIR\LA2PLUS-Launcher.exe"
!insertmacro MUI_PAGE_WELCOME
!insertmacro MUI_PAGE_COMPONENTS
!insertmacro MUI_PAGE_DIRECTORY
!insertmacro MUI_PAGE_INSTFILES
!insertmacro MUI_PAGE_FINISH
!insertmacro MUI_UNPAGE_CONFIRM
!insertmacro MUI_UNPAGE_INSTFILES
!insertmacro MUI_LANGUAGE "Russian"
!insertmacro MUI_LANGUAGE "English"
Function .onInit
 ${IfNot} ${RunningX64}
  MessageBox MB_ICONSTOP "LA2PLUS Launcher: требуется Windows 10/11 x64."
  Abort
 ${EndIf}
 ${IfNot} ${AtLeastWin10}
  MessageBox MB_ICONSTOP "LA2PLUS Launcher: требуется Windows 10/11 x64."
  Abort
 ${EndIf}
 SetShellVarContext current
FunctionEnd
Section "Лаунчер LA2PLUS (обязательно)" Core
 SectionIn RO
 SetOutPath "$INSTDIR"
 File /oname=LA2PLUS-Launcher.exe "${APP_EXE}"
 File "${BUILD_DIR}\ThirdPartyNotices.txt"
 WriteUninstaller "$INSTDIR\Uninstall.exe"
 CreateDirectory "$SMPROGRAMS\LA2PLUS"
 CreateShortcut "$SMPROGRAMS\LA2PLUS\LA2PLUS.lnk" "$INSTDIR\LA2PLUS-Launcher.exe"
 WriteRegStr HKCU "Software\LA2PLUS\Launcher" "InstallDir" "$INSTDIR"
 WriteRegStr HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\LA2PLUSLauncher" "DisplayName" "LA2PLUS Launcher"
 WriteRegStr HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\LA2PLUSLauncher" "UninstallString" '$\"$INSTDIR\Uninstall.exe$\"'
 WriteRegStr HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\LA2PLUSLauncher" "DisplayIcon" "$INSTDIR\LA2PLUS-Launcher.exe"
 WriteRegStr HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\LA2PLUSLauncher" "DisplayVersion" "1.1.0"
 WriteRegStr HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\LA2PLUSLauncher" "Publisher" "LA2PLUS"
 WriteRegDWORD HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\LA2PLUSLauncher" "NoModify" 1
 WriteRegDWORD HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\LA2PLUSLauncher" "NoRepair" 1
SectionEnd
Section "Ярлык на рабочем столе" Desktop
 CreateShortcut "$DESKTOP\LA2PLUS.lnk" "$INSTDIR\LA2PLUS-Launcher.exe"
SectionEnd
Section "Uninstall"
 SetShellVarContext current
 Delete "$DESKTOP\LA2PLUS.lnk"
 Delete "$SMPROGRAMS\LA2PLUS\LA2PLUS.lnk"
 RMDir "$SMPROGRAMS\LA2PLUS"
 Delete "$INSTDIR\LA2PLUS-Launcher.exe"
 Delete "$INSTDIR\ThirdPartyNotices.txt"
 Delete "$INSTDIR\Uninstall.exe"
 RMDir "$INSTDIR"
 DeleteRegKey HKCU "Software\Microsoft\Windows\CurrentVersion\Uninstall\LA2PLUSLauncher"
 DeleteRegKey HKCU "Software\LA2PLUS\Launcher"
 ; Game folders and personal game settings are deliberately preserved.
SectionEnd
