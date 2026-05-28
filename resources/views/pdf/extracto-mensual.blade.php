@php
    $configuracion = $configuracion ?? [];

    $nombreNegocio = $nombre_negocio ?? ($configuracion['nombre_negocio'] ?? 'AUREA Beauty Salon');
    $nombreCorto = $nombre_corto ?? ($configuracion['nombre_corto'] ?? 'AUREA Beauty');
    $sloganNegocio = $slogan ?? ($configuracion['slogan'] ?? 'Sistema inteligente para salones de belleza');
    $telefonoNegocio = $telefono ?? ($configuracion['telefono'] ?? '');
    $whatsappNegocio = $whatsapp ?? ($configuracion['whatsapp'] ?? '');
    $direccionNegocio = $direccion ?? ($configuracion['direccion'] ?? '');
    $logoNegocio = $logo_url ?? ($configuracion['logo_url'] ?? '');
    $moneda = $moneda ?? ($configuracion['moneda'] ?? 'Bs');

    $formatoDinero = function ($valor) use ($moneda) {
        return trim($moneda . ' ' . number_format((float) $valor, 2, '.', ','));
    };

    $estadoTexto = function ($estado) {
        return match ($estado) {
            'concluida' => 'Concluida',
            'cancelada' => 'Cancelada',
            default => 'Pendiente',
        };
    };

    $estadoClase = function ($estado) {
        return match ($estado) {
            'concluida' => 'estado-concluida',
            'cancelada' => 'estado-cancelada',
            default => 'estado-pendiente',
        };
    };

    $nombreCliente = function ($cita) {
        return $cita->cliente->nombre ?? 'Cliente no registrado';
    };

    $telefonoCliente = function ($cita) {
        return $cita->cliente->telefono ?? '-';
    };

    $servicioCita = function ($cita) {
        return $cita->servicio ?? 'Sin servicio';
    };

    $horaCita = function ($hora) {
        if (!$hora) {
            return '-';
        }

        try {
            return \Carbon\Carbon::parse($hora)->format('H:i');
        } catch (\Throwable $error) {
            return $hora;
        }
    };

    $fechaCita = function ($fecha) {
        if (!$fecha) {
            return '-';
        }

        try {
            return \Carbon\Carbon::parse($fecha)->format('d/m/Y');
        } catch (\Throwable $error) {
            return $fecha;
        }
    };
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo ?? 'Extracto mensual' }}</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #241329;
            background: #ffffff;
            font-size: 12px;
        }

        .page {
            padding: 24px;
        }

        .header {
            width: 100%;
            padding: 18px;
            border-radius: 18px;
            background: #241329;
            color: #ffffff;
            margin-bottom: 18px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-left {
            width: 75%;
            vertical-align: middle;
        }

        .header-right {
            width: 25%;
            text-align: right;
            vertical-align: middle;
        }

        .logo-box {
            width: 78px;
            height: 78px;
            border-radius: 18px;
            background: #e91e63;
            text-align: center;
            line-height: 78px;
            color: #ffffff;
            font-size: 26px;
            font-weight: bold;
            display: inline-block;
            overflow: hidden;
        }

        .logo-img {
            width: 78px;
            height: 78px;
            object-fit: cover;
            border-radius: 18px;
        }

        .brand {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 4px;
            color: #ffffff;
        }

        .slogan {
            font-size: 12px;
            color: #f8d7a1;
            margin-bottom: 8px;
        }

        .business-info {
            font-size: 10px;
            line-height: 1.5;
            color: rgba(255, 255, 255, 0.85);
        }

        .title-box {
            margin-bottom: 16px;
            padding: 14px 16px;
            border-radius: 14px;
            background: #fff0f6;
            border: 1px solid #f8bbd0;
        }

        .report-title {
            font-size: 20px;
            font-weight: bold;
            color: #c2185b;
            margin-bottom: 4px;
        }

        .report-subtitle {
            font-size: 12px;
            color: #6a536d;
        }

        .summary-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px;
            margin-bottom: 16px;
        }

        .summary-card {
            width: 25%;
            padding: 12px;
            border-radius: 14px;
            background: #fff7fb;
            border: 1px solid #f8bbd0;
            vertical-align: top;
        }

        .summary-label {
            font-size: 10px;
            color: #7a6f80;
            margin-bottom: 6px;
            font-weight: bold;
        }

        .summary-value {
            font-size: 17px;
            font-weight: bold;
            color: #241329;
        }

        .summary-money {
            color: #2e7d32;
        }

        .summary-warning {
            color: #ef6c00;
        }

        .summary-danger {
            color: #c62828;
        }

        .section-title {
            font-size: 15px;
            font-weight: bold;
            color: #c2185b;
            margin: 18px 0 8px;
            padding-bottom: 6px;
            border-bottom: 2px solid #f8bbd0;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        .data-table th {
            background: #241329;
            color: #ffffff;
            padding: 8px;
            font-size: 10px;
            text-align: left;
            border: 1px solid #241329;
        }

        .data-table td {
            padding: 7px 8px;
            border: 1px solid #eaddea;
            font-size: 10px;
            vertical-align: top;
        }

        .data-table tr:nth-child(even) td {
            background: #fff7fb;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 9px;
            font-weight: bold;
            color: #ffffff;
        }

        .estado-pendiente {
            background: #ef6c00;
        }

        .estado-concluida {
            background: #2e7d32;
        }

        .estado-cancelada {
            background: #c62828;
        }

        .empty-box {
            padding: 22px;
            border-radius: 14px;
            background: #f7f7f7;
            color: #777777;
            text-align: center;
            border: 1px dashed #cccccc;
            margin-bottom: 14px;
        }

        .footer {
            margin-top: 22px;
            padding-top: 10px;
            border-top: 1px solid #eaddea;
            color: #777777;
            font-size: 9px;
            text-align: center;
        }

        .footer strong {
            color: #c2185b;
        }

        .small {
            font-size: 9px;
            color: #777777;
        }

        .page-break {
            page-break-before: always;
        }
    </style>
</head>

<body>
<div class="page">

    <!-- ENCABEZADO -->
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="header-left">
                    <div class="brand">{{ $nombreNegocio }}</div>
                    <div class="slogan">{{ $sloganNegocio }}</div>

                    <div class="business-info">
                        @if($direccionNegocio)
                            Dirección: {{ $direccionNegocio }}<br>
                        @endif

                        @if($telefonoNegocio)
                            Teléfono: {{ $telefonoNegocio }}<br>
                        @endif

                        @if($whatsappNegocio)
                            WhatsApp: {{ $whatsappNegocio }}
                        @endif
                    </div>
                </td>

                <td class="header-right">
                    @if($logoNegocio)
                        <img src="{{ $logoNegocio }}" class="logo-img" alt="Logo">
                    @else
                        <div class="logo-box">
                            {{ mb_substr($nombreCorto, 0, 1) }}
                        </div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <!-- TÍTULO REPORTE -->
    <div class="title-box">
        <div class="report-title">
            {{ $titulo ?? 'Extracto mensual' }}
        </div>

        <div class="report-subtitle">
            Periodo: {{ $mes_nombre ?? '' }} {{ $anio ?? '' }}
            · Desde {{ $fecha_inicio ?? '-' }} hasta {{ $fecha_fin ?? '-' }}
            · Generado: {{ $fecha_generado ?? '-' }}
        </div>
    </div>

    <!-- RESUMEN PRINCIPAL -->
    <table class="summary-grid">
        <tr>
            <td class="summary-card">
                <div class="summary-label">Total de citas</div>
                <div class="summary-value">{{ $total_citas ?? 0 }}</div>
            </td>

            <td class="summary-card">
                <div class="summary-label">Total estimado</div>
                <div class="summary-value summary-money">
                    {{ $total_estimado_texto ?? $formatoDinero($total_estimado ?? 0) }}
                </div>
            </td>

            <td class="summary-card">
                <div class="summary-label">Total pagado</div>
                <div class="summary-value summary-money">
                    {{ $total_pagado_texto ?? $formatoDinero($total_pagado ?? 0) }}
                </div>
            </td>

            <td class="summary-card">
                <div class="summary-label">Total pendiente</div>
                <div class="summary-value summary-warning">
                    {{ $total_pendiente_texto ?? $formatoDinero($total_pendiente ?? 0) }}
                </div>
            </td>
        </tr>
    </table>

    <table class="summary-grid">
        <tr>
            <td class="summary-card">
                <div class="summary-label">Citas pendientes</div>
                <div class="summary-value summary-warning">{{ $citas_pendientes ?? 0 }}</div>
            </td>

            <td class="summary-card">
                <div class="summary-label">Citas concluidas</div>
                <div class="summary-value summary-money">{{ $citas_concluidas ?? 0 }}</div>
            </td>

            <td class="summary-card">
                <div class="summary-label">Citas canceladas</div>
                <div class="summary-value summary-danger">{{ $citas_canceladas ?? 0 }}</div>
            </td>

            <td class="summary-card">
                <div class="summary-label">Moneda</div>
                <div class="summary-value">{{ $moneda }}</div>
            </td>
        </tr>
    </table>

    <!-- RESUMEN POR DÍA -->
    <div class="section-title">Resumen por día</div>

    @if(!empty($resumenDias) && count($resumenDias) > 0)
        <table class="data-table">
            <thead>
            <tr>
                <th>Día</th>
                <th class="text-center">Citas</th>
                <th class="text-center">Pend.</th>
                <th class="text-center">Concl.</th>
                <th class="text-center">Canc.</th>
                <th class="text-right">Estimado</th>
                <th class="text-right">Pagado</th>
                <th class="text-right">Pendiente</th>
            </tr>
            </thead>

            <tbody>
            @foreach($resumenDias as $dia)
                <tr>
                    <td>{{ ucfirst($dia['dia'] ?? '') }}</td>
                    <td class="text-center">{{ $dia['total_citas'] ?? 0 }}</td>
                    <td class="text-center">{{ $dia['pendientes'] ?? 0 }}</td>
                    <td class="text-center">{{ $dia['concluidas'] ?? 0 }}</td>
                    <td class="text-center">{{ $dia['canceladas'] ?? 0 }}</td>
                    <td class="text-right">
                        {{ $dia['total_estimado_texto'] ?? $formatoDinero($dia['total_estimado'] ?? 0) }}
                    </td>
                    <td class="text-right">
                        {{ $dia['total_pagado_texto'] ?? $formatoDinero($dia['total_pagado'] ?? 0) }}
                    </td>
                    <td class="text-right">
                        {{ $dia['total_pendiente_texto'] ?? $formatoDinero($dia['total_pendiente'] ?? 0) }}
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <div class="empty-box">
            No hay movimientos diarios para este mes.
        </div>
    @endif

    <!-- DETALLE DE CITAS -->
    <div class="section-title">Detalle de citas del mes</div>

    @if(isset($citas) && $citas->count() > 0)
        <table class="data-table">
            <thead>
            <tr>
                <th>Fecha</th>
                <th>Hora</th>
                <th>Cliente</th>
                <th>Teléfono</th>
                <th>Servicio</th>
                <th>Estado</th>
                <th class="text-right">Precio</th>
                <th class="text-right">Pagado</th>
            </tr>
            </thead>

            <tbody>
            @foreach($citas as $cita)
                @php
                    $totalPagadoCita = $cita->pagos ? $cita->pagos->sum('monto') : 0;
                @endphp

                <tr>
                    <td>{{ $fechaCita($cita->fecha ?? null) }}</td>
                    <td>{{ $horaCita($cita->hora ?? null) }}</td>
                    <td>{{ $nombreCliente($cita) }}</td>
                    <td>{{ $telefonoCliente($cita) }}</td>
                    <td>{{ $servicioCita($cita) }}</td>
                    <td>
                        <span class="badge {{ $estadoClase($cita->estado ?? 'pendiente') }}">
                            {{ $estadoTexto($cita->estado ?? 'pendiente') }}
                        </span>
                    </td>
                    <td class="text-right">{{ $formatoDinero($cita->precio ?? 0) }}</td>
                    <td class="text-right">{{ $formatoDinero($totalPagadoCita) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <div class="empty-box">
            No hay citas registradas para este mes.
        </div>
    @endif

    <!-- PIE -->
    <div class="footer">
        Reporte generado por <strong>{{ $nombreCorto }}</strong>.
        Este documento resume la actividad mensual registrada en el sistema.
    </div>

</div>
</body>
</html>