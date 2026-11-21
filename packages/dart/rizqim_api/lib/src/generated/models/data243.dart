// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'event12.dart';
import 'guests5.dart';
import 'lines25.dart';
import 'orders5.dart';
import 'advances4.dart';
import 'settlement6.dart';
import 'warnings20.dart';

part 'data243.g.dart';

@JsonSerializable()
class Data243 {
  const Data243({
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

  factory Data243.fromJson(Map<String, Object?> json) =>
      _$Data243FromJson(json);

  final Event12 event;
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
  final List<Warnings20> warnings;

  Map<String, Object?> toJson() => _$Data243ToJson(this);
}
