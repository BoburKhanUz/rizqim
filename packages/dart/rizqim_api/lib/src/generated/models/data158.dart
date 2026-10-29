// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'store2.dart';
import 'branch12.dart';
import 'device10.dart';
import 'user14.dart';
import 'stations5.dart';
import 'station_routes.dart';
import 'printers.dart';
import 'staff2.dart';
import 'halls.dart';
import 'tables5.dart';
import 'shift.dart';
import 'quick_items.dart';
import 'offline.dart';
import 'terminal_flow.dart';
import 'format2.dart';
import 'entitlements.dart';
import 'queue.dart';

part 'data158.g.dart';

@JsonSerializable()
class Data158 {
  const Data158({
    required this.serverTime,
    required this.menuVersion,
    required this.store,
    required this.branch,
    required this.device,
    required this.user,
    required this.stations,
    required this.stationRoutes,
    required this.printers,
    required this.staff,
    required this.halls,
    required this.tables,
    required this.shift,
    required this.quickItems,
    required this.offline,
    required this.terminalFlow,
    required this.format,
    required this.entitlements,
    required this.queue,
  });

  factory Data158.fromJson(Map<String, Object?> json) =>
      _$Data158FromJson(json);

  @JsonKey(name: 'server_time')
  final String serverTime;
  @JsonKey(name: 'menu_version')
  final String menuVersion;
  final Store2 store;
  final Branch12 branch;
  final Device10? device;
  final User14 user;
  final List<Stations5> stations;
  @JsonKey(name: 'station_routes')
  final StationRoutes stationRoutes;
  final List<Printers> printers;
  final List<Staff2> staff;
  final List<Halls> halls;
  final List<Tables5> tables;
  final Shift? shift;
  @JsonKey(name: 'quick_items')
  final List<QuickItems> quickItems;
  final Offline offline;
  @JsonKey(name: 'terminal_flow')
  final TerminalFlow? terminalFlow;
  final Format2 format;
  final Entitlements entitlements;
  final Queue queue;

  Map<String, Object?> toJson() => _$Data158ToJson(this);
}
