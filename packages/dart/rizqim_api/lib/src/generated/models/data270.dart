// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'store2.dart';
import 'branch17.dart';
import 'device10.dart';
import 'user22.dart';
import 'stations10.dart';
import 'station_routes.dart';
import 'printers.dart';
import 'staff20.dart';
import 'halls11.dart';
import 'tables5.dart';
import 'shift.dart';
import 'quick_items.dart';
import 'offline2.dart';
import 'terminal_flow.dart';
import 'staff_pay.dart';
import 'format2.dart';
import 'tips7.dart';
import 'entitlements.dart';
import 'queue2.dart';
import 'booking_rules.dart';
import 'labels.dart';
import 'takeaway.dart';
import 'lan.dart';
import 'banquet.dart';

part 'data270.g.dart';

@JsonSerializable()
class Data270 {
  const Data270({
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
    required this.staffPay,
    required this.format,
    required this.tips,
    required this.entitlements,
    required this.queue,
    required this.bookingRules,
    required this.labels,
    required this.takeaway,
    required this.lan,
    required this.banquet,
  });

  factory Data270.fromJson(Map<String, Object?> json) =>
      _$Data270FromJson(json);

  @JsonKey(name: 'server_time')
  final String serverTime;
  @JsonKey(name: 'menu_version')
  final String menuVersion;
  final Store2 store;
  final Branch17 branch;
  final Device10? device;
  final User22 user;
  final List<Stations10> stations;
  @JsonKey(name: 'station_routes')
  final StationRoutes stationRoutes;
  final List<Printers> printers;
  final List<Staff20> staff;
  final List<Halls11> halls;
  final List<Tables5> tables;
  final Shift? shift;
  @JsonKey(name: 'quick_items')
  final List<QuickItems> quickItems;
  final Offline2 offline;
  @JsonKey(name: 'terminal_flow')
  final TerminalFlow? terminalFlow;
  @JsonKey(name: 'staff_pay')
  final StaffPay? staffPay;
  final Format2 format;
  final Tips7 tips;
  final Entitlements entitlements;
  final Queue2 queue;
  @JsonKey(name: 'booking_rules')
  final BookingRules? bookingRules;
  final Labels? labels;
  final Takeaway takeaway;
  final Lan? lan;
  final Banquet? banquet;

  Map<String, Object?> toJson() => _$Data270ToJson(this);
}
