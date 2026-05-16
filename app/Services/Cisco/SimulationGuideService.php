<?php

namespace App\Services\Cisco;

class SimulationGuideService
{
    /**
     * @param  array<string, mixed>  $scenario
     * @return array<int, string>
     */
    public function steps(array $scenario): array
    {
        $scenarioName = (string) ($scenario['scenario_name'] ?? 'Cisco Scenario');

        return [
            'Open Packet Tracer or lab topology.',
            'Assign device roles and connect interfaces.',
            'Paste the generated CLI into the correct device.',
            'Run validation checklist for '.$scenarioName.'.',
            'Test ping, DHCP, DNS, HTTP, and routing reachability.',
            'Use simulation mode to review expected traffic flow.',
        ];
    }
}