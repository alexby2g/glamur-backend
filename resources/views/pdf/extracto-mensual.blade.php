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

    $estadoPagoTexto = function ($estado) {
        return match ($estado) {
            'pagado' => 'Pagado',
            default => 'Pendiente',
        };
    };

    $estadoPagoClase = function ($estado) {
        return match ($estado) {
            'pagado' => 'estado-concluida',
            default => 'estado-pendiente',
        };
    };

    $horaSimple = function ($hora) {
        if (!$hora) {
            return '-';
        }

        try {
            return \Carbon\Carbon::parse($hora)->format('H:i');
        } catch (\Throwable $error) {
            return $hora;
        }
    };

    $fechaSimple = function ($fecha) {
        if (!$fecha) {
            return '-';
        }

        try {
            return \Carbon\Carbon::parse($fecha)->format('d/m/Y');
        } catch (\Throwable $error) {
            return $fecha;
        }
    };

    $inicialLogo = strtoupper(substr(trim($nombreCorto ?: 'A'), 0, 1));
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo ?? 'Caja diaria' }}</title>

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
            font-size: 28px;
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
            line-height: 1.5;
        }

        .summary-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px;
            margin-bottom: 12px;
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

        .method-name {
            font-weight: bold;
            color: #c2185b;
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

        .note-box {
            margin-top: 14px;
            padding: 12px;
            border-radius: 14px;
            background: #fff7fb;
            border: 1px solid #f8bbd0;
            color: #6a536d;
            font-size: 10px;
            line-height: 1.5;
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
                            {{ $inicialLogo }}
                        </div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <!-- TÍTULO -->
    <div class="title-box">
        <div class="report-title">
            {{ $titulo ?? 'Caja diaria' }}
        </div>

        <div class="report-subtitle">
            Fecha de caja: {{ $fecha_texto ?? ($fecha ?? '-') }}<br>
            Generado: {{ $fecha_generado ?? '-' }}
        </div>
    </div>

    <!-- RESUMEN PRINCIPAL -->
    <table class="summary-grid">
        <tr>
            <td class="summary-card">
                <div class="summary-label">Total cobrado</div>
                <div class="summary-value summary-money">
                    {{ $total_cobrado_texto ?? $formatoDinero($total_cobrado ?? 0) }}
                </div>
            </td>

            <td class="summary-card">
                <div class="summary-label">Cantidad de pagos</div>
                <div class="summary-value">
                    {{ $cantidad_pagos ?? 0 }}
                </div>
            </td>

            <td class="summary-card">
                <div class="summary-label">Ticket promedio</div>
                <div class="summary-value summary-money">
                    {{ $ticket_promedio_texto ?? $formatoDinero($ticket_promedio ?? 0) }}
                </div>
            </td>

            <td class="summary-card">
                <div class="summary-label">Moneda</div>
                <div class="summary-value">
                    {{ $moneda }}
                </div>
            </td>
        </tr>
    </table>

    <table class="summary-grid">
        <tr>
            <td class="summary-card">
                <div class="summary-label">Citas del día</div>
                <div class="summary-value">{{ $citas_dia ?? 0 }}</div>
            </td>

            <td class="summary-card">
                <div class="summary-label">Citas pagadas</div>
                <div class="summary-value summary-money">{{ $citas_pagadas ?? 0 }}</div>
            </td>

            <td class="summary-card">
                <div class="summary-label">Pendientes de pago</div>
                <div class="summary-value summary-warning">{{ $citas_pendientes_pago ?? 0 }}</div>
            </td>

            <td class="summary-card">
                <div class="summary-label">Canceladas</div>
                <div class="summary-value summary-danger">{{ $citas_canceladas ?? 0 }}</div>
            </td>
        </tr>
    </table>

    <!-- RESUMEN POR MÉTODO -->
    <div class="section-title">Resumen por método de pago</div>

    @if(!empty($metodos) && count($metodos) > 0)
        <table class="data-table">
            <thead>
            <tr>
                <th>Método</th>
                <th class="text-center">Cantidad</th>
                <th class="text-right">Total</th>
            </tr>
            </thead>

            <tbody>
            @foreach($metodos as $clave => $metodo)
                <tr>
                    <td class="method-name">{{ $metodo['label'] ?? ucfirst($clave) }}</td>
                    <td class="text-center">{{ $metodo['cantidad'] ?? 0 }}</td>
                    <td class="text-right">
                        {{ $metodo['total_texto'] ?? $formatoDinero($metodo['total'] ?? 0) }}
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <div class="empty-box">
            No hay métodos de pago registrados para esta fecha.
        </div>
    @endif

    <!-- DETALLE DE PAGOS -->
    <div class="section-title">Detalle de pagos recibidos</div>

    @if(!empty($pagos) && count($pagos) > 0)
        <table class="data-table">
            <thead>
            <tr>
                <th>Hora</th>
                <th>Cliente</th>
                <th>Servicio</th>
                <th>Método</th>
                <th class="text-right">Monto</th>
            </tr>
            </thead>

            <tbody>
            @foreach($pagos as $pago)
                <tr>
                    <td>{{ $pago['hora_pago'] ?? '-' }}</td>
                    <td>
                        {{ $pago['cliente'] ?? 'Cliente no registrado' }}
                        @if(!empty($pago['telefono']))
                            <br><span style="color:#777777;">{{ $pago['telefono'] }}</span>
                        @endif
                    </td>
                    <td>{{ $pago['servicio'] ?? 'Sin servicio' }}</td>
                    <td>{{ $pago['metodo'] ?? '-' }}</td>
                    <td class="text-right">
                        {{ $pago['monto_texto'] ?? $formatoDinero($pago['monto'] ?? 0) }}
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <div class="empty-box">
            No hay pagos recibidos para esta fecha.
        </div>
    @endif

    <!-- DETALLE DE CITAS -->
    <div class="section-title">Detalle de citas del día</div>

    @if(!empty($citas) && count($citas) > 0)
        <table class="data-table">
            <thead>
            <tr>
                <th>Hora</th>
                <th>Cliente</th>
                <th>Servicio</th>
                <th>Estado cita</th>
                <th>Estado pago</th>
                <th class="text-right">Precio</th>
                <th class="text-right">Pagado</th>
                <th class="text-right">Pendiente</th>
            </tr>
            </thead>

            <tbody>
            @foreach($citas as $cita)
                <tr>
                    <td>{{ $horaSimple($cita['hora'] ?? null) }}</td>
                    <td>
                        {{ $cita['cliente'] ?? 'Cliente no registrado' }}
                        @if(!empty($cita['telefono']))
                            <br><span style="color:#777777;">{{ $cita['telefono'] }}</span>
                        @endif
                    </td>
                    <td>{{ $cita['servicio'] ?? 'Sin servicio' }}</td>
                    <td>
                        <span class="badge {{ $estadoClase($cita['estado'] ?? 'pendiente') }}">
                            {{ $estadoTexto($cita['estado'] ?? 'pendiente') }}
                        </span>
                    </td>
                    <td>
                        <span class="badge {{ $estadoPagoClase($cita['estado_pago'] ?? 'pendiente') }}">
                            {{ $estadoPagoTexto($cita['estado_pago'] ?? 'pendiente') }}
                        </span>
                    </td>
                    <td class="text-right">
                        {{ $cita['precio_texto'] ?? $formatoDinero($cita['precio'] ?? 0) }}
                    </td>
                    <td class="text-right">
                        {{ $cita['total_pagado_texto'] ?? $formatoDinero($cita['total_pagado'] ?? 0) }}
                    </td>
                    <td class="text-right">
                        {{ $cita['pendiente_texto'] ?? $formatoDinero($cita['pendiente'] ?? 0) }}
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <div class="empty-box">
            No hay citas registradas para esta fecha.
        </div>
    @endif

    <!-- NOTA -->
    <div class="note-box">
        Este reporte muestra los pagos recibidos y las citas registradas en la fecha seleccionada.
        Sirve como respaldo para el cierre diario de caja del negocio.
    </div>

    <!-- PIE -->
    <div class="footer">
        Reporte generado por <strong>{{ $nombreCorto }}</strong>.
        Documento de control interno para cierre de caja diaria.
    </div>

</div>
</body>
</html>