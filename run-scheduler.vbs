Set WshShell = CreateObject("WScript.Shell")
WshShell.Run """C:\Program Files\PHP\php.exe"" ""C:\inetpub\wwwroot\app-magasin\artisan"" schedule:run", 0, False
