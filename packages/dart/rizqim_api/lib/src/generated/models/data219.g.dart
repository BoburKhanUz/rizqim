// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data219.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data219 _$Data219FromJson(Map<String, dynamic> json) => Data219(
  serverTime: json['server_time'] as String,
  menuVersion: json['menu_version'] as String,
  store: Store2.fromJson(json['store'] as Map<String, dynamic>),
  branch: Branch17.fromJson(json['branch'] as Map<String, dynamic>),
  device: json['device'] == null
      ? null
      : Device10.fromJson(json['device'] as Map<String, dynamic>),
  user: User15.fromJson(json['user'] as Map<String, dynamic>),
  stations: (json['stations'] as List<dynamic>)
      .map((e) => Stations9.fromJson(e as Map<String, dynamic>))
      .toList(),
  stationRoutes: StationRoutes.fromJson(
    json['station_routes'] as Map<String, dynamic>,
  ),
  printers: (json['printers'] as List<dynamic>)
      .map((e) => Printers.fromJson(e as Map<String, dynamic>))
      .toList(),
  staff: (json['staff'] as List<dynamic>)
      .map((e) => Staff16.fromJson(e as Map<String, dynamic>))
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
  offline: Offline2.fromJson(json['offline'] as Map<String, dynamic>),
  terminalFlow: json['terminal_flow'] == null
      ? null
      : TerminalFlow.fromJson(json['terminal_flow'] as Map<String, dynamic>),
  staffPay: json['staff_pay'] == null
      ? null
      : StaffPay.fromJson(json['staff_pay'] as Map<String, dynamic>),
  format: Format2.fromJson(json['format'] as Map<String, dynamic>),
  tips: Tips6.fromJson(json['tips'] as Map<String, dynamic>),
  entitlements: Entitlements.fromJson(
    json['entitlements'] as Map<String, dynamic>,
  ),
  queue: Queue2.fromJson(json['queue'] as Map<String, dynamic>),
  bookingRules: json['booking_rules'] == null
      ? null
      : BookingRules.fromJson(json['booking_rules'] as Map<String, dynamic>),
  labels: json['labels'] == null
      ? null
      : Labels.fromJson(json['labels'] as Map<String, dynamic>),
  takeaway: Takeaway.fromJson(json['takeaway'] as Map<String, dynamic>),
  lan: json['lan'] == null
      ? null
      : Lan.fromJson(json['lan'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data219ToJson(Data219 instance) => <String, dynamic>{
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
  'staff_pay': instance.staffPay,
  'format': instance.format,
  'tips': instance.tips,
  'entitlements': instance.entitlements,
  'queue': instance.queue,
  'booking_rules': instance.bookingRules,
  'labels': instance.labels,
  'takeaway': instance.takeaway,
  'lan': instance.lan,
};
