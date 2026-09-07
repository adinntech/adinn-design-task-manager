@props(['needsRefresh' => false, 'scope' => 'tasks', 'livewire' => false])

<button
    type="button"
    class="btn btn-secondary refresh-btn{{ $needsRefresh ? ' is-shaking' : '' }}"
    data-scope="{{ $scope }}"
    onclick="window.adinnAckRefresh(this, '{{ $scope }}', {{ $livewire ? 'true' : 'false' }})"
>
    <span class="refresh-btn-icon">&#8635;</span> Refresh
</button>
