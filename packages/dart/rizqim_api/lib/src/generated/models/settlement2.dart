// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'event3.dart';
import 'guests.dart';
import 'lines20.dart';
import 'orders2.dart';
import 'advances.dart';
import 'settlement.dart';
import 'warnings15.dart';

part 'settlement2.g.dart';

@JsonSerializable()
class Settlement2 {
  const Settlement2({
    required this.event,
    required this.guests,
    required this.lines,
    required this.packageTotal,
    required this.hallTotal,
    required this.extrasTotal,
    required this.orders,
    required this.total,
    required this.advances,
    required this.due,
    required this.excess,
    required this.settlement,
    required this.warnings,
  });

  factory Settlement2.fromJson(Map<String, Object?> json) =>
      _$Settlement2FromJson(json);

  final Event3 event;
  final Guests guests;
  final List<Lines20> lines;
  @JsonKey(name: 'package_total')
  final String packageTotal;
  @JsonKey(name: 'hall_total')
  final String hallTotal;
  @JsonKey(name: 'extras_total')
  final String extrasTotal;
  final Orders2 orders;
  final String total;
  final Advances advances;
  final String due;
  final String excess;
  final Settlement? settlement;
  final List<Warnings15> warnings;

  Map<String, Object?> toJson() => _$Settlement2ToJson(this);
}
