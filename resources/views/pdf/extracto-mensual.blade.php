<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Extracto mensual Glamur</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #222;
            margin: 25px;
        }

        .header {
            background: #15111f;
            color: white;
            padding: 18px;
            border-radius: 10px;
            margin-bottom: 18px;
        }

        .title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .subtitle {
            font-size: 12px;
            color: #f8d7a1;
        }

        .box-row {
            width: 100%;
            margin-bottom: 18px;
        }

        .box {
            display: inline-block;
            width: 23%;
            vertical-align: top;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px;
            margin-right: 1%;
            min-height: 58px;
        }

        .box-title {
            color: #777;
            font-size: 10px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .box-value {
            font-size: 17px;
            font-weight: bold;
            color: #c2185b;
        }

        .section-title {
            font-size: 15px;
            font-weight: bold;
            margin: 18px 0 8px;
            color: #880e4f;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        th {
            background: #fce4ec;
            color: #880e4f;
            border: 1px solid #e0c7d2;
            padding: 7px;
            font-size: 10px;
            text-align: left;
        }

        td {
            border: 1px solid #e0e0e0;
            padding: 6px;
            font-size: 10px;
        }

        .text-right {
            text-align: right;
        }

        .estado {
            font-weight: bold;
            text-transform: capitalize;
        }

        .pendiente {
            color: #f9a825;
        }

        .concluida {
            color: #2e7d32;
        }

        .cancelada {
            color: #c62828;
        }

        .footer {
            margin-top: 25px;
            font-size: 9px;
            color: #777;
            text-align: center;
        }

        .empty {
            padding: 15px;
            border: 1px dashed #ccc;
            color: #777;
            text-align: center;
        }
    </style>
</head>

<body>

    <div class="header">
        <div class="title">Glamur - Extracto mensual</div>
        <div class="subtitle">
            Mes: {{ ucfirst($mes_nombre) }} {{ $anio }} |
            Periodo: {{ $fecha_inicio }} al {{ $fecha_fin }} |
            Generado: {{ $fecha_generado }}
        </div>
    </div>

    <div class="box-row">
        <div class="box">
            <div class="box-title">Total citas</div>
            <div class="box-value">{{ $total_citas }}</div>
        </div>

        <div class="box">
            <div class="box-title">Total generado</div>
            <div class="box-value">Bs {{ number_format($total_estimado, 2) }}</div>
        </div>

        <div class="box">
            <div class="box-title">Total pagado</div>
            <div class="box-value">Bs {{ number_format($total_pagado, 2) }}</div>
        </div>

        <div class="box">
            <div class="box-title">Pendiente por cobrar</div>
            <div class="box-value">Bs {{ number_format($total_pendiente, 2) }}</div>
        </div>
    </div>

    <div class="box-row">
        <div class="box">
            <div class="box-title">Pendientes</div>
            <div class="box-value">{{ $citas_pendientes }}</div>
        </div>

        <div class="box">
            <div class="box-title">Concluidas</div>
            <div class="box-value">{{ $citas_concluidas }}</div>
        </div>

        <div class="box">
            <div class="box-title">Canceladas</div>
            <div class="box-value">{{ $citas_canceladas }}</div>
        </div>
    </div>

    <div class="section-title">Resumen por día</div>

    @if(count($resumenDias) === 0)
        <div class="empty">
            No existen citas registradas en este mes.
        </div>
    @else
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Citas</th>
                    <th>Pendientes</th>
                    <th>Concluidas</th>
                    <th>Canceladas</th>
                    <th class="text-right">Generado</th>
                    <th class="text-right">Pagado</th>
                </tr>
            </thead>

            <tbody>
                @foreach($resumenDias as $dia)
                    <tr>
                        <td>{{ ucfirst($dia['dia']) }}</td>
                        <td>{{ $dia['total_citas'] }}</td>
                        <td>{{ $dia['pendientes'] }}</td>
                        <td>{{ $dia['concluidas'] }}</td>
                        <td>{{ $dia['canceladas'] }}</td>
                        <td class="text-right">Bs {{ number_format($dia['total_estimado'], 2) }}</td>
                        <td class="text-right">Bs {{ number_format($dia['total_pagado'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="section-title">Detalle de citas del mes</div>

    @if($citas->count() === 0)
        <div class="empty">
            No existen citas para mostrar.
        </div>
    @else
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Cliente</th>
                    <th>Servicio</th>
                    <th>Estado</th>
                    <th>Pago</th>
                    <th>Método</th>
                    <th class="text-right">Precio</th>
                    <th class="text-right">Pagado</th>
                </tr>
            </thead>

            <tbody>
                @foreach($citas as $cita)
                    @php
                        $pagado = $cita->pagos->sum(function ($pago) {
                            return (float) ($pago->monto ?? 0);
                        });
                    @endphp

                    <tr>
                        <td>{{ \Carbon\Carbon::parse($cita->fecha)->format('d/m/Y') }}</td>
                        <td>{{ substr($cita->hora, 0, 5) }}</td>
                        <td>{{ $cita->cliente->nombre ?? 'Sin cliente' }}</td>
                        <td>{{ $cita->servicio }}</td>
                        <td class="estado {{ $cita->estado }}">{{ $cita->estado }}</td>
                        <td class="estado {{ $cita->estado_pago === 'pagado' ? 'concluida' : 'pendiente' }}">
                            {{ $cita->estado_pago ?? 'pendiente' }}
                        </td>
                        <td>{{ strtoupper($cita->metodo_pago ?? '-') }}</td>
                        <td class="text-right">Bs {{ number_format((float) $cita->precio, 2) }}</td>
                        <td class="text-right">Bs {{ number_format($pagado, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        Documento generado automáticamente por Glamur.
    </div>

</body>
</html>