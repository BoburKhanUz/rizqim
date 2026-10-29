// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data158.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data158 _$Data158FromJson(Map<String, dynamic> json) => Data158(
  serverTime: json['server_time'] as String,
  menuVersion: json['menu_version'] as String,
  store: Store2.fromJson(json['store'] as Map<String, dynamic>),
  branch: Branch12.fromJson(json['branch'] as Map<String, dynamic>),
  device: json['device'] == null
      ? null
      : Device10.fromJson(json['device'] as Map<String, dynamic>),
  user: User14.fromJson(json['user'] as Map<String, dynamic>),
  stations: (json['stations'] as List<dynamic>)
      .map((e) => Stations5.fromJson(e as Map<String, dynamic>))
      .toList(),
  stationRoutes: StationRoutes.fromJson(
    json['station_routes'] as Map<String, dynamic>,
  ),
  printers: (json['printers'] as List<dynamic>)
      .map((e) => Printers.fromJson(e as Map<String, dynamic>))
      .toList(),
  staff: (json['staff'] as List<dynamic>)
      .map((e) => Staff2.fromJson(e as Map<String, dynamic>))
      .toList(),
  halls: (json['halls'] as List<dynamic>)
      .map((e) => Halls.fromJson(e as Map<String, dynamic>))
      .toList(),
  tables: (json['tables'] as List<dynamic>)
      .map((e) => Tables5.fromJson(e as Map<String, dynamic>))
      .toList(),
  shift: json['shift'] == null
      ? null
      : Shift.fromJson(json['shift'] as Map<String, dynamic>),
  quickItems: (json['quick_items'] as List<dynamic>)
      .map((e) => QuickItems.fromJson(e as Map<String, dynamic>))
      .toList(),
  offline: Offline.fromJson(json['offline'] as Map<String, dynamic>),
  terminalFlow: json['terminal_flow'] == null
      ? null
      : TerminalFlow.fromJson(json['terminal_flow'] as Map<String, dynamic>),
  format: Format2.fromJson(json['format'] as Map<String, dynamic>),
  entitlements: Entitlements.fromJson(
    json['entitlements'] as Map<String, dynamic>,
  ),
  queue: Queue.fromJson(json['queue'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data158ToJson(Data158 instance) => <String, dynamic>{
  'server_time': instance.serverTime,
  'menu_version': instance.menuVersion,
  'store': instance.store,
  'branch': instance.branch,
  'device': instance.device,
  'user': instance.user,
  'stations': instance.stations,
  'station_routes': instance.stationRoutes,
  'printers': instance.printers,
  'staff': instance.staff,
  'halls': instance.halls,
  'tables': instance.tables,
  'shift': instance.shift,
  'quick_items': instance.quickItems,
  'offline': instance.offline,
  'terminal_flow': instance.terminalFlow,
  'format': instance.format,
  'entitlements': instance.entitlements,
  'queue': instance.queue,
};
