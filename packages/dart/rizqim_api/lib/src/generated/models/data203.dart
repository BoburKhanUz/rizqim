// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'store2.dart';
import 'branch13.dart';
import 'device10.dart';
import 'user14.dart';
import 'stations8.dart';
import 'station_routes.dart';
import 'printers.dart';
import 'staff14.dart';
import 'halls.dart';
import 'tables5.dart';
import 'shift.dart';
import 'quick_items.dart';
import 'offline.dart';
import 'terminal_flow.dart';
import 'staff_pay.dart';
import 'format2.dart';
import 'tips5.dart';
import 'entitlements.dart';
import 'queue.dart';
import 'booking_rules.dart';
import 'labels.dart';
import 'takeaway.dart';

part 'data203.g.dart';

@JsonSerializable()
class Data203 {
  const Data203({
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
  });

  factory Data203.fromJson(Map<String, Object?> json) =>
      _$Data203FromJson(json);

  @JsonKey(name: 'server_time')
  final String serverTime;
  @JsonKey(name: 'menu_version')
  final String menuVersion;
  final Store2 store;
  final Branch13 branch;
  final Device10? device;
  final User14 user;
  final List<Stations8> stations;
  @JsonKey(name: 'station_routes')
  final StationRoutes stationRoutes;
  final List<Printers> printers;
  final List<Staff14> staff;
  final List<Halls> halls;
  final List<Tables5> tables;
  final Shift? shift;
  @JsonKey(name: 'quick_items')
  final List<QuickItems> quickItems;
  final Offline offline;
  @JsonKey(name: 'terminal_flow')
  final TerminalFlow? terminalFlow;
  @JsonKey(name: 'staff_pay')
  final StaffPay? staffPay;
  final Format2 format;
  final Tips5 tips;
  final Entitlements entitlements;
  final Queue queue;
  @JsonKey(name: 'booking_rules')
  final BookingRules? bookingRules;
  final Labels? labels;
  final Takeaway takeaway;

  Map<String, Object?> toJson() => _$Data203ToJson(this);
}
