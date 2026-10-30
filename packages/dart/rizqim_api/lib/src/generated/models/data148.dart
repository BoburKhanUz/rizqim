// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'calendar.dart';
import 'tariffs.dart';

part 'data148.g.dart';

@JsonSerializable()
class Data148 {
  const Data148({required this.calendar, required this.tariffs});

  factory Data148.fromJson(Map<String, Object?> json) =>
      _$Data148FromJson(json);

  final Calendar calendar;
  final List<Tariffs> tariffs;

  Map<String, Object?> toJson() => _$Data148ToJson(this);
}
