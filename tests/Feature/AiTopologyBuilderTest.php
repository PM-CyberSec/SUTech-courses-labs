<?php

namespace Tests\Feature;

use App\Models\Topology;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiTopologyBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_topology_builder_page_and_generation_work(): void
    {
        $this->withSession(['role' => 'engineer'])
            ->get(route('ai-topology.index'))
            ->assertOk()
            ->assertSee('AI Topology Builder');

        $this->withSession(['role' => 'engineer'])
            ->post(route('ai-topology.generate'), [
                'prompt' => 'Create a small office topology with 1 router, 1 switch, 4 PCs, VLAN 10 for HR, VLAN 20 for IT, DHCP, DNS, and internet access.',
                'preset_key' => 'vlan_lab',
            ])
            ->assertRedirect();

        $topology = Topology::query()->latest('id')->firstOrFail();
        $this->assertSame('vlan_lab', $topology->scenario_type);
        $this->assertGreaterThan(0, $topology->devices()->count());
        $this->assertGreaterThan(0, $topology->links()->count());
        $this->assertGreaterThan(0, $topology->configs()->count());
        $this->assertGreaterThan(0, $topology->devices()->firstOrFail()->topologyInterfaces()->count());

        $this->withSession(['role' => 'engineer'])
            ->get(route('ai-topology.show', $topology))
            ->assertOk()
            ->assertSee('AI Topology Builder')
            ->assertSee('Config Output');
    }
}
