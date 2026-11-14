// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'event11.dart';
import 'guests5.dart';
import 'lines25.dart';
import 'orders5.dart';
import 'advances4.dart';
import 'settlement6.dart';
import 'warnings19.dart';

part 'data239.g.dart';

@JsonSerializable()
class Data239 {
  const Data239({
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

  factory Data239.fromJson(Map<String, Object?> json) =>
      _$Data239FromJson(json);

  final Event11 event;
  final Guests5 guests;
  final List<Lines25> lines;
  @JsonKey(name: 'package_total')
  final String packageTotal;
  @JsonKey(name: 'hall_total')
  final String hallTotal;
  @JsonKey(name: 'extras_total')
  final String extrasTotal;
  final Orders5 orders;
  final String total;
  final Advances4 advances;
  final String due;
  final String excess;
  final Settlement6? settlement;
  final List<Warnings19> warnings;

  Map<String, Object?> toJson() => _$Data239ToJson(this);
}
