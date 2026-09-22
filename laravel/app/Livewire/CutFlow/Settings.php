<?php

namespace App\Livewire\CutFlow;

use App\Models\CutFlow\CutFlowSetting;
use App\Models\FdUser;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.cutflow.layout')]
class Settings extends Component
{
    public bool $unlocked = false;

    public string $pin = '';

    public string $pinError = '';

    public bool $cutSensorActive = false;

    public bool $showCutToast = true;

    public ?float $kerfInches = null;

    public ?float $standardStockLength = null;

    public function mount(): void
    {
        $settings = CutFlowSetting::current();
        $this->cutSensorActive = $settings->cut_sensor_active;
        $this->showCutToast = $settings->show_cut_toast;
        $this->kerfInches = $settings->kerf_inches !== null ? (float) $settings->kerf_inches : null;
        $this->standardStockLength = $settings->standard_stock_length !== null ? (float) $settings->standard_stock_length : null;

        // an already-active admin crew member on the dashboard doesn't need
        // to re-enter a PIN here
        $crew = session('cutflow_crew', []);
        $activeKey = session('cutflow_active_crew_key');
        $active = collect($crew)->firstWhere('key', $activeKey);

        if ($active && $active['operator_id'] && FdUser::find($active['operator_id'])?->role === 'admin') {
            $this->unlocked = true;
        }
    }

    protected function findUserByPin(string $pin): ?FdUser
    {
        return FdUser::where('active', true)
            ->whereNotNull('fab_pin')
            ->get()
            ->first(fn (FdUser $user) => Hash::check($pin, $user->fab_pin));
    }

    public function unlock(): void
    {
        $user = $this->findUserByPin($this->pin);

        if (! $user || $user->role !== 'admin') {
            $this->pinError = 'Admin PIN required.';
            $this->pin = '';

            return;
        }

        $this->unlocked = true;
        $this->pinError = '';
        $this->pin = '';
    }

    public function save(): void
    {
        if (! $this->unlocked) {
            return;
        }

        CutFlowSetting::current()->update([
            'cut_sensor_active' => $this->cutSensorActive,
            'show_cut_toast' => $this->showCutToast,
            'kerf_inches' => $this->kerfInches,
            'standard_stock_length' => $this->standardStockLength,
        ]);

        session()->flash('settings_status', 'Settings saved.');
    }

    public function render()
    {
        return view('cutflow.livewire.settings');
    }
}
