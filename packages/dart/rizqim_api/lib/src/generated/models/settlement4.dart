// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'event9.dart';
import 'guests3.dart';
import 'lines22.dart';
import 'orders3.dart';
import 'advances2.dart';
import 'settlement3.dart';
import 'warnings17.dart';

part 'settlement4.g.dart';

@JsonSerializable()
class Settlement4 {
  const Settlement4({
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

  factory Settlement4.fromJson(Map<String, Object?> json) =>
      _$Settlement4FromJson(json);

  final Event9 event;
  final Guests3 guests;
  final List<Lines22> lines;
  @JsonKey(name: 'package_total')
  final String packageTotal;
  @JsonKey(name: 'hall_total')
  final String hallTotal;
  @JsonKey(name: 'extras_total')
  final String extrasTotal;
  final Orders3 orders;
  final String total;
  final Advances2 advances;
  final String due;
  final String excess;
  final Settlement3? settlement;
  final List<Warnings17> warnings;

  Map<String, Object?> toJson() => _$Settlement4ToJson(this);
}
