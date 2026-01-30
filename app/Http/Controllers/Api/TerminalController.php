<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Terminal;
use Illuminate\Support\Facades\File;
use Exception;

class TerminalController extends Controller
{
    public function index()
    {
        try {
            $terminals = Terminal::all();

            return response()->json([
                'status' => true,
                'data' => $terminals
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch terminals',
                'data' => null,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getByClientIp(Request $request)
    {
        try {
            $adapterStatus = trim(shell_exec('powershell -Command "(Get-NetAdapter -Name \'Ethernet\').Status"'));

            if (strtolower($adapterStatus) !== 'up') {
                return response()->json([
                    'status' => false,
                    'message' => "Ethernet adapter is not connected or not active",
                    'data' => null
                ], 200);
            }

            $clientIp = trim(shell_exec('powershell -Command "(Get-NetIPAddress -InterfaceAlias \'Ethernet\' -AddressFamily IPv4 | Select-Object -First 1 -ExpandProperty IPAddress)"'));

            if (!$clientIp) {
                return response()->json([
                    'status' => false,
                    'message' => "No IPv4 address found for Ethernet adapter",
                    'data' => null
                ], 200);
            }

            $terminal = Terminal::where('ip_address', $clientIp)->first();

            if (!$terminal) {
                return response()->json([
                    'status' => false,
                    'message' => "No terminal found for IP {$clientIp}",
                    'data' => null
                ], 200);
            }

            return response()->json([
                'status' => true,
                'data' => $terminal
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch terminal by client IP',
                'data' => null,
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function store(Request $request)
    {
        try {
            $request->validate([
                'terminal_name' => 'required|string',
                'ip_address' => 'required|ip',
                'gateway' => 'required|ip',
            ]);


            $terminal = Terminal::updateOrCreate(
                ['ip_address' => $request->ip_address],
                ['terminal_name' => $request->terminal_name]
            );


            $this->generateScript($terminal->terminal_name, $request->ip_address, $request->gateway);

            return response()->json([
                'status' => true,
                'message' => 'Terminal saved and script created successfully',
                'data' => $terminal,
                'script_path' => "C:\\scripts\\{$terminal->terminal_name}\\set_ip.ps1"
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to save terminal or create script',
                'data' => null,
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function updateIP(Request $request)
    {
        try {
            $request->validate([
                'terminal_id' => 'required|exists:terminals,id',
                'ip_address' => 'required|ip',
                'gateway' => 'required|ip',
            ]);

            $terminal = Terminal::find($request->terminal_id);

            $terminal->update([
                'ip_address' => $request->ip_address
            ]);

            $this->generateScript($terminal->terminal_name, $request->ip_address, $request->gateway);

            return response()->json([
                'status' => true,
                'message' => 'Terminal IP updated and script regenerated successfully',
                'data' => $terminal,
                'script_path' => "C:\\scripts\\{$terminal->terminal_name}\\set_ip.ps1"
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update terminal IP or create script',
                'data' => null,
                'error' => $e->getMessage()
            ], 500);
        }
    }


    private function generateScript(string $terminalName, string $ip, string $gateway)
    {
        $dirPath = "C:\\scripts\\{$terminalName}";

        if (!File::exists($dirPath)) {
            File::makeDirectory($dirPath, 0755, true);
        }

        $scriptContent = <<<POWERSHELL
param(
    [string]\$IP = "{$ip}",
    [string]\$Gateway = "{$gateway}"
)

# Name of your network adapter
\$InterfaceAlias = "Ethernet"

try {
    # Remove any existing IP configuration
    Get-NetIPAddress -InterfaceAlias \$InterfaceAlias -ErrorAction SilentlyContinue | Remove-NetIPAddress -Confirm:\$false -ErrorAction SilentlyContinue

    # Set new IP and default gateway
    New-NetIPAddress -InterfaceAlias \$InterfaceAlias -IPAddress \$IP -PrefixLength 24 -DefaultGateway \$Gateway

    Write-Output "IP set successfully to \$IP with Gateway \$Gateway"
} catch {
    Write-Output "Failed to set IP: \$_"
}
POWERSHELL;

        $filePath = $dirPath . "\\set_ip.ps1";
        File::put($filePath, $scriptContent);
    }
}
