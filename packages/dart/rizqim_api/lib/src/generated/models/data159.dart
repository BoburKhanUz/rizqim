// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'store3.dart';
import 'branch11.dart';
import 'device11.dart';
import 'user14.dart';
import 'stations2.dart';
import 'station_routes.dart';
import 'printers.dart';
import 'staff2.dart';
import 'halls.dart';
import 'tables5.dart';
import 'shift.dart';
import 'quick_items.dart';
import 'offline.dart';

part 'data159.g.dart';

@JsonSerializable()
class Data159 {
  const Data159({
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
  });

  factory Data159.fromJson(Map<String, Object?> json) =>
      _$Data159FromJson(json);

  @JsonKey(name: 'server_time')
  final String serverTime;
  @JsonKey(name: 'menu_version')
  final String menuVersion;
  final Store3 store;
  final Branch11 branch;
  final Device11? device;
  final User14 user;
  final List<Stations2> stations;
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

  Map<String, Object?> toJson() => _$Data159ToJson(this);
}
