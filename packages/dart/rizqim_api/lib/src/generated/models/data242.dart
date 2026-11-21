// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'event11.dart';
import 'guests4.dart';
import 'lines24.dart';
import 'orders4.dart';
import 'advances3.dart';
import 'settlement5.dart';
import 'warnings19.dart';

part 'data242.g.dart';

@JsonSerializable()
class Data242 {
  const Data242({
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

  factory Data242.fromJson(Map<String, Object?> json) =>
      _$Data242FromJson(json);

  final Event11 event;
  final Guests4 guests;
  final List<Lines24> lines;
  @JsonKey(name: 'package_total')
  final String packageTotal;
  @JsonKey(name: 'hall_total')
  final String hallTotal;
  @JsonKey(name: 'extras_total')
  final String extrasTotal;
  final Orders4 orders;
  final String total;
  final Advances3 advances;
  final String due;
  final String excess;
  final Settlement5? settlement;
  final List<Warnings19> warnings;

  Map<String, Object?> toJson() => _$Data242ToJson(this);
}
