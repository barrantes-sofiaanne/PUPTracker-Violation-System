@php
    $swalConfig = null;

    if (session('success')) {
        $swalConfig = [
            'icon' => 'success',
            'title' => 'Success',
            'text' => session('success'),
        ];
    } elseif (session('error')) {
        $swalConfig = [
            'icon' => 'error',
            'title' => 'Error',
            'text' => session('error'),
        ];
    } elseif ($errors->any()) {
        $swalConfig = [
            'icon' => 'error',
            'title' => 'Please check the form',
            'html' => collect($errors->all())->map(fn ($message) => e($message))->implode('<br>'),
        ];
    }
@endphp

@if($swalConfig)
    <div id="swal-flash-data" data-config='@json($swalConfig)' hidden></div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const flashData = document.getElementById('swal-flash-data');
            if (!flashData) {
                return;
            }

            const swalConfig = JSON.parse(flashData.dataset.config || '{}');

            if (window.Swal) {
                Swal.fire(swalConfig);
            }
        });
    </script>
@endif